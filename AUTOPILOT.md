# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0 SITE-WIDE UNIFICATION

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is whole-site visual coherence. The public site baseline is about 6.5/10 as one product; do not substitute stronger search-flow engineering scores for this whole-site quality measure.

Use the mature search experience as the visual reference while keeping editorial pages calmer and less dense. Establish one shared system for tokens, shell, header/navigation, footer, typography, grid/spacing, buttons, cards, breadcrumbs and responsive behavior. Fix confirmed spacing, wrapping, overflow, duplicate shell and hierarchy defects before cosmetic flourishes.

## Ordered work

1. Keep `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages on one Design System 1.0 shell.
2. Continue from the current shared primitives rather than introducing page-local visual systems.
3. Audit `/rb/` next, then representative country pages and `/country/` for remaining weak hierarchy, wrapping, card/grid or shell inconsistencies.
4. Validate the complete homepage → country/destination → hot/search → results → selected tour → lead journey at 375/430/768/1024/1440.
5. Preserve all search/recovery/results/comparison/flight/price/fuel/lead regressions while changing visual presentation.
6. Deploy only after relevant PR checks are green and verify production/live behavior after deployment.
7. Continue independent safe pages/tasks when one item is blocked; record/defer the blocker instead of stalling the run.

## Material progress this session

PR #514 was merged and deployed. Shared editorial section-heading geometry now aligns content pages with the stronger search visual language. A shared lower-emphasis supporting-card primitive was added for secondary/legal/caution information. `/contacts/` now separates offices, contact/search actions and legal information with a clearer hierarchy while preserving all phone numbers, addresses, verified destinations and legal text. `/how-to-buy/` now uses the same supporting hierarchy without changing its purchase/legal meaning.

Post-deploy verification passed for the production V2 visual journey, selected-tour production surface and deterministic visual baseline. The current whole-site coherence estimate is about 6.9/10; this is intentionally a whole-site score, not a search-only score.

## Current resume point

Continue with `/rb/` and representative country pages. Then run the complete public journey across 375/430/768/1024/1440 and fix only confirmed visual defects. Prefer shared tokens/primitives and shell fixes that improve multiple routes at once.

PR #254 remains deferred unless a fresh review proves its separate DB/platform architecture safe.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals/analytics contracts. Preserve the Tourvisor contract, existing lead-sending mechanism and field mapping, and verified social/app destinations. Do not migrate unresolved legal/payment content. Do not modify neighboring projects.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy/live evidence and the source-of-truth files. Treat every run as an autonomous development session, not a status check. Prefer multiple narrow SAFE/MEDIUM visual improvements per run, merge autonomously after green evidence, verify production, and update this roadmap plus `AUTOPILOT_STATE.json` after material progress.
