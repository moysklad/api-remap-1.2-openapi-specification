<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MoySklad\Tests\TestCase;
use OpenAPI\Client\Api\ProductsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\BatchResponseEntity;
use OpenAPI\Client\Model\Errors;
use OpenAPI\Client\Model\Product;
use OpenAPI\Client\ObjectSerializer;

/**
 * Поведение batch-ответов:
 * 1) смешанный массив [сущность, Errors] десериализуется в BatchResponseEntity[];
 * 2) HTTP 400+ и объект Errors → ApiException;
 * 3) HTTP 400+ и массив → без ApiException, возвращается список результатов.
 */
class BatchResponseHandlingTest extends TestCase
{
    public function testMixedBatchResponseDeserializesProductAndErrors(): void
    {
        $payload = $this->loadBatchFixture('product_batch_partial.json');
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload);

        $models = ObjectSerializer::deserialize(
            json_encode($payload),
            '\OpenAPI\Client\Model\BatchResponseEntity[]'
        );

        $this->assertIsArray($models);
        $this->assertCount(2, $models);
        $this->assertInstanceOf(Product::class, $models[0]);
        $this->assertInstanceOf(Errors::class, $models[1]);
        $this->assertNotEmpty($models[1]->getErrors());
        $this->assertSame(1000, $models[1]->getErrors()[0]->getCode());
    }

    public function testGlobalBatchErrorThrowsApiException(): void
    {
        $body = $this->loadBatchFixtureRaw('batch_global_error.json');
        $api = $this->productsApiWithResponse(400, $body);

        try {
            $api->createProductsBatch([$this->minimalProduct()]);
            $this->fail('Expected ApiException for global batch error object');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame($body, $e->getResponseBody());
            $this->assertStringContainsString('1056', (string) $e->getResponseBody());
        }
    }

    public function testPartialSuccessAt400ReturnsBatchWithoutException(): void
    {
        $body = $this->loadBatchFixtureRaw('product_batch_partial.json');
        $api = $this->productsApiWithResponse(400, $body);

        $result = $api->createProductsBatch([$this->minimalProduct()]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(BatchResponseEntity::class, $result);
        $this->assertInstanceOf(Product::class, $result[0]);
        $this->assertInstanceOf(Errors::class, $result[1]);
        $this->assertSame(1000, $result[1]->getErrors()[0]->getCode());
    }

    private function productsApiWithResponse(int $status, string $body): ProductsApi
    {
        $mock = new MockHandler([
            new Response($status, ['Content-Type' => 'application/json'], $body),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $config = Configuration::getDefaultConfiguration()
            ->setHost('http://batch-test.local/api/remap/1.2');

        return new ProductsApi($client, $config);
    }

    private function minimalProduct(): Product
    {
        $product = new Product();
        $product->setName('Batch test product');

        return $product;
    }

    /** @return array<mixed> */
    private function loadBatchFixture(string $filename): array
    {
        $path = $this->getBatchFixturesPath() . '/' . $filename;
        $this->assertFileExists($path, "Batch fixture not found: {$filename}");

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());

        return $data;
    }

    private function loadBatchFixtureRaw(string $filename): string
    {
        $path = $this->getBatchFixturesPath() . '/' . $filename;
        $this->assertFileExists($path, "Batch fixture not found: {$filename}");

        return (string) file_get_contents($path);
    }

    private function getBatchFixturesPath(): string
    {
        return $this->getFixturesPath() . '/batch';
    }
}
