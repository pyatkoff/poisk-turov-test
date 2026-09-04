# AnyTour — start here

Updated: 2026-09-04
Program: **Project Definition and Search3 stabilization**
Scope: `pyatkoff/poisk-turov-test` only

This is the short entrypoint for continuing the AnyTour site program. It does
not replace domain-specific sources of truth; it links them and records the
current decision, evidence boundary and next action.

## Owner direction

Build a commercially effective, reliable AnyTour website: a clear discovery
experience, a fast tour search, an honest decision flow and a lead path that
does not fail when supplier data is incomplete or temporarily unavailable.

The current instruction authorizes this project-definition/governance slice.
The master plan defines later implementation sequencing; it does **not** by
itself authorize runtime, platform or release changes. This slice does not
authorize:

- switching Search3 onto production traffic;
- changing Yandex Metrica configuration or existing goals;
- changing the external lead transport, payload or field mapping;
- changing Tourvisor semantics, pricing semantics, manager shifts, routing or
  neighboring projects;
- a whole-repository rewrite or a wholesale merge of the preview branch.

## Source-of-truth hierarchy

| Question | Authoritative source |
| --- | --- |
| Authority, risk and hard boundaries | `AGENTS.md` |
| Current owner priority | `OWNER_PRIORITY.json` |
| Product goal, users and non-goals | `docs/project/PRODUCT_BRIEF.md` |
| Active phases and PR sequence | `docs/project/MASTER_PLAN.md` |
| Architecture and canonical owners | `ARCHITECTURE.md` |
| Search3 donor-to-main mapping | `docs/project/SEARCH3_MIGRATION_MAP.md` |
| Active browser asset order | `v2/bundle-manifest-v1.php` |
| Test ownership and required tiers | `TEST_MATRIX.md` |
| Release acceptance | `docs/project/RELEASE_GATES.md` |
| Observed production release baseline | `docs/project/RELEASE_BASELINE_AUDIT.md` |
| Deploy, canary and rollback procedure | `docs/project/ROLLOUT_RUNBOOK.md` |
| Proposed funnel semantics and KPI | `docs/project/FUNNEL_SPEC.md` |
| Human/machine resume state | `AUTOPILOT.md` / `AUTOPILOT_STATE.json` |

If a historical roadmap or scorecard conflicts with this hierarchy, it is
evidence of a prior state rather than an instruction for current work.

## Verified planning snapshot

This snapshot identifies the evidence used to create the project definition;
it is not a production deployment claim.

| Item | Value |
| --- | --- |
| `main` snapshot | `fa58a0cba6dcfc8624d98c20d64fa06330eae309` |
| Search3 branch head | `6ce565620becaba8e91d50aff13529b5a52aba37` |
| Search3 implementation shown on preview | `e5baf32f455cdb0aa1a704964f28e5efbebf57ff` |
| Search3 visual workflow | run 467, green on desktop/tablet/mobile |
| Search3 preview | `https://anytoour.ru/_preview/search3/poisk-turov/` |
| Preview indexing | HTTP `X-Robots-Tag: noindex, nofollow` confirmed |
| Probable production source | `fa58a0cba6dcfc8624d98c20d64fa06330eae309` via successful deploy run 587; public exact identity remains unverified |
| Last-known-good SHA | **undefined: no designation, retained artifact or LKG pointer** |

The repository is active and `main` may advance. Every implementation branch
must therefore start from a freshly fetched `main` and record its exact base.

## Decisions already made

1. Current production remains the safety baseline.
2. Search3 is a **design and behavior donor**, not a production code base.
3. Do not merge, rebase or cherry-pick the Search3 branch wholesale.
4. Rebuild the owner-designated Search3 target behavior inside canonical owners on fresh `main`; the candidate remains under review.
5. Preserve progressive 25→100 loading, stale-response guards and the rule that
   local facets are exposed only for complete loaded data.
6. A supplier, price or flight failure must never remove the ability to send
   the selected context to a manager.
7. An interface step or CTA must represent a real state transition.
8. Design System 2.0 and the supplied AnyTour logo remain canonical.
9. Trust copy must be factual; unsupported guarantees, timings, availability
   and payment claims are prohibited.
10. CI green is not DONE without real functional, visual and release evidence.

## Current highest-priority finding

Search3 visual QA intentionally finds an offer with flight variants and
simulates lead sending/success/error states. The live preview and source review
show a missing negative path: when flights are empty or fail, final review and
lead entry can become unreachable. The stepper can also visually activate a
future step without changing the underlying state.

This is a release-blocking conversion risk. The first product implementation
must make the failure deterministic in tests, then guarantee a truthful
no-flight-to-lead recovery path without changing the external lead contract.

## Active task

`foundation/project-definition`

Deliverables:

- one current product definition and master plan;
- explicit source-of-truth ownership;
- Search3 migration inventory;
- release gates and rollout/rollback procedure;
- a schema-valid continuation state;
- no production runtime, deploy or analytics behavior changes.

## Next implementation sequence

1. `baseline/release-audit` — evidence is recorded in
   `docs/project/RELEASE_BASELINE_AUDIT.md` on a stacked review branch; it maps
   the probable production source, missing exact identity/LKG and safest
   correction without changing the platform.
2. `release/exact-sha-boundary` — only after explicit platform approval, make
   candidate and rollback identity reproducible.
3. `search3/reference-dossier` — preserve the target mockups, outstanding
   visual-diff backlog and run-467 evidence outside expiring CI artifacts.
4. `search3/contract-boundaries` — freeze current Tourvisor, lifecycle, price,
   lead payload and analytics behavior before UI migration.
5. `search3/integration-scaffold` — create an isolated candidate route from
   current `main`, using current APIs, shared shell, lead boundary and widget.
6. `search3/failure-fixtures` — deterministic flight success/empty/error/
   timeout and lead lifecycle fixtures inside existing test ownership.
7. `search3/no-flight-fallback` — make the first/cheapest failing offer reach a
   real lead entry while preserving selected context.
8. `search3/truthful-state-machine` — real eligibility for stepper and CTA.
9. Continue the form, results, filters, selected-tour and visual migration in
   the order defined by `docs/project/MASTER_PLAN.md`.

## Resume protocol

Before any new task:

1. read `AGENTS.md`, this file, `AUTOPILOT.md`, `AUTOPILOT_STATE.json`,
   `ARCHITECTURE.md` and `TEST_MATRIX.md`;
2. fetch `main` and record the exact base SHA;
3. inspect the current GitHub runtime/CI signal and active deploys;
4. choose one narrow PR from the queue;
5. declare protected areas and required evidence before editing;
6. after merge/deploy, update the state with exact SHA, CI, production evidence,
   remaining risk and the next PR.

Recommended execution model: `gpt-5.6-sol` with `high` reasoning for normal PRs,
`xhigh` for lead/Tourvisor/release-critical work, and `ultra` for program-level audits or work
that benefits from independent parallel review.
