# Site completion and legacy search — 2026-09-04

Owner priority: finish the new design, then switch the canonical search. Preview deployment authorized; production visual approval still pending.

Canonical project task: [Search3 — завершить новый дизайн сайта и подготовить перенос #996](https://github.com/pyatkoff/poisk-turov-test/issues/996). Working implementation: draft [#1334](https://github.com/pyatkoff/poisk-turov-test/pull/1334). `AUTOPILOT_STATE.json` records the same queue in this active branch; the main branch has not received these draft changes.

## Ordered remaining work

1. **Next:** unify button roles/labels, header, footer and spacing across homepage, search, country/resort/month, hot tours, early booking, contacts and how-to-buy.
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
| Before migration | Common shell | Search preview footer/form differ from existing homepage/content pages. Converge spacing, button roles and footer content across page families in a whole-site preview. | Open |
| Before migration | Search handoff | Snapshot links now retain supported dates/nights/two-adult scope; month CTA retains a future date range in its month. Exact hotel selection is not promised. Verify these prepared changes in the whole-site preview. | Prepared in production draft, not published |
| Before migration | Client copy | Technical SEO instructions visible on Turkey and month pages. Replace relevant country/month template copy with customer-facing price-check explanation. | Prepared in production draft, not published |
| Before migration | Price freshness | Turkey/month has 04.09 departure at review time; use explicit departure timezone and availability cutoff. Do not reuse the earlier audit's date assumption. | Open |
| Next design pass | Homepage | Independent native night range, children/ages and extended-search state preservation are implemented and checked. Verify with the real catalogues in the whole-site preview. | Prepared in production draft, not published |
| Next design pass | Contacts | Four offices and call links present; add map actions from verified addresses. Hours/photos require factual source. | Open |
| Next design pass | Hot offers | Readable but contains internal wording and `из Москва`; fix city label consistently. | Open |
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
