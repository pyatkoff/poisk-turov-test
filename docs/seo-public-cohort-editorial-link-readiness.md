# Public SEO cohort editorial and link readiness — review only

This dossier freezes the currently checked-in public SEO cohort as a review
object. It does not render, publish, index, alter, or deploy a page.

## Exact static cohort

The pinned sitemap has 104 URLs: 3 country pages, 5 Turkey resort pages, and
96 month pages. Every listed path has a tracked `index.php` entrypoint. The
validator pins both the sitemap and the derived path-to-entrypoint digest, so
the same URL count cannot conceal a substituted URL or route binding.

The graph contract is structural, not a fresh crawl assertion: 101 parent
relations, 96 parent-to-month navigation relations and 1,034 directed sibling
month relations are expected from the checked-in page families. Two September
pilot entrypoints use their pinned pilot records rather than the generic
month resolver and therefore do not claim the generic 11-sibling set. Search
handoff is deliberately not an SEO graph node. Hotel pages, query state,
fragments, external URLs and dynamic Egypt/Maldives resort materialization are
outside this static cohort.

## Detected gap

`/country/turkey/` presents the five static Turkey resort names as non-link
chips. It therefore has zero rendered anchors to the five static resort URLs.
This is recorded as **detected_unresolved**. It cannot be marked resolved here:
adding those links is a separate runtime and visual-review change.

## Editorial and production boundaries

Editorial source identity is pinned, but semantic copy uniqueness remains
`pending_render_evidence`: no rendered-page comparison is claimed by this
review-only branch. Likewise, production identity is
`not_available_in_checkout`; a later read-only evidence update needs a
successful existing collector run, exact SHA, artifact digest and an age of at
most 24 hours. No historical comment or absent artifact is treated as proof.

No demand, ranking, traffic or conversion numbers appear in this dossier.
Missing measurements remain unknown, not zero.

The machine-readable dossier computes and pins the complete checked-in literal
PHP dependency closure used by the country, resort and seasonal entrypoint
families, plus the declared production-identity collector root. This includes
the seasonal resolver and pilot, launch-state link helper, shell, catalog/graph
validators, DS2 reference owner and dynamic offer helpers. Dynamic configuration
outside the checkout is not represented as local source evidence. CI also
watches every `v2/country/**/index.php` entrypoint in addition to those owners.

## Acceptance

The CI gate must fail closed on source, route, cohort, graph, workflow or
scope drift. It allows exactly the five dossier files in this branch and
rejects runtime, sitemap, robots, canonical, route, Metrika, Tourvisor, lead
or deployment changes.
