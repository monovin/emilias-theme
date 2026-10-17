#!/usr/bin/env python3
"""Write legacy copies of the subset webfonts under their pre-subset filenames.

The theme fonts are served with max-age=315360000 and an expires date in 2037.
WordPress inlines the @font-face rules into the HTML, and on this site that HTML
sits behind a server-level page cache that has been holding a pre-rename copy of
the global styles. Those cached rules still name the original filenames, so they
either resolve to the much larger originals or, once a file has been removed
outright, to nothing at all.

The @font-face rules are generated per request from whichever faces a page
actually uses, so different pages can name different weights. Copying only the
faces seen on the pages that were audited would leave any page using another
weight pointing at a file that does not exist. Every subset file therefore gets a
legacy copy, and the legacy name carries the subset bytes.

This is a stopgap. Once the page cache is purged and the @font-face rules carry
the -subset names, delete these copies and remove this script.

Usage:
    python3 scripts/font-legacy-aliases.py            # write the legacy copies
    python3 scripts/font-legacy-aliases.py --remove   # delete them again
"""

from __future__ import annotations

import argparse
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
FONT_DIRS = ("ibm-plex-sans", "bricolage-grotesque")
SUFFIX = "-subset.woff2"


def target_paths() -> list[tuple[pathlib.Path, pathlib.Path]]:
    """Pair each subset font with the legacy name it should also answer to."""
    pairs: list[tuple[pathlib.Path, pathlib.Path]] = []

    for name in FONT_DIRS:
        directory = ROOT / "assets" / "fonts" / name

        if not directory.is_dir():
            print(f"skipping missing directory {directory}", file=sys.stderr)
            continue

        for subset in sorted(directory.glob("*" + SUFFIX)):
            legacy = directory / (subset.name[: -len(SUFFIX)] + ".woff2")
            pairs.append((subset, legacy))

    return pairs


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--remove",
        action="store_true",
        help="delete the legacy copies instead of writing them",
    )
    args = parser.parse_args()

    pairs = target_paths()

    if not pairs:
        print("no subset fonts found; nothing to do")
        return 1

    if args.remove:
        removed = 0
        for _, legacy in pairs:
            if legacy.exists():
                legacy.unlink()
                removed += 1
        print(f"removed {removed} legacy font copies")
        return 0

    written = 0
    for subset, legacy in pairs:
        subset_bytes = subset.read_bytes()
        if legacy.exists() and legacy.read_bytes() == subset_bytes:
            continue
        legacy.write_bytes(subset_bytes)
        written += 1
        print(f"  {legacy.name:<44} {len(subset_bytes):>7,} B")

    print()
    print(f"{written} legacy copies written, {len(pairs)} total in place")
    print(f"legacy font bytes: {sum(p.stat().st_size for _, p in pairs):,}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())