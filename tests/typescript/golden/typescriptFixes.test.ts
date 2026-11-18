import assert from 'node:assert/strict';
import test from 'node:test';

import type * as Sdk from '../../../clients/typescript/dist/esm/index.js';
import { loadSdk } from '../src/sdk.js';

const sdk = (await loadSdk()) as unknown as typeof Sdk;

function client(status: number, payload: unknown, headers?: Sdk.HTTPHeaders) {
    let captured: RequestInit | undefined;
    const configuration = new sdk.Configuration({
        headers,
        fetchApi: async (_url, init) => {
            captured = init;
            return new Response(status === 204 ? null : JSON.stringify(payload), {
                status,
                headers: { 'Content-Type': 'application/json' },
            });
        },
    });
    return { configuration, headers: () => new Headers(captured?.headers), body: () => captured?.body };
}

test('заголовки по умолчанию берутся из спецификации', async () => {
    const stub = client(200, { rows: [] });
    await new sdk.ProductsApi(stub.configuration).getProducts();
    assert.equal(stub.headers().get('accept'), 'application/json;charset=utf-8');
    assert.equal(stub.headers().get('accept-encoding'), 'gzip');
});

test('Accept из конфигурации сохраняется; параметр метода заменяет его без склейки', async () => {
    const stub = client(200, { rows: [] }, { accept: 'application/custom', 'accept-encoding': 'identity' });
    const api = new sdk.ProductsApi(stub.configuration);
    await api.getProducts();
    assert.equal(stub.headers().get('accept'), 'application/custom');
    assert.equal(stub.headers().get('accept-encoding'), 'identity');
    await api.getProducts({ accept: 'application/json' });
    assert.equal(stub.headers().get('accept'), 'application/json');
});

test('POST без тела отправляет Content-Type', async () => {
    const stub = client(204, null);
    await new sdk.SuppliesApi(stub.configuration).moveSupplyToTrash({ id: 'test-id' });
    assert.equal(stub.headers().get('content-type'), 'application/json');
});

for (const headerName of ['content-type', 'Content-Type', 'CONTENT-TYPE']) {
    test(`POST без тела сохраняет ${headerName} без склейки, параметр метода имеет приоритет`, async () => {
        const stub = client(204, null, { [headerName]: 'application/custom' });
        const api = new sdk.SuppliesApi(stub.configuration);
        await api.moveSupplyToTrash({ id: 'test-id' });
        assert.equal(stub.headers().get('content-type'), 'application/custom');
        await api.moveSupplyToTrash({ id: 'test-id', contentType: 'application/json' });
        assert.equal(stub.headers().get('content-type'), 'application/json');
    });

    test(`нормализация ${headerName} сохраняет JSON-сериализацию тела`, async () => {
        const stub = client(200, [], { [headerName]: 'application/json' });
        await new sdk.ProductsApi(stub.configuration).createProductsBatch({ product: [{ name: 'test' }] });
        assert.equal(stub.headers().get('content-type'), 'application/json');
        assert.equal(stub.body(), '[{"name":"test"}]');
    });
}

test('несколько вариантов регистра Accept не склеиваются', async () => {
    const stub = client(200, { rows: [] }, { Accept: 'application/first', accept: 'application/second' });
    await new sdk.ProductsApi(stub.configuration).getProducts({ accept: 'application/json' });
    assert.equal(stub.headers().get('accept'), 'application/json');
});

const wire = (value: unknown): unknown => JSON.parse(JSON.stringify(value));

const errors = { errors: [{ error: 'partial failure', code: 1000 }] };

test('batch fallback и oneOf сохраняют контейнер Errors', () => {
    assert.deepEqual(wire((sdk.BatchResponseEntityFromJSON(errors) as Sdk.Errors).errors), errors.errors);
    assert.deepEqual(wire(sdk.BatchResponseEntityToJSON(sdk.BatchResponseEntityFromJSON(errors))), errors);
    const row = sdk.DeleteRowResultFromJSON(errors);
    assert.deepEqual(wire(sdk.DeleteRowResultToJSON(row)), errors);
});

