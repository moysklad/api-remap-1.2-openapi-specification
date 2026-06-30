package com.lognex.test.smoke;

import com.lognex.test.BaseTestCase;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.Test;

import java.io.IOException;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;

/**
 * Минимальная smoke-проверка доступности API endpoint'ов через openapi-mock.
 * Это заглушка, чтобы запускался Java smoke pipeline вместо PHP.
 */
class ApiEndpointsTest extends BaseTestCase {
    private static final String API_BASE_PATH = "/api/remap/1.2";
    private static final String HEALTHCHECK_ENDPOINT = "/entity/product";

    @Test
    void endpointIsReachable() throws URISyntaxException {
        final URI uri = buildUri(HEALTHCHECK_ENDPOINT);
        final HttpClient client = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(10))
            .build();
        final HttpRequest request = HttpRequest.newBuilder(uri)
            .timeout(Duration.ofSeconds(30))
            .header("Accept", "application/json")
            .GET()
            .build();

        try {
            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            Assertions.assertNotEquals(
                404,
                response.statusCode(),
                "404 means endpoint path did not match; expected to reach the endpoint: " + uri
            );
        } catch (IOException e) {
            Assertions.fail("Failed to call smoke endpoint " + uri + ": " + e.getMessage(), e);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            Assertions.fail("Smoke request interrupted for endpoint " + uri, e);
        }
    }

    private URI buildUri(String endpointPath) throws URISyntaxException {
        String baseUrl = getPrismBaseUrl();
        if (baseUrl == null || baseUrl.trim().isEmpty()) {
            Assertions.fail("SMOKE_BASE_URL is empty");
        }

        String normalizedBase = baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length() - 1) : baseUrl;
        String normalizedEndpoint = endpointPath.startsWith("/") ? endpointPath : "/" + endpointPath;
        return new URI(normalizedBase + API_BASE_PATH + normalizedEndpoint);
    }
}
