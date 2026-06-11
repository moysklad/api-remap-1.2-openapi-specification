<?php

declare(strict_types=1);

namespace MoySklad\Tests\Smoke;

use MoySklad\Tests\TestCase;
use GuzzleHttp\Client;

/**
 * Smoke тесты для проверки доступности API endpoint'ов через openapi-mock сервер.
 * 
 * Эти тесты проверяют:
 * - Наличие и доступность всех endpoint'ов, описанных в OpenAPI спецификации
 * - Корректность HTTP методов (GET, POST, PUT, DELETE)
 * - Базовую структуру ответов
 * 
 * Тесты запускаются против openapi-mock сервера (muonsoft/openapi-mock),
 * который эмулирует API на основе OpenAPI спецификации.
 * 
 * @see https://dev.moysklad.ru/doc/api/remap/1.2/
 */
class ApiEndpointsTest extends TestCase
{
    private Client $client;
    
    /**
     * Базовый путь API для запросов к mock-серверу.
     * openapi-mock обслуживает пути вместе с prefix из servers.url спеки.
     */
    private const API_BASE_PATH = '/api/remap/1.2';

    /**
     * Допустимые коды для операций по {id} и вложенным subresource (GET/PUT/DELETE и т.п.).
     * 404 здесь означает «ресурс не найден», а не «маршрут не совпал» — endpoint достигнут.
     * 500 допустим: openapi-mock может вернуть его при рекурсивных схемах.
     *
     * Для list/create/batch/delete используйте assertNotEquals(404): там 404 = path mismatch.
     */
    private const BY_ID_ACCEPTABLE_CODES = [200, 401, 404, 500];
    
    /**
     * Допустимые коды для DELETE операций
     */
    private const DELETE_CODES = [200, 204, 401, 404, 500];

