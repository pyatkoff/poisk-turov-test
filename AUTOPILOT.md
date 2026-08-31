# poisk-turov-test — Autopilot Roadmap

Updated: 2026-08-31

Operational companion to `AGENTS.md`. `OWNER_PRIORITY.json` is the canonical owner-priority source and `AUTOPILOT_STATE.json` is the machine-readable resume point. Architecture is owned by `ARCHITECTURE.md`; CI/test ownership is owned by `TEST_MATRIX.md`.

## Current owner-directed phase — TECHNICAL REFACTOR PASS

After emergency overrides (`production_broken → lead_loss → incorrect_data → broken_user_journey`), prioritize architecture consolidation and evidence-driven technical debt reduction. Do not resume UX/visual implementation until the technical consolidation sequence below is complete or the owner explicitly changes direction.

AnyTour Design System 2.0 remains the canonical design-system generation. Existing DS2 runtime work stays in place; this phase does not roll it back. The goal is to remove architectural duplication and make future UX work safer.

## Ordered work

1. Keep `ARCHITECTURE.md` and `TEST_MATRIX.md` canonical and enforce `one concept → one implementation`.
2. Complete repository inventory/dependency mapping and classify files as `ACTIVE`, `COMPATIBILITY`, `DEPRECATED` or `DEAD-CANDIDATE` based on evidence, never name/age alone.
3. Complete GitHub Actions audit and classify every workflow into `PR FAST`, `PR BROWSER`, `POST DEPLOY` or `SCHEDULED-LIVE`; remove/consolidate duplicates only after equivalent behavior/path coverage is proven.
4. Prepare behavior-preserving ownership structure for `shared/search/results/tour/checkout/integrations/site/seo/tests/scripts/templates`; no mass move/rename.
5. Consolidate shared template layer toward one header, one footer, one navigation and one design system implementation.
6. Resume UX/visual convergence only after technical consolidation.

## Current resume point

First reconcile architecture/source-of-truth documents and guards with this technical phase. Then continue exhaustive active/compatibility/dead inventory and dependency mapping. Use `v2/bundle-manifest-v1.php` as the canonical active browser-bundle source and `scripts/ci/inventory_v2_assets.py` as evidence tooling; repository references are evidence, not proof of runtime activity or deadness.

For CI, use `CI_WORKFLOW_AUDIT.md` as the evidence ledger and `TEST_MATRIX.md` as policy. Prefer consolidating runner/bootstrap duplication only when protected behavior, trigger/path coverage and failure visibility remain equivalent.

## Mandatory protections

Work only inside `pyatkoff/poisk-turov-test`. Do not modify Yandex Metrika configuration/goals, Tourvisor external contract, external lead-sending contract/field mapping or neighboring projects. Preserve the existing AnyTour logo and verified destinations. Do not perform mass file moves, renames or deletions without dependency proof and relevant regression coverage.

## Refactor decision rule

Do not refactor for style and do not invent defects. For each slice: map dependencies → identify canonical owner/implementation → prove coverage → migrate/consolidate narrowly → run focused CI → merge SAFE/MEDIUM changes only after green relevant checks. If blocked, record/defer the blocker and continue an independent safe slice.
