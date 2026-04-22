"""Tests for `bin/check.py:check_no_brand_filters_in_playground`.

The `playground/` directory is for boot-time setup that has no analogue
on a real WordPress install: WXR import, WC catalogue seeding, demo
cart pre-fill. Anything that affects what a real shopper sees on a
released theme MUST live in the theme directory, otherwise the
override travels with the demo rig and silently disappears when a
Proprietor downloads the theme and drops it into `wp-content/themes/`.

Two parallel guards live in `check_no_brand_filters_in_playground`:

  1. **Hook denylist.** `add_filter` / `add_action` calls against any
     of the brand-affecting hooks (gettext, sort labels, pagination,
     the six WC empty/login/archive/swatches surfaces migrated out of
     `wo-pages-mu.php` + `wo-swatches-mu.php`, and `body_class`) fail
     the gate, name the file, name the hook, and point at the
     per-theme `// === BEGIN <slug> ===` sentinel where the override
     belongs.

  2. **Marker-class scanner.** Even without a hook registration, any
     hardcoded reference to a per-theme paint marker
     (`wo-empty`, `wo-account-`, `wo-archive-hero`, `wo-swatch`,
     `wo-payment-icons`) inside `playground/*.php` would mean a
     mu-plugin runtime injection or a `wo-configure.php` HEREDOC is
     painting brand markup from outside the theme directory. Both fail
     the gate.

The check uses `MONOREPO_ROOT / "playground"`, so these tests use the
`monorepo` fixture (which monkeypatches `_lib.MONOREPO_ROOT` to a
synthetic two-theme repo) and write fake `playground/*.php` files
under that fake root.
"""

from __future__ import annotations

import textwrap
from pathlib import Path

PHP_HEADER = "<?php\n/** Synthetic playground/*.php for testing the brand-filter gate. */\n"


def _seed_playground_php(monorepo: dict[str, Path], filename: str, body: str) -> Path:
    """Write a single `playground/<filename>.php` and return its path."""
    pg_dir: Path = monorepo["root"] / "playground"
    pg_dir.mkdir(exist_ok=True)
    path = pg_dir / filename
    path.write_text(PHP_HEADER + body, encoding="utf-8")
    return path


def _check(monorepo: dict[str, Path]):
    """Import check.py with `_lib.MONOREPO_ROOT` already patched."""
    import check  # noqa: WPS433

    return check


# ---------------------------------------------------------------------------
# Baseline: empty playground, no playground dir, allowlist guard.
# ---------------------------------------------------------------------------
def test_passes_when_playground_is_empty(monorepo):
    """No `playground/*.php` to scan — gate is silent and passes."""
    (monorepo["root"] / "playground").mkdir(exist_ok=True)
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.passed, result.details


def test_skips_when_playground_dir_is_missing(monorepo):
    """A repo without a playground/ directory has nothing to enforce."""
    pg = monorepo["root"] / "playground"
    if pg.exists():
        for child in pg.iterdir():
            child.unlink()
        pg.rmdir()
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.skipped, result.details


