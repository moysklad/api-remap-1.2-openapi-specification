package com.lognex.test.golden;

import com.fasterxml.jackson.annotation.JsonInclude;
import com.lognex.test.BaseTestCase;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.openapitools.jackson.nullable.JsonNullableJackson3Module;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.model.BatchResponseEntity;
import ru.moysklad.remap_1_2.model.Counterparty;
import ru.moysklad.remap_1_2.model.CustomerOrder;
import ru.moysklad.remap_1_2.model.Employee;
import ru.moysklad.remap_1_2.model.EmployeeList;
import ru.moysklad.remap_1_2.model.Product;
import ru.moysklad.remap_1_2.model.SalesReturn;
import ru.moysklad.remap_1_2.model.SalesReturnPosition;
import ru.moysklad.remap_1_2.model.UnknownEntity;
import tools.jackson.core.JacksonException;
import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;
import tools.jackson.databind.json.JsonMapper;

import java.io.IOException;
import java.net.URISyntaxException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;

/**
 * Известный meta.type, несовместимый с объявленным типом поля, не должен ломать десериализацию:
 * значение пропускается, getter возвращает null, а при сериализации поле не попадает в JSON,
 * чтобы обновление сущности не затирало значение на сервере.
 */
class IncompatibleMetaTypeDeserializationTest extends BaseTestCase {

    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();

    @ParameterizedTest
    @ValueSource(strings = {"default", "setDateFormat", "setObjectMapper"})
    void incompatibleReferencesAreSkippedAndOmittedOnSerialization(String configuration) throws Exception {
        ObjectMapper mapper = mapper(configuration);

        CustomerOrder order = mapper.readValue(fixture("customerorder_incompatible_references.json"), CustomerOrder.class);

        Assertions.assertEquals("00018", order.getName());
        Assertions.assertNull(order.getOrganization());
        Assertions.assertNull(order.getOwner());
        Assertions.assertFalse(order.getOwner_JsonNullable().isPresent());
        Assertions.assertInstanceOf(Counterparty.class, order.getAgent());

        JsonNode json = mapper.readTree(mapper.writeValueAsString(order));
        Assertions.assertFalse(json.has("organization"), json.toString());
        Assertions.assertFalse(json.has("owner"), json.toString());
        Assertions.assertEquals("00018", json.get("name").asText());
        Assertions.assertEquals("counterparty", json.at("/agent/meta/type").asText());
    }

    @Test
    void explicitNullOwnerIsKeptAsExplicitNull() throws Exception {
        CustomerOrder order = MAPPER.readValue("{\"name\":\"00018\",\"owner\":null}", CustomerOrder.class);

        Assertions.assertNull(order.getOwner());
        Assertions.assertTrue(order.getOwner_JsonNullable().isPresent());
        JsonNode json = MAPPER.readTree(MAPPER.writeValueAsString(order));
        Assertions.assertTrue(json.has("owner"), json.toString());
        Assertions.assertTrue(json.get("owner").isNull());
    }

    @Test
    void compatibleOwnerIsDeserialized() throws Exception {
        CustomerOrder order = MAPPER.readValue(
                "{\"owner\":{\"meta\":{\"type\":\"employee\"},\"name\":\"Владелец\"}}", CustomerOrder.class);

        Employee owner = Assertions.assertInstanceOf(Employee.class, order.getOwner());
        Assertions.assertEquals("Владелец", owner.getName());
        JsonNode json = MAPPER.readTree(MAPPER.writeValueAsString(order));
        Assertions.assertEquals("employee", json.at("/owner/meta/type").asText());
    }

    @Test
    void unknownMetaTypeInFieldWithKnownTypeIsSkipped() throws Exception {
        CustomerOrder order = MAPPER.readValue("{\"name\":\"00018\",\"owner\":{\"meta\":{\"type\":\"application\"},"
                + "\"name\":\"Решение\",\"salary\":100}}", CustomerOrder.class);

        Assertions.assertNull(order.getOwner());
        Assertions.assertFalse(order.getOwner_JsonNullable().isPresent());
        JsonNode json = MAPPER.readTree(MAPPER.writeValueAsString(order));
        Assertions.assertFalse(json.has("owner"), json.toString());
    }

