# Search3 release polish — 2026-09-04

Status: implemented locally; browser evidence and preview publication pending.

## Candidate

Repository: `pyatkoff/poisk-turov-test`.
Branch: `integration/search3-release-polish-v1`.
Baseline: `5ca8c7f71ec8039fc1a1f26661101629cfe5f6c8` (existing PR #1324).
Integrated code and test head: `af70845c`.

- Mobile price is a separate amount, with traveler composition in its own caption.
- Summary, detail rail and lead summary consume the existing normalized price update; no price arithmetic or external payload changes.
- Flight list initially shows six choices, can reveal all choices, and retains an out-of-range selected choice when collapsed.
- Three named booking stages and consistent next/review/submit labels.
- Intermediate-width cards have a wider text area and two-column facts; mobile service controls are compact and search parameters remain visible.
- Footer removes unverified commercial promises and faux navigation; small footer labels are at least 12 px.

## Local validation

- Selected-flow focused tests: 3 passed.
- Asset seams: 4 passed, frozen base fingerprints updated for the reviewed price and footer edits.
- Protected Search3 contract checks: passed.
- JavaScript syntax and whitespace checks: passed.
- Added browser scenarios for 133 flights, 101000 / 113600 / 1234567 totals, family with two children, review/lead/return transitions, and card copy clipping.

The browser scenarios have NOT run for this revision. Do not treat the previous candidate's screenshots as evidence for these changes.

## Production preparation

Separate branch from main: `release/search3-production-ready-v1`.
Eight presentation assets are imported with source/destination fingerprints. A route-scoped PHP helper enables them on the canonical AnyTour search page. Existing lead, API, analytics and pricing modules are fingerprinted and unchanged. Preview lead simulation and global matchMedia restoration are omitted. The mobile filter chooses the Search3 breakpoint directly.

Two production static checks passed. PHP rendering is pending CI because PHP is unavailable in the local runtime. No production merge or deployment is authorized by this preparation.

## Immediate next actions

1. Obtain the explicit GitHub publication approval requested by automatic action review. Both attempted pushes were rejected as external code export despite matching the existing PR repository. Do not retry via another transport.
2. Publish the integration branch and a `visual/search3-candidate-*` branch; create draft review and candidate CI PRs.
3. Run the integrated candidate tier once, resolve concrete failures, inspect all five widths and selected-tour states.
4. Publish the exact passing artifact to the existing preview using the previously verified guarded deployment process.
5. Walk the live search through flight selection and lead form; preview lead delivery stays disabled.
6. Finish the production draft PR and its route/render checks. Request visual production approval only with the exact reviewed preview and release diff available. No main merge before that approval.

Production lead delivery remains unverified until a separately approved end-to-end production check.
