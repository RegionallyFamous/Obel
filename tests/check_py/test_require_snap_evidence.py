"""Tests for the FIFTY_REQUIRE_SNAP_EVIDENCE=1 escape from the no-snap SKIP.

The default behaviour of `check_no_serious_axe_in_recent_snaps` is to
SKIP when no `tmp/snaps/<theme>/` directory exists, because most
contributors don't run snap on every theme. That's the right default
locally, but it's a loophole for the 50-theme batch run: a theme that
crashed during snap also has no findings on disk, so the check skips,
and the gate passes despite never seeing evidence.

`FIFTY_REQUIRE_SNAP_EVIDENCE=1` flips both no-snap branches to FAIL. It's
set by:
  * `bin/design.py`'s check phase (after the snap phase already ran)
  * `.githooks/pre-push` (after pre-push runs the visual gate itself)
  * CI's `check.yml` (after CI runs `bin/snap.py shoot --all`)

These tests pin the predicate so a future refactor can't silently
re-introduce the loophole.
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


def test_no_snap_dir_skips_by_default(tmp_path, monkeypatch):
    theme = tmp_path / "ghost-theme"
    theme.mkdir()
    monkeypatch.delenv("FIFTY_REQUIRE_SNAP_EVIDENCE", raising=False)
    check = _import_check(monkeypatch, theme)
    r = check.check_no_serious_axe_in_recent_snaps()
    assert r.skipped, f"expected skip, got passed={r.passed} details={r.details}"
    assert r.passed


def test_no_snap_dir_fails_when_evidence_required(tmp_path, monkeypatch):
    theme = tmp_path / "batch-theme"
    theme.mkdir()
    monkeypatch.setenv("FIFTY_REQUIRE_SNAP_EVIDENCE", "1")
    check = _import_check(monkeypatch, theme)
    r = check.check_no_serious_axe_in_recent_snaps()
    assert not r.passed and not r.skipped, f"expected fail, got {r.details}"
    assert "FIFTY_REQUIRE_SNAP_EVIDENCE" in " ".join(r.details)


def test_empty_snap_dir_skips_by_default(tmp_path, monkeypatch):
    theme = tmp_path / "empty-snaps"
    theme.mkdir()
    snaps = tmp_path / "tmp" / "snaps" / theme.name
    snaps.mkdir(parents=True)
    monkeypatch.delenv("FIFTY_REQUIRE_SNAP_EVIDENCE", raising=False)
    check = _import_check(monkeypatch, theme)
    r = check.check_no_serious_axe_in_recent_snaps()
    assert r.skipped


def test_empty_snap_dir_fails_when_evidence_required(tmp_path, monkeypatch):
    theme = tmp_path / "empty-required"
    theme.mkdir()
    snaps = tmp_path / "tmp" / "snaps" / theme.name
    snaps.mkdir(parents=True)
    monkeypatch.setenv("FIFTY_REQUIRE_SNAP_EVIDENCE", "1")
    check = _import_check(monkeypatch, theme)
    r = check.check_no_serious_axe_in_recent_snaps()
    assert not r.passed and not r.skipped
    assert "no *.findings.json" in " ".join(r.details)
