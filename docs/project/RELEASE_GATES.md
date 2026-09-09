# AnyTour Search3 release gates

Status: required acceptance policy
Updated: 2026-09-04

Search3 is releasable only when one exact candidate SHA passes every applicable
gate. Evidence from different commits cannot be combined into a release claim.
Owner visual approval is necessary but does not override a failed reliability,
contract, accessibility or rollback gate.

## Gate summary

| Gate | Required outcome | Evidence owner |
| --- | --- | --- |
| G0 Scope and identity | exact base/head/deployed/LKG identity; diff is in approved scope | release |
| G1 Protected contracts | Tourvisor, price, analytics and lead behavior unchanged | contract tests |
| G2 Functional journey | search → results → tour → review → lead works in positive and negative paths | browser/diagnostics |
| G3 State integrity | no stale response, false step, duplicate action or lost context | state tests |
| G4 UX/accessibility | approved visuals, semantic controls, keyboard/focus and readable responsive UI | browser/manual |
| G5 Quality/performance | no blocking errors, overflow or material regression; target budgets recorded | CI/lab/field |
| G6 Operations | live dependencies, monitoring, canary controls and exact rollback are ready | release/operator |
| G7 Approval | owner approves the exact candidate and known residual risk | owner |

## G0 — scope and release identity

- branch started from freshly fetched `main`; base and head SHAs recorded;
- production deployed SHA and last-known-good SHA are independently readable;
- release artifact/profile is attributable to the candidate SHA;
- public production route is unchanged before the release step;
- diff contains no neighboring-project, manager-routing or undeclared contract
  change;
- rollback target and command/procedure refer to an exact immutable version.

## G1 — protected contracts

- Tourvisor action names, query mapping, progressive polling and response
  semantics pass current contract snapshots;
- 25→100 loading and generation/stale guards are preserved;
- atomic round-trip, selected-flight, base/flight/fuel and unpriced fallback
  semantics pass;
- lead endpoint, payload fields, bridge/receiver/adapter chain, mapping and
  idempotency pass protected snapshots;
- existing analytics/Metrica configuration, goals and event semantics are
  byte/behavior unchanged unless an explicitly approved analytics PR exists;
- current shared header/footer/logo and `app.anytoour.ru` consultant integration
  are present; retired Search3 external scripts are absent.

## G2 — functional journey matrix

Required on desktop and mobile, with representative tablet evidence:

| Domain | Required scenarios |
| --- | --- |
| Search | valid, invalid, slow, retry, empty, new generation while prior pending |
| Results | first 25, final loaded set, rerender, long/missing content, correct hotel/tour counts |
| Filters | complete facet, incomplete facet hidden/reset, clear-all, mobile drawer, no supplier search on local change |
| Selection | first/cheapest card, another card, back, switch, stale room/flight response |
| Flights | success, empty, error, timeout, retry, price update |
| Review | with flight, without flight, honest uncertainty, preserved context |
| Lead | valid, validation error, pending lock, accepted, duplicate, error/retry, preserved user input |

The test must not search for a convenient offer to bypass empty/error behavior.
Lead lifecycle evidence must exercise the real candidate controller and sender;
preview-only event simulation cannot satisfy this gate.

## G3 — state integrity

- stepper is a projection of one booking state owner and cannot activate an
  ineligible step;
- only the current search/tour/flight generation may update the UI;
- navigation and selection actions are locked or safely canceled during lead
  submission according to existing behavior;
- back/retry/switch preserves valid context and invalidates stale context;
- every pending state has a success, empty/error and retry/escape outcome;
- a flight failure cannot remove manager lead entry;
- repeated render/event delivery does not duplicate cards, handlers, goals or
  lead attempts.

## G4 — UX and accessibility

- owner approves the exact candidate at the evidence viewports;
- required widths: 375, 430, 768, 1024 and 1440 px;
- edge audit: 360, 390, 834, 999, 1000, 1280 and 1366 px where the component
  changes layout;
- no horizontal document overflow, clipped essential content or overlapping
  controls at 200% zoom;
- one logical H1; controls have programmatic names, states and error relations;
- full critical journey works by keyboard with visible focus;
- popovers/dialogs manage expanded state, Escape, focus trap and focus restore;
- visually hidden native inputs are not stray tab stops;
- touch targets and text sizes follow canonical DS2 guidance;
- reduced-motion and screen-reader announcements cover meaningful async state.

## G5 — quality and performance

- required repository test tiers in `TEST_MATRIX.md` are green for the exact
  SHA;
- no unexplained page error, failed critical request, duplicate initialization,
  hydration/DOM ownership race or console exception;
- active asset manifest is closed and ordered; no preview lock/convergence,
  screenshot-only or runtime style-injection layer ships;
- lab comparison against production shows no material regression in critical
  rendering, interaction latency or transferred/parsed code;
- record field baseline before using Core Web Vitals as a rollout signal;
- product targets, once measurable: p75 LCP ≤ 2.5 s, INP ≤ 200 ms and CLS ≤ 0.1
  at the 75th percentile for relevant traffic segments.

The targets do not excuse a regression when insufficient field traffic makes a
percentile unavailable; use lab and runtime evidence until the sample is valid.

## G6 — operational readiness

- candidate deploy and LKG rollback have been rehearsed on a non-production or
  isolated target;
- current deployment copy semantics are accounted for; no check runs against a
  partially copied release;
- live smoke covers public render, one real search, Tourvisor boundary, lead
  bridge/invocation without creating uncontrolled customer data, shared shell
  and consultant widget;
- monitoring distinguishes synthetic traffic and names who can stop rollout;
- rollback triggers, exact actions and post-rollback verification are ready;
- no cleanup/deletion is required to roll back.

## G7 — decision record

The release record must contain:

- candidate SHA and artifact/profile identity;
- production baseline and LKG SHA;
- links to all gate evidence;
- visual approval for the exact candidate;
- known residual risks and accepted limitations;
- rollout stage and operator;
- rollback target and verification result.

Any failed gate stops traffic progression. A waiver requires an explicit owner
decision naming the failed gate, user/business impact, time limit and rollback
trigger; protected external contracts cannot be waived implicitly.
