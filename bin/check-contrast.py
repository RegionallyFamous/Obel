#!/usr/bin/env python3
"""Check a theme.json color palette against WCAG AA contrast pairings.

Usage:
    python3 bin/check-contrast.py            # cwd theme
    python3 bin/check-contrast.py obel       # named theme
    python3 bin/check-contrast.py --all      # every theme in the monorepo

Exits 0 if every required pairing meets WCAG AA, 1 otherwise.
"""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from _lib import iter_themes, resolve_theme_root  # noqa: E402


# (foreground_slug, background_slug, min_ratio, where_it_appears)
REQUIRED_PAIRS = [
    ("contrast", "base",     4.5, "Body text on page background"),
    ("contrast", "surface",  4.5, "Body text on cards / hero blocks"),
    ("contrast", "subtle",   4.5, "Body text on muted strips"),
    ("contrast", "accent",   4.5, "Body text on accent panels"),
    ("contrast", "accent-2", 4.5, "Body text on accent-2 panels"),
    ("accent",   "contrast", 4.5, "Accent text reversed on contrast"),
    ("base",     "contrast", 4.5, "Reversed footer / dark sections"),
    ("secondary", "base",    4.5, "Secondary text on page background"),
    ("secondary", "surface", 4.5, "Secondary text on cards"),
    ("tertiary",  "base",    4.5, "Tertiary / meta text on page bg"),
    ("tertiary",  "surface", 4.5, "Tertiary / meta text on cards"),
    ("border",    "base",    3.0, "Borders / dividers on page bg"),
]


def hex_to_rgb(value: str) -> tuple[int, int, int]:
    s = value.lstrip("#")
    if len(s) == 3:
        s = "".join(ch * 2 for ch in s)
    if len(s) != 6:
        raise ValueError(f"Not a 6-digit hex color: {value!r}")
    return int(s[0:2], 16), int(s[2:4], 16), int(s[4:6], 16)


def relative_luminance(rgb: tuple[int, int, int]) -> float:
    def channel(c: int) -> float:
        v = c / 255.0
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    r, g, b = rgb
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)


def contrast_ratio(fg_hex: str, bg_hex: str) -> float:
    l1 = relative_luminance(hex_to_rgb(fg_hex))
    l2 = relative_luminance(hex_to_rgb(bg_hex))
    lighter, darker = max(l1, l2), min(l1, l2)
    return (lighter + 0.05) / (darker + 0.05)


def load_palette(theme_json_path: Path) -> dict[str, str]:
    data = json.loads(theme_json_path.read_text())
    palette = data.get("settings", {}).get("color", {}).get("palette", [])
    return {item["slug"]: item["color"] for item in palette if "slug" in item and "color" in item}


def check_palette(theme_root: Path) -> int:
    theme_json = theme_root / "theme.json"
    if not theme_json.exists():
        print(f"theme.json not found at {theme_json}", file=sys.stderr)
        return 2
    palette = load_palette(theme_json)
    if not palette:
        print(f"No color palette in {theme_root.name}/theme.json", file=sys.stderr)
        return 2

    failures: list[str] = []
    skipped: list[str] = []
    print(f"== {theme_root.name} ==")
    print(f"Checking {len(REQUIRED_PAIRS)} pairings against WCAG AA")
    print(f"Palette slugs: {', '.join(sorted(palette.keys()))}")
    print()

    for fg, bg, target, where in REQUIRED_PAIRS:
        if fg not in palette or bg not in palette:
            skipped.append(f"  - {fg} on {bg} ({where}): slug missing from palette")
            continue
        ratio = contrast_ratio(palette[fg], palette[bg])
        status = "PASS" if ratio >= target else "FAIL"
        line = f"{status:4} {ratio:5.2f}:1 (need {target}:1)  {fg:9} on {bg:9}  — {where}"
        if status == "FAIL":
            failures.append(line)
        print(line)

    print()
    if skipped:
        print("Skipped (slug not in palette):")
        for s in skipped:
            print(s)
        print()

    if failures:
        print(f"FAILED: {len(failures)} pairing(s) below WCAG AA in {theme_root.name}:")
        for f in failures:
            print("  " + f)
        return 1

    print(f"All required pairings pass WCAG AA in {theme_root.name}.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("theme", nargs="?", default=None, help="Theme directory name.")
    parser.add_argument("--all", action="store_true", help="Run against every theme.")
    args = parser.parse_args()

    if args.all:
        codes = []
        for t in iter_themes():
            codes.append(check_palette(t))
            print()
        return 1 if any(codes) else 0

    return check_palette(resolve_theme_root(args.theme))


if __name__ == "__main__":
    sys.exit(main())
