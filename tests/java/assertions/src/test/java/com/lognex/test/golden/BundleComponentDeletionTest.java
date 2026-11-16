package com.lognex.test.golden;

import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.ApiException;
import ru.moysklad.remap_1_2.api.BundlesApi;
import ru.moysklad.remap_1_2.model.DeleteRowResult;

import java.util.Collections;
import java.util.List;
import java.util.UUID;

import static org.junit.jupiter.api.Assertions.*;

class BundleComponentDeletionTest {
    @ParameterizedTest
    @ValueSource(ints = {200, 404, 412})
    void generatedMethodReturnsComponentDeletionResults(int status) throws Exception {
        String body = "[{\"info\":\"Компонент успешно удален\"},"
                + "{\"errors\":[{\"error\":\"Не найдено\",\"code\":1021}]}]";
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(status, null, body);
            ApiClient client = new ApiClient().setBasePath(server.getBasePath());
            List<DeleteRowResult> rows = new BundlesApi(client)
                    .deleteBundleComponentsBatch(UUID.randomUUID(), Collections.emptyList());

            assertEquals(2, rows.size());
            assertEquals("Компонент успешно удален", rows.get(0).getInfo());
            assertNull(rows.get(0).getErrors());
            assertNull(rows.get(1).getInfo());
            assertEquals(1021, rows.get(1).getErrors().get(0).getCode());
            assertEquals(status, client.getStatusCode());
        }
    }

    @ParameterizedTest
    @ValueSource(ints = {400, 404, 412})
    void globalErrorPreservesHttpContext(int status) throws Exception {
        String body = "{\"errors\":[{\"error\":\"Ошибка запроса\",\"code\":1056}]}";
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(status, null, body);
            ApiClient client = new ApiClient().setBasePath(server.getBasePath());
            ApiException error = assertThrows(ApiException.class, () -> new BundlesApi(client)
                    .deleteBundleComponentsBatch(UUID.randomUUID(), Collections.emptyList()));

            assertEquals(status, error.getCode());
            assertEquals(body, error.getResponseBody());
            assertEquals(1056, error.getApiErrors().getErrors().get(0).getCode());
        }
    }
}
