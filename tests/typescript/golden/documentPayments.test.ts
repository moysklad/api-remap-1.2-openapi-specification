import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { getFromJson, getToJson, loadSdk } from '../src/sdk.js';
import { getRepoRoot } from '../src/fixtures.js';

const sdk = await loadSdk();
const cases: { document: string; base: string; variant: string; type: string; linkedSum?: boolean }[] = JSON.parse(
    readFileSync(`${getRepoRoot()}/tests/fixtures/polymorphic/document_payments.json`, 'utf8'),
);

for (const entry of cases) {
    // A reference and an expanded payment per variant; zero only on the representative pair.
    const expandedSum = entry.linkedSum === false ? undefined : 123.45;
    const scenarios: [boolean, number | undefined][] = entry.document === 'RetailShift'
        ? [[false, undefined], [true, expandedSum], [true, 0]] : [[false, undefined], [true, expandedSum]];
    for (const [expanded, linkedSum] of scenarios) {
        test(`${entry.variant}: expanded=${expanded}, linkedSum=${linkedSum}`, () => {
            const payment = {
                meta: { href: `https://api.moysklad.ru/api/remap/1.2/entity/${entry.type}/1`,
                    type: entry.type, mediaType: 'application/json' },
                ...(expanded ? { name: 'Expanded payment', sum: 456.75, paymentPurpose: 'Invoice settlement' } : {}),
                ...(linkedSum !== undefined ? { linkedSum } : {}),
            };
            const owner = getFromJson(sdk, entry.document)({ payments: [payment] });
            const serialized = getToJson(sdk, entry.document)(owner) as { payments: unknown[] };
            assert.deepEqual(JSON.parse(JSON.stringify(serialized.payments)), [payment]);
            assert.deepEqual(getFromJson(sdk, entry.base)(payment), getFromJson(sdk, entry.variant)(payment));
            assert.deepEqual(getToJson(sdk, entry.base)(getFromJson(sdk, entry.base)(payment)),
                getToJson(sdk, entry.variant)(getFromJson(sdk, entry.variant)(payment)));
        });
    }
}

// Bank account fields belong to bank payments only. These probes distinguish the
// cash and bank converters even though both accept the same meta-only reference.
for (const entry of cases) {
    test(`${entry.variant}: meta.type selects the bank or cash field set`, () => {
        const payload = {
            meta: { type: entry.type },
            organizationAccount: { meta: { type: 'account', href: 'https://example.test/account/1' } },
        };
        const owner = getFromJson(sdk, entry.document)({ payments: [payload] }) as {
            payments: { organizationAccount?: unknown }[];
        };
        const actual = owner.payments[0]!;
        assert.equal(actual.organizationAccount !== undefined, entry.type.startsWith('payment'));
        const serialized = getToJson(sdk, entry.document)(owner) as {
            payments: { organizationAccount?: unknown }[];
        };
        assert.equal(serialized.payments[0]!.organizationAccount !== undefined, entry.type.startsWith('payment'));
    });
}
