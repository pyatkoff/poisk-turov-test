# poisk-turov-test — operational roadmap

Updated: 2026-09-04

Operational companion to `AGENTS.md`. Start with `START_HERE.md`.
`OWNER_PRIORITY.json` records owner direction, `AUTOPILOT_STATE.json` is the
machine resume point, and `docs/project/MASTER_PLAN.md` owns the ordered PR
program. Architecture and test ownership remain in `ARCHITECTURE.md` and
`TEST_MATRIX.md`.

## Current owner-directed phase — ANYTOUR SEARCH3 STABILIZATION

Build a commercially effective and dependable public AnyTour journey, using
the owner-designated Search3 target/reference under review as a design and
behavior donor and current `main` as the only implementation base.

Priority after production, lead-loss, incorrect-data and broken-journey
emergencies:

1. exact production/release identity and protected contract baselines;
2. deterministic Search3 failure fixtures;
3. selected tour → review → lead recovery with no flight;
4. one truthful booking state machine;
5. direct semantic Search3 form/results/filter/tour/lead views;
6. accessibility, responsive readability and asset consolidation;
7. factual content/trust convergence;
8. owner-approved controlled release;
9. separately measured conversion and SEO growth.

AnyTour Design System 2.0 remains the only canonical design system. Treat the public site as one product across homepage → destination → search → results → selected tour → lead.

## Search3 decision and release lock

`feature/search3-preview` must not be merged wholesale. It has diverged
substantially from `main`, uses an overlay/convergence architecture and lacks
later production safety fixes. Preserve the owner-supplied target layouts and
interaction intent, resolve the candidate's remaining review differences, then
rebuild them in small slices inside canonical owners on a fresh `main`.

The Search3 preview may remain available at the existing isolated
`/_preview/search3/poisk-turov/` route for evidence. Do not switch it onto
production `/poisk-turov/`, merge a release candidate, or increase production
traffic until the exact candidate passes `docs/project/RELEASE_GATES.md` and
the owner explicitly approves that candidate.

This lock is documentary until the separately approved exact-SHA and cohort
release controls exist. Current `v2/**` changes merged to `main` can trigger the
production deploy workflow, so candidate runtime work must remain isolated.

Search2 PR #810 and its preview are historical/superseded by this direction;
do not merge or deploy them to production.

## Protected boundaries

- Preserve Yandex Metrica configuration, goals and existing analytics contract.
- Preserve Tourvisor action/query/response, progressive 25→100 loading,
  generation/stale and price/flight semantics.
- Preserve external lead URL, payload, field mapping, bridge/receiver/adapter,
  idempotency and operational routing.
- Preserve current shared header/footer/logo, public routes, verified
  destinations and `https://app.anytoour.ru/web-consultant/widget.js`.
- Do not modify neighboring projects, manager shifts/routing/bonuses or
  unresolved legal/payment behavior.
- `technical_refactor` and PR #254 remain deferred as broad programs. Targeted
  code organization is allowed only inside an approved product slice with
  behavioral evidence.

## Current evidence boundary

- planning `main` snapshot: `fa58a0cba6dcfc8624d98c20d64fa06330eae309`;
- Search3 branch head: `6ce565620becaba8e91d50aff13529b5a52aba37`;
- preview implementation: `e5baf32f455cdb0aa1a704964f28e5efbebf57ff`;
- visual run 467 is green at 390/834/1440, but remains visual happy-path
  evidence;
- production deployed SHA and last-known-good SHA are not yet exposed/verified
  for this program.

The repository advances frequently. Fetch `main` and record the new exact base
before every implementation branch.

## Confirmed P0

Search3 preview QA searches for an offer with flight variants and simulates
lead lifecycle states. In the current implementation, empty/error/timeout
flight resolution can prevent review and lead entry, while the stepper can
visually enter an unavailable future step. This is a release-blocking lead-loss
risk, not a visual polish item.

Required sequence:

1. make success/empty/error/timeout deterministic;
2. preserve selected context;
3. expose an honest manager fallback without a selected flight;
4. prove the unchanged protected lead payload/transport;
5. make step/CTA eligibility derive from one real state owner.

## Resume point

`foundation/project-definition` is the active slice until its PR checks and
review are complete. After merge and an explicit state update, advance to the
read-only `baseline/release-audit`; then follow `docs/project/MASTER_PLAN.md`
one PR at a time. Runtime/platform work remains separately gated.

Do not use earlier numerical scorecards as current release readiness. They are
dated assessments with different scopes. Establish a measured production
baseline before assigning a new score or interpreting conversion.
