# AnyTour CI Cost Audit

Status: focused companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

## Goal

Reduce GitHub Actions runner and browser cost without weakening search, lead, price, tour, flight or recovery coverage. Cost reduction is evidence-based: merge/remove workflows only after equivalent behavior is owned elsewhere.

## Synthetic live-search policy

`visual-anytoour-results-live.yml` remains a POST DEPLOY browser check. It installs Playwright + Chromium, opens the canonical production search route, performs a real search and waits for live hotel cards.

Current policy:

- keep the workflow POST DEPLOY; do not move it into every PR;
- tag synthetic requests with `ci_test=1` and `X-AnyTour-CI: 1` for server-side identification;
- block `mc.yandex.ru` / `mc.yandex.com` in the Playwright harness, leaving production Metrika code and goals untouched;
- keep the stay length fixed at 7 nights;
- advance through sequential non-overlapping Tourvisor departure windows rather than replaying one date range;
- let the normal completed-search path remain intact so valid Tourvisor observations can contribute to local price history.

The scheduled collector separately rotates real active `departure × country` pairs from the Tourvisor-synced catalog and advances each pair through its own sequential date windows.

## Verified CI-cost reductions

### Data-only V2 changes

A real data-only collector PR showed that broad `v2/**` path filters were launching unrelated browser suites. The following expensive workflows now explicitly exclude `v2/data/**` when their assertions do not consume that layer:

- `Visual V2 pull request`;
- `Visual V2 selected tour`;
- `Visual V2 B5 trust pull request`;
- `Validate V2 branch bundles`.

These jobs each installed Playwright/Chromium and exercised multiple viewports, so excluding data-only changes removes repeated browser startup without reducing data-layer coverage.

### Primary meal browser consolidation

The former `validate-v2-primary-meal-responsive.yml` one-width workflow was removed after its unique 1024px assertions were moved into `visual-v2-meal-visibility.yml`.

Coverage retained in the remaining owner workflow:

- 375 / 430 / 768 / 901 / 1024 / 1100 / 1101 / 1200 / 1440 widths;
- no horizontal clipping or document overflow;
- full-width meal field at 1024;
- wrapping instead of horizontal scrolling;
- final `Без питания` option identity and visibility;
- retained 1024 screenshot evidence.

Result: one fewer Playwright/Chromium runner on meal-related PRs with equivalent behavioral coverage.

### Dormant workflow cleanup

Two manual-only disabled stubs were removed:

- `audit-v2-live-traffic.yml`;
- `audit-v2-recent-browser.yml`.

They were functionally identical, performed no real audit and only created workflow clutter / accidental manual-run risk.

## Cost findings still open

The repository still has many browser workflows that independently perform `npm init`, install Playwright, install Chromium system dependencies, then launch one narrow test. Repeated browser bootstrap remains one of the largest optimization targets.

`visual-v2-baseline.yml` is still a particularly expensive broad owner: Playwright + Chromium + pixelmatch/pngjs, five viewports, multiple screenshots and baseline comparison. Its PR trigger currently watches broad `v2/**`, even though the browser journey consumes UI/runtime assets rather than most `v2/data/**` files. This is the next high-value path-filter audit.

`validate-v2-pr.yml` and `validate-anytoour-standalone.yml` also use broad `v2/**` triggers and should be split by actual dependency ownership before excluding data paths. `validate-v2-bundles.yml` is different: it explicitly validates `v2/data/destinations-v1.php`, so it must not receive a blanket data-layer exclusion.

## Consolidation rules

1. One behavioral domain should have one primary owner suite where practical.
2. Do not create `new bug → new workflow`; add coverage to the existing owner suite.
3. Keep PR FAST deterministic checks cheap and browser-free where possible.
4. Keep true production smoke in POST DEPLOY.
5. Move broad expensive visual/live sweeps to POST DEPLOY or SCHEDULED-LIVE when PR correctness is already covered elsewhere.
6. Do not remove lead, search, price, tour, flight or recovery coverage merely to reduce job count.
7. Optimize runner starts, repeated Playwright/Chromium installation and duplicate live searches before cosmetic CI cleanup.

## Resource target

Target at least a 2x reduction in routinely triggered PR jobs and a material reduction in repeated Playwright/Chromium installation. Measure savings by runner starts and browser bootstrap avoided, not only by workflow file count.

## Next cost-audit batch

1. Narrow `visual-v2-baseline.yml` to the UI/runtime paths it actually consumes, preserving workflow self-trigger and post-deploy behavior.
2. Audit `validate-v2-pr.yml` by exact file dependencies and separate fast UI/runtime ownership from data-layer changes.
3. Audit `validate-anytoour-standalone.yml` the same way.
4. Keep `validate-v2-bundles.yml` coverage for the specific data endpoint it genuinely validates instead of applying a blanket exclusion.
5. Then compare header/footer/mobile-sticky browser suites for genuine assertion overlap before any consolidation.
