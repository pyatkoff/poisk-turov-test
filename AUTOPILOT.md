# poisk-turov-test — Autopilot State

Updated: 2026-08-30

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority lock. `AUTOPILOT_STATE.json` is the machine-readable resume point. Technical sources of truth remain `ARCHITECTURE.md`, `TEST_MATRIX.md`, `DEPENDENCY_MAP.md`, `CI_WORKFLOW_AUDIT.md`, `CODEX_QUEUE.md` and `PRODUCT_ROADMAP.md`.

## Current phase — ANYTOUR DESIGN SYSTEM 1.0

The owner's latest explicit direction is site-wide visual unification first. Do not confuse the mature search engineering quality with whole-site coherence.

Canonical priority after emergency overrides:

`ux_visual → technical_refactor → content_seo → cosmetic_cleanup`

Production breakage, lead loss and incorrect data still preempt this phase under `AGENTS.md`, but technical-refactor work must not replace the active Design System owner lock.

## Design System objectives

1. Make `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages feel like one product.
2. Maintain one coherent header/navigation, footer, typography, grid/spacing, buttons/cards/breadcrumbs and responsive behavior.
3. Use the mature search experience as reference without making editorial pages unnecessarily dense.
4. Fix confirmed spacing, wrapping, overflow, duplicated shell and hierarchy issues before cosmetic flourishes.
5. Validate user-facing changes at 375/430/768/1024/1440 and preserve search/results/selected-tour/lead regressions.

## Current evidence and resume point

- Whole-site coherence is approximately 7.1/10 after shipped hierarchy/spacing fixes; this is a whole-site score, not a search-only score.
- PR #381 removed public footer links to `anytour.online` without inventing unresolved new-domain legal/payment pages; PR checks were green before merge.
- Continue confirmed-defect audit on `/hot/`, `/country/` and representative country pages, then re-run the complete homepage → destination → hot/search → results → selected tour → lead visual journey.
- Verify the post-merge live result of #381 and ensure no visible shared-shell links still point to the old domain.

## Mandatory protections

Do not modify without explicit approval:

- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects;
- server/platform architecture outside the allowed repository/deploy scope.

Preserve the AnyTour logo and verified social/app destinations. Legal/payment content migration remains deferred. Keep PR #254 deferred unless a fresh scope-specific review proves its separate DB/platform architecture safe.

## Execution policy

Work in multiple independent safe PR-sized slices per run while execution time allows. Start from current `main`, open PRs, fresh CI/deploy and live evidence. Do not invent visual defects. If a page is already coherent, move to the next surface. Deploy only after relevant green checks, then verify production/live behavior. Update this file and `AUTOPILOT_STATE.json` after material progress.
