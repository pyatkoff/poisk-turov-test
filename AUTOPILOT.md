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

The shared token/header/footer/page-shell foundation already exists and the prior tablet footer-grid mismatch was fixed. Continue from cross-page geometry and hierarchy: align the 769–900 px editorial shell to the same 760 px tablet grid used by shared header/footer, then audit homepage, country/destination, `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` for route-specific hierarchy and overflow. Run the production journey at all required widths after relevant checks are green.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, Tourvisor contract, external lead contract/field mapping, verified social/app destinations or neighboring projects. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence, live behavior where accessible and these source-of-truth files. Choose multiple independent safe Design System slices where time permits and carry each through focused regression/visual evidence. SAFE/MEDIUM changes may be merged autonomously after green evidence. If blocked, record/defer the blocker and continue another independent visual-unification task.
