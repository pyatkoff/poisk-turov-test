# AnyTour delivery master plan

Status: active program plan
Updated: 2026-09-04
Strategy: production-first, fresh-from-`main`, small reversible PRs

## Outcome

Deliver the owner-designated Search3 target experience, currently under review,
as a reliable production journey from search to a delivered manager lead,
without carrying the preview branch's overlay architecture or weakening
current production protections.

The plan is ordered by risk, not by visual convenience. A later slice starts
only when its dependency and evidence gate are complete. Each implementation
PR must state its exact `main` base, protected contracts, rollback method and
test evidence.

## Program rules

- Production remains the behavioral safety baseline until a candidate passes
  all gates and receives owner approval.
- `feature/search3-preview` is a UX/evidence donor. Never merge it wholesale.
- Port one coherent vertical slice into canonical owners on a fresh `main`.
- Keep the public route and active bundle unchanged until the release slice.
- Preserve Tourvisor, price, analytics and lead contracts unless a separately
  approved contract PR proves a defect and compatibility plan.
- A green screenshot workflow is necessary visual evidence, not proof of lead
  delivery, negative states or production readiness.
- No cleanup deletion is combined with a behavior migration.

## Workstreams

| Stream | Owns | Does not own |
| --- | --- | --- |
| Product/funnel | journey, states, factual copy, acceptance | analytics implementation without approval |
| Search/results | form, lifecycle, progressive results, facets | Tourvisor contract changes |
| Tour/checkout | selected tour, rooms, flights, review, lead UI | external lead payload/transport changes |
| Experience | responsive UI, accessibility, DS2, content | parallel business logic for mobile |
| Platform/release | exact-SHA identity, candidate profile, gates, rollback | unreviewed production rollout |

## Execution model

Recommended default for the full program: `gpt-5.6-sol` with `high` reasoning.
Use `xhigh` for Tourvisor/price/lead state, protected-contract and release/rollback
slices. Reserve `ultra` for infrequent cross-program audits or a final release
review where independent parallel checks materially reduce risk. Fast/mechanical
inventory work may use a lighter model, but its conclusions must be reviewed by
the primary model before they change architecture, contracts or release state.

Use parallel agents for independent read-only review (product funnel,
architecture, QA/accessibility, release), then keep one code owner per PR. Model
choice never expands authority: protected changes and production release still
require the approvals defined by `AGENTS.md` and this program.

## Ordered PR map

| # | PR slice | Primary result | Required evidence | Risk |
| ---: | --- | --- | --- | --- |
| 1 | `foundation/project-definition` | Current sources of truth, product brief, migration map, gates and resume state | docs/JSON + governance guard validation; no product/deploy runtime diff | SAFE |
| 2 | `baseline/release-audit` | Read-only map of actual deploy identity, LKG, copy window and rollback gaps | production/repository evidence; no mutation | SAFE |
| 3 | `release/exact-sha-boundary` | Approved immutable candidate/LKG identity and rollback mechanism | rehearsal, compatibility and rollback proof; explicit platform approval | HIGH |
| 4 | `search3/reference-dossier` | Eight target layouts, remaining diff statuses and run-467 evidence are durable and classified | owner-visible evidence index; checksums/links | SAFE |
| 5 | `search3/contract-boundaries` | Behavioral snapshots for Tourvisor mapping, lifecycle, prices, lead payload and existing analytics events | deterministic contract tests on current `main` | MEDIUM |
| 6 | `search3/integration-scaffold` | Isolated candidate route/profile built from current `main`, current shell, APIs and widget | route isolation; production bundle unchanged; five-width smoke | MEDIUM |
| 7 | `search3/failure-fixtures` | Deterministic flight success, empty, error and timeout plus lead lifecycle fixtures | fast fixtures + browser proof; no simulated success in production code | MEDIUM |
| 8 | `search3/no-flight-fallback` | Any selected tour can reach a real manager lead without a selected flight | first/cheapest tour; empty/error/timeout/retry; payload snapshot | HIGH |
| 9 | `search3/truthful-state-machine` | One booking state owner; stepper and CTAs derive from real eligibility | transition matrix; stale response/back/switch/pending-lock tests | HIGH |
| 10 | `search3/form-view` | Direct semantic Search3 form over current catalogs/defaults/URL hydration | keyboard/focus, dates/nights/children, five required widths | MEDIUM |
| 11 | `search3/results-view` | Compact hotel cards and expanded tours over current normalized results | partial 25/final 100, rerender, empty/error, long/missing data | MEDIUM |
| 12 | `search3/facet-engine` | One local facet engine with desktop rail and mobile drawer views | complete-data guard, reset behavior, no search on local filtering | MEDIUM |
| 13 | `search3/selected-review-lead` | Target selected-tour, review and lead presentation over protected send path | flight/no-flight, duplicate, error/retry, preserved input/context | HIGH |
| 14 | `search3/a11y-type-responsive` | Critical path is legible and operable at all target widths and by keyboard | axe/manual focus; 360–1440 edge audit; zoom/overflow proof | MEDIUM |
| 15 | `search3/assets-consolidation` | Component CSS/JS enters the canonical bundle without overlay/lock layers | manifest closure; bundle order; performance comparison | MEDIUM |
| 16 | `search3/content-trust-language` | Claims, labels and recovery language are factual and consistent | content/legal owner verification; destination/link audit | MEDIUM |
| 17 | `release/cohort-routing` | Verified internal/cohort/canary routing, or an explicitly approved non-percentage switch plan | sticky assignment, observability, disable/rollback proof; explicit platform approval | HIGH |
| 18 | `release/search3-candidate` | One exact candidate SHA passes all gates and controlled release | owner approval; preflight; live search/lead/shell/widget; rollback proof | HIGH |
| 19 | `cleanup/search-preview-layers` | Proven-dead Search2/Search3 preview assets are removed separately | repository-wide consumer proof; full regression | MEDIUM |

