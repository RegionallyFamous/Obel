# Visual baselines

Committed PNG references that `bin/snap.py diff` compares each
freshly-captured screenshot against. When a baseline exists, the diff
command flags any cell whose changed-pixel percentage exceeds the
threshold (default 0.5%).

## Layout

```
tests/visual-baseline/
  <theme>/
    <viewport>/
      <route-slug>.png
```

The slug names mirror `bin/snap_config.py::ROUTES` and viewport names
mirror `bin/snap_config.py::VIEWPORTS`. The same paths are used by
`tmp/snaps/` (latest captures) and `tmp/diffs/` (per-pixel overlays).

## Workflow

```bash
# 0. Verify the framework is wired up (Pillow, Playwright/Chromium,
#    npx, axe-core, baseline coverage). Run once per machine / after
#    Python upgrades.
python3 bin/snap.py doctor

# 1. Capture latest into tmp/snaps/. By default --changed picks only
#    themes whose files moved in git; use --all for a full sweep before
#    a release. Each worker boots its own playground at ~400MB RAM.
python3 bin/snap.py shoot --changed
python3 bin/snap.py shoot --all --concurrency 2

# 2. Compare against baselines, see what regressed.
python3 bin/snap.py diff --all

# 3. Triage findings + see the tiered gate verdict. Each per-theme
#    review.md gets a `**GATE: PASS|WARN|FAIL**` badge; the cross-theme
#    rollup ends with `STATUS: PASS|WARN|FAIL`.
python3 bin/snap.py report --open

# 4. If the changes are intentional (intentional redesign,
#    new content, fixed bug), promote latest -> baseline:
python3 bin/snap.py baseline --all                # entire matrix
python3 bin/snap.py baseline chonk                # one theme
python3 bin/snap.py baseline chonk --route checkout-filled --viewport desktop
                                                  # one cell

# 5. Re-run the gate to confirm green.
python3 bin/snap.py check --all
# (= shoot + diff + report --strict; exits 1 only on `fail`).
```

`bin/check.py --visual` wraps steps 1-3-5 with `--visual-scope=changed`
by default; that's the recommended pre-commit gate.

Always review the diff PNG (under `tmp/diffs/`) AND the per-theme
`tmp/snaps/<theme>/review.md` before re-baselining. The diff PNG tells
you which pixels changed; the review surfaces heuristic findings (broken
images, mid-word wraps, narrow grid items, font-load stalls, oversized
responsive images, tap-targets <44px, ellipsis truncation, empty
landmarks, placeholder images), axe-core a11y violations, parity drift
across themes, console errors, network failures, and any scripted
interactive flow that failed. Re-baselining a green-pixel-diff that
ships with a new uncaught JS error or critical a11y violation is
exactly the regression this folder exists to prevent.

The tiered gate triages findings for you:

- **fail** = build-blocking (heuristic `error`, uncaught JS, HTTP 5xx,
  axe critical/serious). `bin/check.py --visual` exits 1.
- **warn** = visible banner, exit 0 (heuristic `warn`/`info`, HTTP 4xx,
  console errors, axe moderate/minor, parity drift, perf-budget
  exceedances, interaction-failed).
- **pass** = nothing flagged.

## Why commit PNGs

Yes, binary blobs in git aren't ideal. The alternative -- regenerating
baselines on every CI run -- doesn't catch regressions because there's
nothing to compare against. Storing the PNGs:

  * makes diffs reviewable in GitHub PRs (the GH UI renders side-by-side
    PNG diffs natively)
  * lets the agent loop work without a separate artifact store
  * is bounded: ~10 routes × 4 viewports × 4 themes ≈ 160 PNGs at ~50KB
    each ≈ 8MB total, within reason for a theme repo

If a particular cell becomes too noisy (e.g. an animation that always
captures mid-transition), exclude it from `bin/snap_config.py::ROUTES`
or `VIEWPORTS` rather than committing a "this one will always be 5%
different" baseline.
