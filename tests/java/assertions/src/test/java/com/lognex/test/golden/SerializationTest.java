package com.lognex.test.golden;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.lognex.test.BaseTestCase;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.MethodSource;
import ru.moysklad.remap_1_2.ApiClient;

import java.io.File;
import java.lang.reflect.Constructor;
import java.lang.reflect.Method;
import java.net.URISyntaxException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.*;
import java.util.stream.Stream;

import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Golden tests for SDK serialization/deserialization.
 * <p>
 * Roundtrip flow:
 * 1) load fixture JSON
 * 2) deserialize to model object
 * 3) serialize model back to JSON-like structure
 * 4) compare with normalized fixture
 */
class SerializationTest extends BaseTestCase {

    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();

    private static final Map<String, String> FIXTURE_MODEL_MAP = new HashMap<>();

    static {
        FIXTURE_MODEL_MAP.put("product", "Product");
        FIXTURE_MODEL_MAP.put("product_full", "Product");
        FIXTURE_MODEL_MAP.put("product_image", "Image");
        FIXTURE_MODEL_MAP.put("product_storebalance", "StoreBalance");
        FIXTURE_MODEL_MAP.put("counterparty", "Counterparty");
        FIXTURE_MODEL_MAP.put("counterparty_legal", "Counterparty");
        FIXTURE_MODEL_MAP.put("counterparty_metadata", "CounterpartyMetadata");
        FIXTURE_MODEL_MAP.put("counterparty_metadata_expanded", "CounterpartyMetadata");
        FIXTURE_MODEL_MAP.put("counterparty_metadata_minimum", "CounterpartyMetadata");
        FIXTURE_MODEL_MAP.put("document_metadata", "DocumentMetadata");
        FIXTURE_MODEL_MAP.put("document_metadata_expanded", "DocumentMetadata");
        FIXTURE_MODEL_MAP.put("product_metadata", "Metadata");
        FIXTURE_MODEL_MAP.put("product_metadata_expanded", "Metadata");
        FIXTURE_MODEL_MAP.put("counterparty_account", "Account");
        FIXTURE_MODEL_MAP.put("counterparty_contactperson", "ContactPerson");
        FIXTURE_MODEL_MAP.put("counterparty_note", "Note");
        FIXTURE_MODEL_MAP.put("counterparty_file", "ModelFile");
        FIXTURE_MODEL_MAP.put("currency", "Currency");
        FIXTURE_MODEL_MAP.put("employee", "Employee");
        FIXTURE_MODEL_MAP.put("employee_security", "EmployeeSecurity");
        FIXTURE_MODEL_MAP.put("employee_role", "EmployeeRole");
        FIXTURE_MODEL_MAP.put("group", "Group");
        FIXTURE_MODEL_MAP.put("entity_with_extra_field", "Group");
        FIXTURE_MODEL_MAP.put("country", "Country");
        FIXTURE_MODEL_MAP.put("region", "Region");
        FIXTURE_MODEL_MAP.put("tax_rate", "TaxRate");
        FIXTURE_MODEL_MAP.put("product_folder", "ProductFolder");
        FIXTURE_MODEL_MAP.put("processing_plan_folder", "ProcessingPlanFolder");
        FIXTURE_MODEL_MAP.put("service", "Service");
        FIXTURE_MODEL_MAP.put("uom", "Uom");
        FIXTURE_MODEL_MAP.put("price_type", "PriceType");
        FIXTURE_MODEL_MAP.put("sale_platform", "SalePlatform");
        FIXTURE_MODEL_MAP.put("store", "Store");
        FIXTURE_MODEL_MAP.put("retail_store", "RetailStore");
        FIXTURE_MODEL_MAP.put("cashier", "Cashier");
        FIXTURE_MODEL_MAP.put("bundle", "Bundle");
        FIXTURE_MODEL_MAP.put("bundle_component", "BundleComponent");
        FIXTURE_MODEL_MAP.put("variant", "Variant");
        FIXTURE_MODEL_MAP.put("webhook", "Webhook");
        FIXTURE_MODEL_MAP.put("webhookstock", "WebhookStock");
        FIXTURE_MODEL_MAP.put("thing", "Thing");
        FIXTURE_MODEL_MAP.put("internal_order", "InternalOrder");
        FIXTURE_MODEL_MAP.put("customer_order", "CustomerOrder");
        FIXTURE_MODEL_MAP.put("demand", "Demand");
        FIXTURE_MODEL_MAP.put("purchase_order", "PurchaseOrder");
        FIXTURE_MODEL_MAP.put("emission_order", "EmissionOrder");
        FIXTURE_MODEL_MAP.put("event_note", "EventNote");
        FIXTURE_MODEL_MAP.put("variantcharacteristic", "VariantCharacteristic");
        FIXTURE_MODEL_MAP.put("contract", "Contract");
        FIXTURE_MODEL_MAP.put("task", "Task");
        FIXTURE_MODEL_MAP.put("sales_channel", "SalesChannel");
        FIXTURE_MODEL_MAP.put("project", "Project");
        FIXTURE_MODEL_MAP.put("consignment", "Consignment");
        FIXTURE_MODEL_MAP.put("expense_item", "ExpenseItem");
        FIXTURE_MODEL_MAP.put("cash_in", "CashIn");
        FIXTURE_MODEL_MAP.put("retail_drawer_cash_in", "RetailDrawerCashIn");
        FIXTURE_MODEL_MAP.put("cash_in_operation", "CashInOperation");
        FIXTURE_MODEL_MAP.put("cash_out", "CashOut");
        FIXTURE_MODEL_MAP.put("cash_out_operation", "CashOutOperation");
        FIXTURE_MODEL_MAP.put("facture_in", "FactureIn");
        FIXTURE_MODEL_MAP.put("facture_out", "FactureOut");
        FIXTURE_MODEL_MAP.put("company_settings", "CompanySettings");
        FIXTURE_MODEL_MAP.put("company_settings_metadata", "CompanySettingsMetadata");
        FIXTURE_MODEL_MAP.put("user_settings", "UserSettings");
        FIXTURE_MODEL_MAP.put("subscription", "Subscription");
        FIXTURE_MODEL_MAP.put("assortment_settings", "AssortmentSettings");
        FIXTURE_MODEL_MAP.put("assortment", "Assortment");
        FIXTURE_MODEL_MAP.put("discount", "Discount");
        FIXTURE_MODEL_MAP.put("accumulation_discount", "AccumulationDiscount");
        FIXTURE_MODEL_MAP.put("personal_discount", "PersonalDiscount");
        FIXTURE_MODEL_MAP.put("special_price_discount", "SpecialPriceDiscount");
        FIXTURE_MODEL_MAP.put("bonus_program", "BonusProgram");
        FIXTURE_MODEL_MAP.put("bonus_transaction", "BonusTransaction");
        FIXTURE_MODEL_MAP.put("custom_entity", "CustomEntity");
        FIXTURE_MODEL_MAP.put("custom_entity_element", "CustomEntityElement");
        FIXTURE_MODEL_MAP.put("commission_report_in", "CommissionReportIn");
    }

