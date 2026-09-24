package com.lognex.test.golden;

import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.ApiException;
import ru.moysklad.remap_1_2.HeaderResponse;
import ru.moysklad.remap_1_2.api.LabelsApi;
import ru.moysklad.remap_1_2.model.LabelPrintRequest;

import java.io.IOException;
import java.util.List;
import java.util.Map;
import java.util.UUID;

import static org.junit.jupiter.api.Assertions.assertEquals;
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

    @BeforeEach
    void startStub() throws IOException {
        stub = new LocationStubServer();
        labelsApi = new LabelsApi(new ApiClient().setBasePath(stub.getBasePath()));
    }

    @AfterEach
    void stopStub() {
        stub.close();
    }

    @Test
    void keepsStatusAndLocationOfPendingPrint() throws ApiException {
        String expected = stub.locationUrl("status");
        stub.enqueue(202, expected, null);

        HeaderResponse result = printLabels();

        assertEquals(202, result.getStatusCode());
        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(0, stub.getLocationHits());
    }

    @Test
    void keepsStatusAndLocationOfReadyPrintWithoutFollowingRedirect() throws ApiException {
        String expected = stub.locationUrl("file.pdf");
        stub.enqueue(303, expected, null);

        HeaderResponse result = printLabels();

        assertEquals(303, result.getStatusCode());
        assertEquals(expected, result.getLocation().orElse(null));
        assertEquals(0, stub.getLocationHits(), "переход по Location выполняться не должен");
    }

    @Test
    void reportsMissingLocationAsEmptyValue() throws ApiException {
        stub.enqueue(202, null, null);

        HeaderResponse result = printLabels();

        assertEquals(202, result.getStatusCode());
        assertTrue(result.getLocation().isEmpty());
    }

    @Test
    void keepsErrorContractForErrorStatus() {
        stub.enqueue(400, null, "{\"errors\":[{\"error\":\"stub\",\"code\":1}]}");

        ApiException exception = assertThrows(ApiException.class, this::printLabels);

        assertEquals(400, exception.getCode());
    }

    @Test
    void readsHeadersRegardlessOfCase() {
        HeaderResponse result = new HeaderResponse(303, Map.of("location", List.of("/file.pdf")));

        assertEquals("/file.pdf", result.getLocation().orElse(null));
        assertEquals("/file.pdf", result.getHeader("LOCATION").orElse(null));
    }

    private HeaderResponse printLabels() throws ApiException {
        return labelsApi.printLabels(ENTITY_ID, "product", new LabelPrintRequest().count(1));
    }
}
