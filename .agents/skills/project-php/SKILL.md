---
name: project-php
description: Use for this WordPress theme project whenever Codex needs project-specific PHP CLI access, PHP syntax linting, WordPress theme PHP validation, or stylesheet build conventions. Provides the Local.app bundled PHP binary path, preferred commands for linting project PHP files, and the style.css to style.min.css build relationship.
---

# Project PHP

Use the Local.app bundled PHP binary for this project instead of assuming `php` is on `PATH`.

## PHP Binary

Primary binary:

```bash
"$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php"
```

Known fallback binary:

```bash
"$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php"
```

Use the primary path first. If it is missing, check for another Local.app PHP binary with:

```bash
find "$HOME/Library/Application Support/Local/lightning-services" -type f -perm +111 -name php 2>/dev/null | sort
```

## Linting Workflow

Lint individual PHP files with:

```bash
"$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php" -l functions.php
"$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php" -l patterns/template-query-loop-masonry.php
```

For broader theme checks, lint tracked PHP files from the theme root:

```bash
git ls-files '*.php' | xargs -I {} "$HOME/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin-arm64/bin/php" -l "{}"
```

If `git ls-files` omits new PHP files, include those files explicitly.

## Stylesheet Build Output

Treat `style.css` as the source stylesheet. Treat `style.min.css` as generated output from `style.css`; do not manually edit both files for the same CSS change.

After changing `style.css`, regenerate `style.min.css` from the theme root with:

```bash
npm run build:css
```

Use the full build when token or LQIP outputs should also be refreshed:

```bash
npm run build
```

The relevant npm scripts are:

```json
"build": "npm run tokens:generate && npm run lqip:generate && npm run build:css",
"build:css": "postcss style.css --use cssnano -o style.min.css --no-map"
```
