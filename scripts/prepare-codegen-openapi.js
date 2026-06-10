#!/usr/bin/env node
'use strict';

const path = require('path');
const { execFileSync } = require('child_process');
const { prepareCodegenOpenapi } = require('./lib/polymorphic-discriminator');

const repoRoot = path.resolve(__dirname, '..');
const srcOpenapi = path.join(repoRoot, 'src/openapi.yaml');
const bundledOpenapi = path.join(repoRoot, 'dist/openapi.json');
const codegenOpenapi = path.join(repoRoot, 'dist/openapi-codegen.json');

function main() {
  execFileSync('npm', ['run', 'bundle-json'], {
    cwd: repoRoot,
    stdio: 'inherit',
  });

  prepareCodegenOpenapi(bundledOpenapi, codegenOpenapi, {
    canonicalNamesFrom: srcOpenapi,
  });

  console.log(`Prepared codegen spec: ${codegenOpenapi}`);
}

main();
