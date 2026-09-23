import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

/** Общие для всех SDK fixtures: tests/fixtures относительно корня репозитория. */
const FIXTURES_RELATIVE_PATH = join('tests', 'fixtures');

/**
 * Корень репозитория ищется вверх по дереву от каталога теста: тесты запускаются
 * и из tests/typescript (npm run test:golden), и из корня (make test-golden-typescript).
 */
export function getRepoRoot(): string {
    let current = import.meta.dirname;

    while (true) {
        if (existsSync(join(current, FIXTURES_RELATIVE_PATH))) {
            return current;
        }
        const parent = dirname(current);
        if (parent === current) {
            throw new Error(`Каталог ${FIXTURES_RELATIVE_PATH} не найден (поиск от ${import.meta.dirname})`);
        }
        current = parent;
    }
}

export function getFixturesPath(): string {
    return join(getRepoRoot(), FIXTURES_RELATIVE_PATH);
}

/** Имена всех fixture-файлов без расширения. */
export function listFixtureNames(): string[] {
    return readdirSync(getFixturesPath())
        .filter((name) => name.endsWith('.json'))
        .map((name) => name.slice(0, -'.json'.length))
        .sort();
}

export function loadFixture(fixtureName: string): Record<string, unknown> {
    const path = join(getFixturesPath(), `${fixtureName}.json`);
    if (!existsSync(path)) {
        throw new Error(`Fixture не найден: ${path}`);
    }

    let parsed: unknown;
    try {
        parsed = JSON.parse(readFileSync(path, 'utf8'));
    } catch (error) {
        throw new Error(`Не удалось разобрать JSON fixture '${fixtureName}': ${(error as Error).message}`);
    }

    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error(`Fixture '${fixtureName}' должен быть JSON-объектом`);
    }

    return parsed as Record<string, unknown>;
}