    private static final Set<String> IGNORED_FIELDS = new HashSet<>();

    static {
        IGNORED_FIELDS.add("updated");
        IGNORED_FIELDS.add("created");
        IGNORED_FIELDS.add("accountId");
        IGNORED_FIELDS.add("pathName");
        IGNORED_FIELDS.add("effectiveVat");
        IGNORED_FIELDS.add("effectiveVatEnabled");
        IGNORED_FIELDS.add("variantsCount");
        IGNORED_FIELDS.add("tobacco");
        IGNORED_FIELDS.add("salesAmount");
        IGNORED_FIELDS.add("bonusPoints");
        IGNORED_FIELDS.add("extra_field");//check objectMapper.configure(DeserializationFeature.FAIL_ON_UNKNOWN_PROPERTIES, false);
    }

    /**
     * Тест на конситентность существующий fixture-файлов и маппинга FIXTURE_MODEL_MAP.
     */
    @Test
    void testMappingAndFixtureConsistency() throws URISyntaxException {
        final File[] files = getFixturesPath().toFile().listFiles((dir, name) -> name.endsWith(".json"));
        for (File file : files) {
            assertTrue(FIXTURE_MODEL_MAP.containsKey(file.getName().replace(".json", "")),
                    "Model mapping not found for " + file.getName());
        }
    }

