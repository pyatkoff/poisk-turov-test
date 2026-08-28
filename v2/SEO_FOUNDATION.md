# V2 SEO foundation

## Current temporary route

`/poisk-turov-test/v2/` is a development/test route, not an SEO landing page.

Current contract:

- keep the route `noindex,follow`;
- keep canonical absent while the final public URL is undecided;
- keep one meaningful H1 and a non-empty title/description;
- keep normal crawlable internal navigation links to real public sections;
- do not expose search-result combinations as indexable landing pages;
- query parameters may prefill the search form, but they must not become an SEO URL architecture.

The live guard in `.github/workflows/validate-v2-seo-foundation.yml` protects this temporary-route contract. It also verifies that valid search-state parameters prefill predictably, invalid values are normalized, and parameterized variants remain non-indexable without canonical.

## Reusable public-page shell

BR4 treats site chrome as reusable server-rendered architecture rather than one-off search-page markup. `site-footer-v1.php` is a route-independent semantic footer partial with verified first-party AnyTour navigation, contact access and purchase/help links; `site-footer-v1.css` owns its responsive presentation.

The footer deliberately contains no guessed social/app destinations. BR5 may extend it only after the exact supplied MAX/Telegram/VK/App Store/Google Play URLs are recovered and verified.

The SEO live guard requires the semantic footer and its internal-link set after deployment. This creates a reusable page-shell boundary without changing the temporary route's indexing policy or coupling future landing pages to transient search state.

## Reusable content primitives

`seo-page-primitives-v1.php` defines route-independent, server-rendered building blocks for future indexable country/resort/seasonal pages without publishing any new route yet:

- semantic breadcrumbs with first-party links only;
- editorial H2 + paragraph sections with escaped text content;
- related-destination/internal-link groups restricted to first-party paths;
- an allowlisted search-handoff URL builder so a stable landing page can prefill V2 search without turning arbitrary search state into the landing URL architecture.

`.github/workflows/validate-v2-seo-primitives.yml` protects escaping, semantic markup, first-party link boundaries and the handoff allowlist. These helpers are intentionally not mounted into the current development route merely to manufacture SEO content.

## Dynamic result/detail boundary

The live search result list is client-rendered application state, not durable SEO page content. Hotel result cards are rendered as `article` elements and the selected-tour view has a semantic `h2`, but this dynamic UI must not be treated as the source of indexable destination content.

Future indexable country/resort/seasonal pages should provide their own server-visible editorial content, metadata, heading structure and stable URL. They may prefill or deep-link into V2 search, while the resulting search/filter state remains transient and non-indexable.

Do not add SEO-only markup to live tour results merely to make a JavaScript result set appear indexable. Search result correctness and conversion UX take precedence on the application route.

## Public promotion checklist

Do not promote the V2 route to indexable production by simply removing `noindex`.

Before promotion:

1. Choose the final public search URL explicitly.
2. Confirm that the chosen URL belongs to the intended public site and does not conflict with an existing page.
3. Move or mount the V2 experience at that final URL.
4. Set canonical to the final clean URL only after the route is live there.
5. Replace `noindex,follow` with the intended public indexing policy only after canonical and routing are verified.
6. Keep arbitrary search/filter query combinations non-indexable or canonicalized away from indexable landing pages according to the final routing design.
7. Re-run the full V2 conversion/search contracts after the move before allowing indexing.

## Future SEO page architecture

SEO growth should use stable, editorially meaningful landing URLs rather than raw search-state permutations. Suitable page types include country, resort/region, seasonal and other curated destination pages where the page has unique intent and useful static content.

The next BR4 layer after the generic primitives should define a reusable page data contract/template that composes metadata, breadcrumbs, editorial sections, related links and a search-prefill handoff, still without making the temporary V2 route indexable or choosing the final public mount point.

An indexable landing page may deep-link or prefill the V2 search experience, but the landing URL and the transient search state are separate concepts. Do not create an indexable URL for every combination of dates, nights, adults, price, meal, hotel rating, operator or flight filters.

## Structured data

Do not add speculative structured data to the temporary search route. Schema should be added only to indexable public page types where the visible content and page purpose support the chosen schema and can be kept accurate.

## Protected behavior

SEO work must not alter analytics configuration, Metrika goals or lead-sending semantics. Search and lead conversion behavior must remain independently regression-tested while SEO foundations evolve.
