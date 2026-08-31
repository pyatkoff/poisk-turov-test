# AnyTour — site-wide quality scorecard

Updated: 2026-08-30

This scorecard evaluates the **whole public anytoour.ru experience**, not only the mature tour-search flow. A green search regression suite is not evidence that the entire site is visually 9/10.

## Current baseline

| Area | Current | First target | Main gap |
|---|---:|---:|---|
| Whole public site / coherent product impression | 7.0 | 8.5 | Shared shell now exists, but homepage and destination/editorial hierarchy still need another refinement pass |
| Cross-page visual consistency | 7.0 | 8.5 | Header, footer, page gutter, cards and controls are unified; route-specific hierarchy still varies |
| Header / navigation consistency | 7.5 | 9.0 | Shared header composition is now used across standalone and search shells; remaining work is hierarchy/polish rather than a split shell |
| Homepage | 6.75 | 8.5 | Uses the shared system but still needs section hierarchy and discovery-flow refinement |
| Search product reference | 8.75 | 9.0 | Mature product flow preserved while outer shell is aligned |
| `/hot/` | 6.75 | 8.5 | Shared shell/rhythm is stronger; page remains content-light and visually generic |
| Country index + country pages | 6.75 | 8.5 | Shared shell is coherent now; destination-product presentation remains the main gap |
| `/contacts/` | 6.75 | 8.5 | Shared cards/spacing improve consistency; office/trust presentation can still be stronger |
| `/how-to-buy/` | 6.75 | 8.5 | Shared hierarchy is cleaner; content presentation still needs a richer guided journey |
| `/rb/` | 6.75 | 8.5 | Shared shell is coherent; discovery handoff remains the main visual/product gap |
| Typography | 7.0 | 9.0 | Shared type hierarchy is materially more consistent, but route-specific headings still need tuning |
| Grid / spacing | 7.25 | 9.0 | One shell/gutter/spacing token layer now controls public pages and footer/header rhythm |
| Mobile site consistency | 7.25 | 8.5 | Shared responsive header, shell and footer are verified across required widths; content density still varies by route |
| Brand coherence | 7.25 | 9.0 | Shared colors, surfaces, controls, header/footer and spacing now read as one AnyTour system; destination storytelling is still weak |

## Evidence supporting the 2026-08-30 score movement

- AnyTour Design System 2.0 central tokens now own shell surfaces, lines, shadows, controls, hero spacing, page gutters and responsive rhythm.
- The shared header/navigation consumes that common token layer across standalone pages and the search outer shell.
- `/contacts/`, `/how-to-buy/`, `/rb/`, `/hot/`, `/country/` and country pages now share common page/card/button/breadcrumb/grid geometry without rewriting unresolved content.
- The community/pre-footer and footer now use the same common shell width, page gutter, spacing, radius, surface, focus and control tokens while preserving verified destinations.
- PR visual checks passed for 375, 430, 768, 1024 and 1440 px across homepage, search, target editorial routes and current country pages, including overflow, header/nav geometry, footer, actions/focus and shell continuity.
- Production deploy completed successfully with public-page verification, unchanged lead-bridge validation and live search smoke.
- Post-deploy production checks passed for responsive navigation and the full standalone route sweep at the required widths; live tour-card/flights and result-detail regressions also remained green.

## Required evidence for further score increases

- Inspect public pages at 375, 430, 768, 1024 and 1440 px after every material shell change.
- Check header, navigation, first-screen hierarchy, cards, actions, footer and long-content states.
- Check overflow, wrapping, touch targets and page-to-page shell continuity.
- Preserve search/recovery/results/selected-tour/flight/lead functional regressions.
- Production visual evidence is required; CI alone cannot raise a site-wide score.

## Current design-system work order

1. Refine homepage section hierarchy and discovery flow inside the shared shell.
2. Strengthen country/destination presentation and handoff into hot/search without making editorial pages dense.
3. Refine `/hot/`, `/contacts/`, `/how-to-buy/` and `/rb/` route-specific hierarchy now that shell geometry is common.
4. Run cross-page journey audit: homepage → destination/hot → search → results → selected tour → lead.
5. Continue responsive fixes before decorative flourishes.
6. Only then deepen destination content, live-price discovery modules and SEO inventory.
