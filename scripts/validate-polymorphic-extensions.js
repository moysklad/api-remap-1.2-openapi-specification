#!/usr/bin/env node

const fs = require("fs");
const os = require("os");
const path = require("path");
const { spawnSync } = require("child_process");

const ROOT_DIR = path.resolve(__dirname, "..");
const SPEC_PATH = path.join(ROOT_DIR, "src", "openapi.yaml");

const POLYMORPHIC_DISCRIMINATOR = "x-polymorphic-discriminator";
const POLYMORPHIC_MISSING_DISCRIMINATOR_COMPONENT = "x-polymorphic-missing-discriminator-component";
const POLYMORPHIC_PARENT = "x-polymorphic-parent";

const errors = [];

function isObject(value) {
    return value !== null && typeof value === "object" && !Array.isArray(value);
}

function isNonEmptyString(value) {
    return typeof value === "string" && value.trim().length > 0;
}

function createBundledSpec() {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), "remap-polymorphic-"));
    const bundledSpecPath = path.join(tempDir, "openapi.json");

    try {
        const result = spawnSync(
            "redocly",
            [
                "bundle",
                "--skip-decorator=filter-out",
                SPEC_PATH,
                "-o",
                bundledSpecPath,
                "--ext",
                "json",
            ],
            {
                cwd: ROOT_DIR,
                encoding: "utf8",
            }
        );

        if (result.error) {
            throw result.error;
        }

        if (result.status !== 0) {
            const output = [result.stdout, result.stderr].filter(Boolean).join("\n").trim();
            throw new Error(output || "redocly bundle завершился с ошибкой");
        }

        return JSON.parse(fs.readFileSync(bundledSpecPath, "utf8"));
    } finally {
        fs.rmSync(tempDir, { recursive: true, force: true });
    }
}

function resolveJsonPointer(document, pointer) {
    if (!pointer || pointer === "#") {
        return document;
    }

    if (!pointer.startsWith("#/")) {
        return undefined;
    }

    return pointer
        .slice(2)
        .split("/")
        .map((part) => part.replace(/~1/g, "/").replace(/~0/g, "~"))
        .reduce((current, part) => (current === undefined ? undefined : current[part]), document);
}

function resolveRef(ref, rootDocument) {
    const [fileRef, pointer = ""] = ref.split("#");

    if (fileRef !== "") {
        return undefined;
    }

    return resolveJsonPointer(rootDocument, pointer ? `#${pointer}` : "#");
}

function getSchemaNameFromRef(ref) {
    const refPrefix = "#/components/schemas/";

    if (!ref.startsWith(refPrefix)) {
        return undefined;
    }

    return ref
        .slice(refPrefix.length)
        .replace(/~1/g, "/")
        .replace(/~0/g, "~");
}

function getPublicSchemaNames(schemas) {
    const internalSchemaNames = new Set();

    // Redocly keeps public component names as aliases and adds internal targets
    // for schemas from external files. componentName must match the public name.
    Object.values(schemas).forEach((schema) => {
        if (!isObject(schema) || !isNonEmptyString(schema.$ref)) {
            return;
        }

        const referencedSchemaName = getSchemaNameFromRef(schema.$ref);

        if (
            referencedSchemaName !== undefined &&
            Object.prototype.hasOwnProperty.call(schemas, referencedSchemaName)
        ) {
            internalSchemaNames.add(referencedSchemaName);
        }
    });

    return new Set(
        Object.keys(schemas).filter((schemaName) => !internalSchemaNames.has(schemaName))
    );
}

function getComponentSchema(componentName, schemas, rootDocument) {
    return resolveSchema(schemas[componentName], rootDocument);
}

function resolveSchema(schema, rootDocument, seenRefs = new Set()) {
    if (isObject(schema) && isNonEmptyString(schema.$ref)) {
        if (seenRefs.has(schema.$ref)) {
            return schema;
        }

        seenRefs.add(schema.$ref);
        return resolveSchema(resolveRef(schema.$ref, rootDocument), rootDocument, seenRefs);
    }

    return schema;
}

function addError(componentName, message) {
    errors.push(`${componentName}: ${message}`);
}

function validatePolymorphicParent(componentName, schema, schemaNames) {
    const parent = schema[POLYMORPHIC_PARENT];

    if (parent === undefined) {
        return;
    }

    if (!isNonEmptyString(parent)) {
        addError(componentName, `${POLYMORPHIC_PARENT} должен быть непустой строкой`);
        return;
    }

    if (!schemaNames.has(parent)) {
        addError(
            componentName,
            `${POLYMORPHIC_PARENT} ссылается на несуществующий компонент "${parent}"`
        );
    }
}

function getPolymorphicParentName(componentName, schemas, rootDocument) {
    const schema = getComponentSchema(componentName, schemas, rootDocument);

    if (!isObject(schema)) {
        return undefined;
    }

    const parent = schema[POLYMORPHIC_PARENT];
    return isNonEmptyString(parent) ? parent : undefined;
}

function hasPolymorphicAncestor(componentName, parentName, schemas, rootDocument) {
    const seenComponents = new Set();
    let currentName = componentName;

    while (isNonEmptyString(currentName) && !seenComponents.has(currentName)) {
        seenComponents.add(currentName);

        const currentParent = getPolymorphicParentName(currentName, schemas, rootDocument);
        if (currentParent === undefined) {
            return false;
        }

        if (currentParent === parentName) {
            return true;
        }

        currentName = currentParent;
    }

    return false;
}

