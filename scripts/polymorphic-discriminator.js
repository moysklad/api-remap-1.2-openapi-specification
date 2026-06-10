#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const {execFileSync} = require('child_process');

const REPO_ROOT = path.resolve(__dirname, '..');
const SRC_OPENAPI = path.join(REPO_ROOT, 'src/openapi.yaml');
const BUNDLED_OPENAPI = path.join(REPO_ROOT, 'dist/openapi.json');
const CODEGEN_OPENAPI = path.join(REPO_ROOT, 'dist/openapi-codegen.json');

const COMPONENTS_PREFIX = '#/components/schemas/';
const DOT_PATH_PATTERN = /^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)+$/;

function loadJson(filePath) {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function collectCanonicalSchemaNames(openapiYamlPath) {
    const lines = fs.readFileSync(openapiYamlPath, 'utf8').split('\n');
    const names = new Set();
    let inSchemas = false;

    for (const line of lines) {
        if (/^  schemas:\s*$/.test(line)) {
            inSchemas = true;
            continue;
        }

        if (!inSchemas) {
            continue;
        }

        if (/^  [A-Za-z]/.test(line) && !line.startsWith('    ')) {
            break;
        }

        const match = line.match(/^    ([A-Za-z][A-Za-z0-9_]*):\s*$/);
        if (match) {
            names.add(match[1]);
        }
    }

    return names;
}

function buildCanonicalNameIndex(canonicalNames) {
    const index = new Map();
    for (const name of canonicalNames) {
        index.set(name.toLowerCase(), name);
    }
    return index;
}

function toCanonicalName(name, canonicalIndex) {
    if (!name) {
        return null;
    }
    return canonicalIndex.get(name.toLowerCase()) || name;
}

function refToName(ref) {
    if (typeof ref !== 'string') {
        return null;
    }

    const componentsIndex = ref.indexOf(COMPONENTS_PREFIX);
    if (componentsIndex === -1) {
        return null;
    }

    return ref.slice(componentsIndex + COMPONENTS_PREFIX.length);
}

function resolveMappingRef(entry) {
    if (typeof entry === 'string') {
        const name = refToName(entry);
        if (!name) {
            return null;
        }
        return name;
    }

    if (entry && typeof entry === 'object' && typeof entry.$ref === 'string') {
        const name = refToName(entry.$ref);
        if (!name) {
            return null;
        }
        return name;
    }

    return null;
}

function resolveParentRef(parent) {
    if (!parent || typeof parent !== 'object' || typeof parent.$ref !== 'string') {
        return null;
    }

    return refToName(parent.$ref);
}

function resolveBundledParentName(parent, polymorphicParentsByConfig, canonicalIndex) {
    const direct = toCanonicalName(resolveParentRef(parent), canonicalIndex);
    if (direct) {
        return direct;
    }

    if (parent?.['x-polymorphic-discriminator']) {
        const key = JSON.stringify(parent['x-polymorphic-discriminator']);
        return polymorphicParentsByConfig.get(key) || null;
    }

    return null;
}

function collectExternalSchemaRefs(openapiYamlPath) {
    const repoRoot = path.dirname(openapiYamlPath);
    const lines = fs.readFileSync(openapiYamlPath, 'utf8').split('\n');
    const refs = new Map();
    let inSchemas = false;
    let currentKey = null;

    for (const line of lines) {
        if (/^  schemas:\s*$/.test(line)) {
            inSchemas = true;
            continue;
        }

        if (!inSchemas) {
            continue;
        }

        if (/^  [A-Za-z]/.test(line) && !line.startsWith('    ')) {
            break;
        }

        const keyMatch = line.match(/^    ([A-Za-z][A-Za-z0-9_]*):\s*$/);
        if (keyMatch) {
            currentKey = keyMatch[1];
            continue;
        }

        const refMatch = line.match(/^      \$ref:\s*'\.\/([^']+)'/);
        if (currentKey && refMatch) {
            refs.set(currentKey, path.resolve(repoRoot, refMatch[1]));
            currentKey = null;
            continue;
        }

        if (currentKey && /^      [A-Za-z_]/.test(line)) {
            currentKey = null;
        }
    }

    return refs;
}

