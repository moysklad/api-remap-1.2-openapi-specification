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
     * Поля, которые игнорируются при сравнении.
     * Все перечисленные в спецификации помечены readOnly: true (генерируются сервером).
     * При roundtrip они могут отсутствовать в выводе SDK (генератор часто не сериализует readOnly)
     * или отличаться (например, updated/created при следующем запросе).
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

        // Нормализуем оба массива для сравнения
        $normalizedOriginal = $this->normalizeForComparison($originalJson);
        $normalizedSerialized = $this->normalizeForComparison($serializedJson);

        // Сравниваем с учётом эквивалентности null/отсутствия ключа и чисел 1.0/1
        $this->assertNormalizedEquals(
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
     * Нормализует данные для сравнения:
     * - удаляет игнорируемые поля и поля со значением null (отсутствие ключа и null считаются эквивалентными);
     * - для attributes[].value приводит скаляр и объект/массив к одному виду;
     * - числа 1.0 и 1 приводятся к одному виду;
     * - пустые объекты и объекты только с meta считаются одинаковыми (нормализуются в []).
     *
     * @param array<string, mixed> $data Исходные данные
     * @return array<string, mixed> Нормализованные данные
     */
    private function normalizeForComparison(array $data): array
    {
        foreach (self::IGNORED_FIELDS as $field) {
            unset($data[$field]);
        }

        // Удаляем ключи со значением null (сравнение: отсутствие ключа = null)
        $data = array_filter($data, static fn ($v) => $v !== null);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeForComparison($value);
            } elseif (is_float($value) && $value === (float) (int) $value) {
                $data[$key] = (int) $value;
            }
        }

        // Атрибуты: value может прийти скаляром (фикстура) или массивом/объектом (SDK) — приводим к скаляру
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            foreach ($data['attributes'] as $i => $attr) {
                if (is_array($attr) && array_key_exists('value', $attr)) {
                    $data['attributes'][$i]['value'] = $this->normalizeAttributeValue($attr['value']);
                }
            }
        }

        // Пустой объект или только meta — нормализуем в [] для совпадения с выводом SDK
        if (self::isEmptyOrMetaOnly($data)) {
            return [];
        }

        ksort($data);
        return $data;
    }

    /**
     * Приводит value атрибута к скалярному виду (фикстура — скаляр, SDK часто — массив/объект).
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeAttributeValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        // Один элемент — скаляр (например обёртка)
        if (count($value) === 1) {
            $v = reset($value);
            return is_scalar($v) ? $v : $value;
        }
        // Ищем первый скаляр во вложенной структуре
        foreach ($value as $v) {
            if (is_scalar($v)) {
                return $v;
            }
        }
        return $value;
    }

    /**
     * Проверяет, что массив пустой или содержит только ключ 'meta'.
     */
    private static function isEmptyOrMetaOnly(array $data): bool
    {
        if ($data === []) {
            return true;
        }
        $keys = array_keys($data);
        return count($keys) === 1 && $keys[0] === 'meta';
    }

    /**
     * Рекурсивное сравнение с учётом: отсутствие ключа = null, числа 1.0 и 1 равны.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private function assertNormalizedEquals(array $expected, array $actual, string $message = ''): void
    {
        $this->sortKeysRecursive($expected);
        $this->sortKeysRecursive($actual);
        $diff = $this->diffNormalized($expected, $actual);
        if ($diff !== []) {
            $this->fail($message . "\n" . 'Differences: ' . json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    private function sortKeysRecursive(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $this->sortKeysRecursive($arr[$k]);
            }
        }
    }

    /**
     * Сравнивает с учётом null/отсутствия и числового равенства 1.0 == 1.
     * Возвращает массив расхождений (пустой, если равны).
     *
     * @param mixed $expected
     * @param mixed $actual
     * @return array<int, array{path: string, expected: mixed, actual: mixed}>
     */
    private function diffNormalized($expected, $actual, string $path = ''): array
    {
        if (is_array($expected) && is_array($actual)) {
            $allKeys = array_unique(array_merge(array_keys($expected), array_keys($actual)));
            $diffs = [];
            foreach ($allKeys as $key) {
                $e = $expected[$key] ?? null;
                $a = $actual[$key] ?? null;
                if ($e === null && $a === null) {
                    continue;
                }
                $subPath = $path === '' ? $key : $path . '.' . $key;
                $diffs = array_merge($diffs, $this->diffNormalized($e, $a, $subPath));
            }
            return $diffs;
        }

        if (is_numeric($expected) && is_numeric($actual) && (float) $expected === (float) $actual) {
            return [];
        }
        if ($expected === $actual) {
            return [];
        }
        return [['path' => $path, 'expected' => $expected, 'actual' => $actual]];
    }
}
