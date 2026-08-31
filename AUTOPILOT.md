# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is site-wide visual unification. The public site should feel like one coherent AnyTour product across homepage → destination/country → hot/search → results → selected tour → lead, rather than a strong search experience surrounded by visually inconsistent editorial pages.

The whole-site visual score is tracked separately from search-only engineering quality. Current baseline is approximately 6.5/10 for coherent public-site product quality; recent shared-shell work has moved this to roughly 6.9/10.

## Ordered work

1. Audit `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages as one product journey.
2. Keep one shared header/navigation, one footer, shared typography, width/grid/spacing, buttons, cards, breadcrumbs and responsive behavior.
3. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell, inconsistent header/footer and hierarchy before cosmetic flourishes.
4. Use the mature search experience as the quality reference without making editorial pages unnecessarily dense.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
6. Continue through multiple independent safe pages/tasks per development run where checks and production evidence allow it.
7. Keep CI-cost/refactor work deferred unless it fixes an emergency or directly enables the active visual work.

## Current resume point

Shared shell/header/footer primitives already cover the migrated standalone pages; contacts/how-to-buy have stronger editorial hierarchy, and tablet shell/footer grid alignment has been corrected. The current safe continuation is `/rb/`, then `/country/` plus representative destination pages, followed by the complete production visual journey at 375/430/768/1024/1440.

A repeated priority rollback was traced to hard-coded CI guards that still forced the superseded technical-refactor / Design System 2.0 state. Those guards must validate consistency with `OWNER_PRIORITY.json` rather than embed an obsolete owner direction.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, the Tourvisor contract, the existing lead-sending external contract/field mapping, neighboring projects, or verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh separate review proves its DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open/recent PRs, CI/deploy evidence, production/live behavior where accessible and the current resume point. Prefer SAFE/MEDIUM visual-unification slices with focused regression and responsive evidence. Merge/deploy only after relevant checks are green, then verify production/live behavior. If blocked, record/defer the blocker and continue another independent safe Design System 1.0 task.
