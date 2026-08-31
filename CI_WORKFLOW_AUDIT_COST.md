# AnyTour CI Cost Audit

Status: focused companion to `CI_WORKFLOW_AUDIT.md` and `TEST_MATRIX.md`.

## Goal

Reduce GitHub Actions runner and browser cost without weakening search, lead, price, tour, flight or recovery coverage. Cost reduction is evidence-based: merge/remove workflows only after equivalent behavior is owned elsewhere.

## First verified cost slice — live result search

`visual-anytoour-results-live.yml` is a POST DEPLOY browser check. It installs Playwright + Chromium, opens the canonical production search route, performs a real search and waits for live hotel cards. This is appropriate after deploy, but its synthetic visit must not contaminate analytics and its repeated Tourvisor search should provide useful data rather than replay one identical segment forever.

Changes in this slice:

- keep the workflow POST DEPLOY; do not move it into every PR;
- tag requests with `ci_test=1` and `X-AnyTour-CI: 1` for server-side identification;
- block `mc.yandex.ru` / `mc.yandex.com` in the Playwright harness, leaving production Metrika code and goals untouched;
- rotate a bounded, known-valid Turkey/Moscow search across 7/8/9-night segments and staggered departure windows;
- allow the normal completed-search path to remain intact so passive price observations can contribute valid Tourvisor price data.

This gives a clean separation: synthetic browser visits are identifiable and do not send Metrika hits, while the underlying real tour observations can still improve the local price-history dataset.

## Cost findings already visible from workflow inventory

The repository has many browser workflows that independently perform `npm init`, install Playwright, install Chromium system dependencies, then launch one narrow test. This repeated bootstrap is a major optimization target even where the behavioral contracts are distinct.

Highest-value consolidation directions:

1. shared Playwright/bootstrap helper or reusable workflow for PR BROWSER families;
2. merge only proven-overlapping checks into one browser run (primary meal is the first documented candidate);
3. keep broad production visual sweeps out of PR paths and in POST DEPLOY / SCHEDULED-LIVE;
4. fold tiny string/selector/version assertions into existing PR FAST owners instead of adding one workflow per assertion;
5. remove dormant duplicate stubs after external/manual references are checked.

## Resource target

Target at least a 2x reduction in routinely triggered PR jobs and a material reduction in repeated Playwright/Chromium installation. Do not optimize by deleting unique behavioral coverage.

## Next cost-audit batch

Audit `visual-v2-baseline.yml`, `visual-v2-pr.yml`, `visual-search-header-layout.yml`, `visual-search-footer-rhythm.yml`, `visual-mobile-search-sticky.yml` and the focused meal workflows together. Measure exact trigger overlap, viewport overlap and unique assertions, then consolidate only the proven equivalent pieces.
