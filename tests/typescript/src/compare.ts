/**
 * Нормализация и сравнение JSON-структур для golden-тестов.
 * Правила совпадают с PHP (tests/php/golden/SerializationTest.php)
 * и Java (tests/java/assertions/.../golden/SerializationTest.java):
 * отсутствие ключа эквивалентно null, ключи сортируются, игнорируемые поля удаляются.
 */

export interface ValueDiff {
    path: string;
    expected: unknown;
    actual: unknown;
}

/** Поле fixture, которого нет в результате сериализации. */
export interface MissingField {
    path: string;
    key: string;
    /** 0 — поле верхнего уровня модели, больше 0 — поле вложенного объекта. */
    depth: number;
}

export interface RoundtripDiff {
    valueDiffs: ValueDiff[];
    missingFields: MissingField[];
}

/**
 * Поля, которые не участвуют в сравнении.
 * `extra_field` есть только в fixture entity_with_extra_field: он проверяет, что SDK
 * не падает на неизвестном поле, а не то, что поле сохраняется (генератор его отбрасывает).
 */
export const IGNORED_FIELDS: ReadonlySet<string> = new Set(['extra_field']);

/** Максимум расхождений в сообщении об ошибке: полный diff по большой fixture нечитаем. */
const MAX_REPORTED_DIFFS = 20;

const MAX_REPORTED_VALUE_LENGTH = 200;

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Приводит структуру к сравнимому виду: удаляет игнорируемые поля, сортирует ключи
 * и отбрасывает null/undefined (в API и в моделях SDK отсутствие значения
 * и null равнозначны, генератор преобразует одно в другое).
 */
export function normalizeForComparison(value: unknown): unknown {
    if (Array.isArray(value)) {
        return value.map((item) => normalizeForComparison(item));
    }

    if (!isPlainObject(value)) {
        return value;
    }

    const normalized: Record<string, unknown> = {};
    for (const key of Object.keys(value).sort()) {
        const item = value[key];
        if (IGNORED_FIELDS.has(key) || item === undefined || item === null) {
            continue;
        }
        normalized[key] = normalizeForComparison(item);
    }

    return normalized;
}

function joinPath(path: string, key: string): string {
    return path === '' ? key : `${path}.${key}`;
}

/**
 * Сравнивает fixture с результатом roundtrip `<Model>ToJSON(<Model>FromJSON(fixture))`.
 * Различает два вида расхождений:
 * - `valueDiffs` — значение искажено или в выводе появился ключ, которого нет в fixture;
 * - `missingFields` — поле fixture отсутствует в выводе (допустимо только для readOnly,
 *   которые генератор сознательно не сериализует; проверяется вызывающей стороной).
 *
 * Оба аргумента должны быть предварительно нормализованы `normalizeForComparison`.
 */
export function diffRoundtrip(expected: unknown, actual: unknown, path = '', depth = 0): RoundtripDiff {
    const result: RoundtripDiff = { valueDiffs: [], missingFields: [] };

    if (isPlainObject(expected) && isPlainObject(actual)) {
        for (const key of new Set([...Object.keys(expected), ...Object.keys(actual)])) {
            const keyPath = joinPath(path, key);
            if (!Object.hasOwn(actual, key)) {
                result.missingFields.push({ path: keyPath, key, depth });
                continue;
            }
            merge(result, diffRoundtrip(expected[key], actual[key], keyPath, depth + 1));
        }

        return result;
    }

    if (Array.isArray(expected) && Array.isArray(actual)) {
        if (expected.length !== actual.length) {
            result.valueDiffs.push({ path: `${path}.length`, expected: expected.length, actual: actual.length });

            return result;
        }
        for (let index = 0; index < expected.length; index += 1) {
            merge(result, diffRoundtrip(expected[index], actual[index], `${path}[${index}]`, depth));
        }

        return result;
    }

    if (expected !== actual) {
        result.valueDiffs.push({ path, expected, actual });
    }

    return result;
}

function merge(target: RoundtripDiff, source: RoundtripDiff): void {
    target.valueDiffs.push(...source.valueDiffs);
    target.missingFields.push(...source.missingFields);
}

function truncate(value: unknown): unknown {
    const serialized = JSON.stringify(value) ?? String(value);
    if (serialized.length <= MAX_REPORTED_VALUE_LENGTH) {
        return value;
    }

    return `${serialized.slice(0, MAX_REPORTED_VALUE_LENGTH)}... (обрезано)`;
}

export function formatValueDiffs(diffs: readonly ValueDiff[]): string {
    const reported = diffs.slice(0, MAX_REPORTED_DIFFS).map((diff) => ({
        path: diff.path,
        expected: truncate(diff.expected),
        actual: truncate(diff.actual),
    }));
    const tail = diffs.length > reported.length ? `\n... и ещё ${diffs.length - reported.length}` : '';

    return `${JSON.stringify(reported, null, 2)}${tail}`;
}
