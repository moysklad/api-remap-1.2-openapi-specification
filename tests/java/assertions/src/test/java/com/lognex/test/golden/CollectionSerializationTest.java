package com.lognex.test.golden;

import org.junit.jupiter.api.Test;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.model.*;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

import java.util.Collections;

import static org.junit.jupiter.api.Assertions.*;

class CollectionSerializationTest {
    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();

    @Test
    void unsetCollectionsStayAbsent() {
        assertNull(new PriceList().getColumns());
        assertFalse(json(new PriceList()).has("columns"));
        assertFalse(json(new RetailShift()).has("payments"));
        assertFalse(json(new RetailStore()).has("cashiers"));
        assertFalse(json(new DeleteRowResult().info("Удалено")).has("errors"));
    }

    @Test
    void explicitlyEmptyWritableListIsSentForClearing() {
        PriceList priceList = new PriceList().columns(Collections.emptyList());
        assertTrue(json(priceList).get("columns").isArray());
        assertTrue(json(priceList).get("columns").isEmpty());
        RetailStore store = new RetailStore().createAgentsTags(Collections.emptyList());
        assertTrue(json(store).get("createAgentsTags").isEmpty());
    }

    @Test
    void collectionHelpersInitializeListsLazily() {
        PriceList priceList = new PriceList().addColumnsItem(new PriceListColumn());
        assertEquals(1, priceList.getColumns().size());
        assertEquals(1, json(priceList).get("columns").size());
    }

    @Test
    void emptyReadonlyCollectionsAreNotSentAfterReading() {
        RetailShift shift = MAPPER.readValue("{\"operations\":[]}", RetailShift.class);
        assertNotNull(shift.getOperations());
        assertTrue(shift.getOperations().isEmpty());
        assertFalse(json(shift).has("operations"));
        Payroll payroll = MAPPER.readValue("{\"payments\":[]}", Payroll.class);
        assertNotNull(payroll.getPayments());
        assertFalse(json(payroll).has("payments"));
    }

    @Test
    void explicitNullableCollectionNullIsPreserved() {
        RetailStore store = new RetailStore().createAgentsTags(null);
        assertTrue(json(store).has("createAgentsTags"));
        assertTrue(json(store).get("createAgentsTags").isNull());
    }

    private JsonNode json(Object value) {
        return MAPPER.readTree(MAPPER.writeValueAsString(value));
    }
}
