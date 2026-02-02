<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;

/**
 * Golden tests for Product model serialization/deserialization
 * 
 * These tests verify that the SDK correctly serializes and deserializes
 * Product entities by comparing against reference JSON files.
 * 
 * Reference: https://dev.moysklad.ru/doc/api/remap/1.2/#mojsklad-json-api-obschie-swedeniq-towary
 */
class ProductSerializationTest extends TestCase
{
    /**
     * Test deserialization of Product from JSON
     */
    public function testDeserializeProduct(): void
    {
        $jsonData = $this->loadFixture('product.json');
        
        // Check that SDK model class exists
        $modelClass = $this->getModelClass('Product');
        $this->assertTrue(
            class_exists($modelClass),
            "Model class {$modelClass} should exist"
        );

        // Deserialize JSON to model
        $product = $this->deserialize($jsonData, $modelClass);
        
        // Verify basic fields
        $this->assertNotNull($product, 'Product should be deserialized');
        $this->assertEquals($jsonData['id'], $product->getId());
        $this->assertEquals($jsonData['name'], $product->getName());
        
        if (isset($jsonData['code'])) {
            $this->assertEquals($jsonData['code'], $product->getCode());
        }
        
        if (isset($jsonData['archived'])) {
            $this->assertEquals($jsonData['archived'], $product->getArchived());
        }
    }

    /**
     * Test serialization of Product to JSON
     */
    public function testSerializeProduct(): void
    {
        $jsonData = $this->loadFixture('product.json');
        
        $modelClass = $this->getModelClass('Product');
        
        // Deserialize and then serialize back
        $product = $this->deserialize($jsonData, $modelClass);
        $serialized = $this->serialize($product);
        
        // Compare key fields (not all fields, as some are read-only)
        $this->assertEquals($jsonData['name'], $serialized['name'] ?? null);
        
        if (isset($jsonData['code'])) {
            $this->assertEquals($jsonData['code'], $serialized['code'] ?? null);
        }
    }

    /**
     * Test Product with nested objects (salePrices, barcodes)
     */
    public function testProductWithNestedObjects(): void
    {
        $jsonData = $this->loadFixture('product_full.json');
        
        $modelClass = $this->getModelClass('Product');
        $product = $this->deserialize($jsonData, $modelClass);
        
        $this->assertNotNull($product);
        
        // Test salePrices if present
        if (isset($jsonData['salePrices']) && method_exists($product, 'getSalePrices')) {
            $salePrices = $product->getSalePrices();
            $this->assertIsArray($salePrices);
            $this->assertCount(count($jsonData['salePrices']), $salePrices);
        }
        
        // Test barcodes if present
        if (isset($jsonData['barcodes']) && method_exists($product, 'getBarcodes')) {
            $barcodes = $product->getBarcodes();
            $this->assertIsArray($barcodes);
        }
    }

    /**
     * Get fully qualified model class name
     */
    private function getModelClass(string $name): string
    {
        // Adjust namespace based on generated SDK structure
        return "OpenAPI\\Client\\Model\\{$name}";
    }

    /**
     * Deserialize JSON to model object
     */
    private function deserialize(array $data, string $class): ?object
    {
        if (!class_exists($class)) {
            $this->markTestSkipped("Model class {$class} not found - SDK may not be generated");
            return null;
        }

        // Use ObjectSerializer if available
        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        if (class_exists($serializerClass)) {
            return $serializerClass::deserialize($data, $class);
        }

        // Fallback: try to create instance directly
        return new $class($data);
    }

    /**
     * Serialize model object to array
     */
    private function serialize(object $model): array
    {
        $serializerClass = "OpenAPI\\Client\\ObjectSerializer";
        if (class_exists($serializerClass) && method_exists($serializerClass, 'sanitizeForSerialization')) {
            $sanitized = $serializerClass::sanitizeForSerialization($model);
            return json_decode(json_encode($sanitized), true);
        }

        // Fallback: use jsonSerialize if available
        if ($model instanceof \JsonSerializable) {
            return $model->jsonSerialize();
        }

        return (array) $model;
    }
}
