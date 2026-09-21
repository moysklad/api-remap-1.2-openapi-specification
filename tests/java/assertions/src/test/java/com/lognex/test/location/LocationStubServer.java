package com.lognex.test.location;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.InetAddress;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.charset.StandardCharsets;
import java.util.ArrayDeque;
import java.util.Deque;
import java.util.concurrent.atomic.AtomicInteger;

/**
 * Локальный HTTP-стаб с фиксированными ответами и счётчиком обращений к адресу из Location.
 *
 * Ответ пишется в сокет побайтово, поэтому тест задаёт точный регистр имени заголовка
 * и отправляет ответы без тела так, как это делает API.
 */
final class LocationStubServer implements AutoCloseable {
    private static final String LOCATION_TARGET_PATH = "/print-result";

    private final ServerSocket serverSocket;
    private final Thread acceptor;
    private final Deque<StubResponse> responses = new ArrayDeque<>();
    private final AtomicInteger locationHits = new AtomicInteger();
    private volatile boolean running = true;

    LocationStubServer() throws IOException {
        serverSocket = new ServerSocket(0, 0, InetAddress.getLoopbackAddress());
        acceptor = new Thread(this::acceptLoop, "location-stub");
        acceptor.setDaemon(true);
        acceptor.start();
    }

    String getBasePath() {
        return "http://127.0.0.1:" + serverSocket.getLocalPort() + "/api/remap/1.2";
    }

    String locationUrl(String name) {
        return "http://127.0.0.1:" + serverSocket.getLocalPort() + LOCATION_TARGET_PATH + "/" + name;
    }

    /** Количество обращений к адресу, переданному в Location. */
    int getLocationHits() {
        return locationHits.get();
    }

    /** Ответы отдаются в порядке постановки в очередь, по одному на вызов. */
    void enqueue(int statusCode, String headerName, String headerValue, String body) {
        synchronized (responses) {
            responses.addLast(new StubResponse(statusCode, headerName, headerValue, body));
        }
    }

    void enqueue(int statusCode) {
        enqueue(statusCode, null, null, null);
    }

    void enqueueHeader(int statusCode, String headerName, String headerValue) {
        enqueue(statusCode, headerName, headerValue, null);
    }

    void enqueueBody(int statusCode, String body) {
        enqueue(statusCode, null, null, body);
    }

    @Override
    public void close() throws IOException {
        running = false;
        serverSocket.close();
        acceptor.interrupt();
    }

    private void acceptLoop() {
        while (running) {
            try (Socket socket = serverSocket.accept()) {
                handle(socket);
            } catch (IOException e) {
                if (running) {
                    throw new IllegalStateException("Стаб не смог обработать соединение", e);
                }
            }
        }
    }

    private void handle(Socket socket) throws IOException {
        InputStream input = socket.getInputStream();
        String requestLine = readLine(input);
        if (requestLine == null) {
            return;
        }

        int contentLength = 0;
        String header;
        while ((header = readLine(input)) != null && !header.isEmpty()) {
            if (header.toLowerCase().startsWith("content-length:")) {
                contentLength = Integer.parseInt(header.substring(header.indexOf(':') + 1).trim());
            }
        }
        readExactly(input, contentLength);

        write(socket.getOutputStream(), responseFor(requestLine));
    }

    private StubResponse responseFor(String requestLine) {
        if (requestLine.contains(LOCATION_TARGET_PATH)) {
            locationHits.incrementAndGet();
            return new StubResponse(200, null, null, null);
        }

        synchronized (responses) {
            StubResponse response = responses.pollFirst();
            if (response == null) {
                throw new IllegalStateException("Стаб получил незапланированный запрос: " + requestLine);
            }
            return response;
        }
    }

    private void write(OutputStream output, StubResponse response) throws IOException {
        byte[] body = response.body() == null
                ? new byte[0]
                : response.body().getBytes(StandardCharsets.UTF_8);

        StringBuilder head = new StringBuilder("HTTP/1.1 ").append(response.statusCode()).append(" Stub\r\n");
        if (response.headerName() != null) {
            head.append(response.headerName()).append(": ").append(response.headerValue()).append("\r\n");
        }
        if (body.length > 0) {
            head.append("Content-Type: application/json;charset=utf-8\r\n");
        }
        head.append("Content-Length: ").append(body.length).append("\r\n");
        head.append("Connection: close\r\n\r\n");

        output.write(head.toString().getBytes(StandardCharsets.US_ASCII));
        output.write(body);
        output.flush();
    }

    private static String readLine(InputStream input) throws IOException {
        ByteArrayOutputStream buffer = new ByteArrayOutputStream();
        int current;
        while ((current = input.read()) != -1) {
            if (current == '\n') {
                break;
            }
            if (current != '\r') {
                buffer.write(current);
            }
        }
        if (current == -1 && buffer.size() == 0) {
            return null;
        }
        return buffer.toString(StandardCharsets.US_ASCII);
    }

    private static void readExactly(InputStream input, int length) throws IOException {
        for (int read = 0; read < length; read++) {
            if (input.read() == -1) {
                return;
            }
        }
    }

    /** Один фиксированный ответ стаба. */
    private record StubResponse(int statusCode, String headerName, String headerValue, String body) {
    }
}
