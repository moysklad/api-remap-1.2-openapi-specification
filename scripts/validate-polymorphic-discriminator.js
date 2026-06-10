#!/usr/bin/env node
'use strict';

const path = require('path');
const { execFileSync } = require('child_process');
const { validatePolymorphicDiscriminator } = require('./lib/polymorphic-discriminator');

const repoRoot = path.resolve(__dirname, '..');
const srcOpenapi = path.join(repoRoot, 'src/openapi.yaml');
const bundledOpenapi = path.join(repoRoot, 'dist/openapi.json');

function ensureBundle() {
  if (process.env.SKIP_BUNDLE === '1') {
    return;
  }

  execFileSync('npm', ['run', 'bundle-json'], {
    cwd: repoRoot,
    stdio: 'inherit',
  });
}
/**
 **Валидация при сборке SDK**

 Проверяется:
 * отсутствие одновременного `discriminator` и `x-polymorphic-discriminator` на одной схеме;
 * формат `path` (только dot-нотация);
 * `mappings` — объект со ссылками на `components.schemas`
 * двусторонняя согласованность `mappings` в `x-polymorphic-discriminator` и `x-polymorphic-parent`.
 * связанный `x-polymorphic-parent` задан только через `$ref`;

 **/


function main() {
  ensureBundle();

  const errors = validatePolymorphicDiscriminator(bundledOpenapi, {
    canonicalNamesFrom: srcOpenapi,
  });

  if (errors.length === 0) {
    console.log('Polymorphic discriminator validation passed.');
    return;
  }

  console.error('Polymorphic discriminator validation failed:\n');
  for (const error of errors) {
    console.error(`- ${error}`);
  }
  process.exit(1);
}

main();
