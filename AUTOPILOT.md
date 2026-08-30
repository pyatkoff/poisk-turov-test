# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point.

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

1. Finish Design System 1.0 shared tokens/primitives and remove duplicate token ownership from shared shell CSS.
2. Align shared header/navigation geometry with the common token layer, then verify search shell compatibility.
3. Normalize shared page/card/button/breadcrumb/grid rhythm across `/contacts/`, `/how-to-buy/`, `/rb/`, `/hot/`, `/country/` and representative country pages.
4. Align homepage section hierarchy and shell spacing with the same primitives.
5. Align search outer header/footer composition without touching search/recovery/results functional contracts.
6. Run cross-page journey audit: homepage → destination/hot → search → results → selected tour → lead.
7. Raise `SITE_QUALITY_SCORECARD.md` only after production visual evidence supports the movement.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Legal/payment migration remains deferred. PR #254 remains deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, fresh CI/deploy state and production evidence. Prefer shared primitives and shell fixes that improve multiple pages at once, then migrate weak pages in safe batches. Do not invent defects. If one item is blocked, record/defer it and continue another independent safe visual slice.
