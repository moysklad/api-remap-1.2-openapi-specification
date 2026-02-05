<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;

/**
 * Golden тесты для проверки сериализации/десериализации моделей SDK.
 * 
 * Тест выполняет roundtrip проверку:
 * 1. Загружает эталонный JSON из fixtures
 * 2. Десериализует JSON в объект модели SDK
 * 3. Сериализует объект обратно в JSON
 * 4. Сравнивает результат с эталоном
 * 
 * Это гарантирует, что SDK корректно обрабатывает все поля сущностей
 * без потери или искажения данных.
 * 
 * @see https://dev.moysklad.ru/doc/api/remap/1.2/
 */
class SerializationTest extends TestCase
{
    /**
     * Маппинг fixture файлов на классы моделей SDK.
     * Ключ - имя файла без расширения, значение - короткое имя класса модели.
     */
    private const FIXTURE_MODEL_MAP = [
        'product' => 'Product',
        'product_full' => 'Product',
        'counterparty' => 'Counterparty',
        'currency' => 'Currency',
        'employee' => 'Employee',
        'group' => 'Group',
        'country' => 'Country',
        'product_folder' => 'ProductFolder',
        'service' => 'Service',
        'uom' => 'Uom',
        'price_type' => 'PriceType',
    ];

    /**
     * Поля, которые игнорируются при сравнении (read-only поля, генерируемые сервером).
     * Эти поля могут отсутствовать или отличаться после roundtrip.
     */
    private const IGNORED_FIELDS = [
        'updated',
        'created', 
        'accountId',
        'pathName',
        'effectiveVat',
        'effectiveVatEnabled',
        'variantsCount',
        'tobacco',
        'salesAmount',
        'bonusPoints',
        'things',
    ];

    /**
     * @dataProvider fixtureProvider
     * 
     * Тест roundtrip сериализации: JSON -> Model -> JSON.
     * Проверяет, что данные не теряются и не искажаются при преобразованиях.
     * 
     * @param string $fixtureName Имя fixture файла (без расширения)
     * @param string $modelName Имя класса модели
     */
    public function testRoundtripSerialization(string $fixtureName, string $modelName): void
    {
        $fixtureFile = $fixtureName . '.json';
        
        // Проверяем наличие fixture файла
        $fixturePath = $this->getFixturesPath() . '/' . $fixtureFile;
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped("Fixture file not found: {$fixtureFile}");
        }

        // Загружаем эталонный JSON
        $originalJson = $this->loadFixture($fixtureFile);
        
        // Получаем полное имя класса модели
        $modelClass = $this->getModelClass($modelName);
        
        // Проверяем существование класса модели
        if (!class_exists($modelClass)) {
            $this->markTestSkipped("Model class not found: {$modelClass}. SDK may not be generated.");
        }

        // Десериализуем JSON в объект модели
        $model = $this->deserialize($originalJson, $modelClass);
        $this->assertNotNull($model, "Failed to deserialize {$fixtureFile} to {$modelName}");

        // Сериализуем модель обратно в массив
        $serializedJson = $this->serialize($model);
        $this->assertIsArray($serializedJson, "Serialization should return array");

        // Нормализуем оба массива для сравнения (убираем ignored поля)
        $normalizedOriginal = $this->normalizeForComparison($originalJson);
        $normalizedSerialized = $this->normalizeForComparison($serializedJson);

        // Сравниваем
        $this->assertEquals(
            $normalizedOriginal,
            $normalizedSerialized,
            "Roundtrip serialization failed for {$fixtureName}: data mismatch after deserialize->serialize"
        );
    }

    /**
     * Провайдер данных для тестов.
     * Возвращает пары [fixture_name, model_name] для каждого fixture файла.
     * 
     * @return array<string, array{string, string}>
     */
    public static function fixtureProvider(): array
    {
        $testCases = [];
        
        foreach (self::FIXTURE_MODEL_MAP as $fixtureName => $modelName) {
            $testCases[$fixtureName] = [$fixtureName, $modelName];
        }
        
        return $testCases;
    }

    /**
     * Возвращает полное имя класса модели SDK.
     * 
     * @param string $shortName Короткое имя модели (например, 'Product')
     * @return string Полное имя класса
     */
    private function getModelClass(string $shortName): string
    {
        // Namespace зависит от настроек openapi-generator
        return "OpenAPI\\Client\\Model\\{$shortName}";
    }

    /**
     * Десериализует JSON массив в объект модели.
     * Использует ObjectSerializer из сгенерированного SDK.
     * 
     * @param array<string, mixed> $data JSON данные
     * @param string $class Полное имя класса модели
     * @return object|null Объект модели или null при ошибке
     */
    private function deserialize(array $data, string $class): ?object
    {
        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        
        if (class_exists($serializerClass) && method_exists($serializerClass, 'deserialize')) {
            try {
                // ObjectSerializer::deserialize ожидает JSON строку или stdClass
                $jsonString = json_encode($data);
                return $serializerClass::deserialize($jsonString, $class);
            } catch (\Throwable $e) {
                $this->fail("Deserialization failed: " . $e->getMessage());
            }
        }

        // Fallback: попытка создать объект напрямую
        try {
            return new $class($data);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Сериализует объект модели в массив.
     * 
     * @param object $model Объект модели
     * @return array<string, mixed> Сериализованные данные
     */
    private function serialize(object $model): array
    {
        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        
        if (class_exists($serializerClass) && method_exists($serializerClass, 'sanitizeForSerialization')) {
            $sanitized = $serializerClass::sanitizeForSerialization($model);
            return json_decode(json_encode($sanitized), true) ?? [];
        }

        if ($model instanceof \JsonSerializable) {
            return $model->jsonSerialize();
        }

        // Fallback: преобразуем объект в массив через JSON
        return json_decode(json_encode($model), true) ?? [];
    }

    /**
     * Нормализует данные для сравнения.
     * Удаляет игнорируемые поля и сортирует ключи для консистентного сравнения.
     * 
     * @param array<string, mixed> $data Исходные данные
     * @return array<string, mixed> Нормализованные данные
     */
    private function normalizeForComparison(array $data): array
    {
        // Удаляем игнорируемые поля
        foreach (self::IGNORED_FIELDS as $field) {
            unset($data[$field]);
        }

        // Рекурсивно обрабатываем вложенные массивы
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeForComparison($value);
            }
        }

        // Сортируем ключи для консистентного сравнения
        ksort($data);

        return $data;
    }
}
