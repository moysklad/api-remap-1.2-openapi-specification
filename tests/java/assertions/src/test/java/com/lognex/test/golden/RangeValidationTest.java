package com.lognex.test.golden;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.api.AuditApi;
import ru.moysklad.remap_1_2.api.PageOptions;
import ru.moysklad.remap_1_2.api.ProductsApi;
import ru.moysklad.remap_1_2.model.Product;
import ru.moysklad.remap_1_2.model.SupplyPosition;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Границы minimum/maximum из спецификации проверяются в сеттерах моделей и в query-параметрах до отправки запроса.
 */
class RangeValidationTest {
    @ParameterizedTest
    @ValueSource(ints = {-1, 100})
    void productVatOutOfRangeIsRejected(int vat) {
        IllegalArgumentException fluent = assertThrows(IllegalArgumentException.class, () -> new Product().vat(vat));
        assertTrue(fluent.getMessage().contains("'vat'"), fluent.getMessage());
        assertThrows(IllegalArgumentException.class, () -> new Product().setVat(vat));
    }

    @Test
    void productVatBoundsAndNullAreAccepted() {
        assertEquals(0, new Product().vat(0).getVat());
        assertEquals(99, new Product().vat(99).getVat());
        assertNull(new Product().vat(null).getVat());
    }

    @Test
    void exclusiveMinimumRejectsBoundary() {
        assertThrows(IllegalArgumentException.class, () -> new SupplyPosition().quantity(0.0));
        assertEquals(0.5, new SupplyPosition().quantity(0.5).getQuantity());
    }

    @Test
    void listLimitAndOffsetOutOfRangeAreRejectedBeforeRequest() {
        ProductsApi api = new ProductsApi(new ApiClient().setBasePath("http://127.0.0.1:1/api/remap/1.2"));

        IllegalArgumentException tooBig = assertThrows(IllegalArgumentException.class,
                () -> api.getProducts(PageOptions.limit(1001)));
        assertTrue(tooBig.getMessage().contains("'limit'"), tooBig.getMessage());
        assertTrue(tooBig.getMessage().contains("1000"), tooBig.getMessage());
        assertThrows(IllegalArgumentException.class, () -> api.getProducts(PageOptions.limit(0)));
        IllegalArgumentException negativeOffset = assertThrows(IllegalArgumentException.class,
                () -> api.getProducts(PageOptions.offset(-1)));
        assertTrue(negativeOffset.getMessage().contains("'offset'"), negativeOffset.getMessage());
    }

    @Test
    void auditLimitUsesItsOwnMaximum() {
        AuditApi api = new AuditApi(new ApiClient().setBasePath("http://127.0.0.1:1/api/remap/1.2"));

        IllegalArgumentException error = assertThrows(IllegalArgumentException.class,
                () -> api.getAuditContexts(PageOptions.limit(101)));
        assertTrue(error.getMessage().contains("100"), error.getMessage());
    }

    @Test
    void boundaryValuesReachServer() throws Exception {
        try (LocationStubServer server = new LocationStubServer()) {
            server.enqueue(200, null, "{\"rows\":[]}");
            server.enqueue(200, null, "{\"rows\":[]}");
            ApiClient client = new ApiClient().setBasePath(server.getBasePath());

            assertNotNull(new ProductsApi(client)
                    .getProducts(PageOptions.builder().limit(1000).offset(0).build()));
            assertNotNull(new AuditApi(client).getAuditContexts(PageOptions.limit(100)));
        }
    }
}