    /**
     * Тестовый UUID для запросов по ID
     */
    private const TEST_UUID = '12345678-1234-1234-1234-123456789012';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = new Client([
            'base_uri' => $this->getSmokeBaseUrl(),
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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
     * Проверяет доступность endpoint'а печати этикеток и ценников.
     * POST /entity/{type}/{id}/export
     */
    public function testPrintLabels(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/export', [
            'json' => [
                'organization' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID,
                        'type' => 'organization',
                        'mediaType' => 'application/json',
                    ],
                ],
                'count' => 10,
                'salePrice' => [
                    'priceType' => [
                        'meta' => [
                            'href' => 'https://api.moysklad.ru/api/remap/1.2/context/companysettings/pricetype/' . self::TEST_UUID,
                            'type' => 'pricetype',
                            'mediaType' => 'application/json',
                        ],
                    ],
                ],
                'template' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/assortment/metadata/embeddedtemplate/' . self::TEST_UUID,
                        'type' => 'embeddedtemplate',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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

    /**
     * Проверяет доступность endpoint'а получения изображений товара.
     * GET /entity/product/{id}/images
     */
    public function testGetProductImages(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/images');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а добавления изображений к товару.
     * POST /entity/product/{id}/images
     */
    public function testAddProductImages(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/images', [
            'json' => ['filename' => 'test.png', 'content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADklEQVQI12P4z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg=='],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления изображения товара.
     * DELETE /entity/product/{id}/images/{imageId}
     */
    public function testDeleteProductImage(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/images/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового удаления изображений товара.
     * POST /entity/product/{id}/images/delete
     */
    public function testBatchDeleteProductImages(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/images/delete', [
            'json' => [['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID . '/images/' . self::TEST_UUID, 'type' => 'image', 'mediaType' => 'application/json']]],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения файлов товара.
     * GET /entity/product/{id}/files
     */
    public function testGetProductFiles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/files');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а добавления файлов к товару.
     * POST /entity/product/{id}/files
     */
    public function testAddProductFiles(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/files', [
            'json' => [['filename' => 'doc.pdf', 'content' => 'SGVsbG8gV29ybGQ=']],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления файла товара.
     * DELETE /entity/product/{id}/files/{fileId}
     */
    public function testDeleteProductFile(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/files/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения НСО товара по складам.
     * GET /entity/product/{id}/storebalances
     */
    public function testGetProductStoreBalances(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания НСО товара для склада.
     * POST /entity/product/{id}/storebalances
     */
    public function testCreateProductStoreBalance(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances', [
            'json' => ['store' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/store/' . self::TEST_UUID, 'type' => 'store', 'mediaType' => 'application/json']], 'quantity' => 5.0],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения НСО товара по складу.
     * GET /entity/product/{id}/storebalances/{storeBalanceId}
     */
    public function testGetProductStoreBalanceById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления НСО товара по складу.
     * PUT /entity/product/{id}/storebalances/{storeBalanceId}
     */
    public function testUpdateProductStoreBalance(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances/' . self::TEST_UUID, [
            'json' => ['quantity' => 10.0],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления НСО товара по складу.
     * DELETE /entity/product/{id}/storebalances/{storeBalanceId}
     */
    public function testDeleteProductStoreBalance(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового удаления НСО товара.
     * POST /entity/product/{id}/storebalances/delete
     */
    public function testBatchDeleteProductStoreBalances(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances/delete', [
            'json' => [['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID . '/storebalances/' . self::TEST_UUID, 'type' => 'minimumstock', 'mediaType' => 'application/json']]],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения счетов контрагента.
     * GET /entity/counterparty/{id}/accounts
     */
    public function testGetCounterpartyAccounts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания счёта контрагента.
     * POST /entity/counterparty/{id}/accounts
     */
    public function testCreateCounterpartyAccount(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts', [
            'json' => ['accountNumber' => '40702810123456789012', 'bankName' => 'ПАО Сбербанк', 'bic' => '044525225'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения счёта контрагента по ID.
     * GET /entity/counterparty/{id}/accounts/{accountId}
     */
    public function testGetCounterpartyAccountById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления счёта контрагента.
     * PUT /entity/counterparty/{id}/accounts/{accountId}
     */
    public function testUpdateCounterpartyAccount(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID, [
            'json' => ['bankName' => 'ПАО ВТБ'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления счёта контрагента.
     * DELETE /entity/counterparty/{id}/accounts/{accountId}
     */
    public function testDeleteCounterpartyAccount(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения контактных лиц контрагента.
     * GET /entity/counterparty/{id}/contactpersons
     */
    public function testGetCounterpartyContactPersons(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания контактного лица контрагента.
     * POST /entity/counterparty/{id}/contactpersons
     */
    public function testCreateCounterpartyContactPerson(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons', [
            'json' => ['name' => 'Иванов Иван Иванович', 'position' => 'Директор'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения контактного лица контрагента по ID.
     * GET /entity/counterparty/{id}/contactpersons/{contactPersonId}
     */
    public function testGetCounterpartyContactPersonById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления контактного лица контрагента.
     * PUT /entity/counterparty/{id}/contactpersons/{contactPersonId}
     */
    public function testUpdateCounterpartyContactPerson(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons/' . self::TEST_UUID, [
            'json' => ['position' => 'Менеджер'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления контактного лица контрагента.
     * DELETE /entity/counterparty/{id}/contactpersons/{contactPersonId}
     */
    public function testDeleteCounterpartyContactPerson(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения событий контрагента.
     * GET /entity/counterparty/{id}/notes
     */
    public function testGetCounterpartyNotes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания события контрагента.
     * POST /entity/counterparty/{id}/notes
     */
    public function testCreateCounterpartyNote(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes', [
            'json' => ['description' => 'Важный клиент'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения события контрагента по ID.
     * GET /entity/counterparty/{id}/notes/{noteId}
     */
    public function testGetCounterpartyNoteById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления события контрагента.
     * PUT /entity/counterparty/{id}/notes/{noteId}
     */
    public function testUpdateCounterpartyNote(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes/' . self::TEST_UUID, [
            'json' => ['description' => 'Обновлённое событие'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления события контрагента.
     * DELETE /entity/counterparty/{id}/notes/{noteId}
     */
    public function testDeleteCounterpartyNote(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения файлов контрагента.
     * GET /entity/counterparty/{id}/files
     */
    public function testGetCounterpartyFiles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/files');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а добавления файлов к контрагенту.
     * POST /entity/counterparty/{id}/files
     */
    public function testAddCounterpartyFiles(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/files', [
            'json' => [['filename' => 'doc.pdf', 'content' => 'SGVsbG8gV29ybGQ=']],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления файла контрагента.
     * DELETE /entity/counterparty/{id}/files/{fileId}
     */
    public function testDeleteCounterpartyFile(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/files/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения прав сотрудника.
     * GET /entity/employee/{id}/security
     */
    public function testGetEmployeeSecurity(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/security');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а изменения прав сотрудника.
     * PUT /entity/employee/{id}/security
     */
    public function testUpdateEmployeeSecurity(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/security', [
            'json' => ['role' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/role/admin', 'type' => 'systemrole', 'mediaType' => 'application/json']]],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а активации сотрудника.
     * PUT /entity/employee/{id}/access/activate
     */
    public function testActivateEmployee(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/access/activate', [
            'json' => ['login' => 'newemployee@lognex'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а деактивации сотрудника.
     * PUT /entity/employee/{id}/access/deactivate
     */
    public function testDeactivateEmployee(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/access/deactivate');
        $this->assertContains($response->getStatusCode(), array_merge(self::BY_ID_ACCEPTABLE_CODES, [204]));
    }

    /**
     * Проверяет доступность endpoint'а сброса пароля сотрудника.
     * PUT /entity/employee/{id}/access/resetpassword
     */
    public function testResetEmployeePassword(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/access/resetpassword');
        $this->assertContains($response->getStatusCode(), array_merge(self::BY_ID_ACCEPTABLE_CODES, [204]));
    }

    /**
     * Проверяет доступность endpoint'а получения роли владельца аккаунта.
     * GET /entity/role/owner
     */
    public function testGetRoleOwner(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/role/owner');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения роли администратора.
     * GET /entity/role/admin
     */
    public function testGetRoleAdmin(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/role/admin');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения индивидуальной роли.
     * GET /entity/role/individual
     */
    public function testGetRoleIndividual(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/role/individual');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения роли кассира.
     * GET /entity/role/cashier
     */
    public function testGetRoleCashier(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/role/cashier');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения роли сотрудника производства.
     * GET /entity/role/worker
     */
    public function testGetRoleWorker(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/role/worker');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    // ==================== REGIONS ====================

    /**
     * Проверяет доступность endpoint'а получения списка регионов.
     * GET /entity/region
     */
    public function testListRegions(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/region');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения региона по ID.
     * GET /entity/region/{id}
     */
    public function testGetRegionById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/region/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    // ==================== TAX RATES ====================

    /**
     * Проверяет доступность endpoint'а получения списка ставок НДС.
     * GET /entity/taxrate
     *
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-stavka-nds
     */
    public function testListTaxRates(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/taxrate');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения ставки НДС по ID.
     * GET /entity/taxrate/{id}
     */
    public function testGetTaxRateById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/taxrate/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания ставки НДС.
     * POST /entity/taxrate
     */
    public function testCreateTaxRate(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/taxrate', [
            'json' => ['rate' => 33.0],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а изменения ставки НДС.
     * PUT /entity/taxrate/{id}
     */
    public function testUpdateTaxRate(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/taxrate/' . self::TEST_UUID, [
            'json' => ['rate' => 28.0],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления ставки НДС.
     * DELETE /entity/taxrate/{id}
     */
    public function testDeleteTaxRate(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/taxrate/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового создания и обновления ставок НДС.
     * POST /entity/taxrate/batch
     */
    public function testBatchCreateTaxRates(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/taxrate/batch', [
            'json' => [['rate' => 33.0]],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а массового удаления ставок НДС.
     * POST /entity/taxrate/delete
     */
    public function testBatchDeleteTaxRates(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/taxrate/delete', [
            'json' => [['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/taxrate/' . self::TEST_UUID, 'type' => 'taxrate', 'mediaType' => 'application/json']]],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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

    // ==================== PROCESSING PLAN FOLDERS ====================

    /**
     * Группы техкарт: список, одиночное создание, массовое создание/обновление, CRUD по id, массовое удаление, метаданные.
     *
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-gruppa-tehkart
     */
    public function testProcessingPlanFolderEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/processingplanfolder';
        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'PPF test']]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'PPF batch']]]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'PPF updated']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->get($base . '/metadata'));
    }

    // ==================== PROCESSING PLAN ====================

    /**
     * Техкарта: список, одиночное создание, массовое создание/обновление, CRUD по id и вложенных сущностей, массовое удаление, метаданные.
     *
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/#suschnosti-gruppa-tehkart
     */
    public function testProcessingPlanEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/processingplan';
        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'PPF test']]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'PPF batch']]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['name' => 'PPF updated']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/stages'));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/stages/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/stages/' . self::TEST_UUID, ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/products'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/products', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/products/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/products/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/products/' . self::TEST_UUID));

        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/products/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/materials'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/materials', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/materials/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/materials/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/materials/' . self::TEST_UUID));

        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/materials/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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

    /**
     * Проверяет доступность endpoint'а получения файлов услуги.
     * GET /entity/service/{id}/files
     */
    public function testGetServiceFiles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/service/' . self::TEST_UUID . '/files');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а добавления файлов к услуге.
     * POST /entity/service/{id}/files
     */
    public function testAddServiceFiles(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/service/' . self::TEST_UUID . '/files', [
            'json' => [['filename' => 'doc.pdf', 'content' => 'SGVsbG8gV29ybGQ=']],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления файла услуги.
     * DELETE /entity/service/{id}/files/{fileId}
     */
    public function testDeleteServiceFile(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/service/' . self::TEST_UUID . '/files/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    // ==================== WEBHOOKS ====================

    /**
     * GET /entity/webhook
     */
    public function testListWebhooks(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/webhook');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/webhook
     */
    public function testCreateWebhook(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhook', [
            'json' => [
                'url' => 'http://www.example.com',
                'action' => 'CREATE',
                'entityType' => 'supply',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/webhook/{id}
     */
    public function testGetWebhookById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/webhook/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/webhook/{id}
     */
    public function testUpdateWebhook(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/webhook/' . self::TEST_UUID, [
            'json' => [
                'url' => 'http://www.example.com',
                'action' => 'DELETE',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/webhook/batch — массовое создание и обновление
     */
    public function testCreateOrUpdateWebhooksBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhook/batch', [
            'json' => [
                [
                    'url' => 'http://www.example.com',
                    'action' => 'CREATE',
                    'entityType' => 'supply',
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * DELETE /entity/webhook/{id}
     */
    public function testDeleteWebhook(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/webhook/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/webhook/delete
     */
    public function testBatchDeleteWebhooks(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhook/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/webhook/' . self::TEST_UUID,
                        'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/webhook/metadata',
                        'type' => 'webhook',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== WEBHOOK STOCK (остатки) ====================

    /**
     * GET /entity/webhookstock
     */
    public function testListWebhookStocks(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/webhookstock');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/webhookstock — создание одного вебхука
     */
    public function testCreateWebhookStock(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhookstock', [
            'json' => [
                'url' => 'http://www.example.com',
                'enabled' => true,
                'reportType' => 'all',
                'stockType' => 'stock',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/webhookstock/batch — массовое создание и обновление (массив в теле)
     */
    public function testCreateOrUpdateWebhookStocksBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhookstock/batch', [
            'json' => [
                [
                    'url' => 'http://www.example.com',
                    'stockType' => 'stock',
                    'reportType' => 'all',
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/webhookstock/{id}
     */
    public function testGetWebhookStockById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/webhookstock/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/webhookstock/{id}
     */
    public function testUpdateWebhookStock(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/webhookstock/' . self::TEST_UUID, [
            'json' => [
                'url' => 'http://www.example.com',
                'stockType' => 'stock',
                'reportType' => 'bystore',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/webhookstock/{id}
     */
    public function testDeleteWebhookStock(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/webhookstock/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/webhookstock/delete
     */
    public function testBatchDeleteWebhookStocks(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/webhookstock/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/webhookstock/' . self::TEST_UUID,
                        'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/webhookstock/metadata',
                        'type' => 'webhookstock',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== PROCESSING STAGES ====================

    /**
     * Этап производства: список, одиночное создание, массовое создание/обновление, CRUD по id, массовое удаление, метаданные.
     *
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/dictionaries/processingstage
     */
    public function testProcessingStageEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/processingstage';
        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'test']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['name' => 'updated']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'batch']]]));
        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
    }

    // ==================== PROCESSING PROCESSES ====================

    /**
     * Техпроцесс: список, одиночное создание, массовое создание/обновление, CRUD по id, CRUD позиций, массовое удаление, метаданные.
     *
     * @see https://dev.moysklad.ru/doc/api/remap/1.2/#/dictionaries/processingprocess
     */
    public function testProcessingProcessEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/processingprocess';
        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'PPF test']]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'PPF batch']]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['name' => 'PPF updated']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));

        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));
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
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
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

    // ==================== SUBSCRIPTIONS ====================

    /**
     * Проверяет доступность endpoint'а получения подписки компании.
     * GET /accountSettings/subscription
     */
    public function testGetSubscription(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/accountSettings/subscription');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== SALE PLATFORMS ====================

    /**
     * Проверяет доступность endpoint'а получения списка площадок для продаж.
     * GET /entity/saleplatform
     */
    public function testListSalePlatforms(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/saleplatform');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения площадки для продаж по ID.
     * GET /entity/saleplatform/{id}
     */
    public function testGetSalePlatformById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/saleplatform/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    public function testListStores(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/store');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testStoreById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    public function testStoreSubresources(): void
    {
        $zones = $this->client->get(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID . '/zones');
        $slots = $this->client->get(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID . '/slots');
        $this->assertNotEquals(404, $zones->getStatusCode());
        $this->assertNotEquals(404, $slots->getStatusCode());
    }

    public function testListRetailStores(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/retailstore');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testListCashiers(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/retailstore/' . self::TEST_UUID . '/cashiers');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testListBundles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bundle');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testBundleSubresources(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testListVariants(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/variant');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testVariantMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/variant/metadata');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testListThings(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/thing');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testInternalOrderCrudAndCPositionsEndpoints(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder', ['json' => ['name' => 'Bundle X']]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID, ['json' => ['name' => 'Bundle Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/batch', ['json' => [['name' => 'internalorder Z']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/metadata'));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/metadata/attributes'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/metadata/attributes', ['json' => [['name' => 'atr1']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/internalorder/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/internalorder/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/internalorder/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/internalorder/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/internalorder/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/internalorder/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/internalorder/new'));
    }

    public function testInternalOrderTemplateAndPositions(): void
    {
        $new = $this->client->put(self::API_BASE_PATH . '/entity/internalorder/new', ['json' => new \stdClass()]);
        $positions = $this->client->get(self::API_BASE_PATH . '/entity/internalorder/' . self::TEST_UUID . '/positions');
        $this->assertNotEquals(404, $new->getStatusCode());
        $this->assertNotEquals(404, $positions->getStatusCode());
    }

    public function testProcessingOrderCrudAndCPositionsEndpoints(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder', ['json' => ['name' => 'ProcessingOrder X']]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID, ['json' => ['name' => 'ProcessingOrder Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/batch', ['json' => [['name' => 'processingorder Z']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'doc.pdf', 'content' => 'SGVsbG8gV29ybGQ=']]]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/processingorder/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/metadata'));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/metadata/attributes'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/metadata/attributes', ['json' => [['name' => 'atr1']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/processingorder/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/processingorder/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/processingorder/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/processingorder/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/processingorder/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/processingorder/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/processingorder/new'));
    }

    public function testProcessingCrudEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/processing';
        $docBase = $base . '/' . self::TEST_UUID;

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'Processing X']]));
        $this->assertReachable($this->client->get($docBase));
        $this->assertReachable($this->client->put($docBase, ['json' => ['name' => 'Processing Y']]));
        $this->assertReachable($this->client->delete($docBase));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'Processing Z']]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => [['name' => 'attr1']]]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new'));

        $this->assertReachable($this->client->get($docBase . '/materials'));
        $this->assertReachable($this->client->post($docBase . '/materials', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($docBase . '/materials/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($docBase . '/materials/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($docBase . '/materials/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($docBase . '/materials/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($docBase . '/products'));
        $this->assertReachable($this->client->post($docBase . '/products', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($docBase . '/products/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($docBase . '/products/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($docBase . '/products/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($docBase . '/products/delete', ['json' => [['meta' => ['href' => 'x']]]]));

//        $this->assertReachable($this->client->get($docBase . '/files'));
//        $this->assertReachable($this->client->post($docBase . '/files', ['json' => [['filename' => 'X']],]));
//        $this->assertReachable($this->client->delete($docBase . '/files/' . self::TEST_UUID));
    }

    public function testCustomerOrderCrudAndCPositionsEndpoints(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder', ['json' => ['name' => 'Bundle X']]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID, ['json' => ['name' => 'Bundle Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/batch', ['json' => [['name' => 'customerorder Z']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/metadata'));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/metadata/attributes'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/metadata/attributes', ['json' => [['name' => 'atr1']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/customerorder/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/customerorder/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/customerorder/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/customerorder/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/customerorder/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/customerorder/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/customerorder/new'));
    }

    public function testCustomerOrderTemplateAndPositions(): void
    {
        $new = $this->client->put(self::API_BASE_PATH . '/entity/customerorder/new', ['json' => new \stdClass()]);
        $positions = $this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/positions');
        $this->assertNotEquals(404, $new->getStatusCode());
        $this->assertNotEquals(404, $positions->getStatusCode());
    }

    public function testDemandCrudMetadataTemplateAndPositions(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/demand', ['json' => ['name' => 'Demand X']]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/demand/' . self::TEST_UUID, ['json' => ['name' => 'Demand Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/demand/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/demand/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/demand/batch', ['json' => [['name' => 'Demand Z']]]));

        $base = self::API_BASE_PATH . '/entity/demand/' . self::TEST_UUID;
        $this->assertReachable($this->client->get($base . '/positions'));
        $this->assertReachable($this->client->post($base . '/positions', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($base . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($base . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/metadata'));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/metadata/attributes'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/demand/metadata/attributes', ['json' => [['name' => 'atr1']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/demand/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/demand/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/demand/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/demand/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/demand/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/demand/new'));
    }


    public function testListMoves(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move'));
    }

    public function testCreateMove(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move', [
            'json' => $this->moveRequiredPayload(),
        ]));
    }

    public function testGetMoveById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID));
    }

    public function testUpdateMove(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID, [
            'json' => ['name' => 'Move Y'],
        ]));
    }

    public function testDeleteMove(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID));
    }

    public function testDeleteMovesBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/delete', [
            'json' => $this->moveMetaPayload(),
        ]));
    }

    public function testCreateMovesBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/batch', [
            'json' => [$this->moveRequiredPayload()],
        ]));
    }

    public function testGetMoveTemplate(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/move/new', [
            'json' => new \stdClass(),
        ]));
    }

    public function testGetMoveMetadata(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/metadata'));
    }

    public function testGetMoveMetadataAttributes(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/metadata/attributes'));
    }

    public function testCreateMoveMetadataAttribute(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/metadata/attributes', [
            'json' => ['name' => 'atr1', 'type' => 'string'],
        ]));
    }

    public function testGetMoveMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/metadata/attributes/' . self::TEST_UUID));
    }

    public function testUpdateMoveMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/move/metadata/attributes/' . self::TEST_UUID, [
            'json' => ['name' => 'atr1'],
        ]));
    }

    public function testDeleteMoveMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/move/metadata/attributes/' . self::TEST_UUID));
    }

    public function testGetMoveMetadataStateById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/metadata/states/' . self::TEST_UUID));
    }

    public function testUpdateMoveMetadataStateById(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/move/metadata/states/' . self::TEST_UUID, [
            'json' => ['name' => 'state1'],
        ]));
    }

    public function testDeleteMoveMetadataStateById(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/move/metadata/states/' . self::TEST_UUID));
    }

    public function testGetMoveFiles(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/files'));
    }

    public function testAddMoveFiles(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/files', [
            'json' => [['filename' => 'X']],
        ]));
    }

    public function testDeleteMoveFile(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
    }

    public function testGetMovePositions(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions'));
    }

    public function testCreateMovePositions(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions', [
            'json' => $this->movePositionPayload(),
        ]));
    }

    public function testCreateMovePositionsBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions/batch', [
            'json' => [$this->movePositionPayload()],
        ]));
    }

    public function testGetMovePositionById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
    }

    public function testUpdateMovePosition(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, [
            'json' => ['quantity' => 2.0],
        ]));
    }

    public function testDeleteMovePosition(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
    }

    public function testDeleteMovePositionsBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/move/' . self::TEST_UUID . '/positions/delete', [
            'json' => $this->movePositionMetaPayload(),
        ]));
    }

    public function testSalesReturnCrudMetadataTemplateAndPositions(): void
    {
        $base = self::API_BASE_PATH . '/entity/salesreturn';

        $payload = [
            'name' => 'SalesReturn X',
            'organization' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID,
                    'type' => 'organization',
                    'mediaType' => 'application/json',
                ],
            ],
            'agent' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID,
                    'type' => 'counterparty',
                    'mediaType' => 'application/json',
                ],
            ],
            'store' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/store/' . self::TEST_UUID,
                    'type' => 'store',
                    'mediaType' => 'application/json',
                ],
            ],
        ];

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => $payload]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'SalesReturn Y']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [$payload]]));

        $docBase = $base . '/' . self::TEST_UUID;
        $this->assertReachable($this->client->get($docBase . '/positions'));
        $this->assertReachable($this->client->post($docBase . '/positions', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($docBase . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($docBase . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($docBase . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($docBase . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new'));
    }

    public function testSupplyCrudMetadataTemplateAndPositions(): void
    {
        $base = self::API_BASE_PATH . '/entity/supply';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions/batch', ['json' => [['quantity' => 1]]]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => []]));
        $this->assertReachable($this->client->put($base . '/new', [
            'json' => [
                'purchaseOrder' => [
                    'meta' => [
                        'href' => self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID,
                        'type' => 'purchaseorder',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]));
    }

    public function testPurchaseReturnCrudMetadataTemplateAndPositions(): void
    {
        $base = self::API_BASE_PATH . '/entity/purchasereturn';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']],]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => [['name' => 'X']]]));
    }

    public function testPrepaymentReturnMetadataAndPositions(): void
    {
        $base = self::API_BASE_PATH . '/entity/prepaymentreturn';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $docBase = $base . '/' . self::TEST_UUID;
        $this->assertReachable($this->client->get($docBase . '/positions'));
        $this->assertReachable($this->client->get($docBase . '/positions/' . self::TEST_UUID));
    }

    public function testCreatePrepaymentMetadataAttribute(): void
    {
        $this->assertReachable($this->client->post(
            self::API_BASE_PATH . '/entity/prepayment/metadata/attributes',
            ['json' => ['name' => 'attr1']]
        ));
    }

    public function testRetailDemandCrudMetadataTemplateAndPositions(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retaildemand', [
            'json' => [
                'retailShift' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/retailshift/' . self::TEST_UUID,
                        'type' => 'retailshift',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/retaildemand/' . self::TEST_UUID, ['json' => ['name' => 'Retail Demand Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/retaildemand/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retaildemand/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retaildemand/batch', ['json' => [['name' => 'Retail Demand Z']]]));

        $base = self::API_BASE_PATH . '/entity/retaildemand/' . self::TEST_UUID;
        $this->assertReachable($this->client->get($base . '/positions'));
        $this->assertReachable($this->client->post($base . '/positions', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($base . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($base . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand/metadata'));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand/metadata/attributes'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retaildemand/metadata/attributes', ['json' => [['name' => 'atr1']]]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/retaildemand/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/retaildemand/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retaildemand/metadata/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retaildemand/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/retaildemand/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/retaildemand/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/retaildemand/new', [
            'json' => [
                'retailShift' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/retailshift/' . self::TEST_UUID,
                        'type' => 'retailshift',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]));
    }

    /**
     * RetailShift проверяется отдельно, потому что общий assertDocumentEndpoints()
     * предполагает наличие /new, /positions и metadata/states, которых у этой сущности нет.
     */
    public function testRetailShiftCrudAndMetadataEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/retailshift';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'Retail Shift X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'Retail Shift Y']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'Retail Shift Z']]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'attr1']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
    }

    public function testListPurchaseOrders(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/purchaseorder');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testPurchaseOrderTemplateAndPositions(): void
    {
        $new = $this->client->put(self::API_BASE_PATH . '/entity/purchaseorder/new', ['json' => new \stdClass()]);
        $positions = $this->client->get(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/positions');
        $this->assertNotEquals(404, $new->getStatusCode());
        $this->assertNotEquals(404, $positions->getStatusCode());
    }

    public function testCreatePurchaseOrderMetadataState(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/purchaseorder/metadata/states', [
            'json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    public function testStoreCrudAndMetaEndpoints(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/store', ['json' => ['name' => 'Store X']]));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID, ['json' => ['name' => 'Store Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/store/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/store/batch', ['json' => [['name' => 'Store Z']]]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/store/metadata'));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/store/metadata/attributes/' . self::TEST_UUID));
    }

    public function testOrganizationCrudAndMetaEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/organization';
        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'PPF test']]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'PPF batch']]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['name' => 'PPF updated']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/accounts'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/accounts', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID));

        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/accounts/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['name' => 'PPF test']));

        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['name' => 'PPF test']));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));
    }

    public function testStoreZoneAndSlotManagementEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID;
        $this->assertReachable($this->client->post($base . '/zones', ['json' => ['name' => 'z1']]));
        $this->assertReachable($this->client->put($base . '/zones/' . self::TEST_UUID, ['json' => ['name' => 'z2']]));
        $this->assertReachable($this->client->delete($base . '/zones/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/zones/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/slots', ['json' => ['name' => 's1']]));
        $this->assertReachable($this->client->put($base . '/slots/' . self::TEST_UUID, ['json' => ['name' => 's2']]));
        $this->assertReachable($this->client->delete($base . '/slots/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/slots/delete', ['json' => [['meta' => ['href' => 'x']]]]));
    }

    public function testRetailStoreCrudEndpoints(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retailstore', ['json' => ['name' => 'Retail X']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retailstore/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/retailstore/' . self::TEST_UUID, ['json' => ['name' => 'Retail Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/retailstore/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retailstore/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/retailstore/batch', ['json' => [['name' => 'Retail Z']]]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/retailstore/' . self::TEST_UUID . '/cashiers/' . self::TEST_UUID));
    }

    public function testBundleCrudAndComponentsEndpoints(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/bundle', ['json' => ['name' => 'Bundle X']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID, ['json' => ['name' => 'Bundle Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/bundle/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/bundle/batch', ['json' => [['name' => 'Bundle Z']]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components', ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/bundle/' . self::TEST_UUID . '/components/delete', ['json' => [['meta' => ['href' => 'x']]]]));
    }

    public function testVariantCrudEndpoints(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/variant', ['json' => ['name' => 'Variant X']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/variant/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/variant/' . self::TEST_UUID, ['json' => ['name' => 'Variant Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/variant/' . self::TEST_UUID));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/variant/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/variant/batch', ['json' => [['name' => 'Variant Z']]]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/variant/metadata/characteristics/', ['json' => ['name' => 'Characteristic X']]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/variant/metadata/characteristics/' . self::TEST_UUID));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/variant/metadata/characteristics/'));
    }

    public function testDocumentEndpointsInternalCustomerPurchase(): void
    {
        $this->assertDocumentEndpoints('/entity/internalorder');
        $this->assertDocumentEndpoints('/entity/processingorder');
        $this->assertDocumentEndpoints('/entity/customerorder');
        $this->assertDocumentEndpoints('/entity/purchaseorder');
    }

    /**
     * GET /entity/customerorder/{id}/notes
     */
    public function testGetCustomerOrderNotes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/notes');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/customerorder/{id}/notes
     */
    public function testCreateCustomerOrderNote(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/notes', [
            'json' => ['description' => 'Привет, {{employee;730c1b3d-00ba-11ef-ac12-00120000000d}}!'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/customerorder/{id}/notes/{noteId}
     */
    public function testGetCustomerOrderNoteById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/customerorder/{id}/notes/{noteId}
     */
    public function testUpdateCustomerOrderNote(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID, [
            'json' => ['description' => 'Обновленное событие'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/customerorder/{id}/notes/{noteId}
     */
    public function testDeleteCustomerOrderNote(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/customerorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * GET /entity/purchaseorder/{id}/notes
     */
    public function testGetPurchaseOrderNotes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/notes');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/purchaseorder/{id}/notes
     */
    public function testCreatePurchaseOrderNote(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/notes', [
            'json' => ['description' => 'Привет, {{employee;730c1b3d-00ba-11ef-ac12-00120000000d}}!'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/purchaseorder/{id}/notes/{noteId}
     */
    public function testGetPurchaseOrderNoteById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/purchaseorder/{id}/notes/{noteId}
     */
    public function testUpdatePurchaseOrderNote(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID, [
            'json' => ['description' => 'Обновленное событие'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/purchaseorder/{id}/notes/{noteId}
     */
    public function testDeletePurchaseOrderNote(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/purchaseorder/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/productiontask/metadata/attributes
     */
    public function testCreateProductionTaskMetadataAttribute(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/productiontask/metadata/attributes', [
            'json' => ['name' => 'productionTaskAttribute'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    public function testEmissionOrderEndpoints(): void
    {
        $base = '/entity/emissionorder';

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base, [
            'json' => [
                'organization' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                'trackingType' => 'MILK',
                'emissionType' => 'LOCAL',
            ],
        ]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated emission order'],
        ]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/batch', [
            'json' => [[
                'organization' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                'trackingType' => 'MILK',
                'emissionType' => 'LOCAL',
            ]],
        ]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata'));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions', [
            'json' => [
                'quantity' => 1,
                'assortment' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID, 'type' => 'product', 'mediaType' => 'application/json']],
            ],
        ]));
        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, [
            'json' => ['quantity' => 2],
        ]));
    }

    private function assertDocumentEndpoints(string $base): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/' . self::TEST_UUID, ['json' => ['name' => 'Y']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . $base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/batch', ['json' => [['name' => 'Z']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata'));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata/attributes/'));
        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/metadata/attributes', ['json' => [['name' => 'atr1']]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . $base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . $base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/new', ['json' => new \stdClass()]));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions', ['json' => ['quantity' => 1]]));

        $this->assertReachable($this->client->get(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));

        $this->assertReachable($this->client->post(self::API_BASE_PATH . $base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));
    }

    // ==================== CONTRACTS ====================

    /**
     * GET /entity/contract
     */
    public function testListContracts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/contract');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/contract/{id}
     */
    public function testGetContractById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/contract/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/contract
     */
    public function testCreateContract(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/contract', [
            'json' => [
                'name' => 'Test Contract',
                'ownAgent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                'agent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID, 'type' => 'counterparty', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/contract/{id}
     */
    public function testUpdateContract(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/contract/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated Contract'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/contract/{id}
     */
    public function testDeleteContract(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/contract/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/contract/delete
     */
    public function testDeleteContractsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/contract/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/contract/' . self::TEST_UUID, 'type' => 'contract', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/contract/batch
     */
    public function testCreateContractsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/contract/batch', [
            'json' => [
                [
                    'name' => 'Batch Contract',
                    'ownAgent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                    'agent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID, 'type' => 'counterparty', 'mediaType' => 'application/json']],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/contract/metadata
     */
    public function testGetContractMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/contract/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/contract/metadata/attributes
     */
    public function testGetContractMetadataAttributes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/contract/metadata/attributes');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/contract/metadata/attributes/{id}
     */
    public function testGetContractMetadataAttributeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/contract/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    // ==================== SALES CHANNELS ====================

    /**
     * GET /entity/saleschannel
     */
    public function testListSalesChannels(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/saleschannel');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/saleschannel/{id}
     */
    public function testGetSalesChannelById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/saleschannel/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/saleschannel
     */
    public function testCreateSalesChannel(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/saleschannel', [
            'json' => [
                'name' => 'Test Sales Channel',
                'type' => 'OTHER',
                'description' => 'Test description',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/saleschannel/batch
     */
    public function testCreateSalesChannelsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/saleschannel/batch', [
            'json' => [
                [
                    'name' => 'Batch Sales Channel',
                    'type' => 'OTHER',
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/saleschannel/{id}
     */
    public function testUpdateSalesChannel(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/saleschannel/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated Sales Channel'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/saleschannel/{id}
     */
    public function testDeleteSalesChannel(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/saleschannel/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/saleschannel/delete
     */
    public function testDeleteSalesChannelsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/saleschannel/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/saleschannel/' . self::TEST_UUID, 'type' => 'saleschannel', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== TASKS ====================

    /**
     * GET /entity/task
     */
    public function testListTasks(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/task');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/task
     */
    public function testCreateTask(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task', [
            'json' => [
                'description' => 'Test Task',
                'assignee' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/employee/' . self::TEST_UUID,
                        'type' => 'employee',
                        'mediaType' => 'application/json',
                    ],
                ],
                'done' => false,
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/task/batch
     */
    public function testCreateTasksBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task/batch', [
            'json' => [
                [
                    'description' => 'Batch Task',
                    'assignee' => [
                        'meta' => [
                            'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/employee/' . self::TEST_UUID,
                            'type' => 'employee',
                            'mediaType' => 'application/json',
                        ],
                    ],
                    'done' => false,
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/task/{id}
     */
    public function testGetTaskById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/task/{id}
     */
    public function testUpdateTask(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID, [
            'json' => [
                'description' => 'Updated Task',
                'done' => true,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/task/{id}
     */
    public function testDeleteTask(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/task/delete
     */
    public function testDeleteTasksBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/task/' . self::TEST_UUID,
                        'type' => 'task',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/task/{id}/notes
     */
    public function testGetTaskNotes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes');
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/task/{id}/notes
     */
    public function testCreateTaskNote(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes', [
            'json' => [
                'text' => 'Test note',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/task/{id}/notes/{noteId}
     */
    public function testGetTaskNoteById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/task/{id}/notes/{noteId}
     */
    public function testUpdateTaskNote(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes/' . self::TEST_UUID, [
            'json' => [
                'text' => 'Updated note',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/task/{id}/notes/{noteId}
     */
    public function testDeleteTaskNote(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/task/{id}/notes/delete
     */
    public function testDeleteTaskNotesBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task/' . self::TEST_UUID . '/notes/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/task/' . self::TEST_UUID . '/notes/' . self::TEST_UUID,
                        'type' => 'tasknote',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/task/metadata
     */
    public function testGetTaskMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/task/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/task/metadata/states
     */
    public function testCreateTaskMetadataStates(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/task/metadata/states', [
            'json' => [
                'name' => 'Встреча',
                'color' => 69446,
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/task/metadata/states/{id}
     */
    public function testUpdateTaskMetadataStateById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/task/metadata/states/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Связаться',
                'color' => 255,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/task/metadata/states/{id}
     */
    public function testDeleteTaskMetadataStateById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/task/metadata/states/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

// ==================== PROJECTS ====================

    /**
     * GET /entity/project
     */
    public function testListProjects(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/project');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/project/{id}
     */
    public function testGetProjectById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/project/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/project
     */
    public function testCreateProject(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/project', [
            'json' => [
                'name' => 'Test Project',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/project/{id}
     */
    public function testUpdateProject(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/project/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated Project'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/project/{id}
     */
    public function testDeleteProject(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/project/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/project/delete
     */
    public function testDeleteProjectsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/project/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/project/' . self::TEST_UUID, 'type' => 'project', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/project/batch
     */
    public function testCreateProjectsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/project/batch', [
            'json' => [
                [
                    'name' => 'Batch Project',
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/project/metadata
     */
    public function testGetProjectMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/project/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/project/metadata/attributes
     */
    public function testGetProjectMetadataAttributes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/project/metadata/attributes');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/project/metadata/attributes
     */
    public function testCreateProjectMetadataAttribute(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/project/metadata/attributes', [
            'json' => ['name' => 'projectAttribute'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/project/metadata/attributes/{id}
     */
    public function testGetProjectMetadataAttributeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/project/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/project/metadata/attributes/{id}
     */
    public function testUpdateProjectMetadataAttribute(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/project/metadata/attributes/' . self::TEST_UUID, [
            'json' => ['name' => 'projectAttributeUpdated'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/project/metadata/attributes/{id}
     */
    public function testDeleteProjectMetadataAttribute(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/project/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    // ==================== CONSIGNMENTS ====================

    /**
     * GET /entity/consignment
     */
    public function testListConsignments(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/consignment');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/consignment/{id}
     */
    public function testGetConsignmentById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/consignment/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/consignment
     */
    public function testCreateConsignment(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/consignment', [
            'json' => [
                'label' => 'Test Consignment',
                'assortment' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/variant/' . self::TEST_UUID,
                        'type' => 'variant',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/consignment/{id}
     */
    public function testUpdateConsignment(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/consignment/' . self::TEST_UUID, [
            'json' => [
                'label' => 'Updated Consignment',
                'assortment' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/variant/' . self::TEST_UUID,
                        'type' => 'variant',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/consignment/{id}
     */
    public function testDeleteConsignment(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/consignment/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/consignment/delete
     */
    public function testDeleteConsignmentsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/consignment/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/consignment/' . self::TEST_UUID, 'type' => 'consignment', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/consignment/batch
     */
    public function testCreateConsignmentsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/consignment/batch', [
            'json' => [
                [
                    'label' => 'Batch Consignment',
                    'assortment' => [
                        'meta' => [
                            'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/variant/' . self::TEST_UUID,
                            'type' => 'variant',
                            'mediaType' => 'application/json',
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/consignment/metadata
     */
    public function testGetConsignmentMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/consignment/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/consignment/metadata/attributes
     */
    public function testGetConsignmentMetadataAttributes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/consignment/metadata/attributes');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/consignment/metadata/attributes
     */
    public function testCreateConsignmentMetadataAttribute(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/consignment/metadata/attributes', [
            'json' => [
                'name' => 'consignment attribute',
                'type' => 'string',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/consignment/metadata/attributes/{id}
     */
    public function testGetConsignmentMetadataAttributeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/consignment/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/consignment/metadata/attributes/{id}
     */
    public function testUpdateConsignmentMetadataAttribute(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/consignment/metadata/attributes/' . self::TEST_UUID, [
            'json' => [
                'name' => 'updated consignment attribute',
                'type' => 'string',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/consignment/metadata/attributes/{id}
     */
    public function testDeleteConsignmentMetadataAttribute(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/consignment/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    // ==================== EXPENSE ITEMS ====================

    /**
     * GET /entity/expenseitem
     */
    public function testListExpenseItems(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/expenseitem');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/expenseitem/{id}
     */
    public function testGetExpenseItemById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/expenseitem/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * POST /entity/expenseitem
     */
    public function testCreateExpenseItem(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/expenseitem', [
            'json' => [
                'name' => 'Налоги и не налоги',
                'description' => 'Статья расходов налоги',
                'code' => 'nalogi',
                'externalCode' => 'wwoaon21431',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/expenseitem/{id}
     */
    public function testUpdateExpenseItem(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/expenseitem/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Не налоги и налоги',
                'description' => 'Налоги и не налоги. Такая вот статья',
                'code' => 'nalogi i net',
                'externalCode' => 'wwoa1142aon21431',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/expenseitem/{id}
     */
    public function testDeleteExpenseItem(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/expenseitem/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/expenseitem/delete
     */
    public function testDeleteExpenseItemsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/expenseitem/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/' . self::TEST_UUID, 'type' => 'expenseitem', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/expenseitem/batch
     */
    public function testCreateExpenseItemsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/expenseitem/batch', [
            'json' => [
                [
                    'name' => 'Налоги и не налоги',
                    'description' => 'Статья расходов налоги',
                    'code' => 'nalogi',
                    'externalCode' => 'wwoaon21431',
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== COMPANY SETTINGS ====================

    /**
     * Проверяет доступность endpoint'а получения настроек компании.
     * GET /context/companysettings
     */
    public function testGetCompanySettings(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/companysettings');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления настроек компании.
     * PUT /context/companysettings
     */
    public function testUpdateCompanySettings(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/context/companysettings', [
            'json' => [
                'globalOperationNumbering' => true,
                'checkShippingStock' => true,
                'checkMinPrice' => true,
                'useRecycleBin' => true,
                'useCompanyAddress' => true,
                'companyAddress' => 'MyCompany@moysklad.ru',
                'discountStrategy' => 'bySum',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения метаданных настроек компании.
     * GET /context/companysettings/metadata
     */
    public function testGetCompanySettingsMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/companysettings/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== USER SETTINGS ====================

    /**
     * Проверяет доступность endpoint'а получения настроек пользователя.
     * GET /context/usersettings
     */
    public function testGetUserSettings(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/context/usersettings');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления настроек пользователя.
     * PUT /context/usersettings
     */
    public function testUpdateUserSettings(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/context/usersettings', [
            'json' => [
                'defaultCompany' => [
                    'metadataHref' => self::API_BASE_PATH . '/entity/organization/metadata',
                    'type' => 'organization',
                    'mediaType' => 'application/json',
                ],
                'defaultCustomerCounterparty' => [
                    'href' => self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID,
                    'type' => 'counterparty',
                    'mediaType' => 'application/json',
                ],
                'defaultPurchaseCounterparty' => [
                    'href' => self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID,
                    'type' => 'counterparty',
                    'mediaType' => 'application/json',
                ],
                'defaultProject' => [
                    'href' => self::API_BASE_PATH . '/entity/project/' . self::TEST_UUID,
                    'type' => 'project',
                    'mediaType' => 'application/json',
                ],
                'defaultPlace' => [
                    'href' => self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID,
                    'type' => 'store',
                    'mediaType' => 'application/json',
                ],
                'locale' => 'ru_RU',
                'mailFooter' => 'подпись в письме',
                'fieldsPerRow' => 3,
                'defaultScreen' => 'importcustom',
                'printFormat' => 'pdf',
                'autoShowReports' => false,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    // ==================== DISCOUNTS ====================

    /**
     * Проверяет доступность endpoint'а получения списка скидок.
     * GET /entity/discount
     */
    public function testListDiscounts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/discount');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления округления копеек.
     * PUT /entity/discount/{id}
     */
    public function testUpdateDiscountRoundingById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/discount/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated Rounding',
                'active' => false,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания накопительной скидки.
     * POST /entity/accumulationdiscount
     */
    public function testCreateAccumulationDiscount(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/accumulationdiscount', [
            'json' => [
                'name' => 'Test AccumulationDiscount',
                'active' => true,
                'allProducts' => false,
                'allAgents' => false,
                'agentTags' => ['tag1'],
                'levels' => [['amount' => 100000, 'discount' => 10]],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения накопительной скидки по ID.
     * GET /entity/accumulationdiscount/{id}
     */
    public function testGetAccumulationDiscountById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/accumulationdiscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления накопительной скидки.
     * PUT /entity/accumulationdiscount/{id}
     */
    public function testUpdateAccumulationDiscount(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/accumulationdiscount/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated AccumulationDiscount',
                'active' => false,
                'allProducts' => false,
                'allAgents' => false,
                'agentTags' => ['tag2'],
                'levels' => [['amount' => 200000, 'discount' => 15]],
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления накопительной скидки.
     * DELETE /entity/accumulationdiscount/{id}
     */
    public function testDeleteAccumulationDiscount(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/accumulationdiscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания персональной скидки.
     * POST /entity/personaldiscount
     */
    public function testCreatePersonalDiscount(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/personaldiscount', [
            'json' => [
                'name' => 'Test PersonalDiscount',
                'active' => true,
                'allProducts' => true,
                'allAgents' => false,
                'agentTags' => ['tag1'],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения персональной скидки по ID.
     * GET /entity/personaldiscount/{id}
     */
    public function testGetPersonalDiscountById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/personaldiscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления персональной скидки.
     * PUT /entity/personaldiscount/{id}
     */
    public function testUpdatePersonalDiscount(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/personaldiscount/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated PersonalDiscount',
                'active' => false,
                'allProducts' => false,
                'allAgents' => false,
                'agentTags' => ['tag2'],
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления персональной скидки.
     * DELETE /entity/personaldiscount/{id}
     */
    public function testDeletePersonalDiscount(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/personaldiscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания специальной цены.
     * POST /entity/specialpricediscount
     */
    public function testCreateSpecialPriceDiscount(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/specialpricediscount', [
            'json' => [
                'name' => 'Test SpecialPriceDiscount',
                'active' => true,
                'allProducts' => false,
                'allAgents' => false,
                'agentTags' => ['tag1'],
                'usePriceType' => true,
                'specialPrice' => [
                    'value' => 15.0,
                    'priceType' => [
                        'meta' => [
                            'href' => self::API_BASE_PATH . '/context/companysettings/pricetype/' . self::TEST_UUID,
                            'type' => 'pricetype',
                            'mediaType' => 'application/json',
                        ],
                        'id' => self::TEST_UUID,
                        'name' => 'Цена продажи',
                        'externalCode' => 'default',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения специальной цены по ID.
     * GET /entity/specialpricediscount/{id}
     */
    public function testGetSpecialPriceDiscountById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/specialpricediscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления специальной цены.
     * PUT /entity/specialpricediscount/{id}
     */
    public function testUpdateSpecialPriceDiscount(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/specialpricediscount/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated SpecialPriceDiscount',
                'active' => false,
                'allProducts' => false,
                'allAgents' => false,
                'agentTags' => ['tag2'],
                'usePriceType' => false,
                'discount' => 50,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления специальной цены.
     * DELETE /entity/specialpricediscount/{id}
     */
    public function testDeleteSpecialPriceDiscount(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/specialpricediscount/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    // ==================== ASSORTMENT ====================

    /**
     * Проверяет доступность endpoint'а получения ассортимента.
     * GET /entity/assortment
     */
    public function testListAssortment(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/assortment');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а массового удаления позиций ассортимента.
     * POST /entity/assortment/delete
     */
    public function testDeleteAssortmentPositionsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/assortment/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID,
                        'type' => 'product',
                        'mediaType' => 'application/json',
                    ],
                ],
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/service/' . self::TEST_UUID,
                        'type' => 'service',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения настроек ассортимента.
     * GET /entity/assortment/settings
     */
    public function testGetAssortmentSettings(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/assortment/settings');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления настроек ассортимента.
     * PUT /entity/assortment/settings
     */
    public function testUpdateAssortmentSettings(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/assortment/settings', [
            'json' => [
                'createdShared' => true,
                'uniqueCodeRules' => [
                    'checkUniqueCode' => true,
                    'fillUniqueCode' => true,
                ],
                'barcodeRules' => [
                    'fillEAN13Barcode' => true,
                    'weightBarcode' => true,
                    'weightBarcodePrefix' => 77,
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== BONUS PROGRAMS ====================

    /**
     * GET /entity/bonusprogram
     */
    public function testListBonusPrograms(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bonusprogram');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/bonusprogram
     */
    public function testCreateBonusProgram(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/bonusprogram', [
            'json' => [
                'name' => 'Test BonusProgram',
                'active' => true,
                'allProducts' => true,
                'allAgents' => false,
                'agentTags' => ['tag1'],
                'earnRateRoublesToPoint' => 7,
                'spendRatePointsToRouble' => 4,
                'maxPaidRatePercents' => 50,
                'welcomeBonusesEnabled' => false,
                'postponedBonusesDelayDays' => 7,
                'earnWhileRedeeming' => false,
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/bonusprogram/{id}
     */
    public function testGetBonusProgramById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bonusprogram/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/bonusprogram/{id}
     */
    public function testUpdateBonusProgram(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/bonusprogram/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated BonusProgram',
                'active' => true,
                'agentTags' => ['tag2'],
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/bonusprogram/{id}
     */
    public function testDeleteBonusProgram(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/bonusprogram/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/bonusprogram/delete
     */
    public function testDeleteBonusProgramsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/bonusprogram/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/bonusprogram/' . self::TEST_UUID,
                        'type' => 'bonusprogram',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== BONUS TRANSACTIONS ====================

    /**
     * GET /entity/bonustransaction
     */
    public function testListBonusTransactions(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bonustransaction');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/bonustransaction
     */
    public function testCreateBonusTransaction(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/bonustransaction', [
            'json' => [
                'name' => 'Test BonusTransaction',
                'applicable' => true,
                'transactionType' => 'EARNING',
                'bonusValue' => 15,
                'agent' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID,
                        'type' => 'counterparty',
                        'mediaType' => 'application/json',
                    ],
                ],
                'bonusProgram' => [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/bonusprogram/' . self::TEST_UUID,
                        'type' => 'bonusprogram',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/bonustransaction/{id}
     */
    public function testGetBonusTransactionById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/bonustransaction/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/bonustransaction/{id}
     */
    public function testUpdateBonusTransaction(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/bonustransaction/' . self::TEST_UUID, [
            'json' => [
                'bonusValue' => 155,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/bonustransaction/{id}
     */
    public function testDeleteBonusTransaction(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/bonustransaction/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * POST /entity/bonustransaction/delete
     */
    public function testDeleteBonusTransactionsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/bonustransaction/delete', [
            'json' => [
                [
                    'meta' => [
                        'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/bonustransaction/' . self::TEST_UUID,
                        'type' => 'bonustransaction',
                        'mediaType' => 'application/json',
                    ],
                ],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== COMMISSION REPORT INS ====================

    /**
     * Проверяет доступность CRUD + metadata + positions subresources
     * для /entity/commissionreportin
     */
    public function testCommissionReportInCrudMetadataAndPositionsEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/commissionreportin';
        $idBase = $base . '/' . self::TEST_UUID;

        // CRUD + batch endpoints
        $this->assertReachable($this->client->get($base . '/'));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'CommissionReportIn Test']]));
        $this->assertReachable($this->client->get($idBase));
        $this->assertReachable($this->client->put($idBase, ['json' => ['name' => 'CommissionReportIn Updated']]));
        $this->assertReachable($this->client->delete($idBase));
        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'CommissionReportIn Batch']]]));

        // Metadata
        $metaBase = $base . '/metadata';
        $this->assertReachable($this->client->get($metaBase));
        $this->assertReachable($this->client->get($metaBase . '/attributes'));
        $this->assertReachable($this->client->post($metaBase . '/attributes', ['json' => [['name' => 'atr1']]]));
        $this->assertReachable($this->client->get($metaBase . '/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($metaBase . '/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete($metaBase . '/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($metaBase . '/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get($metaBase . '/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($metaBase . '/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete($metaBase . '/states/' . self::TEST_UUID));

        // Realized positions
        $positionsBase = $idBase . '/positions';
        $this->assertReachable($this->client->get($positionsBase));
        $this->assertReachable($this->client->post($positionsBase, ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($positionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($positionsBase . '/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($positionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($positionsBase . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        // Return positions
        $returnPositionsBase = $idBase . '/returntocommissionerpositions';
        $this->assertReachable($this->client->get($returnPositionsBase));
        $this->assertReachable($this->client->post($returnPositionsBase, ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->get($returnPositionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($returnPositionsBase . '/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($returnPositionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($returnPositionsBase . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
    }

    /**
     * Проверяет доступность CRUD + metadata + positions subresources
     * для /entity/commissionreportout
     */
    public function testCommissionReportOutCrudMetadataAndPositionsEndpoints(): void
    {
        $base = self::API_BASE_PATH . '/entity/commissionreportout';
        $idBase = $base . '/' . self::TEST_UUID;

        // CRUD + batch endpoints
        $this->assertReachable($this->client->get($base . '/'));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'CommissionReportOut Test']]));
        $this->assertReachable($this->client->get($idBase));
        $this->assertReachable($this->client->put($idBase, ['json' => ['name' => 'CommissionReportOut Updated']]));
        $this->assertReachable($this->client->delete($idBase));
        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'CommissionReportOut Batch']]]));

        // Metadata
        $metaBase = $base . '/metadata';
        $this->assertReachable($this->client->get($metaBase));
        $this->assertReachable($this->client->get($metaBase . '/attributes'));
        $this->assertReachable($this->client->post($metaBase . '/attributes', ['json' => [['name' => 'atr1']]]));
        $this->assertReachable($this->client->get($metaBase . '/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($metaBase . '/attributes/' . self::TEST_UUID, ['json' => ['name' => 'atr1']]));
        $this->assertReachable($this->client->delete($metaBase . '/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($metaBase . '/states', ['json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular']]));
        $this->assertReachable($this->client->get($metaBase . '/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($metaBase . '/states/' . self::TEST_UUID, ['json' => ['name' => 'state1']]));
        $this->assertReachable($this->client->delete($metaBase . '/states/' . self::TEST_UUID));

        // Files
        $filesBase = $idBase . '/files';
        $this->assertReachable($this->client->get($filesBase));
        $this->assertReachable($this->client->post($filesBase, ['json' => [['filename' => 'test.pdf']]]));
        $this->assertReachable($this->client->delete($filesBase . '/' . self::TEST_UUID));

        // Positions
        $positionsBase = $idBase . '/positions';
        $this->assertReachable($this->client->get($positionsBase));
        $this->assertReachable($this->client->post($positionsBase, ['json' => ['quantity' => 1]]));
        $this->assertReachable($this->client->post($positionsBase . '/batch', ['json' => [['quantity' => 1]]]));
        $this->assertReachable($this->client->get($positionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($positionsBase . '/' . self::TEST_UUID, ['json' => ['quantity' => 2]]));
        $this->assertReachable($this->client->delete($positionsBase . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($positionsBase . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
    }

    // ==================== CUSTOM ENTITIES ====================

    /**
     * POST /entity/customentity
     */
    public function testCreateCustomEntity(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/customentity', [
            'json' => [
                'name' => 'Test CustomEntity',
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/customentity/{id}
     */
    public function testGetCustomEntityElements(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * POST /entity/customentity/{id}
     */
    public function testCreateCustomEntityElement(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Test CustomEntityElement',
                'code' => 'code1',
                'description' => 'description',
                'externalCode' => 'external1',
                'shared' => false,
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * PUT /entity/customentity/{id}
     */
    public function testUpdateCustomEntity(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated CustomEntity',
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/customentity/{id}
     */
    public function testDeleteCustomEntity(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * GET /entity/customentity/{id}/metadata
     */
    public function testGetCustomEntityMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID . '/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * GET /entity/customentity/{metadata_id}/{id}
     */
    public function testGetCustomEntityElementById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID . '/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * PUT /entity/customentity/{metadata_id}/{id}
     */
    public function testUpdateCustomEntityElementById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID . '/' . self::TEST_UUID, [
            'json' => [
                'name' => 'Updated CustomEntityElement',
                'code' => 'code2',
                'description' => 'Updated description',
                'externalCode' => 'external2',
                'shared' => true,
            ],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * DELETE /entity/customentity/{metadata_id}/{id}
     */
    public function testDeleteCustomEntityElementById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/customentity/' . self::TEST_UUID . '/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    // ==================== CASH IN / OUT ====================

    /**
     * Проверяет доступность endpoint'а получения списка приходных ордеров.
     * GET /entity/cashin
     */
    public function testListCashIns(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения приходного ордера по ID.
     * GET /entity/cashin/{id}
     */
    public function testGetCashInById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания приходного ордера.
     * POST /entity/cashin
     */
    public function testCreateCashIn(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashin', [
            'json' => [
                'organization' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                'agent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID, 'type' => 'counterparty', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления приходного ордера.
     * PUT /entity/cashin/{id}
     */
    public function testUpdateCashIn(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashin/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated CashIn'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления приходного ордера.
     * DELETE /entity/cashin/{id}
     */
    public function testDeleteCashIn(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashin/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового удаления приходных ордеров.
     * POST /entity/cashin/delete
     */
    public function testDeleteCashInsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashin/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/cashin/' . self::TEST_UUID, 'type' => 'cashin', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а массового создания и обновления приходных ордеров.
     * POST /entity/cashin/batch
     */
    public function testCreateCashInsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashin/batch', [
            'json' => [
                ['name' => 'CashIn Batch'],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения метаданных приходных ордеров.
     * GET /entity/cashin/metadata
     */
    public function testGetCashInMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения доп. полей.
     * GET /entity/cashin/metadata/attributes
     */
    public function testGetCashInMetadataAttributes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin/metadata/attributes');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а создания доп. поля.
     * POST /entity/cashin/metadata/attributes
     */
    public function testCreateCashInMetadataAttribute(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashin/metadata/attributes', [
            'json' => [['name' => 'atr1']],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного доп. поля.
     * GET /entity/cashin/metadata/attributes/{id}
     */
    public function testGetCashInMetadataAttributeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного доп. поля.
     * PUT /entity/cashin/metadata/attributes/{id}
     */
    public function testUpdateCashInMetadataAttributeById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashin/metadata/attributes/' . self::TEST_UUID, [
            'json' => ['name' => 'atr1'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного доп. поля.
     * DELETE /entity/cashin/metadata/attributes/{id}
     */
    public function testDeleteCashInMetadataAttributeById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashin/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного статуса документа.
     * GET /entity/cashin/metadata/states/{id}
     */
    public function testGetCashInMetadataStateById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashin/metadata/states/' . self::TEST_UUID);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного статуса документа.
     * PUT /entity/cashin/metadata/states/{id}
     */
    public function testUpdateCashInMetadataStateById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashin/metadata/states/' . self::TEST_UUID, [
            'json' => ['name' => 'state1'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного статуса документа.
     * DELETE /entity/cashin/metadata/states/{id}
     */
    public function testDeleteCashInMetadataStateById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashin/metadata/states/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения шаблона приходного ордера.
     * PUT /entity/cashin/new
     */
    public function testCashInTemplateNew(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashin/new', ['json' => new \stdClass()]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'ов Входящего платежа: CRUD, batch, metadata и template.
     */
    public function testPaymentInCrud(): void
    {
        $base = self::API_BASE_PATH . '/entity/paymentin';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']],]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));

        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => [['name' => 'X']]]));
    }

    /**
     * Проверяет доступность endpoint'ов Входящего платежа: CRUD, batch, metadata и template.
     */
    public function testPaymentOutCrud(): void
    {
        $base = self::API_BASE_PATH . '/entity/paymentout';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']],]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));

        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => [['name' => 'X']]]));
    }

    /**
     * Проверяет доступность endpoint'ов внесений денег: CRUD, batch, metadata и template.
     */
    public function testRetailDrawerCashInCrudMetadataTemplate(): void
    {
        $base = self::API_BASE_PATH . '/entity/retaildrawercashin';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']],]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));

        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => [['name' => 'X']]]));
    }

    /**
     * Проверяет доступность endpoint'ов оприходований: CRUD, batch, metadata, template, positions и files.
     */
    public function testEnterCrudMetadataTemplateFilesPositions(): void
    {
        $base = self::API_BASE_PATH . '/entity/enter';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' =>['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'X']]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new'));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' =>  ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'ов инвентаризаций: CRUD, batch, metadata, template, positions, files и recalc.
     */
    public function testInventoryCrudMetadataTemplateFilesPositionsRecalc(): void
    {
        $base = self::API_BASE_PATH . '/entity/inventory';
        $organization = [
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID,
                'type' => 'organization',
                'mediaType' => 'application/json',
            ],
        ];
        $store = [
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/store/' . self::TEST_UUID,
                'type' => 'store',
                'mediaType' => 'application/json',
            ],
        ];
        $assortment = [
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID,
                'type' => 'product',
                'mediaType' => 'application/json',
            ],
        ];

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X', 'organization' => $organization, 'store' => $store]]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X', 'organization' => $organization, 'store' => $store]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'inventory.txt', 'content' => 'SGVsbG8=']]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));
        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['name' => 'X', 'organization' => $organization, 'store' => $store]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));
        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new'));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions'));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions', ['json' => [['quantity' => 1, 'price' => 1000, 'assortment' => $assortment]]]));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, ['json' => ['quantity' => 1, 'price' => 1000, 'assortment' => $assortment]]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/positions/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/rpc/inventory/' . self::TEST_UUID . '/recalcCalculatedQuantity'));
    }

    /**
     * Проверяет доступность endpoint'ов Выплат денег: CRUD, batch, metadata, files и template.
     */
    public function testRetailDrawerCashOutCrud(): void
    {
        $base = self::API_BASE_PATH . '/entity/retaildrawercashout';

        $this->assertReachable($this->client->get($base));
        $this->assertReachable($this->client->post($base, ['json' => ['name' => 'X']]));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID));

        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files'));
        $this->assertReachable($this->client->get($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));
        $this->assertReachable($this->client->post($base . '/' . self::TEST_UUID . '/files', ['json' => [['filename' => 'X']],]));
        $this->assertReachable($this->client->delete($base . '/' . self::TEST_UUID . '/files/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/delete', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->post($base . '/batch', ['json' => [['meta' => ['href' => 'x']]]]));

        $this->assertReachable($this->client->get($base . '/metadata'));

        $this->assertReachable($this->client->get($base . '/metadata/attributes'));
        $this->assertReachable($this->client->post($base . '/metadata/attributes', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/attributes/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/attributes/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/attributes/' . self::TEST_UUID));

        $this->assertReachable($this->client->post($base . '/metadata/states', ['json' => [['name' => 'X']]]));
        $this->assertReachable($this->client->get($base . '/metadata/states/' . self::TEST_UUID));
        $this->assertReachable($this->client->put($base . '/metadata/states/' . self::TEST_UUID, ['json' => ['name' => 'X']]));
        $this->assertReachable($this->client->delete($base . '/metadata/states/' . self::TEST_UUID));

        $this->assertReachable($this->client->put($base . '/new', ['json' => [['name' => 'X']]]));
    }

    /**
     * Проверяет доступность endpoint'а получения списка расходных ордеров.
     * GET /entity/cashout
     */
    public function testListCashOuts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения расходного ордера по ID.
     * GET /entity/cashout/{id}
     */
    public function testGetCashOutById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания расходного ордера.
     * POST /entity/cashout
     */
    public function testCreateCashOut(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashout', [
            'json' => [
                'organization' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID, 'type' => 'organization', 'mediaType' => 'application/json']],
                'agent' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . self::TEST_UUID, 'type' => 'counterparty', 'mediaType' => 'application/json']],
                'expenseItem' => ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/expenseitem/' . self::TEST_UUID, 'type' => 'expenseitem', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления расходного ордера.
     * PUT /entity/cashout/{id}
     */
    public function testUpdateCashOut(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashout/' . self::TEST_UUID, [
            'json' => ['name' => 'Updated CashOut'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления расходного ордера.
     * DELETE /entity/cashout/{id}
     */
    public function testDeleteCashOut(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashout/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а массового удаления расходных ордеров.
     * POST /entity/cashout/delete
     */
    public function testDeleteCashOutsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashout/delete', [
            'json' => [
                ['meta' => ['href' => 'https://api.moysklad.ru/api/remap/1.2/entity/cashout/' . self::TEST_UUID, 'type' => 'cashout', 'mediaType' => 'application/json']],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а массового создания и обновления расходных ордеров.
     * POST /entity/cashout/batch
     */
    public function testCreateCashOutsBatch(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashout/batch', [
            'json' => [
                ['name' => 'CashOut Batch'],
            ],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения метаданных расходных ордеров.
     * GET /entity/cashout/metadata
     */
    public function testGetCashOutMetadata(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout/metadata');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения доп. полей.
     * GET /entity/cashout/metadata/attributes
     */
    public function testGetCashOutMetadataAttributes(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout/metadata/attributes');
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а создания доп. поля.
     * POST /entity/cashout/metadata/attributes
     */
    public function testCreateCashOutMetadataAttribute(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashout/metadata/attributes', [
            'json' => [['name' => 'atr1']],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного доп. поля.
     * GET /entity/cashout/metadata/attributes/{id}
     */
    public function testGetCashOutMetadataAttributeById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного доп. поля.
     * PUT /entity/cashout/metadata/attributes/{id}
     */
    public function testUpdateCashOutMetadataAttributeById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashout/metadata/attributes/' . self::TEST_UUID, [
            'json' => ['name' => 'atr1'],
        ]);
        $this->assertContains($response->getStatusCode(), self::BY_ID_ACCEPTABLE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного доп. поля.
     * DELETE /entity/cashout/metadata/attributes/{id}
     */
    public function testDeleteCashOutMetadataAttributeById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashout/metadata/attributes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а создания статуса документа.
     * POST /entity/cashout/metadata/states
     */
    public function testCreateCashOutMetadataState(): void
    {
        $response = $this->client->post(self::API_BASE_PATH . '/entity/cashout/metadata/states', [
            'json' => ['name' => 'state1', 'color' => 15106326, 'stateType' => 'Regular'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного статуса документа.
     * GET /entity/cashout/metadata/states/{id}
     */
    public function testGetCashOutMetadataStateById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/cashout/metadata/states/' . self::TEST_UUID);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного статуса документа.
     * PUT /entity/cashout/metadata/states/{id}
     */
    public function testUpdateCashOutMetadataStateById(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashout/metadata/states/' . self::TEST_UUID, [
            'json' => ['name' => 'state1'],
        ]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного статуса документа.
     * DELETE /entity/cashout/metadata/states/{id}
     */
    public function testDeleteCashOutMetadataStateById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/cashout/metadata/states/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения шаблона расходного ордера.
     * PUT /entity/cashout/new
     */
    public function testCashOutTemplateNew(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/cashout/new', ['json' => new \stdClass()]);
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }


    private function moveRequiredPayload(): array
    {
        return [
            'organization' => $this->entityMeta('organization'),
            'sourceStore' => $this->entityMeta('store'),
            'targetStore' => $this->entityMeta('store'),
        ];
    }

    private function movePositionPayload(): array
    {
        return [
            'quantity' => 1.0,
            'price' => 100.0,
            'assortment' => $this->entityMeta('product'),
        ];
    }

    private function moveMetaPayload(): array
    {
        return [[
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/move/' . self::TEST_UUID,
                'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/move/metadata',
                'type' => 'move',
                'mediaType' => 'application/json',
            ],
        ]];
    }

    private function movePositionMetaPayload(): array
    {
        return [[
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/move/' . self::TEST_UUID . '/positions/' . self::TEST_UUID,
                'type' => 'moveposition',
                'mediaType' => 'application/json',
            ],
        ]];
    }

    private function entityMeta(string $type): array
    {
        return [
            'meta' => [
                'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/' . $type . '/' . self::TEST_UUID,
                'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/' . $type . '/metadata',
                'type' => $type,
                'mediaType' => 'application/json',
            ],
        ];
    }

    /**
     * Проверяет доступность endpoint'а получения списка Списаний.
     * GET /entity/loss
     */
    public function testListLosses(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss'));
    }

    /**
     * Проверяет доступность endpoint'а создания Списания.
     * POST /entity/loss
     */
    public function testCreateLoss(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss', [
            'json' => $this->lossDocumentPayload(),
        ]));
    }

    /**
     * Проверяет доступность endpoint'а массового создания и обновления Списаний.
     * POST /entity/loss/batch
     */
    public function testCreateLossBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss/batch', [
            'json' => [$this->lossDocumentPayload()],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а получения Списания по ID.
     * GET /entity/loss/{id}
     */
    public function testGetLossById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а изменения Списания.
     * PUT /entity/loss/{id}
     */
    public function testUpdateLoss(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID, [
            'json' => ['name' => 'Loss Y'],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а удаления Списания.
     * DELETE /entity/loss/{id}
     */
    public function testDeleteLoss(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а массового удаления Списаний.
     * POST /entity/loss/delete
     */
    public function testDeleteLossBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss/delete', [
            'json' => [[
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/loss/' . self::TEST_UUID,
                    'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/loss/metadata',
                    'type' => 'loss',
                    'mediaType' => 'application/json',
                ],
            ]],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а получения метаданных Списаний.
     * GET /entity/loss/metadata
     */
    public function testGetLossMetadata(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/metadata'));
    }

    /**
     * Проверяет доступность endpoint'а получения списка доп. полей Списаний.
     * GET /entity/loss/metadata/attributes
     */
    public function testGetLossMetadataAttributes(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/metadata/attributes'));
    }

    /**
     * Проверяет доступность endpoint'а создания доп. поля Списания.
     * POST /entity/loss/metadata/attributes
     */
    public function testCreateLossMetadataAttribute(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss/metadata/attributes', [
            'json' => ['name' => 'loss-attribute'],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного доп. поля Списания.
     * GET /entity/loss/metadata/attributes/{id}
     */
    public function testGetLossMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/metadata/attributes/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного доп. поля Списания.
     * PUT /entity/loss/metadata/attributes/{id}
     */
    public function testUpdateLossMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/loss/metadata/attributes/' . self::TEST_UUID, [
            'json' => ['name' => 'loss-attribute'],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного доп. поля Списания.
     * DELETE /entity/loss/metadata/attributes/{id}
     */
    public function testDeleteLossMetadataAttributeById(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/loss/metadata/attributes/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а получения отдельного статуса Списания.
     * GET /entity/loss/metadata/states/{id}
     */
    public function testGetLossMetadataStateById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/metadata/states/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а обновления отдельного статуса Списания.
     * PUT /entity/loss/metadata/states/{id}
     */
    public function testUpdateLossMetadataStateById(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/loss/metadata/states/' . self::TEST_UUID, [
            'json' => ['name' => 'state1'],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а удаления отдельного статуса Списания.
     * DELETE /entity/loss/metadata/states/{id}
     */
    public function testDeleteLossMetadataStateById(): void
    {
        $response = $this->client->delete(self::API_BASE_PATH . '/entity/loss/metadata/states/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::DELETE_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения шаблона Списания.
     * PUT /entity/loss/new
     */
    public function testLossTemplateNew(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/loss/new', [
            'json' => new \stdClass(),
        ]));
    }

    /**
     * Проверяет доступность endpoint'а получения позиций Списания.
     * GET /entity/loss/{id}/positions
     */
    public function testGetLossPositions(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions'));
    }

    /**
     * Проверяет доступность endpoint'а создания позиций Списания.
     * POST /entity/loss/{id}/positions
     */
    public function testCreateLossPositions(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions', [
            'json' => $this->lossPositionPayload(),
        ]));
    }

    /**
     * Проверяет доступность endpoint'а получения позиции Списания по ID.
     * GET /entity/loss/{id}/positions/{positionId}
     */
    public function testGetLossPositionById(): void
    {
        $this->assertReachable($this->client->get(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а обновления позиции Списания.
     * PUT /entity/loss/{id}/positions/{positionId}
     */
    public function testUpdateLossPosition(): void
    {
        $this->assertReachable($this->client->put(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions/' . self::TEST_UUID, [
            'json' => ['quantity' => 2, 'reason' => 'Обновлено'],
        ]));
    }

    /**
     * Проверяет доступность endpoint'а удаления позиции Списания.
     * DELETE /entity/loss/{id}/positions/{positionId}
     */
    public function testDeleteLossPosition(): void
    {
        $this->assertReachable($this->client->delete(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions/' . self::TEST_UUID));
    }

    /**
     * Проверяет доступность endpoint'а массового удаления позиций Списания.
     * POST /entity/loss/{id}/positions/delete
     */
    public function testDeleteLossPositionsBatch(): void
    {
        $this->assertReachable($this->client->post(self::API_BASE_PATH . '/entity/loss/' . self::TEST_UUID . '/positions/delete', [
            'json' => [[
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/loss/' . self::TEST_UUID . '/positions/' . self::TEST_UUID,
                    'type' => 'lossposition',
                    'mediaType' => 'application/json',
                ],
            ]],
        ]));
    }

    private function lossDocumentPayload(): array
    {
        return [
            'organization' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/' . self::TEST_UUID,
                    'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/organization/metadata',
                    'type' => 'organization',
                    'mediaType' => 'application/json',
                ],
            ],
            'store' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/store/' . self::TEST_UUID,
                    'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/store/metadata',
                    'type' => 'store',
                    'mediaType' => 'application/json',
                ],
            ],
        ];
    }

    private function lossPositionPayload(): array
    {
        return [
            'quantity' => 1,
            'price' => 1000,
            'reason' => 'брак',
            'assortment' => [
                'meta' => [
                    'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/' . self::TEST_UUID,
                    'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/product/metadata',
                    'type' => 'product',
                    'mediaType' => 'application/json',
                ],
            ],
        ];
    }

    private function assertReachable(\Psr\Http\Message\ResponseInterface $response): void
    {
        $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
    }

    // ==================== METADATAS ====================

    /**
     * Проверяет доступность endpoint'а получения метаданных перечисленных сущностей.
     * GET /entity/{entityType}/metadata
     */
    public function testGetMetadata(): void
    {
        $template = '/entity/%s/metadata';
        $entities = [
            'counterparty',
            'employee',
            'product'
        ];
        foreach ($entities as $entity) {
            $path = str_replace('%s', $entity, $template);
            $response = $this->client->get(self::API_BASE_PATH . $path);
            $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
        }
    }

    /**
     * Проверяет доступность endpoint'а получения метаданных перечисленных сущностей с expand.
     * GET /entity/{entityType}/metadata?expand=attributes
     */
    public function testGetMetadataWithExpand(): void
    {

        $template = '/entity/%s/metadata?expand=attributes';
        $entities = [
            'counterparty',
            'employee',
            'product'
        ];
        foreach ($entities as $entity) {
            $path = str_replace('%s', $entity, $template);
            $response = $this->client->get(self::API_BASE_PATH . $path);
            $this->assertNotEquals(404, $response->getStatusCode(), '404 means endpoint path did not match; expected to reach the endpoint');
        }
    }
}
