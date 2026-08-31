# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is site-wide visual unification under AnyTour Design System 1.0. The whole public site must read as one coherent product from homepage and destination pages through hot/search, results, selected tour and lead.

The previous technical-refactor / CI-cost phase is superseded by the current owner direction. CI consolidation remains useful supporting work but must not preempt confirmed visual, responsive or cross-page coherence gaps.

## Ordered work

1. Unify shared design tokens/primitives, header/navigation, footer, typography, grid/spacing, buttons, cards, breadcrumbs and responsive behavior across the public site.
2. Strengthen `/country/` and representative country pages, keeping editorial pages clear and lighter than the search product while improving handoff into live search.
3. Audit and refine `/`, `/poisk-turov/`, `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` where route-specific hierarchy still drifts from the shared shell.
4. Validate the full journey `homepage → country/destination → hot/search → results → selected tour → lead` at 375/430/768/1024/1440.
5. Fix confirmed wrapping, overflow, crooked spacing, duplicated shell and hierarchy defects before decorative flourishes.
6. Continue data/SEO and technical work only after higher-priority visual/product gaps or when required for reliability.

## Current resume point

The shared shell, header/navigation, footer, common cards/buttons/breadcrumbs and responsive page gutters already cover the main standalone routes. Resume at the country/destination layer: remove route-specific hierarchy drift, strengthen the destination-to-live-search handoff and verify all representative country pages at the required widths. Then run the full cross-page product journey and address the next confirmed inconsistency.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not redesign or replace the AnyTour logo. Do not modify Yandex Metrika configuration/goals, the Tourvisor contract, or the external lead-sending contract/field mapping. Preserve verified social/app destinations. Do not modify neighboring projects. Do not migrate unresolved legal/payment content. Keep PR #254 deferred unless a fresh review proves its separate architecture safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence, production/live behavior where accessible and the source-of-truth files. Prefer independent SAFE/MEDIUM visual improvements, validate user-facing work at 375/430/768/1024/1440, merge only after relevant checks are green, then verify production behavior. If blocked, record/defer the blocker and continue another safe task.
