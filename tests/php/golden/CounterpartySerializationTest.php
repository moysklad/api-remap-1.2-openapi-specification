<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;

/**
 * Golden tests for Counterparty model serialization/deserialization
 * 
 * Reference: https://dev.moysklad.ru/doc/api/remap/1.2/#mojsklad-json-api-obschie-swedeniq-kontragenty
 */
class CounterpartySerializationTest extends TestCase
{
    /**
     * Test deserialization of Counterparty from JSON
     */
    public function testDeserializeCounterparty(): void
    {
        $jsonData = $this->loadFixture('counterparty.json');
        
        $modelClass = $this->getModelClass('Counterparty');
        $this->assertTrue(
            class_exists($modelClass),
            "Model class {$modelClass} should exist"
        );

        $counterparty = $this->deserialize($jsonData, $modelClass);
        
        $this->assertNotNull($counterparty);
        $this->assertEquals($jsonData['id'], $counterparty->getId());
        $this->assertEquals($jsonData['name'], $counterparty->getName());
        
        if (isset($jsonData['companyType'])) {
            $this->assertEquals($jsonData['companyType'], $counterparty->getCompanyType());
        }
    }

    /**
     * Test serialization of Counterparty to JSON
     */
    public function testSerializeCounterparty(): void
    {
        $jsonData = $this->loadFixture('counterparty.json');
        
        $modelClass = $this->getModelClass('Counterparty');
        $counterparty = $this->deserialize($jsonData, $modelClass);
        $serialized = $this->serialize($counterparty);
        
        $this->assertEquals($jsonData['name'], $serialized['name'] ?? null);
        
        if (isset($jsonData['companyType'])) {
            $this->assertEquals($jsonData['companyType'], $serialized['companyType'] ?? null);
        }
    }

    /**
     * Test Counterparty with legal entity fields
     */
    public function testCounterpartyLegalEntity(): void
    {
        $jsonData = $this->loadFixture('counterparty_legal.json');
        
        $modelClass = $this->getModelClass('Counterparty');
        $counterparty = $this->deserialize($jsonData, $modelClass);
        
        $this->assertNotNull($counterparty);
        $this->assertEquals('legal', $counterparty->getCompanyType());
        
        if (isset($jsonData['inn']) && method_exists($counterparty, 'getInn')) {
            $this->assertEquals($jsonData['inn'], $counterparty->getInn());
        }
        
        if (isset($jsonData['kpp']) && method_exists($counterparty, 'getKpp')) {
            $this->assertEquals($jsonData['kpp'], $counterparty->getKpp());
        }
    }

    private function getModelClass(string $name): string
    {
        return "OpenAPI\\Client\\Model\\{$name}";
    }

    private function deserialize(array $data, string $class): ?object
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("Model class {$class} not found");
            return null;
        }

        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        if (class_exists($serializerClass)) {
            return $serializerClass::deserialize($data, $class);
        }

        return new $class($data);
    }

    private function serialize(object $model): array
    {
        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        if (class_exists($serializerClass) && method_exists($serializerClass, 'sanitizeForSerialization')) {
            $sanitized = $serializerClass::sanitizeForSerialization($model);
            return json_decode(json_encode($sanitized), true);
        }

        if ($model instanceof \JsonSerializable) {
            return $model->jsonSerialize();
        }

        return (array) $model;
    }
}
