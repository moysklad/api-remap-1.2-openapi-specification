package com.lognex.test.golden;

import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import ru.moysklad.remap_1_2.Configuration;
import ru.moysklad.remap_1_2.model.Account;
import ru.moysklad.remap_1_2.model.StoreBalance;

import java.util.UUID;

import static org.junit.jupiter.api.Assertions.assertEquals;

class MetaBuilderTest {
    @ParameterizedTest
    @ValueSource(strings = {"counterparty", "organization"})
    void accountBuilderUsesStringEntityType(String entityType) {
        UUID parentId = UUID.randomUUID();
        UUID id = UUID.randomUUID();
        Account account = Account.createWithMeta(entityType, parentId, id);
        assertEquals(id, account.getId());
        assertEquals("account", account.getMeta().getType());
        assertEquals(Configuration.getDefaultApiClient().getBaseURL() + "/entity/" + entityType
                + "/" + parentId + "/accounts/" + id, account.getMeta().getHref().toString());
    }

    @ParameterizedTest
    @ValueSource(strings = {"product", "variant"})
    void storeBalanceBuilderUsesStringEntityType(String entityType) {
        UUID parentId = UUID.randomUUID();
        UUID id = UUID.randomUUID();
        StoreBalance balance = StoreBalance.createWithMeta(entityType, parentId, id);
        assertEquals(id, balance.getId());
        assertEquals("minimumstock", balance.getMeta().getType());
        assertEquals(Configuration.getDefaultApiClient().getBaseURL() + "/entity/" + entityType
                + "/" + parentId + "/storebalances/" + id, balance.getMeta().getHref().toString());
    }
}