    /**
     * Тест roundtrip сериализации: JSON -> Model -> JSON.
     * Проверяет, что данные не теряются и не искажаются при преобразованиях.
     *
     * @param fixtureName Имя fixture файла (без расширения)
     * @param modelName   Имя класса модели
     */
    @ParameterizedTest(name = "{0}")
    @MethodSource("fixtureProvider")
    void testRoundtripSerialization(String fixtureName, String modelName) throws URISyntaxException {
        String fixtureFile = fixtureName + ".json";
        Path fixturePath = getFixturesPath().resolve(fixtureFile);
        Assertions.assertTrue(Files.exists(fixturePath), "Fixture file not found: " + fixtureFile);

        Map<String, Object> originalJson = loadFixture(fixtureFile);
        String modelClass = getModelClass(modelName);

        Class<?> clazz = loadClass(modelClass);
        assertNotNull(clazz, "Model class not found: " + modelClass + ". SDK may not be generated.");

        Object model = deserialize(originalJson, clazz);
        Assertions.assertNotNull(model, "Failed to deserialize " + fixtureFile + " to " + modelName);

        Map<String, Object> serializedJson = serialize(model);

        Map<String, Object> normalizedOriginal = normalizeForComparison(originalJson);
        Map<String, Object> normalizedSerialized = normalizeForComparison(serializedJson);

        assertNormalizedEquals(
                normalizedOriginal,
                normalizedSerialized,
                "Roundtrip serialization failed for " + fixtureName + ": data mismatch after deserialize->serialize"
        );
    }

    private static Stream<org.junit.jupiter.params.provider.Arguments> fixtureProvider() {
        return FIXTURE_MODEL_MAP.entrySet().stream()
                .map(e -> org.junit.jupiter.params.provider.Arguments.of(e.getKey(), e.getValue()));
    }

    private String getModelClass(String shortName) {
        return "ru.moysklad.remap_1_2.model." + shortName;
    }

    private Class<?> loadClass(String className) {
        try {
            return Class.forName(className);
        } catch (ClassNotFoundException e) {
            return null;
        }
    }

    private Object deserialize(Map<String, Object> data, Class<?> clazz) {
        try {
            return MAPPER.convertValue(data, clazz);
        } catch (Exception primary) {
            try {
                Constructor<?> constructor = clazz.getDeclaredConstructor(Map.class);
                constructor.setAccessible(true);
                return constructor.newInstance(data);
            } catch (Exception ignored) {
                return null;
            }
        }
    }

    @SuppressWarnings("unchecked")
    private Map<String, Object> serialize(Object model) {
        try {
            Method m = model.getClass().getMethod("toMap");
            Object out = m.invoke(model);
            if (out instanceof Map) {
                return (Map<String, Object>) out;
            }
        } catch (Exception ignored) {
            // Fall back to Jackson conversion if SDK-specific API is absent.
        }
        return (Map<String, Object>) MAPPER.convertValue(model, new TypeReference<Object>() {});
    }

    private Map<String, Object> normalizeForComparison(Map<String, Object> data) {
        Map<String, Object> normalized = new LinkedHashMap<>();

        for (Map.Entry<String, Object> entry : data.entrySet()) {
            String key = entry.getKey();
            Object value = entry.getValue();

            if (IGNORED_FIELDS.contains(key) /*|| value == null*/) {
                continue;
            }
            normalized.put(key, normalizeValue(value));
        }

        Object attributes = normalized.get("attributes");
        if (attributes instanceof List) {
            List<Object> fixed = new ArrayList<>();
            List<?> l = (List<?>) attributes;
            for (Object item : l) {
                if (item instanceof Map) {
                    Map<String, Object> attr = castMap((Map<?, ?>) item);
                    if (attr.containsKey("value")) {
                        attr.put("value", normalizeAttributeValue(attr.get("value")));
                    }
                    fixed.add(attr);
                } else {
                    fixed.add(item);
                }
            }
            normalized.put("attributes", fixed);
        }

        if (normalized.isEmpty()) {
            return new LinkedHashMap<>();
        }

        return new TreeMap<>(normalized);
    }

    private Object normalizeValue(Object value) {
        if (value instanceof Map) {
            return normalizeForComparison(castMap((Map<?, ?>) value));
        }
        if (value instanceof List) {
            List<Object> out = new ArrayList<>();
            for (Object item : ((List) value)) {
                out.add(normalizeValue(item));
            }
            return out;
        }
        if (value instanceof Double && (Double) value == Math.rint((Double) value)) {
            return (int) ((Double) value).doubleValue();
        }
        if (value instanceof Float && ((Float) value) == Math.rint((Float) value)) {
            return (int) ((Float) value).floatValue();
        }
        return value;
    }

