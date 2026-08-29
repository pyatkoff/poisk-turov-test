# AnyTour content parity audit

Goal: migrate useful legacy content and functions without blindly copying obsolete wording or architecture.

Statuses: `restored`, `intentional-replacement`, `missing`, `needs-review`, `not-applicable`.

| Area / page | Legacy content/function | New site state | Status / next action |
|---|---|---|---|
| `/contacts/` | Moscow + branches in Saint Petersburg, Kaliningrad, Cheboksary; phones; legal details | All 4 offices restored. Legal address updated to the confirmed current address: Moscow, Butyrskaya 86B | restored |
| `/how-to-buy/` | Full flow: search, tour details, tourist/customer data, wishes, manager verification, payment, booking confirmation, electronic documents | Full practical flow restored and rewritten to match current AnyTour UX; obsolete/legal-risk wording not copied verbatim | restored |
| Site favicon | Browser/site icon | `/favicon.svg` exists and is linked on homepage and standalone content pages | restored; add to full search head in Search track |
| Social networks | Links plus recognizable social destinations | MAX, Telegram, VK links present | restored; branded inline SVG marks added |
| Mobile apps | App Store and Google Play links | Both links present | restored; visual store marks added |
| `/hot/` | Live cards with hotel, resort/country, departure, dates, nights, old price/new price and discount | Only explanatory content + handoff to search | missing: build live hot-tour block from current search/API; never copy stale prices |
| `/country/` catalog | Legacy catalog exposed roughly 70 countries/destinations | Current static catalog exposes only 14 | missing: build full catalog from Platform country entities/current catalog source; routes without SEO landing should safely hand off to search rather than 404 |
| Country pages | Search form preselected to country + live hot tours for that country | Current legacy-migration pages and new Platform country renderer are much thinner | missing: dynamic search/LiveTours block belongs in platform, actual search hydration stays Search-owned |
| `/poisk-turov/` | Full search, resort filters, prices, lead form | Replaced by newer search product | intentional-replacement; preserve useful capabilities, not old UI |
| `/payment/` | Cash/card in office and online-payment explanation | Footer still points to legacy site | missing/needs-review: migrate after current payment process is confirmed |
| `/personal-data/` | Consent text | Footer still points to legacy site | missing/needs-review: legal content must be verified before migration |
| `/politika-konfidentsialnosti/` | Privacy policy | Footer still points to legacy site | missing/needs-review: legal content must be verified before migration |
| Header region selector | Legacy city/subdomain selector | Not migrated | needs-review: do not reproduce old subdomain model automatically; future departure-city experience should use platform/search data |
| Legacy currency/date strip | Static-looking currency/date block | Not migrated | intentional-replacement unless a real live rate feature is added |

## Migration rule

For every migrated route, compare: primary content, forms/tools, live data blocks, images, CTA, phones/offices, legal/trust blocks, documents, links, social/app destinations, metadata and mobile behavior. Mark every legacy element as restored, intentionally replaced, missing or obsolete before calling the page migrated.