def test_passes_when_only_safe_boot_hooks_register(monorepo):
    """`init`, `wp_loaded`, `pre_get_posts` etc. are not on the
    denylist — they're legitimate boot-time hooks the seed step needs."""
    _seed_playground_php(
        monorepo,
        "wo-configure.php",
        textwrap.dedent(
            """\
            add_action( 'init', static function () { /* seed catalogue */ } );
            add_action( 'wp_loaded', static function () { /* prime cart */ } );
            add_filter( 'pre_get_posts', static function ( $q ) { return $q; } );
            """
        ),
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.passed, result.details


# ---------------------------------------------------------------------------
# Hook denylist — every hook the migration killed.
# ---------------------------------------------------------------------------
def test_fails_on_gettext_filter_in_playground(monorepo):
    """`gettext` is the original `wo-microcopy-mu.php` failure mode."""
    _seed_playground_php(
        monorepo,
        "wo-microcopy-mu.php",
        "add_filter( 'gettext', static function ( $t ) { return $t; }, 10, 3 );\n",
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    assert "wo-microcopy-mu.php" in joined
    assert "gettext" in joined
    assert "BEGIN wc microcopy" in joined or "<theme>/functions.php" in joined


def test_fails_on_each_migrated_pages_mu_hook(monorepo):
    """Every hook that migrated out of `wo-pages-mu.php` (login chrome,
    empty cart, no products, archive hero, body class) must fail."""
    pages_hooks = [
        "woocommerce_before_customer_login_form",
        "woocommerce_after_customer_login_form",
        "woocommerce_cart_is_empty",
        "woocommerce_no_products_found",
        "woocommerce_before_main_content",
        "body_class",
    ]
    for hook in pages_hooks:
        body = f"add_action( '{hook}', static function () {{}} );\n"
        _seed_playground_php(monorepo, f"trial-{hook.replace('_', '-')}.php", body)

    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    for hook in pages_hooks:
        assert hook in joined, f"failure message did not name `{hook}`"


def test_fails_on_swatches_hook(monorepo):
    """`woocommerce_dropdown_variation_attribute_options_html` is the
    swatches migration target — the per-theme block owns the swap now."""
    _seed_playground_php(
        monorepo,
        "wo-swatches-trial.php",
        "add_filter( 'woocommerce_dropdown_variation_attribute_options_html', "
        "static function ( $html ) { return $html; }, 10, 2 );\n",
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    assert "woocommerce_dropdown_variation_attribute_options_html" in joined


def test_fails_on_woocommerce_blocks_prefix_hook(monorepo):
    """`woocommerce_blocks_*` and `render_block_woocommerce/*` are
    forbidden by prefix because every callback paints WC Blocks chrome
    that the release theme can't reproduce without the mu-plugin."""
    _seed_playground_php(
        monorepo,
        "wo-blocks-trial.php",
        "add_filter( 'render_block_woocommerce/cart', "
        "static function ( $html ) { return $html; } );\n",
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    assert "render_block_woocommerce/cart" in joined


# ---------------------------------------------------------------------------
# Allowlist: `defined('WO_*')` guard opts a single registration out.
# ---------------------------------------------------------------------------
def test_passes_when_forbidden_hook_is_demo_only_guarded(monorepo):
    """A genuine demo-only override can opt out via `defined('WO_*')`
    within 200 chars upstream of the call. The check looks for the
    guard and skips the registration."""
    _seed_playground_php(
        monorepo,
        "wo-demo-only.php",
        textwrap.dedent(
            """\
            if ( defined( 'WO_DEMO_ONLY' ) ) {
                add_filter( 'gettext', static function ( $t ) { return $t; } );
            }
            """
        ),
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.passed, result.details


# ---------------------------------------------------------------------------
# Comments are scrubbed before scanning so docstring examples are safe.
# ---------------------------------------------------------------------------
def test_passes_when_forbidden_hook_only_appears_in_comments(monorepo):
    """The HISTORICAL NOTE comments inside the gutted `wo-pages-mu.php`
    stub mention every retired hook by name; the scrubber strips
    `// …` and `/* … */` before scanning so those mentions don't
    accidentally flip the gate."""
    _seed_playground_php(
        monorepo,
        "wo-pages-mu.php",
        textwrap.dedent(
            """\
            /**
             * RETIRED. Once registered:
             *   add_filter( 'gettext', ... );
             *   add_action( 'woocommerce_cart_is_empty', ... );
             *   add_filter( 'body_class', ... );
             */
            // add_action( 'woocommerce_no_products_found', '__return_null' );
            """
        ),
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.passed, result.details


# ---------------------------------------------------------------------------
# Marker-class scanner — every per-theme marker that must not leak.
# ---------------------------------------------------------------------------
def test_fails_on_each_forbidden_marker_class(monorepo):
    """Every per-theme paint marker leaking into `playground/*.php`
    means a mu-plugin runtime injection (or a `wo-configure.php` HEREDOC)
    is painting brand markup from outside the theme directory."""
    markers = [
        "wo-empty",
        "wo-account-intro",
        "wo-archive-hero",
        "wo-swatch-blue",
        "wo-payment-icons",
    ]
    for marker in markers:
        _seed_playground_php(
            monorepo,
            f"trial-{marker}.php",
            f"echo '<div class=\"{marker}\">brand markup</div>';\n",
        )

    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    for marker in markers:
        assert marker in joined, f"failure message did not name `{marker}`"


def test_passes_when_marker_only_appears_in_comments(monorepo):
    """`wo-empty` / `wo-payment-icons` etc. naming themselves inside
    HISTORICAL NOTE comments must not flip the gate (the scrubber
    strips comments before the marker scan)."""
    _seed_playground_php(
        monorepo,
        "wo-payment-icons-mu.php",
        textwrap.dedent(
            """\
            /**
             * RETIRED. Once injected `<div class="wo-payment-icons">…</div>`
             * via `wp_footer`. The implementation now ships in each theme's
             * `// === BEGIN payment-icons ===` block.
             */
            // The marker `wo-empty` was painted by the empty-cart shim.
            """
        ),
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert result.passed, result.details


def test_failure_message_points_at_per_theme_sentinel(monorepo):
    """A reviewer must be able to read the failure message and know
    exactly where the override goes (not just that it doesn't go in
    `playground/`)."""
    _seed_playground_php(
        monorepo,
        "wo-rogue.php",
        "add_action( 'woocommerce_cart_is_empty', static function () {} );\n",
    )
    result = _check(monorepo).check_no_brand_filters_in_playground()
    assert not result.passed
    joined = "\n".join(result.details)
    assert "<theme>/functions.php" in joined
    assert "BEGIN" in joined and "===" in joined
