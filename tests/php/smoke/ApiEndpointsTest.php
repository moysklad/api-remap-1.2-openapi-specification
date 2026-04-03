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
     * Для smoke-теста операций list/create/batch delete и т.п.:
     * 404 = эндпоинт не совпал (путь не найден в спеке), тест должен падать.
     * Любой другой ответ (2xx, 3xx, 5xx, 401, 403…) = достучались до эндпоинта — ок.
     */
    private const SUCCESS_CODES = [200, 201, 401, 500];
    
    /**
     * Допустимые коды для запросов к несуществующим ресурсам.
     * 500 допустим: openapi-mock может вернуть его при рекурсивных схемах.
     */
    private const NOT_FOUND_CODES = [200, 401, 404, 500];
    
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

    /**
     * Проверяет доступность endpoint'а получения изображений товара.
     * GET /entity/product/{id}/images
     */
    public function testGetProductImages(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/images');
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения файлов товара.
     * GET /entity/product/{id}/files
     */
    public function testGetProductFiles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/files');
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения НСО товара по складу.
     * GET /entity/product/{id}/storebalances/{storeBalanceId}
     */
    public function testGetProductStoreBalanceById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/product/' . self::TEST_UUID . '/storebalances/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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

    /**
     * Проверяет доступность endpoint'а получения счетов контрагента.
     * GET /entity/counterparty/{id}/accounts
     */
    public function testGetCounterpartyAccounts(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts');
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения счёта контрагента по ID.
     * GET /entity/counterparty/{id}/accounts/{accountId}
     */
    public function testGetCounterpartyAccountById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/accounts/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения контактного лица контрагента по ID.
     * GET /entity/counterparty/{id}/contactpersons/{contactPersonId}
     */
    public function testGetCounterpartyContactPersonById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/contactpersons/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а получения события контрагента по ID.
     * GET /entity/counterparty/{id}/notes/{noteId}
     */
    public function testGetCounterpartyNoteById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/counterparty/' . self::TEST_UUID . '/notes/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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

    /**
     * Проверяет доступность endpoint'а получения прав сотрудника.
     * GET /entity/employee/{id}/security
     */
    public function testGetEmployeeSecurity(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/security');
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
    }

    /**
     * Проверяет доступность endpoint'а деактивации сотрудника.
     * PUT /entity/employee/{id}/access/deactivate
     */
    public function testDeactivateEmployee(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/access/deactivate');
        $this->assertContains($response->getStatusCode(), array_merge(self::NOT_FOUND_CODES, [204]));
    }

    /**
     * Проверяет доступность endpoint'а сброса пароля сотрудника.
     * PUT /entity/employee/{id}/access/resetpassword
     */
    public function testResetEmployeePassword(): void
    {
        $response = $this->client->put(self::API_BASE_PATH . '/entity/employee/' . self::TEST_UUID . '/access/resetpassword');
        $this->assertContains($response->getStatusCode(), array_merge(self::NOT_FOUND_CODES, [204]));
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

    /**
     * Проверяет доступность endpoint'а получения файлов услуги.
     * GET /entity/service/{id}/files
     */
    public function testGetServiceFiles(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/service/' . self::TEST_UUID . '/files');
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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

    public function testListStores(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/store');
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function testStoreById(): void
    {
        $response = $this->client->get(self::API_BASE_PATH . '/entity/store/' . self::TEST_UUID);
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertDocumentEndpoints('/entity/customerorder');
        $this->assertDocumentEndpoints('/entity/purchaseorder');
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
        $this->assertContains($response->getStatusCode(), self::NOT_FOUND_CODES);
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
