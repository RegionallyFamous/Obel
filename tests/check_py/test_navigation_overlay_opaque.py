"""Tests for `check_navigation_overlay_opaque`.

WordPress core's `core/navigation` block ships a mobile overlay menu
that paints `background-color: inherit` by default. When a theme's
header doesn't carry an opaque paint of its own, the modal renders
transparent and the underlying page bleeds through behind the menu
items. The fix is to set `overlayBackgroundColor` + `overlayTextColor`
attributes on the navigation block so it emits its own opaque paint at
the right specificity. The check enforces that contract on every
`core/navigation` block in `parts/` and `templates/` whose
`overlayMenu` attribute opens the modal (`"mobile"` or `"always"`).
"""

from __future__ import annotations

NAV_OK = (
    '<!-- wp:navigation {"overlayMenu":"mobile",'
    '"overlayBackgroundColor":"base","overlayTextColor":"contrast"} -->\n'
    '<!-- wp:navigation-link {"label":"Shop","url":"/shop/"} /-->\n'
    "<!-- /wp:navigation -->\n"
)

NAV_MISSING_BG = (
    '<!-- wp:navigation {"overlayMenu":"mobile",'
    '"overlayTextColor":"contrast"} -->\n'
    "<!-- /wp:navigation -->\n"
)

NAV_MISSING_FG = (
    '<!-- wp:navigation {"overlayMenu":"mobile",'
    '"overlayBackgroundColor":"base"} -->\n'
    "<!-- /wp:navigation -->\n"
)

NAV_NO_ATTRS = '<!-- wp:navigation {"overlayMenu":"mobile"} -->\n<!-- /wp:navigation -->\n'

NAV_BAD_BG_SLUG = (
    '<!-- wp:navigation {"overlayMenu":"mobile",'
    '"overlayBackgroundColor":"chartreuse","overlayTextColor":"contrast"} -->\n'
    "<!-- /wp:navigation -->\n"
)

NAV_NEVER = '<!-- wp:navigation {"overlayMenu":"never"} -->\n<!-- /wp:navigation -->\n'

NAV_ALWAYS_OK = (
    '<!-- wp:navigation {"overlayMenu":"always",'
    '"overlayBackgroundColor":"base","overlayTextColor":"contrast"} -->\n'
    "<!-- /wp:navigation -->\n"
)


def _write_header(theme_root, body: str) -> None:
    (theme_root / "parts" / "header.html").write_text(body, encoding="utf-8")


def test_passes_when_overlay_attrs_are_palette_tokens(minimal_theme, bind_check_root):
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_OK)
    result = check.check_navigation_overlay_opaque()
    assert result.passed, result.details


def test_fails_when_overlay_background_missing(minimal_theme, bind_check_root):
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_MISSING_BG)
    result = check.check_navigation_overlay_opaque()
    assert not result.passed
    assert any("overlayBackgroundColor" in d for d in result.details)


def test_fails_when_overlay_text_missing(minimal_theme, bind_check_root):
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_MISSING_FG)
    result = check.check_navigation_overlay_opaque()
    assert not result.passed
    assert any("overlayTextColor" in d for d in result.details)


def test_fails_when_both_overlay_attrs_missing(minimal_theme, bind_check_root):
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_NO_ATTRS)
    result = check.check_navigation_overlay_opaque()
    assert not result.passed
    joined = "\n".join(result.details)
    assert "overlayBackgroundColor" in joined
    assert "overlayTextColor" in joined


def test_fails_when_overlay_bg_is_not_palette_slug(minimal_theme, bind_check_root):
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_BAD_BG_SLUG)
    result = check.check_navigation_overlay_opaque()
    assert not result.passed
    assert any("chartreuse" in d for d in result.details)


def test_skips_overlay_check_when_overlay_is_never(minimal_theme, bind_check_root):
    """`overlayMenu: "never"` means the modal never opens — no paint
    needed; the check should pass without flagging the missing attrs."""
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_NEVER)
    result = check.check_navigation_overlay_opaque()
    assert result.passed, result.details


def test_passes_for_always_overlay_with_attrs(minimal_theme, bind_check_root):
    """`overlayMenu: "always"` always opens the modal on every breakpoint;
    attrs are just as required as for `"mobile"`."""
    check = bind_check_root(minimal_theme)
    _write_header(minimal_theme, NAV_ALWAYS_OK)
    result = check.check_navigation_overlay_opaque()
    assert result.passed, result.details


def test_skips_when_no_navigation_blocks_present(minimal_theme, bind_check_root):
    """Minimal header has no nav block at all — check has nothing to
    enforce, should skip cleanly."""
    check = bind_check_root(minimal_theme)
    result = check.check_navigation_overlay_opaque()
    assert result.skipped, result.details
