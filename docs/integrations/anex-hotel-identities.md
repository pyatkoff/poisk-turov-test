# ANEX hotel identity registry for preview

The isolated server adapter can now resolve five hotel identities from the
2026-09-07 pilot to the existing `catalog_hotels.id`. This is a versioned JSON
registry, not a database migration or a change to current AnyTour identifiers.
The file is not connected to production search.

`app/integrations/data/anex-hotel-identities.json` keeps all ten evidence entries.
Each explicitly lists `anex_online` and `anex_xml` identities because the pilot
compared both interfaces. Equal IDs in these ten records do not establish a
general identity rule for ANEX, SAMO, Andromeda or other operators. New interfaces
need their own source namespace and evidence.

| ANEX ID | Existing hotel ID | Preview status |
| ---: | ---: | --- |
| 30160 | 40430 | `verified_preview` |
| 30536 | 17469 | `verified_preview` |
| 469 | 245 | `verified_preview` |
| 470 | 252 | `verified_preview` |
| 472 | 260 | `verified_preview` |
| 43689 | 83221 | `needs_review` |
| 464 | 185 | `needs_review` |
| 465 | 195 | `needs_review` |
| 466 | 218 | `needs_review` |
| 468 | 295 | `needs_review` |

`verified_preview` means sufficient name, geography and coordinate evidence for
this bounded preview pilot. It is not supplier-certified identity or production
approval. All five `needs_review` IDs remain candidates only; the resolver never
returns them as usable hotel IDs. The short `DREAMS BEACH` candidate is present,
even though the pilot algorithm labelled its long-name comparison `unmatched`.

## PHP interface

Requiring `app/integrations/hotel-identities.php` performs no file reads, network
requests, configuration loads, sessions or database work. Explicitly load the
bounded local manifest once in a preview process:

```php
require_once __DIR__ . '/hotel-identities.php';
$registry = AnyTourHotelIdentityRegistry::fromFile();
$hotelId = $registry->resolve('anex_online', '30160', 'preview'); // 40430
$candidate = $registry->resolve('anex_online', '465', 'preview'); // null
$status = $registry->status('anex_online', '465'); // needs_review
$disabled = $registry->resolve('anex_online', '30160'); // null
```

The default scope is `production`, which resolves nothing from this pilot.
Only exact scope `preview` enables `verified_preview` links. `status()` returns
`verified_preview`, `needs_review` or `unmapped`; it does not enable a mapping.
An adapter callback can explicitly translate its ANEX Online source to the
`anex_online` namespace. The registry does not alias plain `anex` or copy IDs
between sources.

Canonical positive integer external IDs may be passed as strings or PHP ints.
Leading zeros, floats, booleans, signs, whitespace and scientific notation fail
closed. Existing catalogue IDs remain positive PHP integers. The resolver does
no name matching, ID equality fallback or database query on a request.

The immutable registry rejects the entire document if any entry is malformed,
if schema/status/scope is unknown, or if a source-qualified external ID is
declared twice, including an identical repeat or a review/verified conflict.
Distinct suppliers may reuse an external number. Several explicit external
identities may point to one local hotel. Local registry reads are capped at
512 KiB; stream wrapper URLs are refused. Errors are fixed strings and do not
include file paths or contents.

## Verification and next review

Run `php tests/anex-hotel-identities-smoke.php` and PHP syntax checking. The smoke
checks cover namespace collisions, no API/XML inheritance, many-to-one mappings,
production defaults, review candidates, unknown IDs, malformed data, duplicate
conflicts and whole-document rejection. No live API or database is needed.

The [sanitized evidence](anex-hotel-matching-sample-20260907.json) and
[comparison report](anex-hotel-matching.md) retain source SHA, check date, request
run, names, geography and coordinate differences. The manifest repeats the
source metadata, candidate ID, decision and reason. The five unresolved cases
still need the identity checks documented there; their status has not been
promoted by this implementation. Activating production mappings and catalog
refresh rules is a separate reviewed step.

## Full XML exact candidate registry

The full catalogue pass is retained separately from the ten-entry evidence-rich
pilot. `anex-xml-exact-registry.json` authenticates the compact
`anex-xml-exact-identities.csv` with its SHA-256 digest and records the exact
workflow run, artifact digest, source SHA and ANEX reference stamp.

The 10,611 machine-exact rows were audited again before registry generation.
Every accepted row must still have one selected AnyTour candidate, equal
normalized supplier/candidate names, equal normalized countries and matching
town or region. A further 1,877 rows with a normalized one-word name shorter
than eight characters are deliberately deferred for human review. The compact
preview registry therefore contains 8,734 ANEX XML identities.

```php
require_once __DIR__ . '/anex-xml-exact-registry.php';
$registry = AnyTourAnexXmlExactRegistry::fromFile();
$hotelId = $registry->resolve('anex_xml', '39527', 'preview'); // 116676
$online = $registry->resolve('anex_online', '39527', 'preview'); // null
$production = $registry->resolve('anex_xml', '39527'); // null
```

This registry is not connected to the public search or production. It resolves
only the explicit `anex_xml` namespace and exact `preview` scope. It never
assumes that an XML hotel ID is also an `anex_online` hotel ID. The CSV is
bounded, digest-checked, duplicate-rejecting and sorted by numeric external ID,
so regenerating it from the same evidence is idempotent.

### Bounded geographic enrichment pilot

The catalog diagnostic additionally reads Online `Hotels_DETAILS` for the first
10 XML review IDs in ascending order. It checks XML/Online ID, name, town ID
and known country consistency before using details. AnyTour candidates are
read in read-only transactions; addresses and coordinates are retained in the
separate `anex-hotel-geo-enrichment.json` Actions artifact. Address equality is
supporting evidence only. No mappings are applied and exact-pass counts remain
unchanged. Results are not a full-queue coverage estimate.

`strong_candidate` requires matching known countries, name similarity >=0.90,
distance <=200m, no competing score within 0.10, no recognized section-name
difference, and fewer than the enrichment reader limit of 64 candidates (the original pilot retains eight). This remains
a review proposal, not proof of identity: shared complex coordinates, omitted
section names and candidate retrieval limits can still hide ambiguity.
Missing details and supplier-namespace conflicts remain in review. This pilot
makes at most ten detail calls; it does not implement a resumable whole-catalog
cache. Room categories and property area are not used without verified fields.
