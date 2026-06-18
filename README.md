# Emilias Theme

This WordPress block theme uses [Sugarcube](https://sugarcube.sh/) design tokens
as the source of truth for its default color, spacing, and font-size presets.

## Requirements

- Node.js 20.19 or newer
- npm 10.2.3 or newer
- A local WordPress installation for browser testing

Install the development dependencies:

```sh
npm install
```

## Token workflow

Edit the appropriate source file:

| Presets | Source |
| --- | --- |
| `base`, `contrast`, `accent-1` through `accent-5` | `tokens/colors.json` |
| Spacing `20` through `80` | `tokens/spacing.json` |
| Font sizes `small` through `xx-large` | `tokens/typography.json` |

Then generate the WordPress presets:

```sh
npm run tokens:generate
```

This command validates the tokens, asks Sugarcube to generate CSS variables,
and synchronizes the generated values into `theme.json`.

Do not manually edit these generated sections in `theme.json`:

- `settings.color.palette`
- `settings.spacing.spacingSizes`
- `settings.typography.fontSizes`

Any manual changes there will be replaced the next time tokens are generated.

## How generation works

```text
tokens/*.json
    -> Sugarcube
    -> .generated/sugarcube.css
    -> scripts/sync-theme-json.mjs
    -> theme.json
    -> WordPress editor and front end
```

`.generated/sugarcube.css` is an ignored intermediate file. It is not enqueued
by WordPress. WordPress serves its normal `--wp--preset--*` custom properties
from the generated `theme.json` values.

Sugarcube calculates fluid spacing and type over a viewport range of 320px to
1200px, configured in `sugarcube.config.js`.

## Fluid values

Fluid spacing and font-size tokens use Sugarcube's `sh.sugarcube.fluid`
extension. The token keeps a static fallback in `$value` and defines its fluid
endpoints separately:

```json
{
	"$value": {
		"value": 1,
		"unit": "rem"
	},
	"$extensions": {
		"sh.sugarcube": {
			"fluid": {
				"min": {
					"value": 1,
					"unit": "rem"
				},
				"max": {
					"value": 1.125,
					"unit": "rem"
				}
			}
		}
	}
}
```

The generated `clamp()` value is written directly to `theme.json`. WordPress's
own fluid calculation is disabled so it does not alter Sugarcube's curve.

## The `accent-6` exception

`accent-6` is not a concrete color. It depends on the current text color:

```css
color-mix(in srgb, currentColor 20%, transparent)
```

It remains WordPress-owned inside `scripts/sync-theme-json.mjs`. The sync script
appends it after the Sugarcube-owned palette and fails if its value changes
unexpectedly.

## Available commands

| Command | Purpose |
| --- | --- |
| `npm run tokens:validate` | Validate the DTCG token files. |
| `npm run tokens:css` | Generate the ignored Sugarcube CSS file. |
| `npm run tokens:sync` | Synchronize generated CSS values into `theme.json`. |
| `npm run tokens:generate` | Validate, generate, and synchronize in one command. |
| `npm run tokens:check` | Fail when `theme.json` is stale or tokens are invalid. |
| `npm test` | Run the synchronization tests. |
| `npm run build` | Generate tokens and build the production stylesheet. |

Before committing token changes, run:

```sh
npm run tokens:check
npm test
npm run build
```

## Adding or removing a preset

The synchronization script deliberately enforces the existing WordPress preset
contract. Adding a token alone will fail as an unexpected Sugarcube variable.

To change the preset set:

1. Add or remove the token in `tokens/`.
2. Update the corresponding preset mapping in
   `scripts/sync-theme-json.mjs`.
3. Preserve existing slugs whenever saved WordPress content may reference them.
4. Update the synchronization tests.
5. Run `npm run tokens:generate` and the verification commands above.

## Style variations

The theme intentionally has one global design. Alternate global color and
typography themes are not included. Reusable block and section variations live
under `styles/blocks/` and `styles/sections/` and remain authored as WordPress
JSON files.
