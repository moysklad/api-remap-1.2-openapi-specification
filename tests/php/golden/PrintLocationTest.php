<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OpenAPI\Client\Api\LabelsApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\LocationResponse;
use OpenAPI\Client\Model\Errors;
use OpenAPI\Client\Model\LabelPrintRequest;
use PHPUnit\Framework\TestCase;

/**
 * Контракт операций, полезные данные которых передаются в заголовке Location.
 *
 * Ответы подставляет MockHandler, а стек со стандартным RedirectMiddleware собирает HandlerStack::create:
 * если SDK пойдёт по редиректу, очередь ответов закончится и MockHandler бросит OutOfBoundsException.
 */
final class PrintLocationTest extends TestCase
{
    private const ENTITY_ID = '12345678-1234-1234-1234-123456789012';
    private const FILE_URL = 'https://api.moysklad.ru/download/file.pdf';

    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    public function testKeepsStatusAndLocationOfPendingPrint(): void
    {
        $result = $this->printLabels(new Response(202, ['Location' => self::FILE_URL]));

        self::assertSame(202, $result->getStatusCode());
        self::assertSame(self::FILE_URL, $result->getLocation());
    }

    public function testKeepsStatusAndLocationOfReadyPrintWithoutFollowingRedirect(): void
    {
        $result = $this->printLabels(new Response(303, ['Location' => self::FILE_URL]));

        self::assertSame(303, $result->getStatusCode());
        self::assertSame(self::FILE_URL, $result->getLocation());
        self::assertCount(1, $this->history, 'переход по Location выполняться не должен');
    }

    public function testReadsLocationRegardlessOfHeaderCase(): void
    {
        $result = $this->printLabels(new Response(303, ['location' => self::FILE_URL]));

        self::assertSame(self::FILE_URL, $result->getLocation());
    }

    public function testReportsMissingLocationAsNull(): void
    {
        $result = $this->printLabels(new Response(202));

        self::assertSame(202, $result->getStatusCode());
        self::assertNull($result->getLocation());
    }

    public function testKeepsErrorContractForErrorStatus(): void
    {
        try {
            $this->printLabels(new Response(400, [], '{"errors":[{"error":"stub","code":1}]}'));
            self::fail('Ожидалось ApiException со статусом 400');
        } catch (ApiException $exception) {
            self::assertSame(400, $exception->getCode());
            self::assertInstanceOf(Errors::class, $exception->getResponseObject());
        }
    }

    public function testTreatsUnexpectedRedirectAsError(): void
    {
        try {
            $this->printLabels(new Response(302, ['Location' => self::FILE_URL]));
            self::fail('Ожидалось ApiException со статусом 302');
        } catch (ApiException $exception) {
            self::assertSame(302, $exception->getCode());
            self::assertCount(1, $this->history, 'переход по Location выполняться не должен');
        }
    }

    public function testWithHttpInfoDoesNotFollowRedirect(): void
    {
        [$data, $status, $headers] = $this->labelsApi(new Response(303, ['Location' => self::FILE_URL]))
            ->printLabelsWithHttpInfo(self::ENTITY_ID, 'product', $this->printRequest());

        self::assertNull($data);
        self::assertSame(303, $status);
        self::assertSame([self::FILE_URL], $headers['Location']);
        self::assertCount(1, $this->history);
    }

    private function printLabels(Response $response): LocationResponse
    {
        return $this->labelsApi($response)->printLabels(self::ENTITY_ID, 'product', $this->printRequest());
    }

    private function labelsApi(Response $response): LabelsApi
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));

        return new LabelsApi(new Client(['handler' => $stack]));
    }

    private function printRequest(): LabelPrintRequest
    {
        return new LabelPrintRequest(['count' => 1]);
    }
}
