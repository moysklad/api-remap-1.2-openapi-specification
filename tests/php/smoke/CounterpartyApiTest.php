<?php

declare(strict_types=1);

namespace MoySklad\Tests\Smoke;

use MoySklad\Tests\TestCase;
use GuzzleHttp\Client;

/**
 * Smoke tests for Counterparty API endpoints using Prism mock server
 * 
 * Reference: https://dev.moysklad.ru/doc/api/remap/1.2/#mojsklad-json-api-obschie-swedeniq-kontragenty
 */
class CounterpartyApiTest extends TestCase
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
     * Test GET /entity/counterparty - list counterparties
     */
    public function testListCounterparties(): void
    {
        $response = $this->client->get('/api/remap/1.2/entity/counterparty');
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401],
            'GET /entity/counterparty should return 200 or 401'
        );
    }

    /**
     * Test GET /entity/counterparty/{id} - get single counterparty
     */
    public function testGetCounterparty(): void
    {
        $testId = '12345678-1234-1234-1234-123456789012';
        $response = $this->client->get("/api/remap/1.2/entity/counterparty/{$testId}");
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401, 404],
            'GET /entity/counterparty/{id} should return 200, 401 or 404'
        );
    }

    /**
     * Test POST /entity/counterparty - create counterparty
     */
    public function testCreateCounterparty(): void
    {
        $counterpartyData = [
            'name' => 'Test Counterparty',
            'companyType' => 'legal',
        ];

        $response = $this->client->post('/api/remap/1.2/entity/counterparty', [
            'json' => $counterpartyData,
        ]);
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 201, 401],
            'POST /entity/counterparty should return 200, 201 or 401'
        );
    }

    /**
     * Test PUT /entity/counterparty/{id} - update counterparty
     */
    public function testUpdateCounterparty(): void
    {
        $testId = '12345678-1234-1234-1234-123456789012';
        $counterpartyData = [
            'name' => 'Updated Counterparty',
        ];

        $response = $this->client->put("/api/remap/1.2/entity/counterparty/{$testId}", [
            'json' => $counterpartyData,
        ]);
        
        $this->assertContains(
            $response->getStatusCode(),
            [200, 401, 404],
            'PUT /entity/counterparty/{id} should return 200, 401 or 404'
        );
    }
}
