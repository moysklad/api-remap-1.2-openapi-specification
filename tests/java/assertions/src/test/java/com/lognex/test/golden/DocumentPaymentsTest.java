package com.lognex.test.golden;

import java.nio.file.Files;
import java.util.List;
import java.util.stream.Stream;

import com.lognex.test.BaseTestCase;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.Arguments;
import org.junit.jupiter.params.provider.MethodSource;
import ru.moysklad.remap_1_2.ApiClient;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;

class DocumentPaymentsTest extends BaseTestCase {
    private static final ObjectMapper MAPPER = new ApiClient().getObjectMapper();
    private static final String MODELS = "ru.moysklad.remap_1_2.model.";

    static Stream<Arguments> paymentCases() throws Exception {
        JsonNode cases = MAPPER.readTree(Files.readString(
                new DocumentPaymentsTest().getFixturesPath().resolve("polymorphic/document_payments.json")));
        Stream.Builder<Arguments> result = Stream.builder();
        for (JsonNode entry : cases) {
            // A reference and an expanded payment per variant; zero only on the representative pair.
            Double expandedSum = entry.path("linkedSum").asBoolean(true) ? 123.45 : null;
            Object[][] scenarios = entry.get("document").asString().equals("RetailShift")
                    ? new Object[][] {{false, null}, {true, expandedSum}, {true, 0.0}}
                    : new Object[][] {{false, null}, {true, expandedSum}};
            for (Object[] scenario : scenarios) {
                result.add(Arguments.of(entry.get("document").asString(), entry.get("base").asString(),
                        entry.get("variant").asString(), entry.get("type").asString(), scenario[0], scenario[1]));
            }
        }
        return result.build();
    }

    @ParameterizedTest
    @MethodSource("paymentCases")
    void selectsPaymentAndPreservesFields(String document, String base, String variant, String type,
                                         boolean expanded, Double linkedSum) throws Exception {
        var payment = MAPPER.createObjectNode();
        payment.putObject("meta").put("type", type).put("mediaType", "application/json")
                .put("href", "https://api.moysklad.ru/api/remap/1.2/entity/" + type + "/1");
        if (expanded) {
            payment.put("name", "Expanded payment").put("sum", 456.75).put("paymentPurpose", "Invoice settlement");
        }
        if (linkedSum != null) {
            payment.put("linkedSum", linkedSum);
        }
        var payload = MAPPER.createObjectNode();
        payload.putArray("payments").add(payment);
        Class<?> ownerClass = Class.forName(MODELS + document);
        Object owner = MAPPER.readValue(payload.toString(), ownerClass);
        Object actual = ((List<?>) ownerClass.getMethod("getPayments").invoke(owner)).get(0);
        Assertions.assertEquals(Class.forName(MODELS + variant), actual.getClass());
        Assertions.assertInstanceOf(Class.forName(MODELS + base), actual);
        Assertions.assertEquals(expanded ? "Expanded payment" : null, actual.getClass().getMethod("getName").invoke(actual));
        JsonNode serialized = MAPPER.readTree(MAPPER.writeValueAsString(owner)).get("payments").get(0);
        Assertions.assertEquals(payment, serialized);
        Object roundtrip = MAPPER.readValue(MAPPER.writeValueAsString(owner), ownerClass);
        Assertions.assertEquals(actual.getClass(), ((List<?>) ownerClass.getMethod("getPayments").invoke(roundtrip)).get(0).getClass());
    }
}
