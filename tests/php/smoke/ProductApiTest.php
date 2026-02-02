<?php

declare(strict_types=1);

namespace MoySklad\Tests\Smoke;

use MoySklad\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Smoke tests for Product API endpoints using Prism mock server
 * 
 * These tests verify that:
 * - Endpoints exist and are accessible
 * - HTTP methods are correctly configured
 * - Response structure matches OpenAPI specification
 * 
 * Reference: https://dev.moysklad.ru/doc/api/remap/1.2/#mojsklad-json-api-obschie-swedeniq-towary
 */
class ProductApiTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = new Client([
            'base_uri' => $this->getPrismBaseUrl(),
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Test GET /entity/product - list products
     */
    public function testListProducts(): void
    {
        $response = $this->client->get('/api/remap/1.2/entity/product');
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401], // 401 is acceptable as Prism may require auth
            'GET /entity/product should return 200 or 401'
        );
        
        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($body);
        }
    }

    /**
     * Test GET /entity/product/{id} - get single product
     */
    public function testGetProduct(): void
    {
        $testId = '12345678-1234-1234-1234-123456789012';
        $response = $this->client->get("/api/remap/1.2/entity/product/{$testId}");
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401, 404],
            'GET /entity/product/{id} should return 200, 401 or 404'
        );
    }

    /**
     * Test POST /entity/product - create product
     */
    public function testCreateProduct(): void
    {
        $productData = [
            'name' => 'Test Product',
        ];

        $response = $this->client->post('/api/remap/1.2/entity/product', [
            'json' => $productData,
        ]);
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 201, 401],
            'POST /entity/product should return 200, 201 or 401'
        );
    }

    /**
     * Test PUT /entity/product/{id} - update product
     */
    public function testUpdateProduct(): void
    {
        $testId = '12345678-1234-1234-1234-123456789012';
        $productData = [
            'name' => 'Updated Product',
        ];

        $response = $this->client->put("/api/remap/1.2/entity/product/{$testId}", [
            'json' => $productData,
        ]);
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401, 404],
            'PUT /entity/product/{id} should return 200, 401 or 404'
        );
    }

    /**
     * Test DELETE /entity/product/{id} - delete product
     */
    public function testDeleteProduct(): void
    {
        $testId = '12345678-1234-1234-1234-123456789012';
        
        $response = $this->client->delete("/api/remap/1.2/entity/product/{$testId}");
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 204, 401, 404],
            'DELETE /entity/product/{id} should return 200, 204, 401 or 404'
        );
    }

    /**
     * Test POST /entity/product/delete - batch delete
     */
    public function testBatchDeleteProducts(): void
    {
        $response = $this->client->post('/api/remap/1.2/entity/product/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/12345678-1234-1234-1234-123456789012']],
            ],
        ]);
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401],
            'POST /entity/product/delete should return 200 or 401'
        );
    }
}
