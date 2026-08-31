# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` locks the active technical-refactor phase, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is technical consolidation. AnyTour Design System 2.0 remains the canonical design-system generation, but UX/visual work must not preempt this phase.

## Ordered work

1. Make `ARCHITECTURE.md`, `TEST_MATRIX.md` and the one-concept → one-implementation rule authoritative.
2. Complete a repository-wide inventory/dependency map of ACTIVE, COMPATIBILITY and DEAD candidates; never delete from naming/version evidence alone.
3. Classify GitHub Actions into PR FAST / PR BROWSER / POST DEPLOY / SCHEDULED-LIVE and remove duplicates only after equivalent behavioral coverage is proven.
4. Prepare a behavior-preserving target structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates` before moving runtime code.
5. Consolidate the shared template layer to one header, one footer, one navigation and one canonical design system.
6. Resume UX/visual work only after the technical consolidation phase, except when required to fix a higher-priority broken user journey.

## Current resume point

Continue the evidence-driven inventory and CI audit. Use `v2/bundle-manifest-v1.php` as the active browser-bundle source of truth and repository consumer mapping as evidence for non-manifest assets. Prefer narrow SAFE/MEDIUM changes that reduce duplicated ownership or source-of-truth drift without altering runtime behavior.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration or goals. Preserve the Tourvisor contract and external lead-sending contract/field mapping. Do not modify neighboring projects. Keep AnyTour Design System 2.0 as the canonical design-system generation. Keep PR #254 deferred unless a fresh architecture review proves it safe.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence and the source-of-truth files. Prefer narrow SAFE/MEDIUM technical PRs and merge autonomously after relevant green evidence. If blocked, record/defer the blocker and continue another independent technical task. Do not refactor for style and do not invent defects.
