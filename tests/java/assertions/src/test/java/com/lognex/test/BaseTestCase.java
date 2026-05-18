package com.lognex.test;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.Assertions;

import java.io.IOException;
import java.net.URISyntaxException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.fail;

/**
 * Базовый класс для всех тестов SDK.
 *
 * Предоставляет общие методы для:
 * - Загрузки fixture файлов (эталонные JSON данные)
 * - Получения URL Prism mock сервера
 * - Работы с путями к сгенерированному SDK
 */
public abstract class BaseTestCase {
    private static final ObjectMapper OBJECT_MAPPER = new ObjectMapper();

    /**
     * Возвращает путь к директории с fixture файлами.
     */
    protected Path getFixturesPath() throws URISyntaxException {
        return Paths.get(getClass().getClassLoader().getResource("").toURI());
    }

    /**
     * Загружает JSON fixture файл и возвращает его содержимое как Map.
     */
    protected Map<String, Object> loadFixture(String filename) throws URISyntaxException {
        Path path = getFixturesPath().resolve(filename);

        if (!Files.exists(path)) {
            return Assertions.fail("Fixture file not found: " + path);
        }

        try {
            String content = new String(Files.readAllBytes(path), StandardCharsets.UTF_8);
            return OBJECT_MAPPER.readValue(content, new TypeReference<Map<String, Object>>() {});
        } catch (JsonProcessingException e) {
            return Assertions.fail("Failed to parse JSON fixture '" + filename + "': " + e.getOriginalMessage());
        } catch (IOException e) {
            return Assertions.fail("Failed to read fixture '" + filename + "': " + e.getMessage());
        }
    }

    /**
     * Возвращает базовый URL Prism mock сервера.
     */
    protected String getPrismBaseUrl() {
        String value = System.getenv("SMOKE_BASE_URL");
        return (value == null || value.isEmpty()) ? "http://mock:8080" : value;
    }
}
