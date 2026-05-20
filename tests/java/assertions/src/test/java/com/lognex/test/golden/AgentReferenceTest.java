package com.lognex.test.golden;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.Configuration;
import ru.moysklad.remap_1_2.model.Agent;
import ru.moysklad.remap_1_2.model.CashIn;
import ru.moysklad.remap_1_2.model.Counterparty;
import ru.moysklad.remap_1_2.model.Employee;
import ru.moysklad.remap_1_2.model.FactureOut;
import ru.moysklad.remap_1_2.model.Meta;
import ru.moysklad.remap_1_2.model.Organization;

import java.net.URI;
import java.util.Map;
import java.util.stream.Stream;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertInstanceOf;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;

/**
 * поля, помеченные `x-agent-reference: true`, должны принимать
 * Counterparty, Organization и Agent через перегруженные сеттеры; на проводе должна
 * сериализоваться meta-ссылка соответствующего типа.
 *
 * @see src/components/schemas/dictionary/agent.yaml
 */
class AgentReferenceTest {

    private static final ObjectMapper MAPPER;

    static {
        Configuration.setDefaultApiClient(new ApiClient());
        MAPPER = Configuration.getDefaultApiClient().getObjectMapper();
    }

    static Stream<Arguments> refProvider() {
        Counterparty counterparty = new Counterparty();
        counterparty.setMeta(buildMeta("counterparty"));
        counterparty.setName("Roga i Kopyta");

        Organization organization = new Organization();
        organization.setMeta(buildMeta("organization"));
        organization.setName("Sphere");

        Agent agent = new Agent();
        agent.setMeta(buildMeta("counterparty"));

        return Stream.of(
            Arguments.of("counterparty", counterparty, "counterparty"),
            Arguments.of("organization", organization, "organization"),
            Arguments.of("agent", agent, "counterparty")
        );
    }

    @ParameterizedTest(name = "setAgent({0}) -> meta.type={2}")
    @MethodSource("refProvider")
    void cashInAgentAcceptsRefsAndSerializesMeta(String label, Object ref, String expectedType) {
        CashIn cashIn = new CashIn();
        if (ref instanceof Counterparty) {
            cashIn.setAgent((Counterparty) ref);
        } else if (ref instanceof Organization) {
            cashIn.setAgent((Organization) ref);
        } else if (ref instanceof Agent) {
            cashIn.setAgent((Agent) ref);
        }

        Map<String, Object> serialized = MAPPER.convertValue(cashIn, new TypeReference<Map<String, Object>>() {});
        Object agentJson = serialized.get("agent");
        assertInstanceOf(Map.class, agentJson, "agent must be serialized as object");
        @SuppressWarnings("unchecked")
        Map<String, Object> meta = (Map<String, Object>) ((Map<String, Object>) agentJson).get("meta");
        assertNotNull(meta, "meta must be present");
        assertEquals(expectedType, meta.get("type"));
    }

    @Test
    void cashInAgentFluentSetterAcceptsBothCounterpartyAndOrganization() {
        Counterparty c = new Counterparty();
        c.setMeta(buildMeta("counterparty"));
        Organization o = new Organization();
        o.setMeta(buildMeta("organization"));

        CashIn cashIn1 = new CashIn().agent(c);
        CashIn cashIn2 = new CashIn().agent(o);

        assertNotNull(cashIn1.getAgent());
        assertNotNull(cashIn2.getAgent());
        assertEquals("counterparty", cashIn1.getAgent().getMeta().getType());
        assertEquals("organization", cashIn2.getAgent().getMeta().getType());
    }

    @Test
    void factureOutConsigneeAcceptsNull() {
        FactureOut factureOut = new FactureOut();
        factureOut.setConsignee((Agent) null);

        assertNull(factureOut.getConsignee());
    }

    @ParameterizedTest(name = "setConsignee({0}) -> meta.type={2}")
    @MethodSource("refProvider")
    void factureOutConsigneeAcceptsRefsAndSerializesMeta(String label, Object ref, String expectedType) {
        FactureOut factureOut = new FactureOut();
        if (ref instanceof Counterparty) {
            factureOut.setConsignee((Counterparty) ref);
        } else if (ref instanceof Organization) {
            factureOut.setConsignee((Organization) ref);
        } else if (ref instanceof Agent) {
            factureOut.setConsignee((Agent) ref);
        }

        Map<String, Object> serialized = MAPPER.convertValue(factureOut, new TypeReference<Map<String, Object>>() {});
        Object consigneeJson = serialized.get("consignee");
        assertInstanceOf(Map.class, consigneeJson, "consignee must be serialized as object");
        @SuppressWarnings("unchecked")
        Map<String, Object> meta = (Map<String, Object>) ((Map<String, Object>) consigneeJson).get("meta");
        assertNotNull(meta, "meta must be present");
        assertEquals(expectedType, meta.get("type"));
    }

    @Test
    void cashInAgentSetterDoesNotAcceptArbitraryType() {
        CashIn cashIn = new CashIn();
        Employee foreign = new Employee();
        foreign.setMeta(buildMeta("employee"));

        // Эта строка должна не компилироваться — типобезопасность гарантирована Java:
        // cashIn.setAgent(foreign);
        // Проверяем доступность только трёх ожидаемых перегрузок через рефлексию:
        long agentOverloadCount = java.util.Arrays.stream(CashIn.class.getDeclaredMethods())
            .filter(m -> m.getName().equals("setAgent"))
            .count();
        assertEquals(3, agentOverloadCount, "setAgent should have exactly 3 overloads (Agent, Counterparty, Organization)");
    }

    private static Meta buildMeta(String type) {
        Meta meta = new Meta();
        meta.setType(type);
        meta.setHref(URI.create("https://api.moysklad.ru/api/remap/1.2/entity/" + type + "/00000000-0000-0000-0000-000000000001"));
        meta.setMediaType("application/json");
        return meta;
    }
}
