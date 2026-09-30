/**
 * Minifies a JavaScript file using Terser (installed as a devDependency).
 *
 * Usage: node scripts/minify-js.mjs <input> <output>
 *
 * Example:
 *   node scripts/minify-js.mjs assets/js/post-template-masonry.js assets/js/post-template-masonry.min.js
 */

import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { minify } from "terser";

const [, , inputArg, outputArg] = process.argv;

if (!inputArg || !outputArg) {
	console.error(
		"Usage: node scripts/minify-js.mjs <input.js> <output.min.js>",
	);
	process.exit(1);
}

const ROOT_DIR = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	"..",
);

const inputPath = path.resolve(ROOT_DIR, inputArg);
const outputPath = path.resolve(ROOT_DIR, outputArg);

const source = await readFile(inputPath, "utf8");

const result = await minify(source, {
	compress: {
		passes: 2,
	},
	mangle: true,
	format: {
		comments: false,
	},
});

if (!result.code) {
	console.error("Minification produced no output.");
	process.exit(1);
}

await writeFile(outputPath, result.code, "utf8");

const inputSize = Buffer.byteLength(source, "utf8");
const outputSize = Buffer.byteLength(result.code, "utf8");
const saving = (((inputSize - outputSize) / inputSize) * 100).toFixed(1);

console.log(
	`✓ ${path.relative(ROOT_DIR, inputPath)} → ${path.relative(ROOT_DIR, outputPath)}`,
);
console.log(
	`  ${inputSize} B → ${outputSize} B  (${saving}% smaller)`,
);
