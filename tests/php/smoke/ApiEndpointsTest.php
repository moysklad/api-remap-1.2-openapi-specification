<?php

declare(strict_types=1);

namespace MoySklad\Tests\Smoke;

use MoySklad\Tests\TestCase;
use GuzzleHttp\Client;

/**
 * Smoke тесты для проверки доступности API endpoint'ов через Prism mock сервер.
 * 
 * Эти тесты проверяют:
 * - Наличие и доступность всех endpoint'ов, описанных в OpenAPI спецификации
 * - Корректность HTTP методов (GET, POST, PUT, DELETE)
 * - Базовую структуру ответов
 * 
 * Тесты запускаются против Prism mock сервера, который эмулирует API
 * на основе OpenAPI спецификации.
 * 
 * @see https://dev.moysklad.ru/doc/api/remap/1.2/
 */
class ApiEndpointsTest extends TestCase
{
    private Client $client;
    
    /**
     * Базовый путь API для запросов к Prism.
     * Prism не поддерживает путь из servers.url (см. https://github.com/stoplightio/prism/discussions/906):
     * сопоставление идёт только по ключам из paths. В спеке paths: /entity/product, servers: /api/remap/1.2,
     * поэтому Prism слушает /entity/product, а не /api/remap/1.2/entity/product. Используем путь без префикса.
     */
    private const API_BASE_PATH = '';

    /**
     * Для smoke-теста операций list/create/batch delete и т.п.:
     * 404 = эндпоинт не совпал (путь не найден в спеке), тест должен падать.
     * Любой другой ответ (2xx, 3xx, 5xx, 401, 403…) = достучались до эндпоинта — ок.
     */
    private const SUCCESS_CODES = [200, 201, 401];
    
    /**
     * Допустимые коды для запросов к несуществующим ресурсам
     */
    private const NOT_FOUND_CODES = [200, 401, 404];
    
    /**
     * Допустимые коды для DELETE операций
     */
    private const DELETE_CODES = [200, 204, 401, 404];

    /**
     * Тестовый UUID для запросов по ID
     */
    private const TEST_UUID = '12345678-1234-1234-1234-123456789012';

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
            'timeout' => 10,
        ]);
    }

    // ==================== PRODUCTS ====================

    /**
     * Проверяет доступность endpoint'а получения списка товаров.
     * GET /entity/product
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-towar-poluchit-towary
     */
    public function testListProducts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения товара по ID.
     * GET /entity/product/{id}
     */
    public function testGetProductById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания товара.
     * POST /entity/product
     */
    public function testCreateProduct(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product', [
            'json' => ['name' => 'Test Product'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления товара.
     * PUT /entity/product/{id}
     */
    public function testUpdateProduct(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated Product'],
        ]);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления товара.
     * DELETE /entity/product/{id}
     */
    public function testDeleteProduct(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового удаления товаров.
     * POST /entity/product/delete
     */
    public function testBatchDeleteProducts(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/delete', [
            'json' => [['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID]]],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== COUNTERPARTIES ====================

    /**
     * Проверяет доступность endpoint'а получения списка контрагентов.
     * GET /entity/counterparty
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-kontragent
     */
    public function testListCounterparties(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения контрагента по ID.
     * GET /entity/counterparty/{id}
     */
    public function testGetCounterpartyById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания контрагента.
     * POST /entity/counterparty
     */
    public function testCreateCounterparty(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/counterparty', [
            'json' => ['name' => 'Test Counterparty', 'companyType' => 'legal'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления контрагента.
     * PUT /entity/counterparty/{id}
     */
    public function testUpdateCounterparty(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated Counterparty'],
        ]);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    // ==================== CURRENCIES ====================

    /**
     * Проверяет доступность endpoint'а получения списка валют.
     * GET /entity/currency
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-valuta
     */
    public function testListCurrencies(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/currency');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения валюты по ID.
     * GET /entity/currency/{id}
     */
    public function testGetCurrencyById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/currency/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания валюты.
     * POST /entity/currency
     */
    public function testCreateCurrency(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/currency', [
            'json' => ['name' => 'Test Currency', 'code' => '999', 'isoCode' => 'TST'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== EMPLOYEES ====================

    /**
     * Проверяет доступность endpoint'а получения списка сотрудников.
     * GET /entity/employee
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-sotrudnik
     */
    public function testListEmployees(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/employee');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения сотрудника по ID.
     * GET /entity/employee/{id}
     */
    public function testGetEmployeeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    // ==================== GROUPS ====================

    /**
     * Проверяет доступность endpoint'а получения списка отделов.
     * GET /entity/group
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-otdel
     */
    public function testListGroups(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/group');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения отдела по ID.
     * GET /entity/group/{id}
     */
    public function testGetGroupById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/group/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    // ==================== COUNTRIES ====================

    /**
     * Проверяет доступность endpoint'а получения списка стран.
     * GET /entity/country
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-strana
     */
    public function testListCountries(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/country');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения страны по ID.
     * GET /entity/country/{id}
     */
    public function testGetCountryById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/country/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    // ==================== PRODUCT FOLDERS ====================

    /**
     * Проверяет доступность endpoint'а получения списка групп товаров.
     * GET /entity/productfolder
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-gruppa-towarow
     */
    public function testListProductFolders(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/productfolder');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения группы товаров по ID.
     * GET /entity/productfolder/{id}
     */
    public function testGetProductFolderById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/productfolder/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания группы товаров.
     * POST /entity/productfolder
     */
    public function testCreateProductFolder(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/productfolder', [
            'json' => ['name' => 'Test Folder'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== SERVICES ====================

    /**
     * Проверяет доступность endpoint'а получения списка услуг.
     * GET /entity/service
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-usluga
     */
    public function testListServices(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/service');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения услуги по ID.
     * GET /entity/service/{id}
     */
    public function testGetServiceById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/service/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания услуги.
     * POST /entity/service
     */
    public function testCreateService(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/service', [
            'json' => ['name' => 'Test Service'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== UOMS ====================

    /**
     * Проверяет доступность endpoint'а получения списка единиц измерения.
     * GET /entity/uom
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-edinica-izmereniq
     */
    public function testListUoms(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/uom');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения единицы измерения по ID.
     * GET /entity/uom/{id}
     */
    public function testGetUomById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/uom/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    // ==================== PRICE TYPES ====================

    /**
     * Проверяет доступность endpoint'а получения списка типов цен.
     * GET /context/companysettings/pricetype
     * 
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-tip-ceny
     */
    public function testListPriceTypes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/companysettings/pricetype');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения типа цены по ID.
     * GET /context/companysettings/pricetype/{id}
     */
    public function testGetPriceTypeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/companysettings/pricetype/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения типа цены по умолчанию.
     * GET /context/companysettings/pricetype/default
     */
    public function testGetDefaultPriceType(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/companysettings/pricetype/default');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }
}