    private Object normalizeAttributeValue(Object value) {
        if (!(value instanceof List)) {
            return value;
        }
        List<?> list = (List<?>) value;
        if (list.size() == 1) {
            Object single = list.get(0);
            return isScalar(single) ? single : value;
        }
        for (Object v : list) {
            if (isScalar(v)) {
                return v;
            }
        }
        return value;
    }

    private static boolean isScalar(Object value) {
        return value instanceof String
               || value instanceof Number
               || value instanceof Boolean
               || value instanceof Character;
    }

    private void assertNormalizedEquals(Map<String, Object> expected, Map<String, Object> actual, String message) {
        Map<String, Object> expectedSorted = sortKeysRecursive(expected);
        Map<String, Object> actualSorted = sortKeysRecursive(actual);
        List<Map<String, Object>> diff = diffNormalized(expectedSorted, actualSorted, "");
        if (!diff.isEmpty()) {
            Assertions.fail(message + "\nDifferences: " + toPrettyJson(diff));
        }
    }

    private Map<String, Object> sortKeysRecursive(Map<String, Object> input) {
        Map<String, Object> sorted = new TreeMap<>();
        for (Map.Entry<String, Object> e : input.entrySet()) {
            Object value = e.getValue();
            if (value instanceof Map) {
                sorted.put(e.getKey(), sortKeysRecursive(castMap((Map<?, ?>) value)));
            } else if (value instanceof List) {
                List<Object> mapped = new ArrayList<>();
                for (Object item : ((List) value)) {
                    if (item instanceof Map) {
                        mapped.add(sortKeysRecursive(castMap((Map<?, ?>) item)));
                    } else {
                        mapped.add(item);
                    }
                }
                sorted.put(e.getKey(), mapped);
            } else {
                sorted.put(e.getKey(), value);
            }
        }
        return sorted;
    }

    private List<Map<String, Object>> diffNormalized(Object expected, Object actual, String path) {
        if (expected instanceof Map && actual instanceof Map) {
            Set<String> allKeys = new LinkedHashSet<>();
            allKeys.addAll(castMap((Map<?, ?>) expected).keySet());
            allKeys.addAll(castMap((Map<?, ?>) actual).keySet());

            List<Map<String, Object>> diffs = new ArrayList<>();
            for (String key : allKeys) {
                Object e = castMap((Map<?, ?>) expected).getOrDefault(key, "заглушка для ключа, несуществующего в fixture");
                Object a = castMap((Map<?, ?>) actual).getOrDefault(key, null);
                if (e == null && a == null) {
                    continue;
                }
                String subPath = path.isEmpty() ? key : path + "." + key;
                diffs.addAll(diffNormalized(e, a, subPath));
            }
            return diffs;
        }

        if (expected instanceof List && actual instanceof List) {
            List<?> expectedList = (List<?>) expected;
            List<?> actualList = (List<?>) actual;
            int max = Math.max(expectedList.size(), actualList.size());
            List<Map<String, Object>> diffs = new ArrayList<>();
            for (int i = 0; i < max; i++) {
                Object e = i < expectedList.size() ? expectedList.get(i) : null;
                Object a = i < actualList.size() ? actualList.get(i) : null;
                if (e == null && a == null) {
                    continue;
                }
                String subPath = path + "[" + i + "]";
                diffs.addAll(diffNormalized(e, a, subPath));
            }
            return diffs;
        }

        if (bothNumericAndEqual(expected, actual)) {
            return Collections.emptyList();
        }
        if (Objects.equals(expected, actual)) {
            return Collections.emptyList();
        }

        Map<String, Object> mismatch = new LinkedHashMap<>();
        mismatch.put("path", path);
        mismatch.put("expected", expected);
        mismatch.put("actual", actual);
        return Collections.singletonList(mismatch);
    }

    private static boolean bothNumericAndEqual(Object expected, Object actual) {
        if (!(expected instanceof Number) || !(actual instanceof Number)) {
            return false;
        }
        double e = ((Number) expected).doubleValue();
        double a = ((Number) actual).doubleValue();
        return Double.compare(e, a) == 0;
    }

    private static Map<String, Object> castMap(Map<?, ?> map) {
        Map<String, Object> out = new LinkedHashMap<>();
        for (Map.Entry<?, ?> e : map.entrySet()) {
            out.put(String.valueOf(e.getKey()), e.getValue());
        }
        return out;
    }

    private String toPrettyJson(Object value) {
        try {
            return MAPPER.writerWithDefaultPrettyPrinter().writeValueAsString(value);
        } catch (Exception e) {
            return String.valueOf(value);
        }
    }
}
