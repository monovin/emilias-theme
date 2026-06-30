import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

import postcss from "postcss";

const ROOT_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

const COLOR_PRESETS = [
	{ name: "Base", slug: "base", variable: "--emilias-color-base" },
	{ name: "Contrast", slug: "contrast", variable: "--emilias-color-contrast" },
	{ name: "Accent 1", slug: "accent-1", variable: "--emilias-color-accent-1" },
	{ name: "Accent 2", slug: "accent-2", variable: "--emilias-color-accent-2" },
	{ name: "Accent 3", slug: "accent-3", variable: "--emilias-color-accent-3" },
	{ name: "Accent 4", slug: "accent-4", variable: "--emilias-color-accent-4" },
	{ name: "Accent 5", slug: "accent-5", variable: "--emilias-color-accent-5" },
];

const DERIVED_COLOR_PRESET = {
	color: "color-mix(in srgb, currentColor 20%, transparent)",
	name: "Accent 6",
	slug: "accent-6",
};

const SPACING_PRESETS = [
	{ name: "Tiny", slug: "20", variable: "--emilias-space-20" },
	{ name: "X-Small", slug: "30", variable: "--emilias-space-30" },
	{ name: "Small", slug: "40", variable: "--emilias-space-40" },
	{ name: "Regular", slug: "50", variable: "--emilias-space-50" },
	{ name: "Large", slug: "60", variable: "--emilias-space-60" },
	{ name: "X-Large", slug: "70", variable: "--emilias-space-70" },
	{ name: "XX-Large", slug: "80", variable: "--emilias-space-80" },
];

const FONT_SIZE_PRESETS = [
	{ name: "Small", slug: "small", variable: "--emilias-font-size--1" },
	{ name: "Medium", slug: "medium", variable: "--emilias-font-size-0" },
	{ name: "Large", slug: "large", variable: "--emilias-font-size-1" },
	{ name: "Extra Large", slug: "x-large", variable: "--emilias-font-size-2" },
	{ name: "Extra Extra Large", slug: "xx-large", variable: "--emilias-font-size-3" },
];

const EXPECTED_VARIABLES = new Set(
	[...COLOR_PRESETS, ...SPACING_PRESETS, ...FONT_SIZE_PRESETS].map(
		(preset) => preset.variable,
	),
);

function assertPresetSlugs(presets, expectedSlugs, label) {
	if (!Array.isArray(presets)) {
		throw new Error(`theme.json is missing ${label} presets.`);
	}

	const actualSlugs = presets.map((preset) => preset.slug);
	if (JSON.stringify(actualSlugs) !== JSON.stringify(expectedSlugs)) {
		throw new Error(
			`Unexpected ${label} slugs. Expected ${expectedSlugs.join(", ")}; received ${actualSlugs.join(", ") || "none"}.`,
		);
	}
}

function presetValue(variables, preset) {
	const value = variables.get(preset.variable);
	if (value === undefined) {
		throw new Error(`Missing Sugarcube variable ${preset.variable}.`);
	}

	return value;
}

function detectJsonIndent(json) {
	const match = json.match(/\n([ \t]+)"/);

	return match?.[1] ?? "\t";
}

export function extractSugarcubeVariables(css) {
	const root = postcss.parse(css);
	const variables = new Map();

	root.walkRules((rule) => {
		if (!rule.selectors.includes(":root")) {
			return;
		}

		rule.walkDecls(/^--emilias-/, (declaration) => {
			if (variables.has(declaration.prop)) {
				throw new Error(`Duplicate Sugarcube variable ${declaration.prop}.`);
			}

			variables.set(declaration.prop, declaration.value);
		});
	});

	const unexpected = [...variables.keys()].filter(
		(variable) => !EXPECTED_VARIABLES.has(variable),
	);
	if (unexpected.length > 0) {
		throw new Error(`Unexpected Sugarcube variables: ${unexpected.join(", ")}.`);
	}

	const missing = [...EXPECTED_VARIABLES].filter(
		(variable) => !variables.has(variable),
	);
	if (missing.length > 0) {
		throw new Error(`Missing Sugarcube variables: ${missing.join(", ")}.`);
	}

	return variables;
}

export function synchronizeThemeJson(theme, variables) {
	const nextTheme = structuredClone(theme);
	const { settings } = nextTheme;

	if (!settings?.color || !settings?.spacing || !settings?.typography) {
		throw new Error("theme.json must define color, spacing, and typography settings.");
	}

	assertPresetSlugs(
		settings.color.palette,
		[...COLOR_PRESETS.map((preset) => preset.slug), DERIVED_COLOR_PRESET.slug],
		"color",
	);
	assertPresetSlugs(
		settings.spacing.spacingSizes,
		SPACING_PRESETS.map((preset) => preset.slug),
		"spacing",
	);
	assertPresetSlugs(
		settings.typography.fontSizes,
		FONT_SIZE_PRESETS.map((preset) => preset.slug),
		"font-size",
	);

	const currentDerivedColor = settings.color.palette.at(-1);
	if (currentDerivedColor.color !== DERIVED_COLOR_PRESET.color) {
		throw new Error("The WordPress-owned accent-6 color has changed unexpectedly.");
	}

	settings.color.palette = [
		...COLOR_PRESETS.map((preset) => ({
			color: presetValue(variables, preset),
			name: preset.name,
			slug: preset.slug,
		})),
		{ ...DERIVED_COLOR_PRESET },
	];

	settings.spacing.spacingSizes = SPACING_PRESETS.map((preset) => ({
		name: preset.name,
		size: presetValue(variables, preset),
		slug: preset.slug,
	}));

	settings.typography.fluid = false;
	settings.typography.fontSizes = FONT_SIZE_PRESETS.map((preset) => ({
		fluid: false,
		name: preset.name,
		size: presetValue(variables, preset),
		slug: preset.slug,
	}));

	return nextTheme;
}

export async function syncThemeJson({
	check = false,
	cssPath = path.join(ROOT_DIR, ".generated/sugarcube.css"),
	themePath = path.join(ROOT_DIR, "theme.json"),
} = {}) {
	const [css, currentJson] = await Promise.all([
		fs.readFile(cssPath, "utf8"),
		fs.readFile(themePath, "utf8"),
	]);
	const currentTheme = JSON.parse(currentJson);
	const variables = extractSugarcubeVariables(css);
	const nextTheme = synchronizeThemeJson(currentTheme, variables);
	const nextJson = `${JSON.stringify(nextTheme, null, detectJsonIndent(currentJson))}\n`;
	const changed = currentJson !== nextJson;

	if (check && changed) {
		throw new Error("theme.json is stale. Run npm run tokens:generate.");
	}

	if (!check && changed) {
		await fs.writeFile(themePath, nextJson);
	}

	return { changed, theme: nextTheme };
}

const isMain =
	process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isMain) {
	try {
		const check = process.argv.slice(2).includes("--check");
		const { changed } = await syncThemeJson({ check });
		console.log(
			check
				? "theme.json matches the Sugarcube tokens."
				: changed
					? "Updated theme.json from Sugarcube tokens."
					: "theme.json is already up to date.",
		);
	} catch (error) {
		console.error(error instanceof Error ? error.message : error);
		process.exitCode = 1;
	}
}
