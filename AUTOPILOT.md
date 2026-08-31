# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is site-wide UX/visual unification. Do not confuse the stronger search-flow engineering score with whole-site visual quality.

The public product must feel coherent across homepage → country/destination → hot/search → results → selected tour → lead.

## Required sequence

1. Audit `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
2. Maintain one shared visual language: design tokens/primitives, coherent header/navigation, one footer, typography, grid/spacing, buttons/cards/breadcrumbs and responsive behavior.
3. Use the mature search experience as a reference without making editorial pages unnecessarily dense.
4. Validate at 375 / 430 / 768 / 1024 / 1440 and fix confirmed crooked spacing, wrapping, overflow, duplicated shell, inconsistent header/footer and hierarchy before cosmetic flourishes.
5. Preserve search/recovery/results/comparison/flight/price/fuel/lead regressions.
6. Deploy only after relevant checks are green and verify production/live visual behavior.

## Current resume point

Shared shell/header alignment and the hotel primary-grid regression recovery are production-green. Whole-site score remains 7.0/10 pending complete production evidence. Resume with the full five-width journey: homepage → country/hot → search → results → selected tour → lead. Fix only confirmed visual/hierarchy defects and recalculate the whole-site score only after production verification.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Do not change without explicit owner approval:
- Yandex Metrika configuration, goals/events or analytics external contract;
- external lead-sending contract or field mapping;
- Tourvisor external contract;
- neighboring projects.

Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy results and production/live behavior where accessible. Read `AGENTS.md`, this file and `AUTOPILOT_STATE.json` before editing. Continue through multiple independent safe Design System tasks/pages for as long as execution time allows. If blocked on one task, record/defer it and continue another safe task. SAFE/MEDIUM changes may merge autonomously only after relevant green checks.
