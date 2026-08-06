package com.lognex.test.golden;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.lognex.test.BaseTestCase;
import com.sun.net.httpserver.HttpServer;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.ApiException;
import ru.moysklad.remap_1_2.model.BatchResponseEntity;
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
import java.util.concurrent.Executors;

/**
 * Поведение batch-ответов:
 * 1) смешанный массив [сущность, Errors] десериализуется в List&lt;BatchResponseEntity&gt;;
 * 2) HTTP 400+ и объект Errors → ApiException;
 * 3) HTTP 400+ и массив → без ApiException, возвращается список результатов.
 *
 * HTTP-сценарии вызывают {@link ApiClient#invokeAPIBatch} напрямую
 * (тот же путь, что у batch API-методов), чтобы не упираться в известный
 * Hibernate Validator конфликт иерархии Product/ProductMarker.
 */
class BatchResponseHandlingTest extends BaseTestCase {

    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();
    private static final String BATCH_PATH = "/entity/product/batch";

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

    @Test
    void testPartialSuccessAt400ReturnsBatchWithoutException() throws Exception {
        String body = loadBatchFixtureRaw("product_batch_partial.json");

        try (LocalBatchServer server = LocalBatchServer.start(400, body)) {
            List<BatchResponseEntity> result = invokeProductsBatch(apiClient(server.baseUrl()));

            Assertions.assertEquals(2, result.size());
            Assertions.assertInstanceOf(Product.class, result.get(0));
            Assertions.assertInstanceOf(Errors.class, result.get(1));
            Errors errors = (Errors) result.get(1);
            Assertions.assertEquals(1000, errors.getErrors().get(0).getCode());
        }
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
            server.setExecutor(Executors.newSingleThreadExecutor());
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
