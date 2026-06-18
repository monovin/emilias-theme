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
	--emilias-space-20: 10px;
	--emilias-space-30: 20px;
	--emilias-space-40: 30px;
	--emilias-space-50: clamp(1rem, 2vw, 2rem);
	--emilias-space-60: clamp(2rem, 3vw, 3rem);
	--emilias-space-70: clamp(3rem, 4vw, 4rem);
	--emilias-space-80: clamp(4rem, 5vw, 5rem);
	--emilias-font-size-small: 0.875rem;
	--emilias-font-size-medium: clamp(1rem, 1vw, 1.125rem);
	--emilias-font-size-large: clamp(1.125rem, 2vw, 1.375rem);
	--emilias-font-size-x-large: clamp(1.75rem, 3vw, 2rem);
	--emilias-font-size-xx-large: clamp(2.15rem, 4vw, 3rem);
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
	assert.equal(theme.settings.spacing.spacingSizes[3].size, "clamp(1rem, 2vw, 2rem)");
	assert.equal(theme.settings.typography.fontSizes[1].size, "clamp(1rem, 1vw, 1.125rem)");
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
