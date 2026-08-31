# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source, `TECHNICAL_REFACTOR_LOCK.json` locks the active technical-refactor phase, and `AUTOPILOT_STATE.json` is the machine-readable resume point.

## Current owner-directed phase — CI COST AUDIT AND TECHNICAL REFACTOR

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), the current priority is technical consolidation and GitHub Actions cost reduction. The objective is to cut duplicate runner/browser work without weakening the critical contracts around search, prices, tours, flights, recovery and lead submission.

AnyTour Design System 2.0 is the canonical design-system generation. Legacy Design System generation terminology must not be reintroduced. Implementation filenames such as `*-v1.js` / `*-v1.css` are independent module-generation identifiers and must not be mass-renamed without dependency-mapped migration.

## Ordered work

1. Audit workflows by actual dependency and classify them as PR FAST, PR BROWSER, POST DEPLOY, SCHEDULED-LIVE or consolidate/delete-after-coverage.
2. Reduce repeated runner starts, `npm init`, Playwright installation and Chromium dependency installation before deleting any unique behavioral coverage.
3. Consolidate overlapping checks into one domain owner; do not create one workflow per bug/string/selector.
4. Narrow broad `v2/**` triggers where a workflow does not consume `v2/data/**` or other changed subtrees.
5. Fold tiny source-string guards into an existing cheap owner where equivalent failure visibility is preserved.
6. Keep architecture/source-of-truth files synchronized and periodically re-audit the whole search product rather than only recently changed files.
7. Resume UX/visual work under AnyTour Design System 2.0 after the technical CI phase or when required to preserve a critical user journey.

## Current resume point

Completed cost reductions include data-only exclusions for several unrelated V2 visual/browser owners, consolidation of the duplicate primary-meal responsive workflow, removal of dormant manual traffic-audit stubs, and consolidation of the pending-flight label source guard into the existing pending-flight confidence owner.

Continue with the broad expensive owners first: `visual-v2-baseline.yml`, then dependency filtering for `validate-v2-pr.yml` / standalone owners, and further browser-suite consolidation where exact coverage overlap is proven. Maintain the target of at least a 2x reduction in routinely triggered PR jobs without weakening money/lead/search/price/tour/flight/recovery protection.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration or goals. Preserve the Tourvisor contract and the external lead-sending contract/field mapping. Do not modify neighboring projects. Keep GitHub as source of truth.

## Execution policy

At the start of each run inspect fresh `main`, open PRs, recent CI/deploy evidence and the source-of-truth files. Prefer narrow SAFE/MEDIUM consolidation PRs and merge autonomously after green evidence. If blocked, record/defer the blocker and continue another independent technical-refactor or CI-cost task. Do not let visual/design work silently preempt the active technical phase.
