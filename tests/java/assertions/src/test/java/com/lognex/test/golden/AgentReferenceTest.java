package com.lognex.test.golden;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.Configuration;
import ru.moysklad.remap_1_2.model.Agent;
import ru.moysklad.remap_1_2.model.CashIn;
import ru.moysklad.remap_1_2.model.FactureOut;
import ru.moysklad.remap_1_2.model.Meta;

import java.net.URI;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertInstanceOf;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;

/**
 * поля, помеченные `x-agent-reference: true`, должны принимать
 * только Agent через базовые setter/fluent методы; на проводе должна
 * сериализоваться meta-ссылка.
 *
 * @see src/components/schemas/dictionary/agent.yaml
 */
class AgentReferenceTest {

    private static final ObjectMapper MAPPER;

    static {
        Configuration.setDefaultApiClient(new ApiClient());
        MAPPER = Configuration.getDefaultApiClient().getObjectMapper();
    }

    @Test
    void cashInAgentAcceptsAgentAndSerializesMeta() {
        Agent ref = new Agent();
        ref.setMeta(buildMeta("counterparty"));
        CashIn cashIn = new CashIn();
        cashIn.setAgent(ref);

        Map<String, Object> serialized = MAPPER.convertValue(cashIn, new TypeReference<Map<String, Object>>() {});
        Object agentJson = serialized.get("agent");
        assertInstanceOf(Map.class, agentJson, "agent must be serialized as object");
        @SuppressWarnings("unchecked")
        Map<String, Object> meta = (Map<String, Object>) ((Map<String, Object>) agentJson).get("meta");
        assertNotNull(meta, "meta must be present");
        assertEquals("counterparty", meta.get("type"));
    }

    @Test
    void cashInAgentFluentSetterAcceptsAgent() {
        Agent a = new Agent();
        a.setMeta(buildMeta("counterparty"));
        CashIn cashIn = new CashIn().agent(a);
        assertNotNull(cashIn.getAgent());
        assertEquals("counterparty", cashIn.getAgent().getMeta().getType());
    }

    @Test
    void factureOutConsigneeAcceptsNull() {
        FactureOut factureOut = new FactureOut();
        factureOut.setConsignee((Agent) null);

        assertNull(factureOut.getConsignee());
    }

    @Test
    void factureOutConsigneeAcceptsAgentAndSerializesMeta() {
        Agent ref = new Agent();
        ref.setMeta(buildMeta("organization"));
        FactureOut factureOut = new FactureOut();
        factureOut.setConsignee(ref);

        Map<String, Object> serialized = MAPPER.convertValue(factureOut, new TypeReference<Map<String, Object>>() {});
        Object consigneeJson = serialized.get("consignee");
        assertInstanceOf(Map.class, consigneeJson, "consignee must be serialized as object");
        @SuppressWarnings("unchecked")
        Map<String, Object> meta = (Map<String, Object>) ((Map<String, Object>) consigneeJson).get("meta");
        assertNotNull(meta, "meta must be present");
        assertEquals("organization", meta.get("type"));
    }

    @Test
    void cashInAgentSetterHasSingleOverload() {
        // Проверяем доступность только трёх ожидаемых перегрузок через рефлексию:
        long agentOverloadCount = java.util.Arrays.stream(CashIn.class.getDeclaredMethods())
            .filter(m -> m.getName().equals("setAgent"))
            .count();
        assertEquals(1, agentOverloadCount, "setAgent should have exactly 1 overload (Agent)");
    }

    private static Meta buildMeta(String type) {
        Meta meta = new Meta();
        meta.setType(type);
        meta.setHref(URI.create("https://api.moysklad.ru/api/remap/1.2/entity/" + type + "/00000000-0000-0000-0000-000000000001"));
        meta.setMediaType("application/json");
        return meta;
    }
}
