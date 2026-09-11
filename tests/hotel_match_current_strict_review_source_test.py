from pathlib import Path

p = Path('scripts/diagnostics/hotel_match_current_strict_review.php')
s = p.read_text(encoding='utf-8')

assert "hotel-match-current-strict-review-1971-20260911-v2" in s
assert "START TRANSACTION READ ONLY" in s
assert "cross_provider_anex_tourvisor_strict_name" in s
assert "fuzzy_without_direct_geo" in s
assert "coordinate_conflict" in s
assert "single_token_bridge_requires_direct_geo" in s
assert "manual_decisions_overwritten'=>false" in s
assert "pair_exclusions_overwritten'=>false" in s
assert "existing_mappings_overwritten'=>false" in s
assert "supplier_calls'=>0" in s
assert "starKey" not in s

upper = s.upper()
for forbidden in ("INSERT INTO ", "UPDATE ANDROMEDA_", "DELETE FROM ", "REPLACE INTO ", "ALTER TABLE ", "DROP TABLE "):
    assert forbidden not in upper, forbidden

# A fuzzy candidate is only safe with direct geography, and >5 km stays blocked.
assert "strong_fuzzy_geo_large_margin" in s
assert "$distance !== null && (int)$distance <= 1000" in s
assert "if ($guard['coordinate_conflict']) return null" in s

print('hotel_match_current_strict_review_source_test: ok')
