# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records that the old refactor-first lock is inactive, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is whole-site visual unification. The public site must read as one coherent AnyTour product from homepage through destination/hot/search, results, selected tour and lead. Search-only engineering quality must not be used as the whole-site visual score.

## Ordered work

1. Keep one shared token/primitives layer for typography, shell width, gutters, spacing, cards, controls, breadcrumbs and responsive behavior.
2. Keep one coherent header/navigation and one canonical footer across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
3. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell and inconsistent hierarchy before decorative polish.
4. Migrate weak editorial/destination pages onto the mature shared shell while keeping them lighter than the dense search product.
5. Validate material visual changes at 375, 430, 768, 1024 and 1440 px and audit the full homepage → destination/hot → search → results → selected tour → lead journey.
6. Preserve all search/recovery/results/comparison/flight/price/fuel/lead regressions while changing the outer visual system.
7. Resume Tour Data Platform work after the owner-directed Design System phase or when needed to support an already-approved visual/product surface.

## Current resume point

The shared token/header/footer/page-shell foundation is active. The 769–900 px editorial shell, breadcrumbs, section-heading wraps and card paragraph rhythm have been aligned to the canonical tablet grid; the homepage hero, quick-search card and content sections now use the same shared gutter/tablet cap instead of legacy hard-coded geometry. The existing standalone visual suite already exercises exactly 375/430/768/1024/1440 across homepage, search, editorial routes, country catalog and representative country pages.

Continue with route-specific hierarchy on `/country/` and representative destination pages, then `/contacts/`, `/how-to-buy/` and `/rb/`. After those slices, run the complete production homepage → destination/hot → search → results → selected tour → lead journey at the exact responsive matrix. Treat the owner-calibrated 6.5/10 whole-site baseline separately from stronger search-only engineering scores; current whole-site estimate after the shared-grid work is about 6.7/10.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, external lead contract/field mapping, verified social/app destinations or neighboring projects. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence, live behavior where accessible and these source-of-truth files. Choose multiple independent safe Design System slices where time permits and carry each through focused regression/visual evidence. SAFE/MEDIUM changes may be merged autonomously after green evidence. If blocked, record/defer the blocker and continue another independent visual-unification task.