    @Test
    void unknownMetaTypeInParentTypedCollectionIsUnknownEntity() throws Exception {
        String unknown = "{\"meta\":{\"type\":\"application\"},\"name\":\"Решение\",\"appUid\":\"app-1\"}";
        List<BatchResponseEntity> rows = MAPPER.readValue(
                "[{\"meta\":{\"type\":\"product\"}}," + unknown + "]", new TypeReference<List<BatchResponseEntity>>() {});

        Assertions.assertInstanceOf(Product.class, rows.get(0));
        UnknownEntity entity = Assertions.assertInstanceOf(UnknownEntity.class, rows.get(1));
        Assertions.assertEquals("application", entity.getMeta().getType());
        Assertions.assertEquals(MAPPER.readTree(unknown), MAPPER.readTree(MAPPER.writeValueAsString(entity)));
    }

    @Test
    void unmappedMetaTypeKeepsDeclaredClassFallback() throws Exception {
        SalesReturn salesReturn = MAPPER.readValue(
                "{\"positions\":{\"rows\":[{\"meta\":{\"type\":\"salesreturnposition\"}}]}}", SalesReturn.class);

        SalesReturnPosition position = Assertions.assertInstanceOf(
                SalesReturnPosition.class, salesReturn.getPositions().getRows().get(0));
        Assertions.assertEquals("salesreturnposition", position.getMeta().getType());
    }

    @Test
    void missingMetaTypeKeepsDeclaredClassFallback() throws Exception {
        CustomerOrder order = MAPPER.readValue("{\"owner\":{\"name\":\"Без meta\"}}", CustomerOrder.class);

        Employee owner = Assertions.assertInstanceOf(Employee.class, order.getOwner());
        Assertions.assertEquals("Без meta", owner.getName());
    }

    @Test
    void incompatibleRootValueIsNull() throws Exception {
        Employee employee = MAPPER.readValue("{\"meta\":{\"type\":\"counterparty\"},\"name\":\"Контрагент\"}", Employee.class);

        Assertions.assertNull(employee);
    }

    @Test
    void parseErrorsInCompatibleValueAreNotHidden() {
        Assertions.assertThrows(JacksonException.class, () -> MAPPER.readValue(
                "{\"owner\":{\"meta\":{\"type\":\"employee\"},\"id\":\"not-a-uuid\"}}", CustomerOrder.class));
    }

    @Test
    void incompatibleCollectionElementIsNull() throws Exception {
        EmployeeList list = MAPPER.readValue("{\"rows\":["
                + "{\"meta\":{\"type\":\"employee\"},\"name\":\"Сотрудник\"},"
                + "{\"meta\":{\"type\":\"counterparty\"},\"name\":\"Контрагент\"}"
                + "]}", EmployeeList.class);

        Assertions.assertEquals(2, list.getRows().size());
        Assertions.assertInstanceOf(Employee.class, list.getRows().get(0));
        Assertions.assertNull(list.getRows().get(1));
    }

    private static ObjectMapper mapper(String configuration) {
        ApiClient client = new ApiClient();
        switch (configuration) {
            case "setDateFormat" -> client.setDateFormat(ApiClient.buildDefaultDateFormat());
            case "setObjectMapper" -> client.setObjectMapper(JsonMapper.builder()
                    .changeDefaultPropertyInclusion(v -> v.withValueInclusion(JsonInclude.Include.NON_NULL))
                    .addModule(new JsonNullableJackson3Module())
                    .build());
            default -> { }
        }
        return client.getObjectMapper();
    }

    private String fixture(String filename) throws URISyntaxException, IOException {
        Path path = getFixturesPath().resolve("polymorphic").resolve(filename);
        Assertions.assertTrue(Files.exists(path), "Polymorphic fixture not found: " + filename);
        return new String(Files.readAllBytes(path), StandardCharsets.UTF_8);
    }
}
