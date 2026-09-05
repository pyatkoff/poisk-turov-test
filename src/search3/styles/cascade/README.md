# Cascade compatibility modules

These nine physical modules replace `styles/cascade-compatibility.css` in the
same position and order in `src/search3/manifest.json`. They are not additional
browser requests; the public asset is still `v2/search3-results-filters-v1.css`.
All declarations remain active unless a separate cleanup proves otherwise.

## Byte-preserving split

The baseline is the original cascade blob from `c2212d3a` (also `f729158e`):
78,306 bytes, Git blob `9b3583c4261dda23109b369595cb955aa473b7fb`.
The largest physical module is now `search3-convergence.css`, 16,945 bytes.
The first module retains the original two leading LF bytes. Other boundaries
start at the next donor marker; all trailing bytes belong to the previous part.

The first split in `6e87c0fc` lost nine LF bytes: one at the end of each module
except iteration1, plus one between the desktop and mobile convergence blocks.
Repair restores those bytes, not the expected hashes. No generated asset or
protected-runtime fingerprint changes are needed for this repair.

For any later source-only split, slice `read_bytes()` by marker byte offsets.
Do not trim, normalize newlines, or reconstruct text with `splitlines()`/join.
Verify the concatenation before creating a commit. A hash failure is not a reason
to bless new generated output during a source-only move.

```sh
python3 scripts/build/search3_cascade_sections.py --check
python3 scripts/build/search3_cascade_sections.py --json
python3 scripts/build/search3_assets.py --check
python3 -B tests/search3_production_presentation_test.py
```

The presentation suite tests lost seam LF, LF-to-CRLF conversion and same-length
CSS corruption in temporary copies. The byte-count and content-hash guards must
reject all three; checked-in files are never mutated by those tests.

Actual declaration cleanup is a separate change: prove the removed rules are
inert or preserve cascade behavior, review responsive evidence, then update the
section contract and generated asset/import hash together. Do not mix cleanup
with this byte-identical split repair. Main/production remain owner-gated.
