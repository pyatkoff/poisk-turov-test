# Search3 numeric cascade cleanup verification

Baseline: `03e7422eb3efa2cf4382e89bfdb610d9dadc7bd1`.
Only `v2/search3-results-filters-v1.css` changes in the public payload.
After CSS SHA256: `0644c80ac4e088137cc0a3d2cb1d1891dc3267d9d2a5c8161a2940c67cb1bea8`.

## Scope and proof

Removed 127 earlier important numeric-pixel longhand declarations only when a later
valid numeric-pixel declaration has the same literal selector, media stack,
property and importance. Shorthands, variables and fallback keywords were not
rewritten. All surviving declarations retain their order. The exact last-occurrence
declaration stream agrees before/after. Native Chromium CSSOM independently agrees,
including shorthand expansion. Per-declaration winners and section hashes are in
`search3-cascade-cleanup.json`.

Removed 60 empty blocks: one pre-existing empty media block and 59 rules emptied by
the declaration cleanup. Removed three orphan footer comments and surplus blank
lines. These removals are not evidence that the remaining compatibility rules are dead.

| Scope | Before bytes | After bytes | Before lines | After lines |
| --- | ---: | ---: | ---: | ---: |
| Nine cascade source files combined | 78,306 | 71,256 | 874 | 770 |
| Public results/filters CSS | 349,588 | 342,538 | 3,700 | 3,596 |

Lines are counted with `bytes.splitlines()`. Savings are 7,050 uncompressed bytes
and 104 lines, not a measured network-load or rendering-time improvement.

## Verification performed before release-branch publication

- Isolated preparation run 33998257062 passed the actual eight-asset source build,
  cascade contract, five source-build tests, nine production-presentation tests,
  flight presentation, both production/preview path smoke tests, diff whitespace
  check and exact changed-file allowlist.
- Protected runtime fingerprints and unrelated import metadata remained identical;
  only the target CSS production hash changed. No JavaScript asset changed.
- Local fresh-page Chromium comparison: 375, 430, 768, 1024 and 1440 pixels;
  initial form, open editor, hotel/packages and selected-tour fixture states.
  All 20 comparisons had identical geometry and named computed property values.
  The final 20 screenshot pairs were byte-identical. A before-versus-before
  diagnostic also exposed intermittent repaint noise in earlier runs; the harness
  allows at most two RGB levels in 0.1% of pixels. Custom property enumeration
  order was sorted before comparison; no style value was ignored.
- The local fixture uses the actual result renderer and four Search3 CSS assets,
  with synthetic DOM/data and blocked external requests. It does not establish
  full-site visual acceptance, a live supplier journey or real lead delivery.
  Existing full PR responsive/artifact gates still apply to the final commit.

The temporary preparation workflow and scripts remain only on the isolated
`refactor/search3-cascade-cleanup-03e7422e` branch; they are not imported into the
release branch. The first preparation attempt stopped on whitespace and published
no generated changes. No CI rule was disabled to make it pass.

No merge to main, production activation or preview deployment is part of this pass.
API/Tourvisor, price arithmetic, lead transport/mapping and analytics are unchanged.
Continue from the cleaned modules, not the original 78,306-byte split baseline.
Any further pruning needs new cascade evidence; different media conditions and
shorthand/fallback relationships must not be treated as duplicate declarations.
