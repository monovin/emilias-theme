import { createHash } from "node:crypto";
import { mkdir, readFile, readdir, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

import sharp from "sharp";

const ROOT_DIR = path.resolve(
	path.dirname(fileURLToPath(import.meta.url)),
	"..",
);
const IMAGES_DIR = path.join(ROOT_DIR, "assets", "images");
const OUTPUT_PATH = path.join(ROOT_DIR, "assets", "generated", "lqip.json");
const PLACEHOLDER_SIZE = 24;
const PLACEHOLDER_QUALITY = 24;
const SUPPORTED_EXTENSIONS = new Set([
	".avif",
	".jpg",
	".jpeg",
	".png",
	".webp",
]);

async function findImages(directory) {
	const entries = await readdir(directory, { withFileTypes: true });
	const files = [];

	for (const entry of entries) {
		const fullPath = path.join(directory, entry.name);

		if (entry.isDirectory()) {
			files.push(...(await findImages(fullPath)));
			continue;
		}

		if (SUPPORTED_EXTENSIONS.has(path.extname(entry.name).toLowerCase())) {
			files.push(fullPath);
		}
	}

	return files.sort();
}

async function readExistingManifest() {
	try {
		return JSON.parse(await readFile(OUTPUT_PATH, "utf8"));
	} catch {
		return { version: 1, images: {} };
	}
}

function manifestKey(filePath) {
	return `/${path.relative(ROOT_DIR, filePath).split(path.sep).join("/")}`;
}

function hashBuffer(buffer) {
	return createHash("sha256").update(buffer).digest("hex");
}

async function createPlaceholder(buffer) {
	const metadata = await sharp(buffer).metadata();
	const placeholder = await sharp(buffer)
		.resize({
			fit: "inside",
			height: PLACEHOLDER_SIZE,
			width: PLACEHOLDER_SIZE,
			withoutEnlargement: true,
		})
		.webp({ effort: 4, quality: PLACEHOLDER_QUALITY })
		.toBuffer();

	return {
		base64: `data:image/webp;base64,${placeholder.toString("base64")}`,
		height: metadata.height ?? 0,
		width: metadata.width ?? 0,
	};
}

function hasReusablePlaceholder(previous, stats, hash) {
	return (
		previous?.base64 &&
		previous?.size === stats.size &&
		previous?.hash === hash &&
		Number.isFinite(previous?.height) &&
		Number.isFinite(previous?.width)
	);
}

async function generateManifest() {
	const existingManifest = await readExistingManifest();
	const existingImages = existingManifest.images ?? {};
	const nextImages = {};
	const files = await findImages(IMAGES_DIR);
	let generated = 0;

	for (const filePath of files) {
		const key = manifestKey(filePath);
		const stats = await stat(filePath);
		const buffer = await readFile(filePath);
		const hash = hashBuffer(buffer);
		const previous = existingImages[key];

		if (hasReusablePlaceholder(previous, stats, hash)) {
			nextImages[key] = previous;
			continue;
		}

		nextImages[key] = {
			...(await createPlaceholder(buffer)),
			hash,
			size: stats.size,
		};
		generated += 1;
	}

	return {
		files,
		generated,
		manifest: { images: nextImages, version: 1 },
	};
}

const check = process.argv.includes("--check");
const { files, generated, manifest } = await generateManifest();
const nextJson = `${JSON.stringify(manifest, null, "\t")}\n`;

if (check) {
	const currentJson = await readFile(OUTPUT_PATH, "utf8").catch(() => "");

	if (currentJson !== nextJson) {
		console.error(
			"assets/generated/lqip.json is stale. Run npm run lqip:generate.",
		);
		process.exitCode = 1;
	} else {
		console.log(`LQIP manifest is up to date for ${files.length} images.`);
	}
} else {
	await mkdir(path.dirname(OUTPUT_PATH), { recursive: true });
	await writeFile(OUTPUT_PATH, nextJson);
	console.log(
		`Generated ${generated} LQIP placeholder${generated === 1 ? "" : "s"} for ${files.length} image${files.length === 1 ? "" : "s"}.`,
	);
}
