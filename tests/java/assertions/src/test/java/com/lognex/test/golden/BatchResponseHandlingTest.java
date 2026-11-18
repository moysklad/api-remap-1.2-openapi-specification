package com.lognex.test.golden;

import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.ObjectMapper;
import com.lognex.test.BaseTestCase;
import com.sun.net.httpserver.HttpServer;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.junit.jupiter.params.provider.CsvSource;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.ApiException;
import ru.moysklad.remap_1_2.api.RetailShiftsApi;
import ru.moysklad.remap_1_2.model.BatchResponseEntity;
import ru.moysklad.remap_1_2.model.DeleteRowResult;
import ru.moysklad.remap_1_2.model.Errors;
import ru.moysklad.remap_1_2.model.Product;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URISyntaxException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Collections;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

class BatchResponseHandlingTest extends BaseTestCase {

    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();
    private static final String BATCH_PATH = "/entity/product/batch";

    @ParameterizedTest
    @CsvSource({
            "invoicein, invoiceposition, InvoiceInPosition",
            "invoiceout, invoiceposition, InvoiceOutPosition",
            "salesreturn, salesreturnposition, SalesReturnPosition",
            "retailsalesreturn, salesreturnposition, RetailSalesReturnPosition"
    })
    void testBatchPositionPreservesTypeAndFields(String entity, String type, String model) throws Exception {
        String json = "{\"meta\":{\"type\":\"" + type
                + "\",\"href\":\"https://example.test/entity/" + entity
                + "/document/positions/position\"},\"quantity\":2,\"price\":100}";
        BatchResponseEntity position = MAPPER.readValue(json, BatchResponseEntity.class);

        Assertions.assertEquals(model, position.getClass().getSimpleName());
        var serialized = MAPPER.valueToTree(position);
        Assertions.assertEquals(2, serialized.path("quantity").asDouble());
        Assertions.assertEquals(100, serialized.path("price").asDouble());
        Object concrete = MAPPER.readValue(json, position.getClass());
        Assertions.assertNotNull(concrete);
        Assertions.assertEquals(model, concrete.getClass().getSimpleName());
    }

    @Test
    void testMixedBatchResponseDeserializesProductAndErrors() throws Exception {
        String json = loadBatchFixtureRaw("product_batch_partial.json");
        List<BatchResponseEntity> models = MAPPER.readValue(
                json,
                new TypeReference<List<BatchResponseEntity>>() {}
        );

        Assertions.assertEquals(2, models.size());
        Assertions.assertInstanceOf(Product.class, models.get(0));
        Assertions.assertInstanceOf(Errors.class, models.get(1));
        Errors errors = (Errors) models.get(1);
        Assertions.assertFalse(errors.getErrors().isEmpty());
        Assertions.assertEquals(1000, errors.getErrors().get(0).getCode());
    }

    @Test
    void testGlobalBatchErrorThrowsApiException() throws Exception {
        String body = loadBatchFixtureRaw("batch_global_error.json");

        try (LocalBatchServer server = LocalBatchServer.start(400, body)) {
            ApiClient client = apiClient(server.baseUrl());
            ApiException exception = Assertions.assertThrows(
                    ApiException.class,
                    () -> invokeProductsBatch(client)
            );
            Assertions.assertEquals(400, exception.getCode());
            Assertions.assertTrue(
                    exception.getResponseBody().contains("\"code\":1056")
                            || exception.getResponseBody().contains("\"code\": 1056"),
                    "response body should contain error code 1056: " + exception.getResponseBody()
            );
            Assertions.assertNotNull(exception.getApiErrors());
            Assertions.assertFalse(exception.getApiErrors().getErrors().isEmpty());
            Assertions.assertEquals(1056, exception.getApiErrors().getErrors().get(0).getCode());
        }
    }

    @ParameterizedTest
    @ValueSource(ints = {200, 404, 412})
    void testPartialSuccessReturnsBatchWithoutException(int status) throws Exception {
        String body = loadBatchFixtureRaw("product_batch_partial.json");

        try (LocalBatchServer server = LocalBatchServer.start(status, body)) {
            List<BatchResponseEntity> result = invokeProductsBatch(apiClient(server.baseUrl()));

            Assertions.assertEquals(2, result.size());
            Assertions.assertInstanceOf(Product.class, result.get(0));
            Assertions.assertInstanceOf(Errors.class, result.get(1));
            Errors errors = (Errors) result.get(1);
            Assertions.assertEquals(1000, errors.getErrors().get(0).getCode());
        }
    }