function withoutFullLineComments(content) {
    return content
        .split('\n')
        .filter((line) => !/^\s*#/.test(line))
        .join('\n');
}

function validateSourceStrictFormat(srcOpenapiPath, report) {
    const content = fs.readFileSync(srcOpenapiPath, 'utf8');
    const refs = collectExternalSchemaRefs(srcOpenapiPath);

    for (const [schemaName, filePath] of refs) {
        const activeContent = withoutFullLineComments(fs.readFileSync(filePath, 'utf8'));
        if (!activeContent.includes('x-polymorphic-parent')) {
            continue;
        }

        const parentRefMatch = activeContent.match(
            /x-polymorphic-parent:\s*\n\s+\$ref:\s*['"]([^'"]+)['"]/
        );
        if (!parentRefMatch) {
            report(
                schemaName,
                'некорректный `x-polymorphic-parent`: ожидается объект `{ $ref: \'#/components/schemas/<Имя>\' }`'
            );
        }
    }

    const discriminatorBlocks = content.match(
        /x-polymorphic-discriminator:\s*\n([\s\S]*?)(?=\n {6}[a-zA-Z_]|\n {4}[A-Z][a-zA-Z]+:|\n {2}[a-z]+:)/g
    );

    if (!discriminatorBlocks) {
        return;
    }

    for (const block of discriminatorBlocks) {
        const schemaMatch = content.slice(0, content.indexOf(block)).match(/    ([A-Za-z][A-Za-z0-9_]*):\s*$/m);
        const schemaName = schemaMatch?.[1] || 'unknown';

        if (/- value:/.test(block)) {
            report(
                schemaName,
                '`mappings` должен быть объектом, а не массивом с полями `value`/`model`'
            );
        }

        if (/\bmodel:/.test(block)) {
            report(schemaName, 'в `mappings` нельзя использовать устаревший тег `model`');
        }

        const pathMatch = block.match(/path:\s*([^\n]+)/);
        if (pathMatch) {
            const pathValue = pathMatch[1].trim();
            validatePath(schemaName, pathValue, report);
        }
    }
}

function collectSchemaEntries(openapi) {
    const schemas = openapi.components?.schemas || {};
    return new Map(Object.entries(schemas));
}

function buildSchemaIndex(schemas) {
    const index = new Map();
    for (const [name, schema] of Object.entries(schemas)) {
        index.set(name.toLowerCase(), {name, schema});
    }
    return index;
}

function findSchemaByName(schemas, name, schemaIndex = null) {
    const index = schemaIndex || buildSchemaIndex(schemas);
    const found = index.get(name.toLowerCase());
    return found || null;
}

function resolveCanonicalSchemaNode(canonicalName, schemaIndex, nodeCache) {
    if (nodeCache.has(canonicalName)) {
        return nodeCache.get(canonicalName);
    }

    const found = schemaIndex.get(canonicalName.toLowerCase());
    if (!found) {
        nodeCache.set(canonicalName, null);
        return null;
    }

    let node = null;
    if (found.schema.$ref) {
        const targetName = refToName(found.schema.$ref);
        const target = targetName ? schemaIndex.get(targetName.toLowerCase()) : null;
        node = target ? getSchemaNode(target.schema) : null;
    } else {
        node = getSchemaNode(found.schema);
    }

    nodeCache.set(canonicalName, node);
    return node;
}

function derefSchema(schemas, schema, seen = new Set()) {
    if (!schema || typeof schema !== 'object') {
        return schema;
    }

    if (schema.$ref) {
        const refName = refToName(schema.$ref);
        if (!refName || seen.has(refName)) {
            return {};
        }

        const found = findSchemaByName(schemas, refName);
        if (!found) {
            return {};
        }

        seen.add(refName);
        return derefSchema(schemas, found.schema, seen);
    }

    return schema;
}

function mergeComposedSchema(schemas, schema, seen = new Set()) {
    if (!schema || typeof schema !== 'object') {
        return {properties: {}, required: []};
    }

    if (schema.$ref) {
        const refName = refToName(schema.$ref);
        if (!refName || seen.has(refName)) {
            return {properties: {}, required: []};
        }

        const found = findSchemaByName(schemas, refName);
        if (!found) {
            return {properties: {}, required: []};
        }

        seen.add(refName);
        return mergeComposedSchema(schemas, found.schema, seen);
    }

    let properties = {...(schema.properties || {})};
    let required = [...(schema.required || [])];

    if (Array.isArray(schema.allOf)) {
        for (const item of schema.allOf) {
            const merged = mergeComposedSchema(schemas, item, new Set(seen));
            properties = {...merged.properties, ...properties};
            required = [...new Set([...merged.required, ...required])];
        }
    }

    return {properties, required};
}

function getSchemaNode(schema) {
    if (!schema || typeof schema !== 'object' || schema.$ref) {
        return null;
    }

    return schema;
}

function validatePath(schemaName, pathValue, report) {
    if (!pathValue || typeof pathValue !== 'string' || !pathValue.trim()) {
        report(schemaName, 'укажите непустой `x-polymorphic-discriminator.path`');
        return;
    }

    if (pathValue.includes('/')) {
        report(
            schemaName,
            `\`path\` должен быть в dot-нотации (например, \`meta.type\`), без «/»; получено: «${pathValue}»`
        );
        return;
    }

    if (!DOT_PATH_PATTERN.test(pathValue)) {
        report(
            schemaName,
            `\`path\` должен содержать минимум два сегмента через точку (например, \`meta.type\`); получено: «${pathValue}»`
        );
    }
}

function validateMappings(
    schemaName,
    mappings,
    report,
    canonicalNames,
    canonicalIndex,
    schemaIndex,
    nodeCache,
    polymorphicParentName,
    polymorphicParentsByConfig
) {
    if (!mappings || typeof mappings !== 'object' || Array.isArray(mappings)) {
        report(
            schemaName,
            '`mappings` должен быть объектом: ключ — значение дискриминатора, значение — `$ref` на схему'
        );
        return;
    }

    const entries = Object.entries(mappings);
    if (entries.length === 0) {
        report(schemaName, '`mappings` не должен быть пустым');
        return;
    }

    const canonicalParentName = toCanonicalName(polymorphicParentName, canonicalIndex);

    for (const [value, refEntry] of entries) {
        if (!value) {
            report(schemaName, 'ключ в `mappings` должен быть непустым значением дискриминатора');
            continue;
        }

        if (refEntry && typeof refEntry === 'object' && 'model' in refEntry) {
            report(
                schemaName,
                `в mapping «${value}» нельзя использовать устаревший тег \`model\`; укажите \`$ref: '#/components/schemas/<Имя>'\``
            );
            continue;
        }

        const modelName = toCanonicalName(resolveMappingRef(refEntry), canonicalIndex);
        if (!modelName) {
            report(
                schemaName,
                `в mapping «${value}» ожидается \`#/components/schemas/<Имя>\` или объект \`{ $ref: '#/components/schemas/<Имя>' }\``
            );
            continue;
        }

        if (!canonicalNames.has(modelName)) {
            report(
                schemaName,
                `в mapping «${value}» указана неизвестная схема «${modelName}» — нет такого компонента в components.schemas (src/openapi.yaml)`
            );
            continue;
        }

        const childNode = resolveCanonicalSchemaNode(modelName, schemaIndex, nodeCache);
        if (!childNode) {
            report(schemaName, `в mapping «${value}» схема «${modelName}» не найдена в bundle`);
            continue;
        }

        const parentField = childNode['x-polymorphic-parent'];

        if (!parentField) {
            report(modelName, `укажите \`x-polymorphic-parent\` со ссылкой на родительскую схему «${canonicalParentName}»`);
            continue;
        }

        const declaredParent = resolveBundledParentName(
            parentField,
            polymorphicParentsByConfig,
            canonicalIndex
        );

        if (!declaredParent) {
            report(
                modelName,
                'некорректный `x-polymorphic-parent`: ожидается объект `{ $ref: \'#/components/schemas/<Имя>\' }`'
            );
        } else if (declaredParent !== canonicalParentName) {
            report(
                modelName,
                `\`x-polymorphic-parent\` указывает на «${declaredParent}», а в mappings родителя «${canonicalParentName}» ` +
                `для значения «${value}» ожидается связь именно с «${canonicalParentName}»`
            );
        }
    }
}

function createErrorReporter(canonicalIndex) {
    const seen = new Set();
    const errors = [];

    const report = (schemaName, message) => {
        const canonical = toCanonicalName(schemaName, canonicalIndex) || schemaName;
        const key = `${canonical}\0${message}`;
        if (seen.has(key)) {
            return;
        }
        seen.add(key);
        errors.push(`[${canonical}] ${message}`);
    };

    return {report, errors};
}

function validatePolymorphicDiscriminator(openapiPath, options = {}) {
    const srcOpenapiPath = options.canonicalNamesFrom || openapiPath;
    const openapi = loadJson(openapiPath);
    const canonicalNames = collectCanonicalSchemaNames(srcOpenapiPath);
    const canonicalIndex = buildCanonicalNameIndex(canonicalNames);
    const schemas = openapi.components?.schemas || {};
    const schemaIndex = buildSchemaIndex(schemas);
    const nodeCache = new Map();
    const {report, errors} = createErrorReporter(canonicalIndex);

    validateSourceStrictFormat(srcOpenapiPath, report);

    const polymorphicParents = new Map();
    const polymorphicParentsByConfig = new Map();

    for (const schemaName of canonicalNames) {
        const node = resolveCanonicalSchemaNode(schemaName, schemaIndex, nodeCache);
        if (!node) {
            continue;
        }

        const hasDiscriminator = Boolean(node.discriminator);
        const hasPolymorphicDiscriminator = Boolean(node['x-polymorphic-discriminator']);

        if (hasDiscriminator && hasPolymorphicDiscriminator) {
            report(
                schemaName,
                'нельзя одновременно указывать `discriminator` и `x-polymorphic-discriminator` — выберите один механизм полиморфизма'
            );
        }

        if (!hasPolymorphicDiscriminator) {
            continue;
        }

        const canonicalParentName = toCanonicalName(schemaName, canonicalIndex);
        polymorphicParents.set(canonicalParentName, node['x-polymorphic-discriminator']);
        polymorphicParentsByConfig.set(
            JSON.stringify(node['x-polymorphic-discriminator']),
            canonicalParentName
        );

        const config = node['x-polymorphic-discriminator'];
        if (!config || typeof config !== 'object') {
            report(schemaName, '`x-polymorphic-discriminator` должен быть объектом');
            continue;
        }

        validatePath(schemaName, config.path, report);
        validateMappings(
            schemaName,
            config.mappings,
            report,
            canonicalNames,
            canonicalIndex,
            schemaIndex,
            nodeCache,
            schemaName,
            polymorphicParentsByConfig
        );
    }

    for (const schemaName of canonicalNames) {
        const node = resolveCanonicalSchemaNode(schemaName, schemaIndex, nodeCache);
        if (!node?.['x-polymorphic-parent']) {
            continue;
        }

        const parentName = resolveBundledParentName(
            node['x-polymorphic-parent'],
            polymorphicParentsByConfig,
            canonicalIndex
        );
        if (!parentName) {
            report(
                schemaName,
                'некорректный `x-polymorphic-parent`: ожидается объект `{ $ref: \'#/components/schemas/<Имя>\' }`'
            );
            continue;
        }

        if (!canonicalNames.has(parentName)) {
            report(
                schemaName,
                `\`x-polymorphic-parent\` ссылается на неизвестную схему «${parentName}» — нет такого компонента в components.schemas`
            );
            continue;
        }

        const parentConfig = polymorphicParents.get(parentName);
        if (!parentConfig) {
            report(
                schemaName,
                `\`x-polymorphic-parent\` должен указывать на схему с \`x-polymorphic-discriminator\` (получено: «${parentName}»)`
            );
            continue;
        }

        const mappings = parentConfig.mappings;
        if (!mappings || typeof mappings !== 'object' || Array.isArray(mappings)) {
            continue;
        }

        const mapped = Object.entries(mappings).some(
            ([, refEntry]) => toCanonicalName(resolveMappingRef(refEntry), canonicalIndex) === schemaName
        );

        if (!mapped) {
            report(
                schemaName,
                `схема объявляет \`x-polymorphic-parent\` → «${parentName}», но схема «${schemaName}» не указана в ` +
                `\`x-polymorphic-discriminator.mappings\` этого родителя. ` +
                `Необходимо добавить запись с $ref на «${schemaName}» в родительский компонент «${parentName}»`
            );
        }
    }

    return errors;
}

function transformSchemaForCodegen(schemas, schema, polymorphicParentsByConfig, canonicalIndex) {
    const node = getSchemaNode(schema);
    if (!node) {
        return schema;
    }

    const parentName = resolveBundledParentName(
        node['x-polymorphic-parent'],
        polymorphicParentsByConfig,
        canonicalIndex
    );
    if (!parentName || !Array.isArray(node.allOf) || node.allOf.length < 2) {
        return schema;
    }

    const parentRef = node.allOf.find((item) => {
        const refName = refToName(item?.$ref);
        return refName && refName.toLowerCase() === parentName.toLowerCase();
    });

    if (!parentRef) {
        return schema;
    }

    const parentMerged = mergeComposedSchema(schemas, parentRef);
    const siblingAllOf = node.allOf.filter((item) => item !== parentRef);
    let properties = {...(node.properties || {})};
    let required = [...(node.required || [])];

    for (const item of siblingAllOf) {
        const merged = mergeComposedSchema(schemas, item);
        properties = {...merged.properties, ...properties};
        required = [...new Set([...required, ...merged.required])];
    }

    properties = Object.fromEntries(
        Object.entries(properties).filter(([name]) => !(name in parentMerged.properties))
    );
    required = required.filter((name) => !(name in parentMerged.properties));

    const transformed = {
        ...node,
        properties,
    };

    delete transformed.allOf;

    if (required.length > 0) {
        transformed.required = required;
    } else {
        delete transformed.required;
    }

    return transformed;
}

function buildPolymorphicParentsByConfig(schemas, canonicalIndex) {
    const map = new Map();

    for (const [name, schema] of Object.entries(schemas)) {
        const node = getSchemaNode(schema) || derefSchema(schemas, schema);
        if (!node?.['x-polymorphic-discriminator']) {
            continue;
        }

        map.set(
            JSON.stringify(node['x-polymorphic-discriminator']),
            toCanonicalName(name, canonicalIndex)
        );
    }

    return map;
}

function normalizePolymorphicExtensions(node, canonicalIndex, polymorphicParentsByConfig) {
    const config = node['x-polymorphic-discriminator'];
    if (config?.mappings && typeof config.mappings === 'object' && !Array.isArray(config.mappings)) {
        config.mappings = Object.entries(config.mappings).map(([value, refEntry]) => ({
            value,
            className: toCanonicalName(resolveMappingRef(refEntry), canonicalIndex),
        }));
    }

    const parentName = resolveBundledParentName(
        node['x-polymorphic-parent'],
        polymorphicParentsByConfig,
        canonicalIndex
    );
    if (parentName) {
        node['x-polymorphic-parent'] = parentName;
    }
}

function resolveSchemaEntry(schemas, schemaName) {
    const found = findSchemaByName(schemas, schemaName);
    if (!found) {
        return null;
    }

    if (found.schema.$ref) {
        const targetName = refToName(found.schema.$ref);
        const target = targetName ? findSchemaByName(schemas, targetName) : null;
        if (!target) {
            return null;
        }

        const node = getSchemaNode(target.schema);
        if (!node) {
            return null;
        }

        return {
            storageName: target.name,
            aliasName: found.name,
            canonicalName: schemaName,
            node,
        };
    }

    const node = getSchemaNode(found.schema);
    if (!node) {
        return null;
    }

    return {
        storageName: found.name,
        aliasName: null,
        canonicalName: schemaName,
        node: found.schema,
    };
}

function prepareCodegenOpenapi(bundlePath, outputPath, options = {}) {
    const openapi = loadJson(bundlePath);
    const schemas = openapi.components?.schemas || {};
    const canonicalNames = collectCanonicalSchemaNames(
        options.canonicalNamesFrom || path.resolve(path.dirname(bundlePath), '../src/openapi.yaml')
    );
    const canonicalIndex = buildCanonicalNameIndex(canonicalNames);
    const polymorphicParentsByConfig = buildPolymorphicParentsByConfig(schemas, canonicalIndex);

    for (const schemaName of canonicalNames) {
        const entry = resolveSchemaEntry(schemas, schemaName);
        if (!entry) {
            continue;
        }

        const transformed = transformSchemaForCodegen(
            schemas,
            entry.node,
            polymorphicParentsByConfig,
            canonicalIndex
        );
        normalizePolymorphicExtensions(transformed, canonicalIndex, polymorphicParentsByConfig);
        schemas[entry.storageName] = transformed;

        if (entry.aliasName && entry.aliasName !== entry.storageName) {
            schemas[entry.aliasName] = {$ref: `${COMPONENTS_PREFIX}${entry.storageName}`};
        } else if (entry.storageName !== schemaName) {
            schemas[schemaName] = transformed;
        }
    }

    for (const schemaName of canonicalNames) {
        const entry = resolveSchemaEntry(schemas, schemaName);
        if (!entry) {
            continue;
        }

        const node = getSchemaNode(schemas[entry.storageName]);
        if (node) {
            normalizePolymorphicExtensions(node, canonicalIndex, polymorphicParentsByConfig);
        }
    }

    fs.mkdirSync(path.dirname(outputPath), {recursive: true});
    fs.writeFileSync(outputPath, `${JSON.stringify(openapi, null, 2)}\n`);
}

function collectSourceFilesToWatch() {
    const files = [SRC_OPENAPI];
    for (const filePath of collectExternalSchemaRefs(SRC_OPENAPI).values()) {
        files.push(filePath);
    }
    return files;
}

function isBundleFresh() {
    if (!fs.existsSync(BUNDLED_OPENAPI)) {
        return false;
    }

    const bundleMtime = fs.statSync(BUNDLED_OPENAPI).mtimeMs;
    return collectSourceFilesToWatch().every((file) => {
        try {
            return fs.statSync(file).mtimeMs <= bundleMtime;
        } catch {
            return false;
        }
    });
}

function ensureBundleJson(force = false) {
    if (!force && process.env.SKIP_BUNDLE === '1') {
        return;
    }

    if (!force && isBundleFresh()) {
        return;
    }

    execFileSync('npm', ['run', 'bundle-json'], {
        cwd: REPO_ROOT,
        stdio: 'inherit',
    });
}

function runValidate() {
    ensureBundleJson();

    const errors = validatePolymorphicDiscriminator(BUNDLED_OPENAPI, {
        canonicalNamesFrom: SRC_OPENAPI,
    });

    if (errors.length === 0) {
        console.log('Проверка x-polymorphic-discriminator прошла успешно.');
        return;
    }

    console.error('Проверка x-polymorphic-discriminator не пройдена:\n');
    for (const error of errors) {
        console.error(`- ${error}`);
    }
    process.exit(1);
}

function runPrepare() {
    ensureBundleJson();

    prepareCodegenOpenapi(BUNDLED_OPENAPI, CODEGEN_OPENAPI, {
        canonicalNamesFrom: SRC_OPENAPI,
    });

    console.log(`Prepared codegen spec: ${CODEGEN_OPENAPI}`);
}

function printUsage() {
    console.error(`Usage: node scripts/polymorphic-discriminator.js <validate|prepare>

  validate  Check x-polymorphic-discriminator and x-polymorphic-parent extensions
  prepare   Build dist/openapi-codegen.json for SDK generation`);
}

function main() {
    const command = process.argv[2];

    if (command === 'validate') {
        runValidate();
        return;
    }

    if (command === 'prepare') {
        runPrepare();
        return;
    }

    printUsage();
    process.exit(1);
}

main();