for (const status of [404, 412]) {
    test(`смешанный batch-ответ ${status} сохраняет успех и ошибку`, async () => {
        const success = { info: 'deleted' };
        const stub = client(status, [success, errors]);
        const result = await new sdk.ProductsApi(stub.configuration).deleteProductsBatch({ product: [] });
        assert.deepEqual(wire(result.map(sdk.DeleteRowResultToJSON)), [success, errors]);
    });

    const invalidPayloads = [
        [], [null], [42], [[]], [{ unexpected: 'not a batch row' }],
        [{ errors: 'invalid' }], [{ errors: [] }], [{ errors: [null] }],
        [{ errors: [{ code: 1000 }] }],
        [{ errors: [{ error: 'failure' }, null] }],
        [{ meta: { type: 'product' } }, { errors: 'invalid' }],
    ];
    for (const [index, payload] of invalidPayloads.entries()) {
        for (const operation of ['delete', 'create'] as const) {
            test(`некорректный batch ${operation} ${status}, случай ${index}: исходный ResponseError сохраняется`, async () => {
                const stub = client(status, payload);
                const api = new sdk.ProductsApi(stub.configuration);
                await assert.rejects(
                    operation === 'delete'
                        ? api.deleteProductsBatch({ product: [] })
                        : api.createProductsBatch({ product: [] }),
                    asyncError => {
                        assert.ok(asyncError instanceof sdk.ResponseError);
                        assert.equal(asyncError.response.status, status);
                        assert.equal(asyncError.response.bodyUsed, false);
                        return true;
                    },
                );
            });
        }
    }
}

test('обычная ошибка 412 остаётся исключением', async () => {
    const stub = client(412, errors);
    await assert.rejects(
        new sdk.ProductsApi(stub.configuration).deleteProductsBatch({ product: [] }),
        sdk.ResponseError,
    );
});

test('ассортимент скидки выбирает Variant по meta.type и сохраняет characteristics', () => {
    const input = {
        meta: { type: 'variant', href: 'https://example.test/entity/variant/id' },
        characteristics: [{ name: 'size', value: 'XL' }],
    };
    const variant = sdk.DiscountAssortmentItemFromJSON(input) as Sdk.Variant;
    assert.deepEqual(wire(variant.characteristics), input.characteristics);
    assert.deepEqual(wire(sdk.DiscountAssortmentItemToJSON(variant).characteristics), input.characteristics);
});

for (const status of [403, 500]) {
    test(`batch-массив при статусе ${status} остаётся исключением`, async () => {
        const stub = client(status, [{ info: 'deleted' }, errors]);
        await assert.rejects(
            new sdk.ProductsApi(stub.configuration).deleteProductsBatch({ product: [] }),
            sdk.ResponseError,
        );
    });
}

test('массив обычной операции при 412 остаётся исключением', async () => {
    const stub = client(412, [errors]);
    await assert.rejects(
        new sdk.ProductsApi(stub.configuration).addProductFiles({ id: 'test-id', fileUpload: [] }),
        sdk.ResponseError,
    );
});

test('batch создания при 412 сохраняет сущность и Errors', async () => {
    const product = { meta: { type: 'product' }, name: 'test product' };
    const stub = client(412, [product, errors]);
    const result = await new sdk.ProductsApi(stub.configuration).createProductsBatch({ product: [] });
    assert.deepEqual(wire(result.map(sdk.BatchResponseEntityToJSON)), [product, errors]);
});

for (const type of ['product', 'service']) {
    test(`ассортимент скидки выбирает ${type} по meta.type`, () => {
        const input = {
            meta: { type, href: `https://example.test/entity/${type}/id` },
            name: 'test',
            ...(type === 'product' ? { article: 'test article' } : {}),
        };
        const item = sdk.DiscountAssortmentItemFromJSON(input);
        assert.deepEqual(wire(sdk.DiscountAssortmentItemToJSON(item)), input);
    });
}

for (const [entity, type] of [
    ['invoicein', 'invoiceposition'],
    ['invoiceout', 'invoiceposition'],
    ['salesreturn', 'salesreturnposition'],
    ['retailsalesreturn', 'salesreturnposition'],
]) {
    test(`batch сохраняет поля позиции ${entity}`, () => {
        const payload = {
            meta: { type, href: `https://example.test/entity/${entity}/document/positions/position` },
            quantity: 2,
            price: 100,
        };
        const position = sdk.BatchResponseEntityFromJSON(payload);
        assert.deepEqual(wire(sdk.BatchResponseEntityToJSON(position)), payload);
    });
}

test('minimumBalance отправляется числом и null для очистки', () => {
    for (const minimumBalance of [5, null]) {
        assert.deepEqual(wire(sdk.ProductToJSON({ minimumBalance })), { minimumBalance });
    }
});
