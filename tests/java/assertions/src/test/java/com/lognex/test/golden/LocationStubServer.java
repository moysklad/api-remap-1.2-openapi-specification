package com.lognex.test.golden;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;

import java.io.IOException;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.Queue;
import java.util.concurrent.ConcurrentLinkedQueue;
import java.util.concurrent.atomic.AtomicInteger;

/**
 * Локальный HTTP-стаб с фиксированными ответами и счётчиком обращений к адресу из Location.
 */
final class LocationStubServer implements AutoCloseable {
    private static final String LOCATION_TARGET_PATH = "/print-result";

    private final HttpServer server;
    private final Queue<StubResponse> responses = new ConcurrentLinkedQueue<>();
    private final AtomicInteger locationHits = new AtomicInteger();

    LocationStubServer() throws IOException {
        server = HttpServer.create(new InetSocketAddress(InetAddress.getLoopbackAddress(), 0), 0);
        server.createContext("/", this::handle);
        server.start();
    }

    String getBasePath() {
        return "http://127.0.0.1:" + server.getAddress().getPort() + "/api/remap/1.2";
    }

    String locationUrl(String name) {
        return "http://127.0.0.1:" + server.getAddress().getPort() + LOCATION_TARGET_PATH + "/" + name;
    }

    /** Количество обращений к адресу, переданному в Location. */
    int getLocationHits() {
        return locationHits.get();
    }

    /** Ответы отдаются в порядке постановки в очередь, по одному на вызов. */
    void enqueue(int statusCode, String location, String body) {
        responses.add(new StubResponse(statusCode, location, body));
    }

    @Override
    public void close() {
        server.stop(0);
    }

    private void handle(HttpExchange exchange) throws IOException {
        exchange.getRequestBody().readAllBytes();

        StubResponse response = exchange.getRequestURI().getPath().startsWith(LOCATION_TARGET_PATH)
                ? hitLocation()
                : responses.poll();
        if (response == null) {
            response = new StubResponse(599, null, null);
        }

        if (response.location() != null) {
            exchange.getResponseHeaders().set("Location", response.location());
        }
        byte[] body = response.body() == null ? new byte[0] : response.body().getBytes(StandardCharsets.UTF_8);
        if (body.length > 0) {
            exchange.getResponseHeaders().set("Content-Type", "application/json;charset=utf-8");
        }
        exchange.sendResponseHeaders(response.statusCode(), body.length == 0 ? -1 : body.length);
        exchange.getResponseBody().write(body);
        exchange.close();
    }

    private StubResponse hitLocation() {
        locationHits.incrementAndGet();
        return new StubResponse(200, null, null);
    }

    private record StubResponse(int statusCode, String location, String body) {
    }
}
