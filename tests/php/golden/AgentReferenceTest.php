<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;
use OpenAPI\Client\Model\Agent;
use OpenAPI\Client\Model\CashIn;
use OpenAPI\Client\Model\Counterparty;
use OpenAPI\Client\Model\Employee;
use OpenAPI\Client\Model\FactureOut;
use OpenAPI\Client\Model\Meta;
use OpenAPI\Client\Model\Organization;
use OpenAPI\Client\ObjectSerializer;

/**
 * Тесты на поля, помеченные `x-agent-reference: true`,
 * должны принимать Counterparty, Organization и Agent через один и тот же setter,
 * отвергать произвольные типы, и корректно сериализоваться в meta-ссылку на проводе.
 *
 * @see src/components/schemas/dictionary/agent.yaml
 */
class AgentReferenceTest extends TestCase
{
    /** @return iterable<string, array{0: object, 1: string}> */
    public static function refProvider(): iterable
    {
        $counterparty = new Counterparty();
        $counterparty->setMeta(self::buildMeta('counterparty'));
        $counterparty->setName('Roga i Kopyta');

        $organization = new Organization();
        $organization->setMeta(self::buildMeta('organization'));
        $organization->setName('Sphere');

        $agent = new Agent();
        $agent->setMeta(self::buildMeta('counterparty'));

        yield 'counterparty' => [$counterparty, 'counterparty'];
        yield 'organization' => [$organization, 'organization'];
        yield 'agent' => [$agent, 'counterparty'];
    }

    /** @dataProvider refProvider */
    public function testSetAgentAcceptsAndSerializesMeta(object $ref, string $expectedType): void
    {
        $cashIn = new CashIn();
        $cashIn->setAgent($ref);

        $serialized = $this->serializeToArray($cashIn);

        $this->assertArrayHasKey('agent', $serialized);
        $this->assertIsArray($serialized['agent']);
        $this->assertArrayHasKey('meta', $serialized['agent']);
        $this->assertSame($expectedType, $serialized['agent']['meta']['type']);
    }

    public function testSetAgentRejectsArbitraryType(): void
    {
        $cashIn = new CashIn();
        $foreign = new Employee();
        $foreign->setMeta(self::buildMeta('employee'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must be one of Counterparty, Organization, or Agent");
        $cashIn->setAgent($foreign);
    }

    public function testNullableConsigneeAcceptsNull(): void
    {
        $factureOut = new FactureOut();
        $factureOut->setConsignee(null);

        $this->assertNull($factureOut->getConsignee());
    }

    /** @dataProvider refProvider */
    public function testNullableConsigneeAcceptsAgentRef(object $ref, string $expectedType): void
    {
        $factureOut = new FactureOut();
        $factureOut->setConsignee($ref);

        $serialized = $this->serializeToArray($factureOut);

        $this->assertArrayHasKey('consignee', $serialized);
        $this->assertIsArray($serialized['consignee']);
        $this->assertArrayHasKey('meta', $serialized['consignee']);
        $this->assertSame($expectedType, $serialized['consignee']['meta']['type']);
    }

    public function testAgentDeserializesFromCounterpartyJson(): void
    {
        $json = [
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/00000000-0000-0000-0000-000000000001',
                'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata',
                'type' => 'counterparty',
                'mediaType' => 'application/json',
            ],
            'name' => 'Acme',
            'legalTitle' => 'Acme LLC',
            'inn' => '7700000000',
        ];

        /** @var Agent $agent */
        $agent = ObjectSerializer::deserialize(json_encode($json), Agent::class);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertSame('Acme', $agent->getName());
        $this->assertSame('Acme LLC', $agent->getLegalTitle());
        $this->assertSame('7700000000', $agent->getInn());
        $this->assertSame('counterparty', $agent->getMeta()->getType());
    }

    /** @return array<string, mixed> */
    private function serializeToArray(object $model): array
    {
        $sanitized = ObjectSerializer::sanitizeForSerialization($model);
        $array = json_decode(json_encode($sanitized), true);
        $this->assertIsArray($array);
        return $array;
    }

    private static function buildMeta(string $type): Meta
    {
        $meta = new Meta();
        $meta->setType($type);
        $meta->setHref('https://api.moysklad.ru/api/remap/1.2/entity/' . $type . '/00000000-0000-0000-0000-000000000001');
        $meta->setMediaType('application/json');
        return $meta;
    }
}
