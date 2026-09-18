import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

import { getRepoRoot } from './fixtures.js';

export type SdkModule = Record<string, unknown>;
export type FromJsonFn = (json: unknown) => unknown;
export type ToJsonFn = (value: unknown) => unknown;

const SDK_RELATIVE_PATH = join('clients', 'typescript');

/** Тесты работают с собранным пакетом (ESM-сборка), а не с исходниками генератора. */
const SDK_BUILD_RELATIVE_PATH = join('dist', 'esm');

const BUILD_HINT = 'Запустите: make generate-typescript && make build-typescript';

export function getSdkPackageRoot(): string {
    return join(getRepoRoot(), SDK_RELATIVE_PATH);
}

export function getSdkBuildPath(): string {
    return join(getSdkPackageRoot(), SDK_BUILD_RELATIVE_PATH);
}

export async function loadSdk(): Promise<SdkModule> {
    const entryPoint = join(getSdkBuildPath(), 'index.js');
    if (!existsSync(entryPoint)) {
        throw new Error(`Сборка TypeScript SDK не найдена: ${entryPoint}. ${BUILD_HINT}`);
    }

    return (await import(pathToFileURL(entryPoint).href)) as SdkModule;
}

function getExportedFunction(sdk: SdkModule, exportName: string): (value: unknown) => unknown {
    const exported = sdk[exportName];
    if (typeof exported !== 'function') {
        throw new Error(`SDK не экспортирует функцию ${exportName}. ${BUILD_HINT}`);
    }

    return exported as (value: unknown) => unknown;
}

export function hasModel(sdk: SdkModule, modelName: string): boolean {
    return typeof sdk[`${modelName}FromJSON`] === 'function' && typeof sdk[`${modelName}ToJSON`] === 'function';
}

export function getFromJson(sdk: SdkModule, modelName: string): FromJsonFn {
    return getExportedFunction(sdk, `${modelName}FromJSON`);
}

export function getToJson(sdk: SdkModule, modelName: string): ToJsonFn {
    return getExportedFunction(sdk, `${modelName}ToJSON`);
}

/**
 * Поля, которые модель сознательно не сериализует.
 * typescript-fetch исключает readOnly-поля из вывода `<Model>ToJSON` и объявляет их
 * в сигнатуре `<Model>ToJSONTyped(value?: Omit<Model, 'id'|'accountId'|...>)`.
 * Список читается из декларации собранного пакета, поэтому не требует ручной поддержки:
 * любое другое потерянное при сериализации поле — ошибка теста.
 */
export function getFieldsOmittedOnSerialization(modelName: string): ReadonlySet<string> {
    if (!/^[A-Za-z0-9_]+$/.test(modelName)) {
        throw new Error(`Недопустимое имя модели: ${modelName}`);
    }

    return getModelFieldsOmittedOnSerialization(modelName, new Set());
}

function getModelFieldsOmittedOnSerialization(modelName: string, visitedModels: Set<string>): Set<string> {
    if (visitedModels.has(modelName)) {
        return new Set();
    }
    visitedModels.add(modelName);

    const declarationPath = join(getSdkBuildPath(), 'models', `${modelName}.d.ts`);
    if (!existsSync(declarationPath)) {
        throw new Error(`Декларация модели не найдена: ${declarationPath}. ${BUILD_HINT}`);
    }

    const declaration = readFileSync(declarationPath, 'utf8');
    if (!new RegExp(`function ${modelName}ToJSONTyped\\(`).test(declaration)) {
        throw new Error(`В ${declarationPath} не найдена сигнатура ${modelName}ToJSONTyped`);
    }

    const fields = parseOmittedFields(declaration, modelName);
    const polymorphicParent = declaration.match(
        new RegExp(`export type ${modelName} = [^;]+ & ([A-Za-z0-9_]+)\\.([A-Za-z0-9_]+);`),
    );
    if (polymorphicParent === null) {
        return fields;
    }

    const [, importAlias, parentModel] = polymorphicParent;
    const parentImport = declaration.match(
        new RegExp(`import \\* as ${importAlias} from './([^']+)\\.js';`),
    );
    if (parentImport === null || parentModel === undefined) {
        throw new Error(`В ${declarationPath} не найден импорт полиморфного родителя ${parentModel}`);
    }

    for (const field of getModelFieldsOmittedOnSerialization(parentModel, visitedModels)) {
        fields.add(field);
    }
    return fields;
}

let allOmittedFields: ReadonlySet<string> | undefined;

/**
 * Объединение readOnly-полей всех моделей пакета.
 * Нужно для вложенных объектов: во время выполнения неизвестно, какой моделью описано
 * поле вложенного объекта, поэтому его пропуск допускается, только если такое поле
 * объявлено readOnly хотя бы в одной модели SDK.
 */
export function getAllFieldsOmittedOnSerialization(): ReadonlySet<string> {
    if (allOmittedFields !== undefined) {
        return allOmittedFields;
    }

    const modelsPath = join(getSdkBuildPath(), 'models');
    if (!existsSync(modelsPath)) {
        throw new Error(`Каталог моделей не найден: ${modelsPath}. ${BUILD_HINT}`);
    }

    const fields = new Set<string>();
    for (const fileName of readdirSync(modelsPath).filter((name) => name.endsWith('.d.ts'))) {
        for (const field of parseOmittedFields(readFileSync(join(modelsPath, fileName), 'utf8'))) {
            fields.add(field);
        }
    }
    allOmittedFields = fields;

    return fields;
}

function parseOmittedFields(declaration: string, modelName = '[A-Za-z0-9_]+'): Set<string> {
    const signatures = declaration.matchAll(
        new RegExp(`function ${modelName}ToJSONTyped\\(value\\?: Omit<[^,]+,([^>]*)>`, 'g'),
    );

    const fields = new Set<string>();
    for (const signature of signatures) {
        for (const field of (signature[1] ?? '').matchAll(/'([^']+)'/g)) {
            fields.add(field[1] as string);
        }
    }

    return fields;
}
