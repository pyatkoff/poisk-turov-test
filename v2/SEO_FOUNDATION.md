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

The live guard in `.github/workflows/validate-v2-seo-foundation.yml` protects this temporary-route contract.

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

An indexable landing page may deep-link or prefill the V2 search experience, but the landing URL and the transient search state are separate concepts. Do not create an indexable URL for every combination of dates, nights, adults, price, meal, hotel rating, operator or flight filters.

## Structured data

Do not add speculative structured data to the temporary search route. Schema should be added only to indexable public page types where the visible content and page purpose support the chosen schema and can be kept accurate.

## Protected behavior

SEO work must not alter analytics configuration, Metrika goals or lead-sending semantics. Search and lead conversion behavior must remain independently regression-tested while SEO foundations evolve.
