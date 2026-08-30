# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain:

- `ARCHITECTURE.md` — canonical architecture and ownership boundaries;
- `TEST_MATRIX.md` — CI/test policy and required coverage;
- `DEPENDENCY_MAP.md` — active/compatibility/deprecated/dead dependency inventory;
- `CI_WORKFLOW_AUDIT.md` — GitHub Actions classification;
- `CODEX_QUEUE.md` — narrow execution slices;
- `PRODUCT_ROADMAP.md` — broader product roadmap.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is site-wide visual unification. This supersedes the temporary technical-refactor-first lock from PRs #353/#354.

Priority after emergency overrides:

`production broken → lead loss → incorrect data → broken user journey → Design System 1.0 / site-wide visual unification → supporting technical consolidation → content/SEO → cosmetic cleanup`

Do not confuse the mature tour-search engineering quality with whole-site visual coherence. The site-wide score is tracked separately.

## Design System objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Keep one shared header/navigation, one footer, coherent typography, spacing/grid, buttons, cards and breadcrumbs.
3. Use the mature search flow as the visual reference without making editorial pages unnecessarily dense.
4. Fix confirmed spacing, wrapping, overflow, uneven grids, duplicate shell and hierarchy problems before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
6. Continue technical cleanup only when it directly supports the current visual/system work or prevents regressions.

## Current progress

Production-green slices already include the mobile iOS layout fixes (#348), shared editorial primitives (#349), country/rb unification (#351), homepage journey-card balancing (#352), and `/hot/` balanced-three layouts (#355, production verification in progress at this checkpoint).

Current follow-up applies the same shared balanced-three primitive to the three fact cards used by representative country pages, removing another 2+1 tablet/intermediate-width imbalance without introducing page-specific CSS.

## Exact next work order

1. Verify #355 production/live completion and merge/deploy the representative-country fact-grid slice if its full PR checks stay green.
2. Re-audit homepage → country directory → representative country → hot/search continuity on 375/430/768/1024/1440.
3. Inspect remaining header/footer/breadcrumb/spacing mismatches on `/contacts/`, `/how-to-buy/`, `/rb/` and `/hot/`; only change confirmed material defects.
4. Keep mature search/result/tour/lead visual and functional regressions green while avoiding a deep `/poisk-turov/` shell rewrite unless evidence justifies it.
5. Continue extracting duplicated editorial geometry into shared primitives when multiple consumers prove the need.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo and verified social/app destinations. Legal/payment migration remains deferred. PRs #248/#249/#254 are not automatic merge candidates without fresh scope-specific review; #254 specifically remains deferred unless its separate DB/platform architecture is freshly proven safe.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs, fresh CI/deploy and production/live evidence. Merge/deploy only after relevant checks are green, verify live behavior after release, then continue to the next independent safe Design System task instead of stopping at a status check.
