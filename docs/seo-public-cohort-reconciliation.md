# Public SEO cohort reconciliation — review-only

This dossier makes the next SEO decision auditable before any user-facing,
indexation, analytics, search, pricing or lead change is proposed. It does not
publish a page or give launch approval.

## What is known from the exact #1301 base

The checked-in `v2/sitemap.xml` contains 104 unique AnyTour URLs: 3 country,
5 resort and 96 seasonal. It contains no hotel-tour URL and no
`/poisk-turov/` URL. The controlled launch source can additionally include
materialized Egypt/Maldives resort families; those are deliberately outside
the static-sitemap count and require their own fresh materializer artifact.
Both the sitemap file and its sorted canonical URL list are SHA-256 pinned, so
a same-count URL substitution cannot pass as the expected static cohort.

| Set | Current dossier state | Required evidence before decision |
| --- | --- | --- |
| Source/static sitemap | Reproducible | Tracked file hashes and URL list hash |
| Dynamic resort materializer | Not asserted | Fresh generated route manifest and its hash |
| Live identity | Not asserted | Successful existing production-evidence run, exact SHA, artifact hash and age ≤24 h |
| Demand/conversion | `measurement_pending` | Aggregate 28/90-day search and outcome exports; no user-level data |

The only prior launch feedback snapshot discoverable in the checkout is a
ten-path historical baseline. Its own source marks the current scope as
drifted after later cohort expansion, so this dossier explicitly rejects it
as evidence of the current live state.

## Commercial use after evidence arrives

The subsequent owner review can rank only already-public country, resort and
seasonal templates. Each recommendation must include a measurable hypothesis:
organic landing → search start, then search start → completed lead. Missing
data remains unknown; it cannot be converted into a zero-demand claim.

No recommendation may make a hotel-tour page or `/poisk-turov/` eligible for
SEO publication. Any eventual page or conversion change requires a separate
approved implementation slice.

## Acceptance boundary

The validator fails closed when a tracked source hash, the 104-page static
cohort, exclusion boundary, measurement status, or workflow safety policy
drifts. It accepts no live or demand claim until the required fresh artifacts
are supplied in a later review-only update.

The accompanying CI gate also compares the branch to the exact #1301 base
`7feb3c6a965ddeee4f77893536ea31965821a5c4`. It permits only the five files
in this review-only slice, rejecting any runtime, sitemap or unrelated change.
