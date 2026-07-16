package com.lognex.test.smoke;

import com.lognex.test.BaseTestCase;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.MethodSource;

import java.io.IOException;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

class ApiEndpointsTest extends BaseTestCase {
    private static final String API_BASE_PATH = "/api/remap/1.2";
    private static final String TEST_UUID = "12345678-1234-1234-1234-123456789012";

    @ParameterizedTest(name = "{index} {0}")
    @MethodSource("com.lognex.test.smoke.SmokeEndpointCatalog#cases")
    void endpointIsReachable(SmokeEndpointCase testCase) throws IOException {
        int statusCode = request(testCase);
        testCase.assertStatus(statusCode);
    }

    private int request(SmokeEndpointCase testCase) throws IOException {
        String path = API_BASE_PATH + testCase.resolvePath(TEST_UUID);
        URL url = new URL(getPrismBaseUrl() + path);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setRequestMethod(testCase.getMethod());
        connection.setRequestProperty("Accept", "application/json");
        connection.setRequestProperty("Content-Type", "application/json");
        connection.setConnectTimeout(5000);
        connection.setReadTimeout(30000);

        String payload = SmokePayloads.resolve(testCase);
        if (payload != null) {
            connection.setDoOutput(true);
            byte[] body = payload.getBytes(StandardCharsets.UTF_8);
            connection.setFixedLengthStreamingMode(body.length);
            try (OutputStream os = connection.getOutputStream()) {
                os.write(body);
            }
        }

        try {
            return connection.getResponseCode();
        } finally {
            connection.disconnect();
        }
    }
}
