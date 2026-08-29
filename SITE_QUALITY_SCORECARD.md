# AnyTour — site-wide quality scorecard

Updated: 2026-08-29

This scorecard evaluates the **whole public anytoour.ru experience**, not only the mature tour-search flow. A green search regression suite is not evidence that the entire site is visually 9/10.

## Current baseline

| Area | Current | First target | Main gap |
|---|---:|---:|---|
| Whole public site / coherent product impression | 6.5 | 8.5 | Pages still feel like different shells rather than one AnyTour product |
| Cross-page visual consistency | 5.5 | 8.5 | Different header composition, spacing, typography and page rhythm |
| Header / navigation consistency | 5.5 | 9.0 | Search header is a legacy two-row pattern while standalone pages use newer one-row headers |
| Homepage | 6.5 | 8.5 | Stronger than migrated pages, but still not yet the reference shell for the whole site |
| Search product reference | 8.75 | 9.0 | Mature product flow; preserve while aligning outer shell |
| `/hot/` | 6.25 | 8.5 | Useful handoff but visually generic and content-light |
| Country index + country pages | 6.0 | 8.5 | Functional route/content foundation; weak destination-product presentation |
| `/contacts/` | 6.0 | 8.5 | Needs stronger hierarchy, office/trust presentation and shared shell |
| `/how-to-buy/` | 6.0 | 8.5 | Content works but page presentation is basic |
| `/rb/` | 6.25 | 8.5 | Needs shared visual hierarchy and stronger discovery handoff |
| Typography | 6.5 | 9.0 | Similar fonts but inconsistent scale/hierarchy between shells |
| Grid / spacing | 6.0 | 9.0 | Different container widths, header heights and section rhythm |
| Mobile site consistency | 6.75 | 8.5 | Search and content/header behavior differ materially |
| Brand coherence | 6.5 | 9.0 | Logo/colors exist but do not yet form one unmistakable interface system |

## Required evidence for raising scores

- Inspect public pages at 375, 430, 768, 1024 and 1440 px.
- Check header, navigation, first-screen hierarchy, cards, actions, footer and long-content states.
- Check overflow, wrapping, touch targets and page-to-page shell continuity.
- Preserve search/recovery/results/selected-tour/flight/lead functional regressions.
- Production visual evidence is required; CI alone cannot raise a site-wide score.

## Current design-system work order

1. Shared AnyTour tokens and primitives.
2. Shared modern header/navigation for standalone pages; align the search header to the same composition.
3. Shared footer rhythm and no duplicated footer/pre-footer structures.
4. Homepage hierarchy refinement.
5. Move `/hot/`, country pages, `/contacts/`, `/how-to-buy/`, `/rb/` onto the common shell.
6. Cross-page journey audit: homepage → destination/hot → search → results → selected tour → lead.
7. Only then deepen destination content, live-price discovery modules and SEO inventory.
