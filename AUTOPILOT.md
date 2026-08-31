# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` records whether the older refactor-first phase is active, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is site-wide visual unification. The whole public site is evaluated separately from the stronger search-only engineering experience.

## Ordered work

1. Establish and preserve shared design tokens/primitives: one coherent header/navigation, footer, typography, grid/spacing, buttons/cards, breadcrumbs and responsive behavior.
2. Audit and improve `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/`, `/rb/`, `/country/` and representative country pages.
3. Make the journey feel like one product across homepage → country/destination → hot/search → results → selected tour → lead.
4. Validate user-facing changes at 375/430/768/1024/1440 and fix confirmed spacing, wrapping, overflow, duplicated-shell and hierarchy problems before cosmetic flourishes.
5. Preserve mature search/recovery/results/comparison/flight/price/fuel/lead regressions while migrating weaker pages onto the shared shell.
6. Continue technical refactoring only when justified and safe; it no longer outranks the explicit Design System priority.

## Current resume point

Shared header/page-grid, breadcrumb-grid and search-shell alignment slices have reached production with green relevant regression/live evidence. PR #472 removes the next confirmed shared-shell discontinuity: at 768–820px the global community/footer wrappers were capped at 720px while the Design System page grid uses the 760px tablet contract. The PR five-width/relevant validation set completed without failures before merge. Post-merge standalone/V2 deploy and live checks are the release gate for this slice.

Continue with the full production journey at 375/430/768/1024/1440. Re-audit `/`, `/country/` plus a representative country page, `/hot/`, `/poisk-turov/`, results, selected tour and lead first; then `/contacts/`, `/how-to-buy/` and `/rb/`. Fix only confirmed hierarchy, spacing, wrapping, overflow or duplicated-shell regressions. Keep the recently fixed hotel field inside advanced filters and preserve its autocomplete behavior.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test` and allowed V2/standalone production scope. Do not redesign/replace the AnyTour logo. Do not modify Yandex Metrika/goals/analytics contract, Tourvisor contract, existing lead-sending mechanism or neighboring projects. Preserve verified social/app destinations. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate DB/platform architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence, production/live behavior where accessible and this resume point. Continue through multiple independent safe Design System tasks for as long as execution time allows. Deploy only after relevant checks are green and verify production/live visual behavior after release.
