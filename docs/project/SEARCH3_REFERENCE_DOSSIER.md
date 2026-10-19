# Search3 reference dossier

Status: **reference only / review locked**

Machine source: `docs/project/search3-reference-dossier.json`

Captured: `2026-09-04T08:20:50Z`

## Decision

Search3 is a **donor of validated visual and interaction patterns**, not a production branch and not a wholesale merge source. New implementation slices must start from fresh `main` and migrate one owned state at a time.

This matters because the live Search3 entry screen is already visually behind the current production DS2 search entry. Copying the branch as a unit would replace a stronger current surface with an older one while also importing preview-only deployment, a disabled lead adapter, a state simulator and a large order-dependent CSS/JS overlay.

## Frozen identity

| Item | Frozen value |
| --- | --- |
| Preview | `https://anytoour.ru/_preview/search3/poisk-turov/` |
| Search3 branch head | `6ce565620becaba8e91d50aff13529b5a52aba37` |
| Branch tree | `232320be541dc08271fb6a32fb997c60970b103e` |
| Verified implementation commit | `e5baf32f455cdb0aa1a704964f28e5efbebf57ff` |
| Implementation tree | `2c88a4e3786cdadc6a0eec2b88fafb1f388ba541` |
| Run attestation | [PR #1293](https://github.com/pyatkoff/poisk-turov-test/pull/1293) |
| Run | [Search3 preview run 467](https://github.com/pyatkoff/poisk-turov-test/actions/runs/33813829683), attempt 1, `SUCCESS` |

The branch head is a docs-only descendant of the implementation commit. Live Search3 asset URLs observed on 2026-09-04 carry `v=e5baf32f455c`, independently tying the preview to the frozen implementation.

## Admissible design set

The recorded source label is `/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE`. `99_ARCHIVE_ITERATIONS` and earlier one-off layouts are excluded.

The design pixels are **not present in the Git object database available to this repository checkout**. Their labels are frozen, but pixel integrity is not. This is an evidence blocker: the final direct visual comparison must use a durable, checksum-addressed copy of the eight approved sources.

| # | Approved layout | Required states | Current status |
| ---: | --- | --- | --- |
| 1 | Интерфейс поиска туров AnyTour | search, results, hotel tours | REVIEW |
| 2 | Макет фильтров поиска туров AnyTour | filters, sorting, sub-screens, reset/apply/cancel | REVIEW |
| 3 | Выбор рейсов AnyTour | outbound/inbound, baggage, delta, total, continue | REVIEW |
| 4 | Итог тура AnyTour | hotel, room, flights, services, tourists, total, submit | REVIEW |
| 5 | Страница бронирования тура AnyTour | selected hotel, room/meal, flights, composition, total | REVIEW |
| 6 | UI-кит заявки на тур AnyTour | entry, sending, success, error, MAX/Telegram | REVIEW |
| 7 | Спецификация футера AnyTour | navigation, support, social, apps, trust, legal | REVIEW |
| 8 | Цельный mobile flow AnyTour | search → results → hotel → tour → flight → total → lead → messenger | REVIEW |

No layout is marked approved. A green functional run is not visual owner approval.

## Run 467 evidence boundary

The merged Search3 work map attests that run 467 passed `deploy-preview`, `visual-qa desktop`, `visual-qa tablet` and `visual-qa mobile` on implementation commit `e5baf32f…`.

The exact QA source files are frozen in the manifest with both Git blob IDs and SHA-256 digests. On 2026-09-04 the three still-live GitHub artifacts were recovered, their server archive digests were reproduced locally, and the 35 PNG plus three JSON report digests were recorded. The original ZIP bytes are retained in owner-private storage and are deliberately not committed to this public repository.

| Mode | Artifact ID | ZIP bytes | ZIP SHA-256 | GitHub expiry |
| --- | ---: | ---: | --- | --- |
| desktop | `9915925993` | 2,738,645 | `ae398a14323d99bb23ad5b2bd26be06b6923845da6e3218d1613770c064718fd` | 2026-10-03 22:41 UTC |
| tablet | `9915896367` | 2,645,305 | `cd001dd85c81a051a5a02446e087656eeb45bd5f62cf14db6bf7cf3252be02d3` | 2026-10-03 22:40 UTC |
| mobile | `9915896517` | 854,848 | `0b00d1827004b3c516c3dcc1c4305c68b3e250210ca5605f8e910c147062c8ff` | 2026-10-03 22:40 UTC |

The manifest records every state filename and pixel SHA-256. The tablet archive's report is genuinely named `report-mobile.json`; that source quirk is preserved rather than rewritten.

The recovered artifacts define these viewports:

| Mode | Viewport | Captured state count |
| --- | ---: | ---: |
| desktop | 1440×1000 | 11 |
| tablet | 834×1112 | 12 |
| mobile | 390×844 | 12 |

What run 467 proves: the scripted happy path reached results, selected tour, flight choice, final review and simulated lead lifecycle without the script's structural/overflow assertions failing at those three sizes.

What it does **not** prove:

- pixel parity with all eight approved layouts;
- standard product widths 375/430/768/1024 in the same run;
- deterministic empty-flight, upstream error or timeout behavior;
- real lead delivery or payload mapping (preview lead submission is disabled and UI states are simulated);
- direct parity with the eight approved design sources: their immutable source IDs and exported pixels remain unavailable;
- public redistribution of the candidate captures: they contain commercial hotel/price imagery and remain owner-private even though their hashes are reviewable.

## Live read-only observation

Observed on 2026-09-04 without submitting search or lead forms:

| Signal | Production `/poisk-turov/` | Search3 preview |
| --- | --- | --- |
| Reachable | yes | yes |
| Canonical | production route | preview route |
| Robots meta | `noindex,follow,max-image-preview:large` | `noindex,follow,max-image-preview:large` |
| Search3 assets | 0 | bound to `e5baf32f455c` |
| Visible entry heading | `Поиск тура, который действительно подходит вам` | `Поиск туров` |
| H1 elements | 1 | 2, including one zero-area H1 |
| Horizontal overflow in observed desktop viewport | none | none |

The entry-screen mismatch and duplicate Search3 H1 are review blockers. They are findings, not permission to change production in this PR.

## Migration ownership

| Area | Canonical owner | Search3 disposition |
| --- | --- | --- |
| Public URLs/canonicals | `main` | do not copy runtime |
| Initial search + shared DS2 shell | `main` | review selected patterns only |
| Tourvisor lifecycle, 25→100 loading, stale guards | `main` | do not copy runtime |
| Results/filter/card/tour/flight/review presentation | `main` | migrate validated patterns in isolated slices |
| Lead lifecycle UI | `main` | visual states may migrate; transport/mapping may not |
| Metrika/analytics contract | `main` | do not copy runtime |
| Shared footer | canonical DS2 footer on `main` | composition reference only; no fork |
| Preview deploy, 403 lead adapter, state simulator | preview only | never import to production |

## Open P1 backlog

| ID | Finding | Required closure evidence |
| --- | --- | --- |
| S3-REF-001 | Search3 entry is behind current production DS2 entry | explicit ownership decision for layout 1 |
| S3-REF-002 | two H1 elements in live preview; one has zero area | one truthful visible H1 in the candidate |
| S3-REF-003 | direct visual diff remains open for layouts 1, 2, 4, 5, 7, 8 | signed comparison matrix with zero P0/P1 |
| S3-REF-004 | run 467 IDs, archives and candidate hashes are recovered privately, but all eight approved design-source pixels remain unavailable | immutable design document/node/revision IDs plus checksum-addressed exports of the eight canonical layouts |
| S3-REF-005 | run widths do not cover 375/430/768/1024/1440 | one candidate SHA green at all required widths |
| S3-REF-006 | lead UI is simulated | contract snapshot tests plus read-only integration evidence |
| S3-REF-007 | no deterministic no-flight/error/timeout references | committed fixtures and state assertions |

## Protected boundary

This dossier changes no runtime code. Future slices must preserve, byte-for-contract where applicable:

- existing public URLs and routes;
- Tourvisor external semantics;
- lead payload, field mapping and delivery behavior;
- Yandex Metrika configuration, goals and event contract;
- pricing semantics;
- progressive loading and stale-state guards.

## Release lock and next gate

Search3 remains review-only. This dossier does not authorize merge of the Search3 branch or any production deploy.

The next program slice is `search3/contract-boundaries`: freeze Tourvisor mapping/lifecycle, pricing, lead payload/field mapping and analytics event surfaces with deterministic read-only tests before any candidate UI migration.
