package com.lognex.test.smoke;

import java.util.Arrays;

import static org.junit.jupiter.api.Assertions.assertNotEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

final class SmokeEndpointCase {
    private static final int[] BY_ID_ACCEPTABLE_CODES = {200, 401, 404, 500};
    private static final int[] BY_ID_OR_204_ACCEPTABLE_CODES = {200, 204, 401, 404, 500};
    private static final int[] DELETE_CODES = {200, 204, 401, 404, 500};

    private final String name;
    private final String method;
    private final String path;
    private final Expectation expectation;
    private final boolean hasBody;

    SmokeEndpointCase(String name, String method, String path, Expectation expectation, boolean hasBody) {
        this.name = name;
        this.method = method;
        this.path = path;
        this.expectation = expectation;
        this.hasBody = hasBody;
    }

    String getName() {
        return name;
    }

    String getMethod() {
        return method;
    }

    String getPath() {
        return path;
    }

    boolean hasBody() {
        return hasBody;
    }

    @Override
    public String toString() {
        return name + " [" + method + " " + path + "]";
    }

    String resolvePath(String testUuid) {
        return path.replace("{id}", testUuid);
    }

    void assertStatus(int statusCode) {
        switch (expectation) {
            case BY_ID:
                assertTrue(contains(BY_ID_ACCEPTABLE_CODES, statusCode),
                        "Unexpected status for by-id endpoint: " + statusCode);
                break;
            case BY_ID_OR_204:
                assertTrue(contains(BY_ID_OR_204_ACCEPTABLE_CODES, statusCode),
                        "Unexpected status for by-id endpoint (204 allowed): " + statusCode);
                break;
            case DELETE:
                assertTrue(contains(DELETE_CODES, statusCode),
                        "Unexpected status for delete endpoint: " + statusCode);
                break;
            case NOT_404:
            default:
                assertNotEquals(404, statusCode,
                        "404 means endpoint path did not match; expected to reach the endpoint");
                break;
        }
    }

    private static boolean contains(int[] values, int statusCode) {
        return Arrays.stream(values).anyMatch(code -> code == statusCode);
    }

    enum Expectation {
        NOT_404,
        BY_ID,
        BY_ID_OR_204,
        DELETE
    }
}
