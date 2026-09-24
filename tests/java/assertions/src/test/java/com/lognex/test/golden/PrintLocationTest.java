package com.lognex.test.golden;

import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.ApiException;
import ru.moysklad.remap_1_2.HeaderResponse;
import ru.moysklad.remap_1_2.api.LabelsApi;
import ru.moysklad.remap_1_2.api.ProductsApi;
import ru.moysklad.remap_1_2.api.RequestOptions;
import ru.moysklad.remap_1_2.model.LabelPrintRequest;
import ru.moysklad.remap_1_2.model.Product;

import java.io.IOException;
import java.util.UUID;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Контракт операций, полезные данные которых передаются в заголовке Location.
 * Проверяется на локальном HTTP-стабе: openapi-mock не отдаёт заголовки из спецификации.
 */
class PrintLocationTest {
    private static final UUID ENTITY_ID = UUID.fromString("12345678-1234-1234-1234-123456789012");

    private LocationStubServer stub;
    private LabelsApi labelsApi;
    private ApiClient apiClient;

    @BeforeEach
    void startStub() throws IOException {
        stub = new LocationStubServer();
        apiClient = new ApiClient().setBasePath(stub.getBasePath());
        labelsApi = new LabelsApi(apiClient);
    }

    @AfterEach
    void stopStub() throws IOException {
        stub.close();
    }

    @Test
    void keepsStatusAndLocationOfPendingPrint() throws ApiException {
        String expected = stub.locationUrl("status");
        stub.enqueueHeader(202, "Location", expected);

        HeaderResponse result = labelsApi.printLabels(ENTITY_ID, "product", printRequest());

        assertEquals(202, result.getStatusCode());
        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(0, stub.getLocationHits());
    }

    @Test
    void keepsStatusAndLocationOfReadyPrintWithoutFollowingRedirect() throws ApiException {
        String expected = stub.locationUrl("file.pdf");
        stub.enqueueHeader(303, "Location", expected);

        HeaderResponse result = labelsApi.printLabels(ENTITY_ID, "product", printRequest());

        assertEquals(303, result.getStatusCode());
        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(0, stub.getLocationHits(), "переход по Location выполняться не должен");
    }

    @ParameterizedTest(name = "заголовок {0}")
    @ValueSource(strings = {"Location", "location", "LOCATION"})
    void readsLocationRegardlessOfHeaderCase(String headerName) throws ApiException {
        String expected = stub.locationUrl("file.pdf");
        stub.enqueueHeader(303, headerName, expected);

        HeaderResponse result = labelsApi.printLabels(ENTITY_ID, "product", printRequest());

        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(expected, result.getHeader("location").orElse(null));
    }

    @Test
    void reportsMissingLocationAsEmptyValue() throws ApiException {
        stub.enqueue(202);

        HeaderResponse result = labelsApi.printLabels(ENTITY_ID, "product", printRequest());

        assertEquals(202, result.getStatusCode());
        assertTrue(result.getLocation().isEmpty());
    }

    @Test
    void resultsOfSubsequentCallsAreIndependent() throws ApiException {
        String first = stub.locationUrl("first");
        String second = stub.locationUrl("second");
        stub.enqueueHeader(303, "Location", first);
        stub.enqueueHeader(202, "Location", second);

        HeaderResponse firstResult = labelsApi.printLabels(ENTITY_ID, "product", printRequest());
        HeaderResponse secondResult = labelsApi.printLabels(ENTITY_ID, "product", printRequest());

        assertEquals(first, firstResult.getLocation().orElse(null));
        assertEquals(303, firstResult.getStatusCode());
        assertEquals(second, secondResult.getLocation().orElse(null));
        assertEquals(202, secondResult.getStatusCode());
    }

    @ParameterizedTest(name = "статус {0}")
    @ValueSource(ints = {400, 401, 500})
    void keepsErrorContractForErrorStatuses(int statusCode) {
        stub.enqueueBody(statusCode, "{\"errors\":[{\"error\":\"stub\",\"code\":1}]}");

        ApiException exception = assertThrows(
                ApiException.class,
                () -> labelsApi.printLabels(ENTITY_ID, "product", printRequest()));

        assertEquals(statusCode, exception.getCode());
    }

    @Test
    void overloadWithRequestOptionsReturnsHeaders() throws ApiException {
        String expected = stub.locationUrl("file.pdf");
        stub.enqueueHeader(303, "Location", expected);

        HeaderResponse result = labelsApi.printLabels(ENTITY_ID, "product", printRequest(), RequestOptions.empty());

        assertEquals(303, result.getStatusCode());
        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(0, stub.getLocationHits());
    }

    @Test
    void ordinaryJsonResponseIsStillDeserialized() throws ApiException {
        stub.enqueueBody(200, "{\"id\":\"" + ENTITY_ID + "\",\"name\":\"Стул\"}");

        Product product = new ProductsApi(apiClient).getProductById(ENTITY_ID);

        assertNotNull(product);
        assertEquals("Стул", product.getName());
    }

    private LabelPrintRequest printRequest() {
        return new LabelPrintRequest().count(1);
    }
}
