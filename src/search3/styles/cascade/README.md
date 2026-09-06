# Cascade compatibility modules

These nine physical modules now contain provenance markers only. The
owner-authorized retirement removed their declarations together with the tenth
layer, `styles/visual-compatibility.css`. Necessary rules remain in `lead-state`,
`tour-detail-convergence`, `results-context` and `hotel-packages`.
See `docs/project/search3-whole-layer-retirement.json` for scope and verification.

The modules retain the position and order of the former
`styles/cascade-compatibility.css` in `src/search3/manifest.json`. They are not
additional browser requests; the public asset remains
`v2/search3-results-filters-v1.css`. The split and cleanup notes below are historical.

## Historical byte-preserving split

The baseline is the original cascade blob from `c2212d3a` (also `f729158e`):
78,306 bytes, Git blob `9b3583c4261dda23109b369595cb955aa473b7fb`.
The largest physical module at that split was `search3-convergence.css`, 16,945 bytes.
Every module ended with exactly one LF. The original two blank separator LF bytes
belonged to the beginning of the following module, before its donor marker. The
first module retained the original two leading LF bytes. Concatenation was exact;
per-module boundaries do not have to start at the donor marker itself.

The first split in `6e87c0fc` lost nine LF bytes: one at the end of each module
except iteration1, plus one between the desktop and mobile convergence blocks.
`f5f616a4` restored those bytes and passed core, but whole-site boundary CI also
runs `git diff --check` against main. Its preserved blank lines at EOF failed
that lint. Moving the two separator LF bytes to the next module's beginning
satisfies both checks without changing CSS or disabling whitespace validation.
No generated asset, import hash or protected-runtime fingerprint changes are
needed for either repair.

For later source-only splits, slice `read_bytes()` using verified byte offsets.
Do not trim, normalize newlines, or reconstruct text with `splitlines()`/join.
Preserve every byte, assigning separator whitespace to the next module as above.
Verify concatenation and whitespace lint before creating a commit. A hash failure
is not a reason to bless new generated output during a source-only move.

```sh
python3 scripts/build/search3_cascade_sections.py --check
python3 scripts/build/search3_cascade_sections.py --json
python3 scripts/build/search3_assets.py --check
python3 -B tests/search3_production_presentation_test.py
git diff --check main HEAD
```

The presentation suite checks module EOFs, lost seam LF, LF-to-CRLF conversion
and same-length CSS corruption. Corruption tests mutate temporary copies only;
the byte-count and content-hash guards must reject all three kinds of drift.

Actual declaration cleanup is a separate change: prove the removed rules are
inert or preserve cascade behavior, review responsive evidence, then update the
section contract and generated asset/import hash together. Do not mix cleanup
with this byte-identical split repair. Main/production remain owner-gated.

## Historical numeric-longhand cleanup after the split

The 78,306-byte blob above is the historical split baseline at `03e7422e`,
not the current cleaned size. The current combined bytes/hash are owned by
`docs/project/search3-cascade-sections.json`.
`docs/project/search3-cascade-cleanup.json` records every removed declaration
and its later same-selector/media/importance winner. Only basic numeric-px
longhands were pruned; shorthands, variables, fallback keywords and different
media contexts were not merged. Surviving declarations retain their exact order.
The public bundle was rebuilt atomically with its import hash. This pass does
not change the JS assets, protected contracts or deployment permissions.
