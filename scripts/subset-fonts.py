#!/usr/bin/env python3
"""Subset the theme's static webfonts to a Latin character set.

The shipped IBM Plex Sans and Bricolage Grotesque files carry the full upstream
character set, which makes a single weight around 70-90 KB. Only Latin text is
used on this site, so a subset keeps roughly a fifth of the bytes for the same
rendering. The unicode range below covers Latin-1 Supplement, Latin Extended-A,
general punctuation, currency and arrows, so Finnish and Swedish characters such
as a, o, A and O render correctly.

Variable fonts are skipped: they are not fetched on the audited pages and
retaining their weight axis adds risk for no measured gain.

The unsubsetted originals stay in git history, so this is safe to revert.

Usage:
    python3 scripts/subset-fonts.py            # subset in place
    python3 scripts/subset-fonts.py --check    # report without writing
"""

from __future__ import annotations

import argparse
import pathlib
import sys

from fontTools import subset

ROOT = pathlib.Path(__file__).resolve().parent.parent
FONT_DIRS = ("ibm-plex-sans", "bricolage-grotesque")

# Latin-1, Latin Extended-A, combining diacritics, punctuation, symbols.
UNICODES = (
    "U+0000-00FF,"
    "U+0131,U+0152-0153,"
    "U+0100-017F,"
    "U+02BB-02BC,U+02C6,U+02DA,U+02DC,"
    "U+0300-036F,"
    "U+1E00-1EFF,"
    "U+2000-206F,U+2074,U+20AC,U+2116,U+2122,"
    "U+2190-2199,U+221A,U+2248,"
    "U+FEFF,U+FFFD"
)

FEATURES = ("kern", "liga", "clig", "calt")


def font_files() -> list[pathlib.Path]:
    files: list[pathlib.Path] = []

    for name in FONT_DIRS:
        directory = ROOT / "assets" / "fonts" / name
        if not directory.is_dir():
            print(f"skipping missing directory {directory}", file=sys.stderr)
            continue
        files.extend(sorted(directory.glob("*.woff2")))

    return files


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--check",
        action="store_true",
        help="report what would change without writing any file",
    )
    args = parser.parse_args()

    total_before = total_after = 0
    rows: list[tuple[str, int, int]] = []

    for path in font_files():
        before = path.stat().st_size
        options = subset.Options()
        options.flavor = "woff2"
        options.layout_features = list(FEATURES)
        options.hinting = False
        options.desubroutinize = True
        options.name_IDs = [0, 1, 2, 3, 4, 5, 6]
        options.notdef_outline = True
        options.recalc_bounds = True
        options.drop_tables = []

        font = subset.load_font(str(path), options)
        subsetter = subset.Subsetter(options=options)
        subsetter.populate(unicodes=subset.parse_unicodes(UNICODES))
        subsetter.subset(font)
        subset.save_font(font, str(path), options)
        font.close()

        after = path.stat().st_size
        rows.append((path.name, before, after))
        total_before += before
        total_after += after

        if not args.check:
            print(f"  {path.name:<44} {before:>7,} -> {after:>7,} B")

    print()
    print(f"{'total':<44} {total_before:>7,} -> {total_after:>7,} B")
    saved = total_before - total_after
    print(
        f"saved {saved:,} bytes "
        f"({saved / total_before * 100:.0f}% smaller) across {len(rows)} files"
    )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())