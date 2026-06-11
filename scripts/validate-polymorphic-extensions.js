#!/usr/bin/env node

const fs = require("fs");
const path = require("path");
const yaml = require("js-yaml");

const ROOT_DIR = path.resolve(__dirname, "..");
const SPEC_PATH = path.join(ROOT_DIR, "src", "openapi.yaml");

const POLYMORPHIC_DISCRIMINATOR = "x-polymorphic-discriminator";
const POLYMORPHIC_PARENT = "x-polymorphic-parent";

const parsedFiles = new Map();
const errors = [];

function readYaml(filePath) {
    const absolutePath = path.resolve(filePath);

    if (!parsedFiles.has(absolutePath)) {
        const content = fs.readFileSync(absolutePath, "utf8");
        parsedFiles.set(absolutePath, yaml.load(content));
    }

    return parsedFiles.get(absolutePath);
}

function isObject(value) {
    return value !== null && typeof value === "object" && !Array.isArray(value);
}

function isNonEmptyString(value) {
    return typeof value === "string" && value.trim().length > 0;
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

function resolveRef(ref, currentFilePath, rootDocument) {
    const [fileRef, pointer = ""] = ref.split("#");
    const normalizedPointer = pointer ? `#${pointer}` : "#";

    if (fileRef === "") {
        return resolveJsonPointer(rootDocument, normalizedPointer);
    }

    const referencedFilePath = path.resolve(path.dirname(currentFilePath), fileRef);
    const referencedDocument = readYaml(referencedFilePath);

    return resolveJsonPointer(referencedDocument, normalizedPointer);
}

function getComponentSchema(componentName, schemas, rootDocument) {
    const schema = schemas[componentName];

    if (isObject(schema) && isNonEmptyString(schema.$ref)) {
        return resolveRef(schema.$ref, SPEC_PATH, rootDocument);
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

function validateMappingParent(parentName, mapping, mappingIndex, schemas, schemaNames, rootDocument) {
    const componentName = mapping.componentName;

    if (!isNonEmptyString(componentName) || !schemaNames.has(componentName)) {
        return;
    }

    const childSchema = getComponentSchema(componentName, schemas, rootDocument);

    if (!isObject(childSchema) || childSchema[POLYMORPHIC_PARENT] !== parentName) {
        addError(
            parentName,
            `${POLYMORPHIC_DISCRIMINATOR}.mappings[${mappingIndex}].componentName="${componentName}" требует ${POLYMORPHIC_PARENT}: ${parentName} в дочернем компоненте`
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

function validate() {
    const rootDocument = readYaml(SPEC_PATH);
    const schemas = rootDocument && rootDocument.components && rootDocument.components.schemas;

    if (!isObject(schemas)) {
        throw new Error("components.schemas не найден в src/openapi.yaml");
    }

    const schemaNames = new Set(Object.keys(schemas));

    Object.keys(schemas).forEach((componentName) => {
        const schema = getComponentSchema(componentName, schemas, rootDocument);

        if (!isObject(schema)) {
            return;
        }

        validatePolymorphicParent(componentName, schema, schemaNames);
        validatePolymorphicDiscriminator(componentName, schema, schemas, schemaNames, rootDocument);
    });
}

try {
    validate();

    if (errors.length > 0) {
        console.error("Ошибки валидации x-polymorphic-* расширений:");
        errors.forEach((error) => console.error(`- ${error}`));
        process.exit(1);
    }

    console.log("Проверка x-polymorphic-* расширений пройдена без ошибок.");
} catch (error) {
    console.error(`Ошибка при валидации x-polymorphic-* расширений: ${error.message}`);
    process.exit(1);
}