    @ParameterizedTest
    @ValueSource(ints = {200, 404, 412})
    void deletionKeepsSuccessAndErrors(int status) throws Exception {
        String body = "[{\"info\":\"Удалено\"},{\"errors\":[{\"error\":\"Не найдено\",\"code\":1021}]}]";
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(status, null, body);
            ApiClient client = apiClient(server.getBasePath());
            List<DeleteRowResult> rows = new RetailShiftsApi(client)
                    .deleteRetailShiftBatch(Collections.emptyList());
            Assertions.assertEquals(2, rows.size());
            Assertions.assertEquals("Удалено", rows.get(0).getInfo());
            Assertions.assertNull(rows.get(0).getErrors());
            Assertions.assertEquals(1021, rows.get(1).getErrors().get(0).getCode());
            Assertions.assertEquals(status, client.getStatusCode());
            Assertions.assertNotNull(client.getResponseHeaders());
        }
    }

    @ParameterizedTest
    @ValueSource(strings = {"invoicein", "invoiceout", "facturein", "factureout", "retailsalesreturn", "retailshift"})
    void generatedBatchMethodKeepsTypedEntityAt404(String type) throws Exception {
        String body = "[{\"meta\":{\"type\":\"" + type + "\"},\"name\":\"batch\"},"
                + "{\"errors\":[{\"error\":\"Не найдено\",\"code\":1021}]}]";
        Map<String, String> apiNames = Map.of("invoicein", "InvoiceIns", "invoiceout", "InvoiceOuts",
                "facturein", "FactureIns", "factureout", "FactureOuts",
                "retailsalesreturn", "RetailSalesReturns", "retailshift", "RetailShifts");
        Map<String, String> modelNames = Map.of("invoicein", "InvoiceIn", "invoiceout", "InvoiceOut",
                "facturein", "FactureIn", "factureout", "FactureOut",
                "retailsalesreturn", "RetailSalesReturn", "retailshift", "RetailShift");
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(404, null, body);
            ApiClient client = apiClient(server.getBasePath());
            Class<?> apiClass = Class.forName("ru.moysklad.remap_1_2.api." + apiNames.get(type) + "Api");
            Object api = apiClass.getConstructor(ApiClient.class).newInstance(client);
            List<?> rows = (List<?>) apiClass.getMethod("create" + (List.of("facturein", "factureout", "retailsalesreturn").contains(type)
                    ? apiNames.get(type) : modelNames.get(type)) + "Batch", List.class)
                    .invoke(api, Collections.emptyList());
            Assertions.assertEquals(modelNames.get(type), rows.get(0).getClass().getSimpleName());
            Assertions.assertInstanceOf(Errors.class, rows.get(1));
        }
    }

    @ParameterizedTest
    @ValueSource(ints = {400, 401, 403, 404, 412, 500})
    void unrelatedErrorsPreserveStatusAndBody(int status) throws Exception {
        for (String body : List.of("{\"errors\":[{\"error\":\"Ошибка\",\"code\":1056}]}",
                "[]", "[{}]", "[null]", "[{\"errors\":[]}]", "not json", "")) {
            try (LocationStubServer server = new LocationStubServer()) {
                server.enqueue(status, null, body);
                ApiException error = Assertions.assertThrows(ApiException.class,
                        () -> invoke(apiClient(server.getBasePath()), new TypeReference<List<DeleteRowResult>>() {}));
                Assertions.assertEquals(status, error.getCode());
                Assertions.assertEquals(body, error.getResponseBody());
            }
        }
    }

    @Test
    void regularListDoesNotAcceptBatchErrors() throws Exception {
        String body = loadBatchFixtureRaw("product_batch_partial.json");
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(404, null, body);
            ApiException error = Assertions.assertThrows(ApiException.class,
                    () -> invoke(apiClient(server.getBasePath()), new TypeReference<List<Product>>() {}));
            Assertions.assertEquals(404, error.getCode());
            Assertions.assertEquals(body, error.getResponseBody());
        }
    }

    private <T> T invoke(ApiClient client, TypeReference<T> type) throws ApiException {
        return client.invokeAPI(BATCH_PATH, "POST", Collections.emptyList(), Collections.emptyList(), null,
                Collections.emptyList(), new HashMap<>(), new HashMap<>(), new HashMap<>(),
                "application/json", "application/json", new String[0], type);
    }

    private ApiClient apiClient(String baseUrl) {
        ApiClient client = new ApiClient();
        client.setBasePath(baseUrl);
        return client;
    }

    private List<BatchResponseEntity> invokeProductsBatch(ApiClient client) throws ApiException {
        Map<String, Object> product = new HashMap<String, Object>();
        product.put("name", "Batch test product");

        return client.invokeAPIBatch(
                BATCH_PATH,
                "POST",
                Collections.emptyList(),
                Collections.emptyList(),
                null,
                Collections.singletonList(product),
                new HashMap<String, String>(),
                new HashMap<String, String>(),
                new HashMap<String, Object>(),
                "application/json",
                "application/json",
                new String[0],
                new TypeReference<List<BatchResponseEntity>>() {}
        );
    }

    private String loadBatchFixtureRaw(String filename) throws URISyntaxException, IOException {
        Path path = getFixturesPath().resolve("batch").resolve(filename);
        Assertions.assertTrue(Files.exists(path), "Batch fixture not found: " + filename);
        return new String(Files.readAllBytes(path), StandardCharsets.UTF_8);
    }

    private static final class LocalBatchServer implements AutoCloseable {
        private final HttpServer server;
        private final String baseUrl;

        private LocalBatchServer(HttpServer server, String baseUrl) {
            this.server = server;
            this.baseUrl = baseUrl;
        }

        static LocalBatchServer start(int status, String body) throws IOException {
            HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
            byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
            server.createContext(BATCH_PATH, exchange -> {
                try {
                    // Drain request body so the client can finish sending (Java 8 compatible).
                    drain(exchange.getRequestBody());
                    exchange.getResponseHeaders().add("Content-Type", "application/json");
                    exchange.sendResponseHeaders(status, bytes.length);
                    try (OutputStream os = exchange.getResponseBody()) {
                        os.write(bytes);
                    }
                } finally {
                    exchange.close();
                }
            });
            server.start();
            String baseUrl = "http://127.0.0.1:" + server.getAddress().getPort();
            return new LocalBatchServer(server, baseUrl);
        }

        String baseUrl() {
            return baseUrl;
        }

        @Override
        public void close() {
            server.stop(0);
        }

        private static void drain(InputStream in) throws IOException {
            byte[] buffer = new byte[1024];
            while (in.read(buffer) != -1) {
                // discard
            }
        }
    }
}
