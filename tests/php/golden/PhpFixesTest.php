<?php

declare(strict_types=1);

namespace MoySklad\Tests\Golden;

use MoySklad\Tests\TestCase;
use OpenAPI\Client\Api\ProductsApi;
use OpenAPI\Client\Api\ReportsStockCurrentApi;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\HeaderSelector;
use OpenAPI\Client\Model\Assortment;
use OpenAPI\Client\Model\AttributeFile;
use OpenAPI\Client\Model\AttributeString;
use OpenAPI\Client\Model\DiscountAssortmentItem;
use OpenAPI\Client\Model\Product;
use OpenAPI\Client\Model\RetailShift;
use OpenAPI\Client\Model\SpecialPriceDiscount;
use OpenAPI\Client\ObjectSerializer;
use PHPUnit\Framework\Attributes\DataProvider;

class PhpFixesTest extends TestCase
{
    public function testDefaultHeadersComeFromSpecification(): void
    {
        $request = (new ProductsApi())->getProductsRequest();

        $this->assertSame('application/json;charset=utf-8', $request->getHeaderLine('Accept'));
        $this->assertSame('gzip', $request->getHeaderLine('Accept-Encoding'));
    }

    public function testExplicitHeadersOverrideSelectorRegardlessOfCasing(): void
    {
        $selector = new class extends HeaderSelector {
            public function selectHeaders(array $accept, string $contentType, bool $isMultipart): array
            {
                return ['aCcEpT' => 'text/html', 'Content-Type' => $contentType];
            }
        };
        $api = new ProductsApi(null, null, $selector);
        $request = $api->createProductsBatchRequest(
            [new Product(['name' => 'Test'])],
            accept: 'application/vnd.test+json',
            accept_encoding: 'identity',
            content_type: 'application/json;charset=utf-8'
        );

        $this->assertSame(['application/vnd.test+json'], $request->getHeader('Accept'));
        $this->assertSame(['identity'], $request->getHeader('Accept-Encoding'));
        $this->assertSame(['application/json;charset=utf-8'], $request->getHeader('Content-Type'));
    }

    #[DataProvider('booleanQueryProvider')]
    public function testBooleanQueryUsesStrings(bool $value, string $expected): void
    {
        $this->assertSame(Configuration::BOOLEAN_FORMAT_STRING, (new Configuration())->getBooleanFormatForQueryString());
        $request = (new ReportsStockCurrentApi())->getStockByStoreCurrentRequest(with_recalculate: $value);

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame($expected, $query['withRecalculate']);
    }

    public static function booleanQueryProvider(): array
    {
        return [[true, 'true'], [false, 'false']];
    }

    #[DataProvider('assortmentProvider')]
    public function testAssortmentResolvesMetaType(string $type, string $modelName): void
    {
        $payload = ['meta' => ['type' => $type], 'name' => 'Expanded entity'];
        $model = ObjectSerializer::deserialize(json_encode($payload), Assortment::class);

        $this->assertInstanceOf('OpenAPI\\Client\\Model\\' . $modelName, $model);
        $this->assertSame('Expanded entity', $model->getName());
    }

    public static function assortmentProvider(): array
    {
        return [
            ['product', 'Product'], ['service', 'Service'], ['variant', 'Variant'],
            ['bundle', 'Bundle'], ['consignment', 'Consignment'],
        ];
    }

    public function testAssortmentPreservesStockFieldsOnConcreteProduct(): void
    {
        $payload = ['meta' => ['type' => 'product'], 'stock' => 3.5, 'reserve' => 1, 'inTransit' => 2, 'quantity' => 4.5];
        $product = ObjectSerializer::deserialize(json_encode($payload), Assortment::class);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame(3.5, $product['stock']);
        $this->assertEquals($payload, json_decode(json_encode(ObjectSerializer::sanitizeForSerialization($product)), true));
    }

    #[DataProvider('discountProvider')]
    public function testExpandedDiscountAssortmentResolvesMetaType(string $type, string $modelName): void
    {
        $discount = ObjectSerializer::deserialize(
            json_encode(['assortment' => [['meta' => ['type' => $type], 'name' => 'Expanded item']]]),
            SpecialPriceDiscount::class
        );
        $item = $discount->getAssortment()[0];

        $this->assertInstanceOf('OpenAPI\\Client\\Model\\' . $modelName, $item);
        $serialized = ObjectSerializer::sanitizeForSerialization($discount);
        $this->assertSame($type, $serialized->assortment[0]->meta->type);
        $this->assertSame('Expanded item', $serialized->assortment[0]->name);
    }

    public static function discountProvider(): array
    {
        return [['product', 'Product'], ['service', 'Service'], ['variant', 'Variant']];
    }

    public function testUnknownDiscountTypeDoesNotBecomeProduct(): void
    {
        $item = ObjectSerializer::deserialize('{"meta":{"type":"futuretype"}}', DiscountAssortmentItem::class);

        $this->assertInstanceOf(DiscountAssortmentItem::class, $item);
    }

    #[DataProvider('retailPaymentProvider')]
    public function testRetailShiftPaymentsResolveMetaType(string $type, string $modelName): void
    {
        $shift = ObjectSerializer::deserialize(
            json_encode(['payments' => [['meta' => ['type' => $type]]]]),
            RetailShift::class
        );

        $this->assertInstanceOf('OpenAPI\\Client\\Model\\' . $modelName, $shift->getPayments()[0]);
    }

    public static function retailPaymentProvider(): array
    {
        return [['cashin', 'CashIn'], ['paymentin', 'PaymentIn']];
    }

    #[DataProvider('nullableFieldProvider')]
    public function testAbsentNullableFieldStaysAbsent(string $modelClass, string $field): void
    {
        $model = ObjectSerializer::deserialize('{}', $modelClass);

        $this->assertObjectNotHasProperty($field, ObjectSerializer::sanitizeForSerialization($model));
    }

    #[DataProvider('nullableFieldProvider')]
    public function testExplicitNullStaysNull(string $modelClass, string $field): void
    {
        $model = ObjectSerializer::deserialize(json_encode([$field => null]), $modelClass);
        $serialized = ObjectSerializer::sanitizeForSerialization($model);

        $this->assertObjectHasProperty($field, $serialized);
        $this->assertNull($serialized->{$field});
    }

    public static function nullableFieldProvider(): array
    {
        return [[Product::class, 'description'], [AttributeString::class, 'value'], [AttributeFile::class, 'file']];
    }
}
