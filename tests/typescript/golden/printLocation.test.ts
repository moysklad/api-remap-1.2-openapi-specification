/**
 * Контракт операций, полезные данные которых передаются в заголовке Location.
 * Проверяется на локальном HTTP-стабе: openapi-mock не отдаёт заголовки из спецификации.
 */

import assert from 'node:assert/strict';
import { afterEach, beforeEach, test } from 'node:test';

import type * as Sdk from '../../../clients/typescript/dist/esm/index.js';
import { LocationStubServer } from '../src/locationStubServer.js';
import { loadSdk } from '../src/sdk.js';

const ENTITY_ID = '12345678-1234-1234-1234-123456789012';

const sdk = (await loadSdk()) as unknown as typeof Sdk;

let stub: LocationStubServer;
let labelsApi: Sdk.LabelsApi;

beforeEach(async () => {
    stub = await LocationStubServer.start();
    labelsApi = new sdk.LabelsApi(new sdk.Configuration({ basePath: stub.basePath }));
});

afterEach(async () => {
    await stub.close();
});

function printParameters(): Sdk.PrintLabelsRequest {
    return {
        id: ENTITY_ID,
        type: sdk.PrintLabelsTypeEnum.Product,
        labelPrintRequest: sdk.LabelPrintRequestFromJSON({ count: 1 }),
    };
}

function printLabels(): Promise<Sdk.HeaderResponse> {
    return labelsApi.printLabels(printParameters());
}

test('202: сохраняются статус и Location ожидающей печати', async () => {
    const expected = stub.locationUrl('status');
    stub.enqueue(202, 'Location', expected);

    const result = await printLabels();

    assert.ok(result instanceof sdk.HeaderResponse);
    assert.equal(result.statusCode, 202);
    assert.equal(result.location, expected);
    assert.equal(stub.locationHits, 0);
});

test('303: результат возвращается без перехода по Location', async () => {
    const expected = stub.locationUrl('file.pdf');
    stub.enqueue(303, 'Location', expected);

    const result = await printLabels();

    assert.equal(result.statusCode, 303);
    assert.equal(result.location, expected);
    assert.equal(stub.locationHits, 0, 'переход по Location выполняться не должен');
});

test('заголовок location читается без учёта регистра', async () => {
    const expected = stub.locationUrl('file.pdf');
    stub.enqueue(303, 'location', expected);

    const result = await printLabels();

    assert.equal(result.location, expected);
    assert.equal(result.getHeader('LOCATION'), expected);
});

test('отсутствие Location — undefined', async () => {
    stub.enqueue(202);

    const result = await printLabels();

    assert.equal(result.statusCode, 202);
    assert.equal(result.location, undefined);
});

test('статус 400: сохраняется контракт ошибок', async () => {
    stub.enqueue(400, undefined, undefined, '{"errors":[{"error":"stub","code":1}]}');

    await assert.rejects(printLabels(), (error: unknown) => {
        assert.ok(error instanceof sdk.ResponseError);
        assert.equal(error.response.status, 400);
        return true;
    });
});

test('пользовательский initOverrides применяется вместе с отключением редиректа', async () => {
    const expected = stub.locationUrl('file.pdf');
    stub.enqueue(303, 'Location', expected);

    const result = await labelsApi.printLabels(printParameters(), async ({ init }) => ({
        headers: { ...init.headers, 'X-Test': 'override' },
    }));

    assert.equal(result.statusCode, 303);
    assert.equal(result.location, expected);
    assert.equal(stub.lastRequestHeaders['x-test'], 'override');
    assert.equal(stub.locationHits, 0);
});
