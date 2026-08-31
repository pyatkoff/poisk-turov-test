# AnyTour — site-wide quality scorecard

Updated: 2026-08-31

This scorecard evaluates the **whole public anytoour.ru experience**, not only the mature tour-search flow.

## Current baseline

| Area | Current | First target | Main gap |
|---|---:|---:|---|
| Whole public site / coherent product impression | 7.1 | 8.5 | Shared shell and destination handoff are stronger; homepage/hot/editorial hierarchy still needs refinement |
| Cross-page visual consistency | 7.1 | 8.5 | Header/footer/gutters/cards/controls and destination handoff align; route-specific hierarchy still varies |
| Header / navigation consistency | 7.5 | 9.0 | Shared header composition is established across standalone and search shells |
| Homepage | 6.75 | 8.5 | Shared system is coherent; section hierarchy/discovery flow remains the next visual gap |
| Search product reference | 8.75 | 9.0 | Mature product flow preserved while outer shell is aligned |
| `/hot/` | 6.75 | 8.5 | Shared shell is strong; page remains content-light and visually generic |
| Country index + country pages | 7.1 | 8.5 | Neutral shared destination hierarchy and live-search confidence handoff are now production-verified |
| `/contacts/` | 6.75 | 8.5 | Shared cards/spacing are consistent; office/trust presentation can still be stronger |
| `/how-to-buy/` | 6.75 | 8.5 | Shared hierarchy is cleaner; guided journey can still be richer |
| `/rb/` | 6.75 | 8.5 | Shared shell is coherent; discovery handoff remains the main page-specific gap |
| Typography | 7.0 | 9.0 | Shared hierarchy is consistent; route-specific headings still need tuning |
| Grid / spacing | 7.25 | 9.0 | One shell/gutter/spacing token layer controls public pages and footer/header rhythm |
| Mobile site consistency | 7.35 | 8.5 | Country/live-search handoff is now verified at 375/430 plus tablet/desktop widths |
| Brand coherence | 7.35 | 9.0 | Shared system and destination handoff read more clearly as one AnyTour product |

## Evidence supporting the 2026-08-31 movement

- AnyTour Design System 1.0 remains the current owner-directed visual generation.
- Country pages now use one neutral destination kicker and one shared live-search handoff with explicit current-offer, flight/baggage-when-available and price-before-lead signals.
- PR checks for the country change passed at 375, 430, 768, 1024 and 1440 px, including overflow/header/footer/navigation/action checks.
- Production deployment succeeded with public-page verification, unchanged lead-bridge validation and live search smoke.
- Post-deploy `Visual standalone content live` passed across all required widths, so the score increase is based on production evidence rather than CI alone.
- Search/recovery/results/selected-tour/flight/lead protections remained green through the change.

## Current work order

1. Run the full cross-page journey audit: homepage → country/destination → hot/search → results → selected tour → lead at 375/430/768/1024/1440.
2. Refine homepage section hierarchy/discovery flow based on the first confirmed gap.
3. Refine `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` route-specific hierarchy.
4. Continue responsive wrapping/overflow/spacing fixes before decorative flourishes.
5. Only then deepen destination content, live-price discovery modules and SEO inventory.
