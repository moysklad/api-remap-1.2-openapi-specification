<?php

declare(strict_types=1);

namespace MoySklad\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Базовый класс для всех тестов SDK.
 * 
 * Предоставляет общие методы для:
 * - Загрузки fixture файлов (эталонные JSON данные)
 * - Получения URL Prism mock сервера
 * - Работы с путями к сгенерированному SDK
 * 
 * @package MoySklad\Tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Возвращает путь к директории с fixture файлами.
     * Fixtures содержат эталонные JSON данные для тестирования сериализации.
     * 
     * @return string Абсолютный путь к директории fixtures
     */
    protected function getFixturesPath(): string
    {
        return __DIR__ . '/../fixtures';
    }

    /**
     * Загружает JSON fixture файл и возвращает его содержимое как массив.
     * 
     * @param string $filename Имя файла (например, 'product.json')
     * @return array<string, mixed> Декодированные JSON данные
     * @throws \PHPUnit\Framework\AssertionFailedError Если файл не найден или JSON невалиден
     */
    protected function loadFixture(string $filename): array
    {
        $path = $this->getFixturesPath() . '/' . $filename;
        
        if (!file_exists($path)) {
            $this->fail("Fixture file not found: {$path}");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Failed to parse JSON fixture '{$filename}': " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Возвращает список всех fixture файлов в директории.
     * 
     * @return array<string> Массив имён файлов
     */
    protected function getFixtureFiles(): array
    {
        $path = $this->getFixturesPath();
        $files = glob($path . '/*.json');
        
        return array_map('basename', $files ?: []);
    }

    /**
     * Возвращает базовый URL Prism mock сервера.
     * Значение берётся из переменной окружения PRISM_BASE_URL.
     * 
     * @return string URL сервера (например, 'http://localhost:4010')
     */
    protected function getPrismBaseUrl(): string
    {
        return $_ENV['PRISM_BASE_URL'] ?? getenv('PRISM_BASE_URL') ?: 'http://localhost:4010';
    }

    /**
     * Возвращает путь к сгенерированному SDK.
     * 
     * @return string Относительный путь к директории SDK
     */
    protected function getSdkPath(): string
    {
        return $_ENV['SDK_PATH'] ?? getenv('SDK_PATH') ?: '../../clients/php';
    }
}
