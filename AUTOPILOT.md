# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether a refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is whole-site visual unification and product coherence.

Do not confuse the stronger tour-search engineering score with whole-site visual quality. The public product still needs to feel coherent across homepage → country/destination → hot/search → results → selected tour → lead.

## Active scope

Audit and improve `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages. Keep one coherent header/navigation, one footer, typography, grid/spacing, buttons, cards, breadcrumbs and responsive behavior. Use the mature search experience as a reference without making editorial pages unnecessarily dense.

Validate user-facing changes at **375 / 430 / 768 / 1024 / 1440**. Fix confirmed crooked spacing, wrapping, overflow, duplicated shell, inconsistent header/footer and hierarchy before cosmetic flourishes.

## Current resume point

Shared shell/design primitives are established across the public site. Country-page intent flow is production-green. The hotel autocomplete regression that moved the optional hotel into the primary grid was fixed by PR #440 and guarded by PR #447; keep that invariant permanent. Critical departure/country catalog readiness also has a dedicated live guard.

PR #462 restored Design System 1.0 as the canonical owner priority after PR #460 had again rewritten the canonical priority/state/guards to refactor-first. `OWNER_PRIORITY.json`, `AUTOPILOT.md`, `AUTOPILOT_STATE.json`, the refactor lock and both CI guards now agree on Design System 1.0. Owner-priority, technical-lock and security checks passed before merge.

PR #461 fixed a confirmed shared-grid inconsistency on the mature search surface. `.v2-shell` still used hard-coded horizontal gutters, which diverged from the shared site/header grid at tablet width. The search shell now consumes the canonical `--at-shell` and `--at-page-gutter` Design System tokens while preserving all search behavior. The first CI pass exposed only an expected bundle-count guard mismatch after adding the CSS asset; the guard was updated, then the complete relevant suite passed, including V2 baseline, V2 PR visual, selected-tour visual, search header/footer/mobile checks, startup/bundle, standalone, comparison, flight and lead guards.

PR #461 merged as `e887eda5d87a1daa1bf1cc1b029c2d31d8b68a51`. Standalone production deploy run `33362874549` is currently in progress; release validation passed and the release copy is running. Do not claim production verification until public-page, unchanged lead-bridge and live-search steps finish green.

Next work is to consume that deployment evidence, then continue the complete production journey `/ → country/hot → /poisk-turov/ → results → selected tour → lead` at 375 / 430 / 768 / 1024 / 1440, fixing only confirmed layout/hierarchy defects. The whole-site score remains 7.0/10 until broader end-to-end production evidence supports a move.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Do not modify Yandex Metrika/goals/analytics contract, Tourvisor contract or the existing lead-sending mechanism. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy results, production/live behavior and `AUTOPILOT_STATE.json`. Work through multiple independent safe tasks when time allows. User-facing changes require relevant green checks and five-width visual evidence before deploy. Deploy only after checks are green and verify live behavior afterward. If blocked on one task, record/defer it and continue another safe Design System task.
