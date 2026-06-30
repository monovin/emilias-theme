import assert from "node:assert/strict";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import test from "node:test";

import {
	extractSugarcubeVariables,
	synchronizeThemeJson,
	syncThemeJson,
} from "../scripts/sync-theme-json.mjs";

const css = `
:root {
	--emilias-color-base: #FFFFFF;
	--emilias-color-contrast: #111111;
	--emilias-color-accent-1: #FFEE58;
	--emilias-color-accent-2: #F6CFF4;
	--emilias-color-accent-3: #503AA8;
	--emilias-color-accent-4: #686868;
	--emilias-color-accent-5: #FBFAF3;
	--emilias-space-20: clamp(0.5rem, 0.41rem + 0.45vw, 0.75rem);
	--emilias-space-30: clamp(0.75rem, 0.61rem + 0.68vw, 1.125rem);
	--emilias-space-40: clamp(1rem, 0.82rem + 0.91vw, 1.5rem);
	--emilias-space-50: clamp(1.5rem, 1.23rem + 1.36vw, 2.25rem);
	--emilias-space-60: clamp(2.25rem, 1.84rem + 2.05vw, 3.375rem);
	--emilias-space-70: clamp(3.375rem, 2.76rem + 3.07vw, 5.0625rem);
	--emilias-space-80: clamp(5.0625rem, 4.14rem + 4.60vw, 7.5938rem);
	--emilias-font-size--1: clamp(0.8333rem, 0.83rem + 0.02vw, 0.844rem);
	--emilias-font-size-0: clamp(1rem, 0.95rem + 0.23vw, 1.125rem);
	--emilias-font-size-1: clamp(1.2rem, 1.09rem + 0.54vw, 1.4996rem);
	--emilias-font-size-2: clamp(1.44rem, 1.24rem + 1.02vw, 1.999rem);
	--emilias-font-size-3: clamp(1.728rem, 1.39rem + 1.70vw, 2.6647rem);
}
`;

function createTheme() {
	return {
		version: 3,
		settings: {
			color: {
				palette: [
					...[
						"base",
						"contrast",
						"accent-1",
						"accent-2",
						"accent-3",
						"accent-4",
						"accent-5",
					].map((slug) => ({ color: "old", name: slug, slug })),
					{
						color: "color-mix(in srgb, currentColor 20%, transparent)",
						name: "Accent 6",
						slug: "accent-6",
					},
				],
			},
			spacing: {
				spacingSizes: ["20", "30", "40", "50", "60", "70", "80"].map(
					(slug) => ({ name: slug, size: "old", slug }),
				),
			},
			typography: {
				fluid: true,
				fontFamilies: [{ name: "Keep me" }],
				fontSizes: ["small", "medium", "large", "x-large", "xx-large"].map(
					(slug) => ({ name: slug, size: "old", slug }),
				),
			},
		},
		styles: { color: { text: "preserved" } },
	};
}

test("maps Sugarcube variables to WordPress presets", () => {
	const variables = extractSugarcubeVariables(css);
	const theme = synchronizeThemeJson(createTheme(), variables);

	assert.deepEqual(
		theme.settings.color.palette.map(({ slug }) => slug),
		[
			"base",
			"contrast",
			"accent-1",
			"accent-2",
			"accent-3",
			"accent-4",
			"accent-5",
			"accent-6",
		],
	);
	assert.equal(
		theme.settings.color.palette.at(-1).color,
		"color-mix(in srgb, currentColor 20%, transparent)",
	);
	assert.deepEqual(
		theme.settings.spacing.spacingSizes.map(({ slug }) => slug),
		["20", "30", "40", "50", "60", "70", "80"],
	);
	assert.deepEqual(
		theme.settings.typography.fontSizes.map(({ slug }) => slug),
		["small", "medium", "large", "x-large", "xx-large"],
	);
	assert.equal(
		theme.settings.spacing.spacingSizes[0].size,
		"clamp(0.5rem, 0.41rem + 0.45vw, 0.75rem)",
	);
	assert.equal(
		theme.settings.spacing.spacingSizes[6].size,
		"clamp(5.0625rem, 4.14rem + 4.60vw, 7.5938rem)",
	);
	assert.equal(
		theme.settings.typography.fontSizes[0].size,
		"clamp(0.8333rem, 0.83rem + 0.02vw, 0.844rem)",
	);
	assert.equal(
		theme.settings.typography.fontSizes[4].size,
		"clamp(1.728rem, 1.39rem + 1.70vw, 2.6647rem)",
	);
	assert.equal(theme.settings.typography.fluid, false);
	assert.ok(theme.settings.typography.fontSizes.every(({ fluid }) => fluid === false));
	assert.deepEqual(theme.settings.typography.fontFamilies, [{ name: "Keep me" }]);
	assert.deepEqual(theme.styles, { color: { text: "preserved" } });
});

test("rejects missing, duplicate, and unexpected variables", () => {
	assert.throws(
		() => extractSugarcubeVariables(css.replace(/\s*--emilias-space-20:[^;]+;/, "")),
		/Missing Sugarcube variables: --emilias-space-20/,
	);
	assert.throws(
		() =>
			extractSugarcubeVariables(
				css.replace(":root {", ":root {\n--emilias-space-20: 1px;"),
			),
		/Duplicate Sugarcube variable --emilias-space-20/,
	);
	assert.throws(
		() =>
			extractSugarcubeVariables(
				css.replace(":root {", ":root {\n--emilias-color-extra: red;"),
			),
		/Unexpected Sugarcube variables: --emilias-color-extra/,
	);
});

test("rejects changed WordPress preset contracts", () => {
	const theme = createTheme();
	theme.settings.color.palette.at(-1).color = "red";

	assert.throws(
		() => synchronizeThemeJson(theme, extractSugarcubeVariables(css)),
		/accent-6 color has changed unexpectedly/,
	);
});

test("writes deterministically and detects stale output in check mode", async () => {
	const directory = await fs.mkdtemp(path.join(os.tmpdir(), "emilias-tokens-"));
	const cssPath = path.join(directory, "sugarcube.css");
	const themePath = path.join(directory, "theme.json");

	await fs.writeFile(cssPath, css);
	await fs.writeFile(themePath, `${JSON.stringify(createTheme(), null, "\t")}\n`);

	await assert.rejects(
		syncThemeJson({ check: true, cssPath, themePath }),
		/theme.json is stale/,
	);
	const first = await syncThemeJson({ cssPath, themePath });
	const second = await syncThemeJson({ cssPath, themePath });
	const checked = await syncThemeJson({ check: true, cssPath, themePath });

	assert.equal(first.changed, true);
	assert.equal(second.changed, false);
	assert.equal(checked.changed, false);
});
