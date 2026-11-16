<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;
use OpenAPI\Client\ObjectSerializer;

class DocumentPaymentsTest extends TestCase
{
    public static function paymentCases(): iterable
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/../../fixtures/polymorphic/document_payments.json'), true);
        foreach ($cases as $case) {
            yield $case['variant'] . ':reference' => [$case, false, null];
            yield $case['variant'] . ':expanded' => [$case, true, ($case['linkedSum'] ?? true) ? 123.45 : null];
            // Check zero on both representative variants without repeating it for every document.
            if ($case['document'] === 'RetailShift') {
                yield $case['variant'] . ':zero' => [$case, true, 0];
            }
        }
    }

    /** @dataProvider paymentCases */
    public function testPaymentSelectionAndRoundtrip(array $case, bool $expanded, ?float $linkedSum): void
    {
        $payment = ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/' . $case['type'] . '/1',
            'type' => $case['type'], 'mediaType' => 'application/json']];
        if ($expanded) {
            $payment += ['name' => 'Expanded payment', 'sum' => 456.75, 'paymentPurpose' => 'Invoice settlement'];
        }
        if ($linkedSum !== null) {
            $payment['linkedSum'] = $linkedSum;
        }
        $class = 'OpenAPI\\Client\\Model\\' . $case['document'];
        $owner = ObjectSerializer::deserialize(json_decode(json_encode(['payments' => [$payment]])), $class);
        $actual = $owner->getPayments()[0];
        $this->assertSame('OpenAPI\\Client\\Model\\' . $case['variant'], get_class($actual));
        $this->assertInstanceOf('OpenAPI\\Client\\Model\\' . $case['base'], $actual);
        $this->assertSame($case['type'], $actual->getMeta()->getType());
        $this->assertSame($expanded ? 'Expanded payment' : null, $actual->getName());
        if ($expanded) {
            $this->assertEquals(456.75, $actual->getSum());
            $this->assertSame('Invoice settlement', $actual->getPaymentPurpose());
        }
        $serialized = json_decode(json_encode(ObjectSerializer::sanitizeForSerialization($owner)), true);
        $this->assertEquals($payment, $serialized['payments'][0]);
    }
}
