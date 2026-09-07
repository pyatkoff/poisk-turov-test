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
