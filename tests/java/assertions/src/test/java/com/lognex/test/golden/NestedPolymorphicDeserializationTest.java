package com.lognex.test.golden;

import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.ObjectMapper;
import com.lognex.test.BaseTestCase;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;
import ru.moysklad.remap_1_2.ApiClient;
import ru.moysklad.remap_1_2.model.AttributeAbstract;
import ru.moysklad.remap_1_2.model.AttributeCounterpartyEntity;
import ru.moysklad.remap_1_2.model.AttributeProductEntity;
import ru.moysklad.remap_1_2.model.BatchResponseEntity;
import ru.moysklad.remap_1_2.model.Counterparty;
import ru.moysklad.remap_1_2.model.CustomerOrder;
import ru.moysklad.remap_1_2.model.Employee;
import ru.moysklad.remap_1_2.model.EntityWithMeta;
import ru.moysklad.remap_1_2.model.Errors;
import ru.moysklad.remap_1_2.model.Product;
import ru.moysklad.remap_1_2.model.Task;

import java.io.IOException;
import java.net.URISyntaxException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;

/**
 * Вложенные полиморфные поля (EntityWithMeta / Agent / ProductMarker внутри сущности того же
 * семейства) должны десериализоваться в конкретный класс по meta.type, а не в базовый тип.
 */
class NestedPolymorphicDeserializationTest extends BaseTestCase {

    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();

    @Test
    void productAttributeWithExpandedProductResolvesToProduct() throws Exception {
        Product product = MAPPER.readValue(fixture("product_attribute_product_expanded.json"), Product.class);

        EntityWithMeta value = productAttributeValue(product, 0);
        Product linked = Assertions.assertInstanceOf(Product.class, value);
        Assertions.assertEquals("Связанный товар (expand)", linked.getName());
        Assertions.assertEquals("00042", linked.getCode());
        Assertions.assertEquals("product", linked.getMeta().getType());
    }

    @Test
    void productAttributeWithoutExpandResolvesToConcreteClassWithMetaOnly() throws Exception {
        Product product = MAPPER.readValue(fixture("product_attribute_product_expanded.json"), Product.class);

        Product linked = Assertions.assertInstanceOf(Product.class, productAttributeValue(product, 1));
        Assertions.assertNotNull(linked.getMeta());
        Assertions.assertEquals("product", linked.getMeta().getType());
        Assertions.assertNull(linked.getName());
        Assertions.assertNull(linked.getId());
    }

    @Test
    void productAttributeWithUnknownMetaTypeFallsBackToEntityWithMeta() throws Exception {
        Product product = MAPPER.readValue(fixture("product_attribute_product_expanded.json"), Product.class);

        EntityWithMeta value = productAttributeValue(product, 2);
        Assertions.assertNotNull(value);
        Assertions.assertEquals(EntityWithMeta.class, value.getClass());
        Assertions.assertEquals("futuretype", value.getMeta().getType());
    }

    @Test
    void counterpartyAttributeWithExpandedCounterpartyResolvesToCounterparty() throws Exception {
        Counterparty counterparty = MAPPER.readValue(
                fixture("counterparty_attribute_counterparty_expanded.json"), Counterparty.class);

        AttributeAbstract attribute = counterparty.getAttributes().get(0);
        AttributeCounterpartyEntity entityAttribute =
                Assertions.assertInstanceOf(AttributeCounterpartyEntity.class, attribute);
        Counterparty linked = Assertions.assertInstanceOf(Counterparty.class, entityAttribute.getValue());
        Assertions.assertEquals("ООО Головная компания (expand)", linked.getName());
        Assertions.assertEquals("7736570901", linked.getInn());
    }

    @Test
    void taskOperationWithExpandedCustomerOrderResolvesToCustomerOrder() throws Exception {
        Task task = MAPPER.readValue(fixture("task_operation_customerorder_expanded.json"), Task.class);

        CustomerOrder order = Assertions.assertInstanceOf(CustomerOrder.class, task.getOperation());
        Assertions.assertEquals("00017", order.getName());
        Assertions.assertEquals(150000.0, order.getSum());
        Assertions.assertEquals(Boolean.TRUE, order.getApplicable());
        Counterparty agent = Assertions.assertInstanceOf(Counterparty.class, order.getAgent());
        Assertions.assertEquals("counterparty", agent.getMeta().getType());
    }

    @Test
    void selfReferencingEmployeeOwnerDeserializesWithoutLooping() throws Exception {
        Employee employee = MAPPER.readValue(fixture("employee_owner_self_reference.json"), Employee.class);

        Employee owner = Assertions.assertInstanceOf(Employee.class, employee.getOwner());
        Assertions.assertEquals("Владелец (expand)", owner.getName());
        Employee ownerOfOwner = Assertions.assertInstanceOf(Employee.class, owner.getOwner());
        Assertions.assertEquals("employee", ownerOfOwner.getMeta().getType());
        Assertions.assertNull(ownerOfOwner.getName());
    }

    @Test
    void batchResponseKeepsErrorsFallbackAndResolvesNestedValues() throws Exception {
        String json = "[" + fixture("product_attribute_product_expanded.json") + ","
                + "{\"errors\":[{\"error\":\"Ошибка в структуре JSON передаваемого запроса\",\"code\":1000}]}"
                + "]";

        List<BatchResponseEntity> models = MAPPER.readValue(json, new TypeReference<List<BatchResponseEntity>>() {});

        Assertions.assertEquals(2, models.size());
        Product product = Assertions.assertInstanceOf(Product.class, models.get(0));
        Assertions.assertInstanceOf(Product.class, productAttributeValue(product, 0));
        Errors errors = Assertions.assertInstanceOf(Errors.class, models.get(1));
        Assertions.assertEquals(1000, errors.getErrors().get(0).getCode());
    }

    @Test
    void repeatedDeserializationReusesCachedDeserializers() throws Exception {
        String json = fixture("product_attribute_product_expanded.json");
        for (int i = 0; i < 3; i++) {
            Product product = MAPPER.readValue(json, Product.class);
            Assertions.assertInstanceOf(Product.class, productAttributeValue(product, 0));
        }
    }

    private static EntityWithMeta productAttributeValue(Product product, int index) {
        AttributeAbstract attribute = product.getAttributes().get(index);
        return Assertions.assertInstanceOf(AttributeProductEntity.class, attribute).getValue();
    }

    private String fixture(String filename) throws URISyntaxException, IOException {
        Path path = getFixturesPath().resolve("polymorphic").resolve(filename);
        Assertions.assertTrue(Files.exists(path), "Polymorphic fixture not found: " + filename);
        return new String(Files.readAllBytes(path), StandardCharsets.UTF_8);
    }
}
