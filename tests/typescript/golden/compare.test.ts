/**
 * Тесты правил сравнения, на которых держатся golden-тесты: roundtrip-проверка обязана
 * замечать искажение значений, лишние и потерянные поля.
 */

import assert from 'node:assert/strict';
import test from 'node:test';

import { diffRoundtrip, normalizeForComparison } from '../src/compare.js';

function compare(fixture: unknown, serialized: unknown) {
    return diffRoundtrip(normalizeForComparison(fixture), normalizeForComparison(serialized));
}

test('одинаковые структуры расхождений не дают', () => {
    const { valueDiffs, missingFields } = compare(
        { name: 'Товар', packs: [{ quantity: 1 }], meta: { type: 'product' } },
        { name: 'Товар', packs: [{ quantity: 1 }], meta: { type: 'product' } },
    );

    assert.deepEqual(valueDiffs, []);
    assert.deepEqual(missingFields, []);
});

test('изменённое значение — расхождение', () => {
    const { valueDiffs } = compare({ name: 'Товар' }, { name: 'Другой товар' });

    assert.deepEqual(valueDiffs, [{ path: 'name', expected: 'Товар', actual: 'Другой товар' }]);
});

test('изменённое значение во вложенном элементе массива — расхождение', () => {
    const { valueDiffs } = compare({ packs: [{ quantity: 1 }] }, { packs: [{ quantity: 2 }] });

    assert.deepEqual(valueDiffs, [{ path: 'packs[0].quantity', expected: 1, actual: 2 }]);
});

test('другая длина массива — расхождение', () => {
    const { valueDiffs } = compare({ packs: [{ id: 'a' }, { id: 'b' }] }, { packs: [{ id: 'a' }] });

    assert.deepEqual(valueDiffs, [{ path: 'packs.length', expected: 2, actual: 1 }]);
});

test('лишний ключ в выводе SDK — расхождение', () => {
    const { valueDiffs } = compare({ name: 'Товар' }, { name: 'Товар', vat: 20 });

    assert.deepEqual(valueDiffs, [{ path: 'vat', expected: undefined, actual: 20 }]);
});

test('потерянные поля отделены от искажений и размечены по уровню вложенности', () => {
    const { valueDiffs, missingFields } = compare(
        { name: 'Товар', owner: { id: '1', accountId: '2' } },
        { owner: { id: '1' } },
    );

    assert.deepEqual(valueDiffs, []);
    assert.deepEqual(missingFields, [
        { path: 'name', key: 'name', depth: 0 },
        { path: 'owner.accountId', key: 'accountId', depth: 1 },
    ]);
});

test('null, undefined и отсутствие ключа равнозначны', () => {
    const { valueDiffs, missingFields } = compare(
        { name: 'Товар', article: null, description: undefined },
        { name: 'Товар', code: null },
    );

    assert.deepEqual(valueDiffs, []);
    assert.deepEqual(missingFields, []);
});
