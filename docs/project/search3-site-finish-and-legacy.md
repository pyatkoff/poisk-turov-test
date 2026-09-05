# Site completion and legacy search — 2026-09-04

Owner priority: finish the new design, then switch the canonical search. Preview deployment authorized; production visual approval still pending.

Canonical project task: [Search3 — завершить новый дизайн сайта и подготовить перенос #996](https://github.com/pyatkoff/poisk-turov-test/issues/996). Working implementation: draft [#1334](https://github.com/pyatkoff/poisk-turov-test/pull/1334). `AUTOPILOT_STATE.json` records the same queue in this active branch; the main branch has not received these draft changes.

## Ordered remaining work

1. Shared button/header/footer convergence is prepared and checked in draft. **Next:** publish and inspect the coherent whole-site preview.
2. Publish a whole-site preview of the prepared draft; verify links and preservation of city, dates, nights, party and child ages.
3. Fix confirmed customer-copy/city-inflection issues, map actions from verified office addresses and broken legal/payment links using existing approved materials. Defer missing/unapproved legal terms, hours and unsupported advantages.
4. Exclude past departures using an explicit timezone and verified availability rules for same-day departures. Preserve departure city across handoffs. A new remembered city shared by every data showcase is a separate product extension if it requires changing the data source.
5. Complete one relevant phone/desktop acceptance pass through the actual search to the lead form, including iPhone controls, long values, family composition and recovery. Preview does not establish receipt of a real lead.
6. Obtain visual approval of the concrete release, retain prior deployment/configuration and verify rollback/old search, then perform the authorized production migration and post-deploy checks. Confirm real lead delivery separately with a controlled agreed test.

Completed implementation is distinguished below from preview publication and production acceptance. Mass SEO expansion and technical refactor are outside this finish pass.

## Preserve the previous search

- Source archive: `archive/search-before-search3-2026-09-04`, exact main `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. This records source/assets; it is not a database or server-configuration backup.
- Prepared production compatibility route: `/poisk-turov-old/`. Disables Search3 presentation, retains maintained search/API/lead runtime, sends noindex in HTTP and HTML, canonical points to `/poisk-turov/`. Route is not yet published and is not added to sitemap/navigation.
- Before actual switch: retain previous deploy artifact and server configuration under the existing release procedure; verify old and new routes, unchanged API/lead contracts, and rollback on the exact previous artifact. Do not overwrite current deployment until owner approves.
- Compatibility route is a working old interface with shared runtime. For a byte-frozen historical version use the archive branch, not this maintained route.

## Latest search correction

Published candidate `470474414a3930f1f6c095a8dbbc187075b253c0`: native select controls for nights 1–28 synchronized with original named inputs/events; readable two-row desktop form including 1920/2048 and reduced initial footer gap. CI 33925976956 passed the five standard widths plus wide-screen checks; evidence 9956839979, deploy PR #1339 and successful deployment 33926206799. Physical iPhone system night-picker UI and Safari alignment still require confirmation. Calendar opening was confirmed by the owner. Earlier `cc4e` / #1338 evidence is superseded by this candidate.

## Site review scope and findings

Live browser inspected homepage, contacts, how-to-buy, early booking, hot tours, country catalogue, Turkey, Turkey/September; checked payment link. This is a representative template/navigation/content review, not an exhaustive mobile audit of every URL. Search mobile evidence covers 375/430; full candidate widths 375/430/768/1024/1440.

| Priority | Area | Finding and action | State |
| --- | --- | --- | --- |
| Before migration | Legal/payment | `/payment/` still renders Not Found. Earlier privacy/consent 404s remain unresolved; obtain valid pages/content. Do not invent legal terms. | Blocking open work |
| Before migration | Common shell | Search preview footer/form differ from existing homepage/content pages. Converge spacing, button roles and footer content across page families in a whole-site preview. | Prepared in draft; whole-site preview pending |
| Before migration | Search handoff | Snapshot links now retain supported dates/nights/two-adult scope; month CTA retains a future date range in its month. Exact hotel selection is not promised. Verify these prepared changes in the whole-site preview. | Prepared in production draft, not published |
| Before migration | Client copy | Technical SEO instructions visible on Turkey and month pages. Replace relevant country/month template copy with customer-facing price-check explanation. | Prepared in production draft, not published |
| Before migration | Price freshness | Turkey/month has 04.09 departure at review time; use explicit departure timezone and availability cutoff. Do not reuse the earlier audit's date assumption. | Open |
| Next design pass | Homepage | Independent native night range, children/ages and extended-search state preservation are implemented and checked. Verify with the real catalogues in the whole-site preview. | Prepared in production draft, not published |
| Next design pass | Contacts | Four map-search actions added using the existing office addresses. Hours/photos still require factual sources. | Prepared in draft, not published |
| Next design pass | Hot offers | Technical copy replaced with customer-facing explanations; neutral `Вылет: Москва` avoids incorrect inflection. | Prepared in draft, not published |
| Confirmed working | Turkey links | Month links and similar destinations are populated; the earlier claim of empty blocks is not currently true for Turkey. | No fix needed |
| Retain | Buying flow | How-to-buy explains that a request is not confirmed booking/payment. Keep this consistent with tour UI. | Retain |

Do not expand mass SEO pages, analytics, transport or supplier contracts as part of these design corrections. No production lead was sent during review.

## Follow-up — desktop screenshot and handoff implementation

- Fixed the wide-screen CSS conflict: desktop now uses a readable two-row primary form; the date range, night range and submit button share the second row. Reduced initial footer margin. Added 1920/2048 geometry checks and JPEG evidence in addition to the five established widths. Safari date-value line height adjusted; physical Safari confirmation still needed.
- Country, resort and month snapshot offer links retain advertised departure date and duration; two-adult scope matches the snapshot builder's `adults=2 AND children_count=0` selection. Links do not promise exact hotel selection.
- Seasonal page CTA now selects dates inside the recorded month, beginning no earlier than tomorrow and bounded by the existing 21-day search range limit. The whole month is not searched at once.
- Home uses independent native night range controls and child-age selectors. Search page restores validated ages (0–17, up to three children) from existing `child_age[]` query values. Extended-search link retains entered form values.
- Draft CI verifies offer-to-form parameters, independent night range and child ages 0/17; home mobile/desktop layout inspected from CI screenshots. Local CI catalogue loading lacks production catalogue service and is not a finding of production failure.
- These main-site changes remain in the production draft. Common footer/button convergence, legal/payment pages, freshness cutoffs and office map actions are still open; no production switch is authorized by this follow-up.

## Shared site shell follow-up

Shared server-rendered footer now owns navigation, existing social/app links and contact phone on every page. Its marker prevents the Search3 donor from replacing the DOM. Removed donor-only header/legacy-footer CSS overrides from the production import; selected-tour footer hiding is retained. Primary search/content actions use the same blue; header search action is secondary. Four office map searches use the published addresses. Hot-page internal data-source wording and departure label are cleaned up. Legal/payment destinations remain unresolved and are not represented as repaired.

At `c46d76a973a4cb89b1c50eb985a6c4a2f155c454`, all applicable gates passed; visual run `33929767087`, artifact `9958121988`. Inspected phone/desktop evidence of the actual Search3 route as well as the shared footer and contacts. The older audit had rendered the legacy `/index.php`; CI now also exercises canonical Search3 using a local standalone wrapper. Failed catalogue loading in isolated CI is a fixture limitation, not live-site evidence.

Next task is the whole-site preview artifact/publication. The existing preview deployment is limited to nine search files and cannot publish these shared PHP/home changes. Keep its allowlist intact; prepare a separately reviewed site preview instead of calling the production deploy workflow. This draft has not changed the public preview or main site. Hourly scheduled development is enabled (Europe/Amsterdam). Continue beyond one PR when time remains for another safe step. The first background execution has not yet been verified.

Owner continuation rule: **do not stop after one PR if the current run has time for the next safe step**. Update the handoff after each checked change and continue. A final production visual-approval gate blocks production publication only, not other authorized safe preparation.

## Snapshot freshness follow-up — 2026-09-05

Confirmed in source: country/resort/hotel readers validated snapshot TTL but allowed past departure dates to occupy the cheapest-offer slots. They now filter past/malformed dates before sorting and limiting. Hot-offer display uses the same explicit Europe/Moscow business clock as the existing price calendar instead of the database session date. This is a business-day display rule, not an airport timezone or a new same-day sales cutoff. Existing same-day availability and recheck behavior remain unchanged.

Integration fixtures exercise the real readers with an in-memory SQLite database: past cheap offers, stale snapshots, empty results, city filters, valid today/future offers and limiting. Date tests include Moscow midnight with a different host timezone and leap/year boundaries. No live search/API/lead calls. Implementation is prepared, awaiting the existing core CI; no public artifact or production change yet.
