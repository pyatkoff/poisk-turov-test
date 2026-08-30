# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is Design System 1.0/site-wide visual unification first. Technical refactoring may support this work but must not replace the active UX/visual priority.

Canonical priority after emergency overrides:

`ux_visual → technical_refactor → content_seo → cosmetic_cleanup`

Production breakage, lead loss, incorrect data and broken user journeys may preempt temporarily under `AGENTS.md`, but do not rewrite `OWNER_PRIORITY.json`.

## Current Design System objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Keep one coherent header/navigation/footer, typography, grid/spacing, buttons, cards, breadcrumbs and responsive behavior.
3. Use the mature search experience as the reference without making editorial pages unnecessarily dense.
4. Validate material visual changes at 375/430/768/1024/1440 and preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
5. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.

## Material progress

- Shared header/footer/navigation parity and five-width visual guards are established.
- Legacy-domain links were removed from the new shared footer while unresolved legal/payment content remains deferred.
- `/rb/`, `/how-to-buy/` and `/contacts/` received hierarchy/spacing fixes.
- `/country/` mobile catalog density fix is merged via #386 after green visual/regression gates; deploy is green and five-width artifacts confirm the compact 375/430 layout without tablet/desktop regression.
- Country migration map is synchronized with the actual V2 runtime via #387.
- Owner-priority guard drift introduced by #388 was corrected through #389 so the explicit Design System 1.0 phase is canonical again.
- Whole-site coherence is now 7.2/10 after the live-verified country catalog improvement.

## Exact next work order

1. Continue representative country-page visual audit at 375/430/768/1024/1440; fix only confirmed spacing/wrapping/overflow/hierarchy defects.
2. Re-audit `/hot/`, `/contacts/`, `/rb/` and `/how-to-buy/` after shared-shell changes for regressions.
3. Run the full homepage → destination → search → results → selected tour → lead visual journey and address the highest-impact confirmed defect.
4. Continue shared Design System token/primitive consolidation only where it reduces actual cross-page divergence safely.

## Mandatory protections

Do not modify without explicit approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo, verified social/app destinations and mature search/recovery/results/comparison/flight/price/fuel/lead behavior. Legal/payment migration remains deferred. PR #254 remains deferred unless a fresh scope review proves its separate DB/platform architecture safe.

## Execution policy

Work in narrow independent PR-sized slices. At the start of each run inspect current `main`, open PRs, fresh CI/deploy and live visual behavior. Prefer confirmed user-visible Design System defects over speculative cleanup. If one task is blocked, record/defer it and continue another independent safe visual slice.
