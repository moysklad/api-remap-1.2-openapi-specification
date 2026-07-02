package com.lognex.test.smoke;

import java.util.Arrays;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.function.Predicate;

final class SmokePayloads {

    private static final String META_HREF_BASE = "https://api.moysklad.ru/api/remap/1.2";
    private static final String TEST_UUID = "12345678-1234-1234-1234-123456789012";
    private static final String PNG_BASE64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADklEQVQI12P4z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==";

    private static final String EMPTY_ARRAY = "[]";

    private static final String FILES = array(obj(str("filename", "doc.pdf"), str("content", "SGVsbG8gV29ybGQ=")));

    private static final String IMAGE = obj(str("filename", "test.png"), str("content", PNG_BASE64));

    private static final String MOVE = obj(
            ref("sourceStore", "store", "/entity/store"),
            ref("targetStore", "store", "/entity/store"));

    private static final String LOSS = obj(
            ref("organization", "organization", "/entity/organization"),
            ref("store", "store", "/entity/store"));

    private static final String DOCUMENT_WITH_AGENT = obj(
            ref("organization", "organization", "/entity/organization"),
            ref("agent", "counterparty", "/entity/counterparty"),
            ref("store", "store", "/entity/store"));

    private static final String STATE =
            obj(str("name", "state1"), field("color", "15106326"), str("stateType", "Regular"));

    private static final String ATTRIBUTE = obj(str("name", "attr1"));

    private static final String POSITIONS_BATCH = array(obj(field("quantity", "1")));

    private static final String POSITION = obj(field("quantity", "1"));

    private static final String CHARACTERISTIC = obj(str("name", "Characteristic X"));

    private static final String ACTIVATE = obj(str("login", "newemployee@lognex"));

    private static final String EXPORT = obj(
            ref("organization", "organization", "/entity/organization"),
            field("count", "10"),
            field("salePrice", obj(ref("priceType", "pricetype", "/context/companysettings/pricetype"))),
            ref("template", "embeddedtemplate", "/entity/assortment/metadata/embeddedtemplate"));

    private static final String DEFAULT_PAYLOAD = obj(str("name", "Smoke Test"));

    private static final List<Rule> RULES = Arrays.asList(
            rule(methods("POST", "PUT").and(pathEndsWith("/delete")), EMPTY_ARRAY),
            rule(methods("POST").and(pathEndsWith("/files")), FILES),
            rule(methods("POST").and(pathEndsWith("/images")), IMAGE),
            rule(methods("POST").and(pathIn("/entity/move")), MOVE),
            rule(methods("POST").and(pathIn("/entity/loss")), LOSS),
            rule(methods("POST").and(pathIn("/entity/salesreturn", "/entity/supply", "/entity/purchasereturn", "/entity/enter")), DOCUMENT_WITH_AGENT),
            rule(methods("POST").and(pathEndsWith("/metadata/states")), STATE),
            rule(methods("POST", "PUT").and(pathContains("/metadata/attributes")), ATTRIBUTE),
            rule(methods("POST").and(pathEndsWith("/positions/batch")), POSITIONS_BATCH),
            rule(methods("POST", "PUT").and(pathContains("/positions")), POSITION),
            rule(methods("POST").and(pathContains("/metadata/characteristics")), CHARACTERISTIC),
            rule(methods("PUT").and(pathEndsWith("/access/activate")), ACTIVATE),
            rule(methods("POST").and(pathEndsWith("/export")), EXPORT));

    private SmokePayloads() {
    }

    static String resolve(SmokeEndpointCase testCase) {
        if (!testCase.hasBody()) {
            return null;
        }
        for (Rule rule : RULES) {
            if (rule.condition.test(testCase)) {
                return rule.payload;
            }
        }
        return DEFAULT_PAYLOAD;
    }

    // --- Condition builders -------------------------------------------------

    private static Predicate<SmokeEndpointCase> methods(String... methods) {
        Set<String> allowed = new HashSet<>(Arrays.asList(methods));
        return testCase -> allowed.contains(testCase.getMethod());
    }

    private static Predicate<SmokeEndpointCase> pathEndsWith(String suffix) {
        return testCase -> testCase.getPath().endsWith(suffix);
    }

    private static Predicate<SmokeEndpointCase> pathContains(String part) {
        return testCase -> testCase.getPath().contains(part);
    }

    private static Predicate<SmokeEndpointCase> pathIn(String... paths) {
        Set<String> allowed = new HashSet<>(Arrays.asList(paths));
        return testCase -> allowed.contains(testCase.getPath());
    }

    private static Rule rule(Predicate<SmokeEndpointCase> condition, String payload) {
        return new Rule(condition, payload);
    }

    private static String obj(String... fields) {
        return "{" + String.join(",", fields) + "}";
    }

    private static String array(String... items) {
        return "[" + String.join(",", items) + "]";
    }

    private static String str(String name, String value) {
        return "\"" + name + "\":\"" + value + "\"";
    }

    private static String field(String name, String rawValue) {
        return "\"" + name + "\":" + rawValue;
    }

    private static String ref(String name, String type, String hrefPath) {
        return field(name, meta(type, hrefPath));
    }

    private static String meta(String type, String hrefPath) {
        return obj(field("meta", obj(
                str("href", META_HREF_BASE + hrefPath + "/" + TEST_UUID),
                str("type", type),
                str("mediaType", "application/json"))));
    }

    private static final class Rule {
        private final Predicate<SmokeEndpointCase> condition;
        private final String payload;

        Rule(Predicate<SmokeEndpointCase> condition, String payload) {
            this.condition = condition;
            this.payload = payload;
        }
    }
}
