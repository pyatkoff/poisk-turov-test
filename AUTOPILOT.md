# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0 SITE-WIDE

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), priority #1 is whole-site visual coherence. The mature tour-search experience remains the quality reference, but the product is scored as one journey, not as search alone.

## Ordered work

1. Consolidate shared design tokens/primitives and one coherent header/navigation/footer without replacing the AnyTour logo.
2. Converge `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages onto the shared shell.
3. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
4. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/comparison/flight/price/fuel/lead regressions.
5. Continue technical consolidation where it directly reduces duplicate shell/design ownership or makes visual migration safer.
6. Keep unresolved legal/payment content deferred; keep PR #254 deferred unless a fresh independent architecture review proves it safe.

## Current resume point

The editorial routes already share `v2/site-page-shell-v1.php`. Continue making that shell the single final source for editorial geometry and responsive behavior, then audit homepage/search shell parity and weak route-specific components. Do not create route-local overrides when a shared primitive is sufficient.

The next implementation slice is a fresh shared DS1 alignment layer from current `main`: fix min-width/overflow, balanced two/three-column grids, section rhythm, breadcrumbs and mobile CTA wrapping across the shared editorial shell, and reuse the same safe geometry guards on the homepage. Validate through relevant PR/browser checks before merge, then verify production. Live HTTP access may be temporarily unavailable from the automation environment; when that happens, use repository/browser CI evidence and keep production verification explicitly pending rather than guessing.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not migrate unresolved legal/payment content.

## Decision rule

Prefer narrow shared-shell changes with broad visual benefit. For each slice: inspect current implementation → identify confirmed inconsistency → change the canonical shared owner → run focused CI/visual checks → merge only after relevant checks are green → verify production/live behavior where accessible. If blocked, record/defer the blocker and continue an independent safe slice.
