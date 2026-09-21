<?php

declare(strict_types=1);

namespace MoySklad\Tests\Location;

use OpenAPI\Client\Api\LabelsApi;
use OpenAPI\Client\Api\ProductsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Errors;
use OpenAPI\Client\Model\LabelPrintRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Контракт операций, полезные данные которых передаются в заголовке Location.
 * Проверяется на локальном HTTP-стабе: openapi-mock не отдаёт заголовки из спецификации.
 */
final class PrintLocationTest extends TestCase
{
    private const ENTITY_ID = '12345678-1234-1234-1234-123456789012';

    private LocationStubServer $stub;
    private Configuration $config;
    private LabelsApi $labelsApi;

    protected function setUp(): void
    {
        $this->stub = new LocationStubServer();
        $this->config = (new Configuration())->setHost($this->stub->getHost());
        $this->labelsApi = new LabelsApi(null, $this->config);
    }

    protected function tearDown(): void
    {
        $this->stub->stop();
    }

    public function testKeepsStatusAndLocationOfPendingPrint(): void
    {
        $expected = $this->stub->locationUrl('status');
        $this->stub->enqueue(202, 'Location', $expected);

        $result = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertSame(202, $result->getStatusCode());
        self::assertSame($expected, $result->getLocation());
        self::assertSame(0, $this->stub->getLocationHits());
    }

    public function testKeepsStatusAndLocationOfReadyPrintWithoutFollowingRedirect(): void
    {
        $expected = $this->stub->locationUrl('file.pdf');
        $this->stub->enqueue(303, 'Location', $expected);

        $result = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertSame(303, $result->getStatusCode());
        self::assertSame($expected, $result->getLocation());
        self::assertSame(0, $this->stub->getLocationHits(), 'переход по Location выполняться не должен');
    }

    #[DataProvider('headerNames')]
    public function testReadsLocationRegardlessOfHeaderCase(string $headerName): void
    {
        $expected = $this->stub->locationUrl('file.pdf');
        $this->stub->enqueue(303, $headerName, $expected);

        $result = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertSame($expected, $result->getLocation());
    }

    /** @return array<string, string[]> */
    public static function headerNames(): array
    {
        return [
            'Location' => ['Location'],
            'location' => ['location'],
            'LOCATION' => ['LOCATION'],
        ];
    }

    public function testReportsMissingLocationAsNull(): void
    {
        $this->stub->enqueue(202);

        $result = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertSame(202, $result->getStatusCode());
        self::assertNull($result->getLocation());
    }

    public function testResultsOfSubsequentCallsAreIndependent(): void
    {
        $first = $this->stub->locationUrl('first');
        $second = $this->stub->locationUrl('second');
        $this->stub->enqueue(303, 'Location', $first);
        $this->stub->enqueue(202, 'Location', $second);

        $firstResult = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());
        $secondResult = $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertSame($first, $firstResult->getLocation());
        self::assertSame(303, $firstResult->getStatusCode());
        self::assertSame($second, $secondResult->getLocation());
        self::assertSame(202, $secondResult->getStatusCode());
    }

    #[DataProvider('errorStatuses')]
    public function testKeepsErrorContractForErrorStatuses(int $status): void
    {
        $this->stub->enqueue($status, null, null, '{"errors":[{"error":"stub","code":1}]}');

        try {
            $this->labelsApi->printLabelsWithLocation(self::ENTITY_ID, 'product', $this->printRequest());
            self::fail('Ожидалось ApiException со статусом ' . $status);
        } catch (ApiException $exception) {
            self::assertSame($status, $exception->getCode());
            self::assertInstanceOf(Errors::class, $exception->getResponseObject());
        }
    }

    /** @return array<string, int[]> */
    public static function errorStatuses(): array
    {
        return ['400' => [400], '401' => [401], '500' => [500]];
    }

    public function testExistingMethodsDoNotFollowRedirect(): void
    {
        $this->stub->enqueue(303, 'Location', $this->stub->locationUrl('file.pdf'));

        [$data, $status, $headers] = $this->labelsApi
            ->printLabelsWithHttpInfo(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertNull($data);
        self::assertSame(303, $status);
        self::assertSame(0, $this->stub->getLocationHits());
        self::assertNotEmpty($headers['Location']);
    }

    public function testOrdinaryJsonResponseIsStillDeserialized(): void
    {
        $this->stub->enqueue(200, null, null, '{"id":"' . self::ENTITY_ID . '","name":"Стул"}');

        $product = (new ProductsApi(null, $this->config))->getProductById(self::ENTITY_ID);

        self::assertSame('Стул', $product->getName());
    }

    private function printRequest(): LabelPrintRequest
    {
        return new LabelPrintRequest(['count' => 1]);
    }
}
