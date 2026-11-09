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
    return { configuration, headers: () => new Headers(captured?.headers) };
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
    await api.getProducts({ accept: 'application/explicit' });
    assert.equal(stub.headers().get('accept'), 'application/explicit');
});

test('POST без тела отправляет Content-Type', async () => {
    const stub = client(204, null);
    await new sdk.SuppliesApi(stub.configuration).moveSupplyToTrash({ id: 'test-id' });
    assert.equal(stub.headers().get('content-type'), 'application/json');
});

const wire = (value: unknown): unknown => JSON.parse(JSON.stringify(value));

const errors = { errors: [{ error: 'partial failure', code: 1000 }] };

test('batch fallback и oneOf сохраняют контейнер Errors', () => {
    assert.deepEqual(wire((sdk.BatchResponseEntityFromJSON(errors) as Sdk.Errors).errors), errors.errors);
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
