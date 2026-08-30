# poisk-turov-test — Autopilot State

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical source-of-truth documents remain available for supporting refactors, but they do not override the current explicit owner priority.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is whole-site visual unification. The mature search product remains the functional/UX reference, but the whole public site must be judged as one product rather than by search-only engineering quality.

Canonical priority after emergency overrides:

`ux_visual_site_unification → technical_refactor_supporting_design_system → content_seo → cosmetic_cleanup`

Production breakage, lead loss, incorrect data and broken user journeys may preempt temporarily under `AGENTS.md`.

## Design-system objectives

1. Establish one shared AnyTour token/primitives layer for typography, spacing, surfaces, radii, controls, cards, breadcrumbs and responsive rhythm.
2. Maintain one coherent header/navigation and one footer across `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
3. Migrate weak standalone/editorial pages to the common shell without making them as dense as search/results UI.
4. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell, inconsistent hierarchy and mobile/desktop discontinuity before decorative flourishes.
5. Preserve mature search/recovery/results/comparison/flight/price/fuel/lead regressions while aligning its outer shell.
6. Validate visual behavior at 375, 430, 768, 1024 and 1440 px and verify production after deploy.

## Exact next work order

1. Continue the page-hierarchy pass across homepage → country/destination → hot/search, starting with homepage and country layers where shell consistency is established but hierarchy still feels flatter than mature search.
2. Normalize remaining hard-coded homepage/content geometry onto Design System 1.0 shell/gutter/spacing primitives where visual evidence shows drift.
3. Improve country catalog and representative country-page information hierarchy without inventing unavailable prices, ratings or destination data.
4. Audit `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` against the same shared card/button/breadcrumb/section rhythm and fix confirmed inconsistencies.
5. Re-run the cross-page journey audit: homepage → destination/hot → search → results → selected tour → lead at 375/430/768/1024/1440.
6. Raise `SITE_QUALITY_SCORECARD.md` only after production visual evidence supports the movement.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Legal/payment migration remains deferred. PR #254 remains deferred unless a fresh review proves its separate DB/platform architecture safe.

Supporting technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`. Apply technical refactors only when they directly support the design-system migration or emergency correctness work, and retain `one concept → one implementation` where consolidation is proven safe.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, fresh CI/deploy state and production evidence. Prefer shared primitives and shell fixes that improve multiple pages at once, then migrate weak pages in safe batches. Do not invent defects. If one item is blocked, record/defer it and continue another independent safe visual slice.
