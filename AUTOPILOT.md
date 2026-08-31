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

PR #456 restored Design System 1.0 as the canonical owner priority after a later refactor-first session had again overwritten the owner direction. It updated the owner-priority/state files and both CI guards together; owner-priority, technical-refactor-lock and security checks were green before merge and the main-branch guards stayed green afterward.

PR #457 fixed a confirmed shared-grid inconsistency on editorial/country breadcrumbs. The breadcrumb wrapper used the full viewport at intermediate widths while `.sp-main` already used `--at-page-gutter`, so at 1024px breadcrumbs sat outside the content grid. Breadcrumbs now consume the same shell + page-gutter width contract as the page body. The complete relevant PR suite passed, including five-width standalone visual coverage at 375 / 430 / 768 / 1024 / 1440, V2 baseline, selected-tour, B5 trust, standalone, startup/bundle and security checks. Visual evidence for representative country pages was inspected at 375 and 1024 with no confirmed overflow or wrapping regression.

PR #457 deployed successfully in production run `33359258585`. Public-page verification, the unchanged production lead bridge and live search smoke all passed. Post-deploy visual/runtime workflows were triggered and are the next evidence to consume before raising the whole-site score.

Next work remains the complete production journey `/ → country/hot → /poisk-turov/ → results → selected tour → lead` at the five target widths, followed by only confirmed layout/hierarchy fixes. The whole-site score remains 7.0/10 until broader end-to-end production evidence supports a move.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Preserve verified social/app destinations. Do not modify Yandex Metrika/goals/analytics contract, Tourvisor contract or the existing lead-sending mechanism. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy results, production/live behavior and `AUTOPILOT_STATE.json`. Work through multiple independent safe tasks when time allows. User-facing changes require relevant green checks and five-width visual evidence before deploy. Deploy only after checks are green and verify live behavior afterward. If blocked on one task, record/defer it and continue another safe Design System task.
