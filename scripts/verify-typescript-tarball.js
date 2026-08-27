#!/usr/bin/env node
// Проверка состава npm-архива TypeScript SDK перед публикацией (аналог scripts/verify-python-wheel.py).

const fs = require("fs");
const zlib = require("zlib");

const PACKAGE_ROOT = "package";
const REQUIRED_FILES = [
    `${PACKAGE_ROOT}/package.json`,
    `${PACKAGE_ROOT}/README.md`,
    `${PACKAGE_ROOT}/LICENSE`,
    `${PACKAGE_ROOT}/dist/index.js`,
    `${PACKAGE_ROOT}/dist/index.d.ts`,
    `${PACKAGE_ROOT}/dist/runtime.js`,
    `${PACKAGE_ROOT}/dist/runtime.d.ts`,
    `${PACKAGE_ROOT}/dist/apis/index.js`,
    `${PACKAGE_ROOT}/dist/apis/index.d.ts`,
    `${PACKAGE_ROOT}/dist/models/index.js`,
    `${PACKAGE_ROOT}/dist/models/index.d.ts`,
    `${PACKAGE_ROOT}/dist/esm/index.js`,
    `${PACKAGE_ROOT}/dist/esm/index.d.ts`,
    `${PACKAGE_ROOT}/dist/esm/package.json`,
    `${PACKAGE_ROOT}/dist/esm/runtime.js`,
    `${PACKAGE_ROOT}/dist/esm/runtime.d.ts`,
    `${PACKAGE_ROOT}/dist/esm/apis/index.js`,
    `${PACKAGE_ROOT}/dist/esm/apis/index.d.ts`,
    `${PACKAGE_ROOT}/dist/esm/models/index.js`,
    `${PACKAGE_ROOT}/dist/esm/models/index.d.ts`,
];
const ALLOWED_ROOT_FILES = [
    `${PACKAGE_ROOT}/package.json`,
    `${PACKAGE_ROOT}/README.md`,
    `${PACKAGE_ROOT}/LICENSE`,
];
const DIST_PREFIX = `${PACKAGE_ROOT}/dist/`;

const BLOCK_SIZE = 512;

function readString(block, start, length) {
    const raw = block.subarray(start, start + length);
    const end = raw.indexOf(0);
    return raw.subarray(0, end === -1 ? raw.length : end).toString("utf8");
}

function listFileNames(tarball) {
    const archive = zlib.gunzipSync(fs.readFileSync(tarball));
    const names = [];

    for (let offset = 0; offset + BLOCK_SIZE <= archive.length; offset += BLOCK_SIZE) {
        const header = archive.subarray(offset, offset + BLOCK_SIZE);

        if (header.every((byte) => byte === 0)) {
            break;
        }

        const name = readString(header, 0, 100);
        const size = parseInt(readString(header, 124, 12).trim() || "0", 8);
        const typeFlag = readString(header, 156, 1);
        const prefix = readString(header, 345, 155);

        if (typeFlag === "" || typeFlag === "0") {
            names.push(prefix ? `${prefix}/${name}` : name);
        }

        offset += Math.ceil(size / BLOCK_SIZE) * BLOCK_SIZE;
    }

    return names;
}

function main(argv) {
    if (argv.length !== 1) {
        console.error("Usage: verify-typescript-tarball.js <tarball.tgz>");
        return 2;
    }

    const tarball = argv[0];
    if (!fs.existsSync(tarball) || !fs.statSync(tarball).isFile() || !tarball.endsWith(".tgz")) {
        console.error(`Tarball not found: ${tarball}`);
        return 2;
    }

    const names = new Set(listFileNames(tarball));

    const missing = REQUIRED_FILES.filter((name) => !names.has(name)).sort();
    if (missing.length > 0) {
        console.error(`Tarball is missing required files: ${missing.join(", ")}`);
        return 1;
    }

    const unexpected = [...names]
        .filter((name) => !ALLOWED_ROOT_FILES.includes(name) && !name.startsWith(DIST_PREFIX))
        .sort();
    if (unexpected.length > 0) {
        console.error(`Tarball contains unexpected package files: ${unexpected.slice(0, 10).join(", ")}`);
        return 1;
    }

    console.log(`Tarball verified: ${tarball}; ${names.size} files`);
    return 0;
}

process.exit(main(process.argv.slice(2)));
