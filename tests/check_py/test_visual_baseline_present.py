"""Tests for `check_visual_baseline_present`.

The default visual gate (`bin/snap.py diff`) only flags routes whose
PNG differs from the committed baseline. A theme with NO baseline
files at all therefore looks "green" because there's nothing to
diff. This invariant is the upstream shield: every theme must ship
at least one baseline PNG, or the check fails.
"""

from __future__ import annotations

import sys
from pathlib import Path


def _import_check(monkeypatch, theme_root: Path):
    repo_root = Path(__file__).resolve().parent.parent.parent
    bin_dir = repo_root / "bin"
    if str(bin_dir) not in sys.path:
        sys.path.insert(0, str(bin_dir))
    import check  # noqa: WPS433

    monkeypatch.setattr(check, "ROOT", theme_root)
    monkeypatch.setattr(check, "MONOREPO_ROOT", theme_root.parent)
    return check


def test_missing_baseline_dir_fails(tmp_path, monkeypatch):
    theme = tmp_path / "fresh-theme"
    theme.mkdir()
    monkeypatch.delenv("FIFTY_SKIP_VISUAL_BASELINE_CHECK", raising=False)
    check = _import_check(monkeypatch, theme)
    r = check.check_visual_baseline_present()
    assert not r.passed and not r.skipped
    assert "tests/visual-baseline/fresh-theme" in " ".join(r.details)


def test_empty_baseline_dir_fails(tmp_path, monkeypatch):
    theme = tmp_path / "empty-baseline"
    theme.mkdir()
    (tmp_path / "tests" / "visual-baseline" / theme.name).mkdir(parents=True)
    monkeypatch.delenv("FIFTY_SKIP_VISUAL_BASELINE_CHECK", raising=False)
    check = _import_check(monkeypatch, theme)
    r = check.check_visual_baseline_present()
    assert not r.passed and not r.skipped
    assert "no PNGs" in " ".join(r.details)


def test_baseline_with_pngs_passes(tmp_path, monkeypatch):
    theme = tmp_path / "good-theme"
    theme.mkdir()
    base = tmp_path / "tests" / "visual-baseline" / theme.name / "desktop"
    base.mkdir(parents=True)
    (base / "home.png").write_bytes(b"\x89PNG\r\n\x1a\n")
    monkeypatch.delenv("FIFTY_SKIP_VISUAL_BASELINE_CHECK", raising=False)
    check = _import_check(monkeypatch, theme)
    r = check.check_visual_baseline_present()
    assert r.passed and not r.skipped


def test_escape_hatch_skips(tmp_path, monkeypatch):
    theme = tmp_path / "fixture-theme"
    theme.mkdir()
    monkeypatch.setenv("FIFTY_SKIP_VISUAL_BASELINE_CHECK", "1")
    check = _import_check(monkeypatch, theme)
    r = check.check_visual_baseline_present()
    assert r.skipped
