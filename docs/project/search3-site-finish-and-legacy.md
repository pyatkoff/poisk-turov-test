# Site completion and legacy search — 2026-09-04

Owner priority: finish the new design, then switch the canonical search. Preview deployment authorized; production visual approval still pending.

## Preserve the previous search

- Source archive: `archive/search-before-search3-2026-09-04`, exact main `fa58a0cba6dcfc8624d98c20d64fa06330eae309`. This records source/assets; it is not a database or server-configuration backup.
- Prepared production compatibility route: `/poisk-turov-old/`. Disables Search3 presentation, retains maintained search/API/lead runtime, sends noindex in HTTP and HTML, canonical points to `/poisk-turov/`. Route is not yet published and is not added to sitemap/navigation.
- Before actual switch: retain previous deploy artifact and server configuration under the existing release procedure; verify old and new routes, unchanged API/lead contracts, and rollback on the exact previous artifact. Do not overwrite current deployment until owner approves.
- Compatibility route is a working old interface with shared runtime. For a byte-frozen historical version use the archive branch, not this maintained route.

## Latest search correction

Candidate `cc4e0362db3d52131d95542e185b8c074d3bc6f6`: native select controls for nights 1–28, synchronized with original named inputs/events; Safari date-value alignment. CI 33924709725 passed five widths and canonical-value synchronization. Evidence 9956390145; deploy PR #1338. Physical iPhone system UI still requires confirmation. Previous calendar opening was confirmed by the owner.

## Site review scope and findings

Live browser inspected homepage, contacts, how-to-buy, early booking, hot tours, country catalogue, Turkey, Turkey/September; checked payment link. This is a representative template/navigation/content review, not an exhaustive mobile audit of every URL. Search mobile evidence covers 375/430; full candidate widths 375/430/768/1024/1440.

| Priority | Area | Finding and action | State |
| --- | --- | --- | --- |
| Before migration | Legal/payment | `/payment/` still renders Not Found. Earlier privacy/consent 404s remain unresolved; obtain valid pages/content. Do not invent legal terms. | Blocking open work |
| Before migration | Common shell | Search preview footer/form differ from existing homepage/content pages. Converge spacing, button roles and footer content across page families in a whole-site preview. | Open |
| Before migration | Search handoff | Turkey price cards pass only departure city/country, losing displayed date/nights. Month CTA passes only country. Preserve supported dates/nights/party parameters; do not imply exact hotel selection unless supported. | Open |
| Before migration | Client copy | Technical SEO instructions visible on Turkey and month pages. Replace relevant country/month template copy with customer-facing price-check explanation. | Prepared in production draft, not published |
| Before migration | Price freshness | Turkey/month has 04.09 departure at review time; use explicit departure timezone and availability cutoff. Do not reuse the earlier audit's date assumption. | Open |
| Next design pass | Homepage | Night field differs from search; no child selector in compact form. Ensure consistent parameters and native controls before canonical handoff. | Open |
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