function validateMappingParent(parentName, mapping, mappingIndex, schemas, schemaNames, rootDocument) {
    const componentName = mapping.componentName;

    if (!isNonEmptyString(componentName) || !schemaNames.has(componentName)) {
        return;
    }

    if (!hasPolymorphicAncestor(componentName, parentName, schemas, rootDocument)) {
        addError(
            parentName,
            `${POLYMORPHIC_DISCRIMINATOR}.mappings[${mappingIndex}].componentName="${componentName}" требует ${POLYMORPHIC_PARENT}: ${parentName} в дочернем компоненте или его родительской цепочке`
        );
    }
}

function validateDiscriminatorMappings(componentName, discriminator, schemas, schemaNames, rootDocument) {
    if (!Array.isArray(discriminator.mappings)) {
        addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings должен быть массивом`);
        return;
    }

    if (discriminator.mappings.length === 0) {
        addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings должен содержать минимум 1 элемент`);
        return;
    }

    const seenTypes = new Set();

    discriminator.mappings.forEach((mapping, index) => {
        if (!isObject(mapping)) {
            addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings[${index}] должен быть объектом`);
            return;
        }

        if (!isNonEmptyString(mapping.type)) {
            addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings[${index}].type должен быть непустой строкой`);
        } else if (seenTypes.has(mapping.type)) {
            addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings содержит повторяющийся type "${mapping.type}"`);
        } else {
            seenTypes.add(mapping.type);
        }

        if (!isNonEmptyString(mapping.componentName)) {
            addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.mappings[${index}].componentName должен быть непустой строкой`);
        } else if (!schemaNames.has(mapping.componentName)) {
            addError(
                componentName,
                `${POLYMORPHIC_DISCRIMINATOR}.mappings[${index}].componentName ссылается на несуществующий компонент "${mapping.componentName}"`
            );
        }

        validateMappingParent(componentName, mapping, index, schemas, schemaNames, rootDocument);
    });
}

function validatePolymorphicDiscriminator(componentName, schema, schemas, schemaNames, rootDocument) {
    const discriminator = schema[POLYMORPHIC_DISCRIMINATOR];

    if (discriminator === undefined) {
        return;
    }

    if (schema.discriminator !== undefined) {
        addError(componentName, `${POLYMORPHIC_DISCRIMINATOR} конфликтует со стандартным discriminator`);
    }

    if (!isObject(discriminator)) {
        addError(componentName, `${POLYMORPHIC_DISCRIMINATOR} должен быть объектом`);
        return;
    }

    if (!isNonEmptyString(discriminator.path)) {
        addError(componentName, `${POLYMORPHIC_DISCRIMINATOR}.path должен быть непустой строкой`);
    }

    validateDiscriminatorMappings(componentName, discriminator, schemas, schemaNames, rootDocument);
}

function validateMissingDiscriminatorComponent(componentName, schema, schemas, schemaNames, rootDocument) {
    const missingComponent = schema[POLYMORPHIC_MISSING_DISCRIMINATOR_COMPONENT];

    if (missingComponent === undefined) {
        return;
    }

    if (!isNonEmptyString(missingComponent)) {
        addError(componentName, `${POLYMORPHIC_MISSING_DISCRIMINATOR_COMPONENT} должен быть непустой строкой`);
        return;
    }

    if (!schemaNames.has(missingComponent)) {
        addError(
            componentName,
            `${POLYMORPHIC_MISSING_DISCRIMINATOR_COMPONENT} ссылается на несуществующий компонент "${missingComponent}"`
        );
        return;
    }

    if (!hasPolymorphicAncestor(missingComponent, componentName, schemas, rootDocument)) {
        addError(
            componentName,
            `${POLYMORPHIC_MISSING_DISCRIMINATOR_COMPONENT}="${missingComponent}" требует ${POLYMORPHIC_PARENT}: ${componentName} в дочернем компоненте или его родительской цепочке`
        );
    }
}

function validate() {
    const rootDocument = createBundledSpec();
    const schemas = rootDocument && rootDocument.components && rootDocument.components.schemas;

    if (!isObject(schemas)) {
        throw new Error("components.schemas не найден в src/openapi.yaml");
    }

    const schemaNames = getPublicSchemaNames(schemas);

    schemaNames.forEach((componentName) => {
        const schema = getComponentSchema(componentName, schemas, rootDocument);

        if (!isObject(schema)) {
            return;
        }

        validatePolymorphicParent(componentName, schema, schemaNames);
        validatePolymorphicDiscriminator(componentName, schema, schemas, schemaNames, rootDocument);
        validateMissingDiscriminatorComponent(componentName, schema, schemas, schemaNames, rootDocument);
    });
}

try {
    validate();

    if (errors.length > 0) {
        console.error("Ошибки валидации x-polymorphic-* расширений:");
        errors.forEach((error) => console.error(`* ${error}`));
        process.exit(1);
    }

    console.log("Проверка x-polymorphic-* расширений пройдена без ошибок.");
} catch (error) {
    console.error(`Ошибка при валидации x-polymorphic-* расширений: ${error.message}`);
    process.exit(1);
}