The read-only `baseline/release-audit` refined umbrella slice 3 into four narrow
reviews: build-only `release/package-contract`,
`release/deploy-cancellation-containment`, `release/exact-sha-boundary`, and
`release/atomic-current-lkg`. The latter three remain HIGH risk; no production
workflow or server-layout change is authorized by the audit. See
`docs/project/RELEASE_BASELINE_AUDIT.md` for evidence and exit criteria.

SEO/content growth is an independent program after the transactional journey is
stable. It must not expand indexable inventory faster than quality, canonical,
internal-link and conversion handoff checks can validate it.

## Phase gates

### Phase 0 — definition and baseline

Complete PRs 1, 2, 4 and 5. The separately approved release-platform PR 3 may
run in parallel after its audit; it blocks production release, not isolated
reference/contract work. Exit only when the current identity gaps are explicit
and protected contracts have executable coverage.

### Phase 1 — safe candidate path

Complete PRs 6–9. Exit only when failure states are real, deterministic and a
flight failure cannot remove the lead path.

### Phase 2 — experience migration

Complete PRs 10–16. Exit only when the owner-designated target design is implemented through
canonical state/controllers, not DOM rearrangement or CSS convergence patches.

### Phase 3 — release

Complete release-platform PRs 3 and 17, then PR 18 under `RELEASE_GATES.md` and
`ROLLOUT_RUNBOOK.md`. Production traffic changes only after explicit owner
approval of the exact candidate and platform scope.

### Phase 4 — cleanup and growth

Complete PR 19 only after production evidence. Start conversion experiments,
content expansion and feature growth from a measured stable baseline.

## First implementation milestone

The first code milestone is not a new visual form. It is a trustworthy release
foundation followed by a no-flight recovery slice:

1. audit exact deployed/LKG identity and define the separately approved fix;
2. freeze protected contract snapshots;
3. create a current-`main` isolated candidate;
4. create deterministic failure fixtures;
5. make the first or cheapest tour reach lead review when flights are empty,
   error or time out.

This milestone removes the highest-known lead-loss risk while keeping the
external lead mechanism unchanged.

## Definition of done for every implementation PR

- one named product behavior and one canonical code owner;
- exact base/head SHA and an explicit runtime-diff statement;
- affected protected contracts named and verified;
- focused deterministic tests plus the required broader tier;
- real browser evidence for user-visible work;
- negative and recovery paths, not only the happy path;
- no unexplained console/page errors, overflow or duplicate rendering;
- docs/state updated when ownership, queue or evidence changes;
- a specific rollback action that does not depend on deleting user data.

## Decision log

| Date | Decision | Reason |
| --- | --- | --- |
| 2026-09-04 | Start with a Project Definition Pack | Existing operational docs describe different generations and scores |
| 2026-09-04 | Rebuild Search3 from current `main` | The preview branch diverged substantially and lacks later safety fixes |
| 2026-09-04 | Treat no-flight lead recovery as P0 | Current preview happy-path QA can skip the conversion-blocking case |
| 2026-09-04 | Defer analytics implementation | Existing Metrica/goals are protected; measurement needs separate approval |
| 2026-09-04 | Treat `fa58a0c…` as probable, not verified, production source | Run 587 points to that SHA; live bundles are compatible but cannot independently identify it, and no public SHA, immutable artifact, LKG or atomic release boundary exists |
| 2026-09-04 | Contain the public lead-file deployment window before Search3 release work | Current tar briefly publishes the direct Bitrix adapter at the public bridge path and cancellations have occurred during remote mutation |
