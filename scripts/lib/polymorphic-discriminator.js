'use strict';

const fs = require('fs');
const path = require('path');

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

function validateSourceStrictFormat(srcOpenapiPath, report) {
  const content = fs.readFileSync(srcOpenapiPath, 'utf8');
  const refs = collectExternalSchemaRefs(srcOpenapiPath);

  for (const [schemaName, filePath] of refs) {
    const fileContent = fs.readFileSync(filePath, 'utf8');
    if (!fileContent.includes('x-polymorphic-parent')) {
      continue;
    }

    const parentRefMatch = fileContent.match(
      /x-polymorphic-parent:\s*\n\s+\$ref:\s*['"]([^'"]+)['"]/
    );
    if (!parentRefMatch) {
      report(
        schemaName,
        '`x-polymorphic-parent` must be an object with `$ref` to `#/components/schemas/<Name>`'
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
        '`x-polymorphic-discriminator.mappings` must be an object, not an array with `value`/`model`'
      );
    }

    if (/\bmodel:/.test(block)) {
      report(schemaName, '`x-polymorphic-discriminator.mappings` must not use deprecated `model`');
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

function findSchemaByName(schemas, name) {
  if (schemas[name]) {
    return { name, schema: schemas[name] };
  }

  const match = Object.keys(schemas).find((key) => key.toLowerCase() === name.toLowerCase());
  if (!match) {
    return null;
  }

  return { name: match, schema: schemas[match] };
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
    return { properties: {}, required: [] };
  }

  if (schema.$ref) {
    const refName = refToName(schema.$ref);
    if (!refName || seen.has(refName)) {
      return { properties: {}, required: [] };
    }

    const found = findSchemaByName(schemas, refName);
    if (!found) {
      return { properties: {}, required: [] };
    }

    seen.add(refName);
    return mergeComposedSchema(schemas, found.schema, seen);
  }

  let properties = { ...(schema.properties || {}) };
  let required = [...(schema.required || [])];

  if (Array.isArray(schema.allOf)) {
    for (const item of schema.allOf) {
      const merged = mergeComposedSchema(schemas, item, new Set(seen));
      properties = { ...merged.properties, ...properties };
      required = [...new Set([...merged.required, ...required])];
    }
  }

  return { properties, required };
}

function getSchemaNode(schema) {
  if (!schema || typeof schema !== 'object' || schema.$ref) {
    return null;
  }

  return schema;
}

function walkSchemas(openapi, visitor) {
  const schemas = openapi.components?.schemas || {};

  for (const [name, schema] of Object.entries(schemas)) {
    const node = getSchemaNode(schema) || derefSchema(schemas, schema);
    if (node) {
      visitor(name, node, schemas);
    }
  }
}

function validatePath(schemaName, pathValue, report) {
  if (!pathValue || typeof pathValue !== 'string' || !pathValue.trim()) {
    report(schemaName, '`x-polymorphic-discriminator.path` must be a non-empty string');
    return;
  }

  if (pathValue.includes('/')) {
    report(
      schemaName,
      `\`x-polymorphic-discriminator.path\` must use dot notation (for example \`meta.type\`), got "${pathValue}"`
    );
    return;
  }

  if (!DOT_PATH_PATTERN.test(pathValue)) {
    report(
      schemaName,
      `\`x-polymorphic-discriminator.path\` must use dot notation with at least two segments (for example \`meta.type\`), got "${pathValue}"`
    );
  }
}

function validateMappings(
  schemaName,
  mappings,
  report,
  canonicalNames,
  canonicalIndex,
  schemas,
  polymorphicParentName,
  polymorphicParentsByConfig
) {
  if (!mappings || typeof mappings !== 'object' || Array.isArray(mappings)) {
    report(
      schemaName,
      '`x-polymorphic-discriminator.mappings` must be an object that maps discriminator values to schema $ref'
    );
    return;
  }

  const entries = Object.entries(mappings);
  if (entries.length === 0) {
    report(schemaName, '`x-polymorphic-discriminator.mappings` must not be empty');
    return;
  }

  for (const [value, refEntry] of entries) {
    if (!value) {
      report(schemaName, 'mapping key must be a non-empty discriminator value');
      continue;
    }

    if (refEntry && typeof refEntry === 'object' && 'model' in refEntry) {
      report(
        schemaName,
        `mapping "${value}" must not use deprecated \`model\`; use \`$ref: '#/components/schemas/<Name>'\` or '#/components/schemas/<Name>'`
      );
      continue;
    }

    const modelName = toCanonicalName(resolveMappingRef(refEntry), canonicalIndex);
    if (!modelName) {
      report(
        schemaName,
        `mapping "${value}" must be '#/components/schemas/<Name>' or an object with $ref to '#/components/schemas/<Name>'`
      );
      continue;
    }

    if (!canonicalNames.has(modelName)) {
      report(
        schemaName,
        `mapping "${value}" references unknown schema "${modelName}"; expected an existing components.schemas entry from src/openapi.yaml`
      );
      continue;
    }

    const childSchema = findSchemaByName(schemas, modelName);
    if (!childSchema) {
      report(schemaName, `mapping "${value}" references unresolved schema "${modelName}"`);
      continue;
    }

    const childNode = getSchemaNode(childSchema.schema) || derefSchema(schemas, childSchema.schema);
      const declaredParent = resolveBundledParentName(
        childNode?.['x-polymorphic-parent'],
        polymorphicParentsByConfig,
        canonicalIndex
      );
      const canonicalParentName = toCanonicalName(polymorphicParentName, canonicalIndex);

    if (!declaredParent) {
      report(
        modelName,
        `schema listed in x-polymorphic-discriminator.mappings must define x-polymorphic-parent as $ref to ${canonicalParentName}`
      );
    } else if (declaredParent !== canonicalParentName) {
      report(
        modelName,
        `x-polymorphic-parent points to "${declaredParent}", but "${canonicalParentName}" maps this schema in x-polymorphic-discriminator.mappings`
      );
    }
  }
}

function validatePolymorphicDiscriminator(openapiPath, options = {}) {
  const srcOpenapiPath = options.canonicalNamesFrom || openapiPath;
  const openapi = loadJson(openapiPath);
  const canonicalNames = collectCanonicalSchemaNames(srcOpenapiPath);
  const canonicalIndex = buildCanonicalNameIndex(canonicalNames);
  const schemas = Object.fromEntries(collectSchemaEntries(openapi));
  const errors = [];

  const report = (schemaName, message) => {
    errors.push(`[${toCanonicalName(schemaName, canonicalIndex) || schemaName}] ${message}`);
  };

  validateSourceStrictFormat(srcOpenapiPath, report);

  const polymorphicParents = new Map();
  const polymorphicParentsByConfig = new Map();

  walkSchemas(openapi, (schemaName, schema) => {
    const hasDiscriminator = Boolean(schema.discriminator);
    const hasPolymorphicDiscriminator = Boolean(schema['x-polymorphic-discriminator']);

    if (hasDiscriminator && hasPolymorphicDiscriminator) {
      report(
        schemaName,
        'schema must not define both `discriminator` and `x-polymorphic-discriminator`; use only one polymorphism mechanism'
      );
    }

    if (!hasPolymorphicDiscriminator) {
      return;
    }

    const canonicalParentName = toCanonicalName(schemaName, canonicalIndex);
    polymorphicParents.set(canonicalParentName, schema['x-polymorphic-discriminator']);
    polymorphicParentsByConfig.set(
      JSON.stringify(schema['x-polymorphic-discriminator']),
      canonicalParentName
    );

    const config = schema['x-polymorphic-discriminator'];
    if (!config || typeof config !== 'object') {
      report(schemaName, '`x-polymorphic-discriminator` must be an object');
      return;
    }

    validatePath(schemaName, config.path, report);
    validateMappings(
      schemaName,
      config.mappings,
      report,
      canonicalNames,
      canonicalIndex,
      schemas,
      schemaName,
      polymorphicParentsByConfig
    );
  });

  walkSchemas(openapi, (schemaName, schema) => {
    const canonicalSchemaName = toCanonicalName(schemaName, canonicalIndex);
    const parentField = schema['x-polymorphic-parent'];
    if (!parentField) {
      return;
    }

    const parentName = resolveBundledParentName(
      parentField,
      polymorphicParentsByConfig,
      canonicalIndex
    );
    if (!parentName) {
      report(
        canonicalSchemaName,
        '`x-polymorphic-parent` must be an object with `$ref` to `#/components/schemas/<Name>`'
      );
      return;
    }

    if (!canonicalNames.has(parentName)) {
      report(
        canonicalSchemaName,
        `x-polymorphic-parent references unknown schema "${parentName}"; expected an existing components.schemas entry from src/openapi.yaml`
      );
      return;
    }

    const parentConfig = polymorphicParents.get(parentName);
    if (!parentConfig) {
      report(
        canonicalSchemaName,
        `x-polymorphic-parent must point to a schema with x-polymorphic-discriminator`
      );
      return;
    }

    const mappings = parentConfig.mappings;
    if (!mappings || typeof mappings !== 'object' || Array.isArray(mappings)) {
      return;
    }

    const mapped = Object.entries(mappings).some(
      ([, refEntry]) => toCanonicalName(resolveMappingRef(refEntry), canonicalIndex) === canonicalSchemaName
    );

    if (!mapped) {
      report(
        canonicalSchemaName,
        `schema defines x-polymorphic-parent to "${parentName}" but is missing from ${parentName}.x-polymorphic-discriminator.mappings`
      );
    }
  });

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
  let properties = { ...(node.properties || {}) };
  let required = [...(node.required || [])];

  for (const item of siblingAllOf) {
    const merged = mergeComposedSchema(schemas, item);
    properties = { ...merged.properties, ...properties };
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
      schemas[entry.aliasName] = { $ref: `${COMPONENTS_PREFIX}${entry.storageName}` };
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

  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, `${JSON.stringify(openapi, null, 2)}\n`);
}

module.exports = {
  COMPONENTS_PREFIX,
  DOT_PATH_PATTERN,
  collectCanonicalSchemaNames,
  prepareCodegenOpenapi,
  refToName,
  resolveMappingRef,
  resolveParentRef,
  validatePolymorphicDiscriminator,
};
