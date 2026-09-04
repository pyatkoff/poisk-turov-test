# Search3 release polish — 2026-09-04

Status: corrected preview published and verified; production owner approval pending.

## Published candidate

- Repository: `pyatkoff/poisk-turov-test`.
- Exact deployed source: `c72fbd44205927640f0ce45d785af70f88351d21`.
- Preview: https://anytoour.ru/_preview/search3-candidate/poisk-turov/
- Candidate CI: https://github.com/pyatkoff/poisk-turov-test/actions/runs/33918861700
- Deployment: https://github.com/pyatkoff/poisk-turov-test/actions/runs/33919209379
- Evidence artifact: `9954267481`; release artifact: `9954272229`.
- Integration review: PR #1332. Candidate artifact review: PR #1333 (do not merge to main).

## Corrections

Mobile price uses a distinct amount. Summary, detail rail and lead form follow the existing normalized price event without changing price arithmetic. Flight choices initially show six options, with expansion and preservation of the selected option. Three named stages and consistent action labels clarify the route to the application. Legacy duplicate checkout stages are suppressed inside Search3.

Cards at intermediate widths have more room for text. Mobile filter and sort controls share a row, and dates, nights and traveler composition remain visible. Footer removes unverified commercial promises and faux navigation.

## Verification

Candidate CI passed at 375, 430, 768, 1024 and 1440 px. Browser guards cover long prices, a family with two children, selected flight price consistency, review/form/return transitions, legacy checkout suppression and card clipping. Screenshots were inspected. PHP route/render checks passed in production-draft CI.

Final live desktop search returned 92 hotels and 328 tours. LUXOR APART started at 72,873 RUB; selecting flight variant 2 updated the total to 90,095 RUB. That amount persisted through review, application form and return. Duplicate legacy stages had computed display none and zero height after return. No real application was sent. Live mobile interaction was not performed; mobile validation uses candidate browser CI and screenshots.

Preview deployment succeeded and protected production file fingerprints remained unchanged.

## Production handoff

Draft PR: https://github.com/pyatkoff/poisk-turov-test/pull/1334
Branch: `release/search3-production-ready-v1`, based on main `fa58a0cba6dcfc8624d98c20d64fa06330eae309`.

Eight presentation assets are imported with source/destination fingerprints. A route-scoped PHP helper enables them on the canonical AnyTour search page. Existing lead, API, analytics and pricing modules remain unchanged. Preview-only lead simulation is excluded. Production draft checks passed.

Next gate: owner visual acceptance of the published preview, then explicit approval to merge/deploy production. Main has not been merged or deployed. After an approved production release, verify canonical search and form behavior; real lead delivery requires an approved end-to-end check and remains unverified.
