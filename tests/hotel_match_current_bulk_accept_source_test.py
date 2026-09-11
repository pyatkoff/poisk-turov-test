from pathlib import Path

src = Path('scripts/diagnostics/hotel_match_current_bulk_accept.php').read_text(encoding='utf-8')

required = [
    "hotel-match-current-bulk-accept-1971-20260911-v1",
    "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ",
    "beginTransaction()",
    "FOR UPDATE",
    "anex_hotel_decisions",
    "anex_review_pair_exclusions",
    "decision_status='pending' AND local_hotel_id IS NULL",
    "evidence_sha256 <=> ?",
    "mbr_review_anex",
    "mbr_review_andromeda",
    "coordinate_conflict_auto_block_m'=>5000",
    "supplier_calls'=>0",
    "$db->commit();",
    "anex_post_commit_readback_failed",
    "andromeda_post_commit_readback_failed",
    "'committed'=>$committed",
    "mba_identity_counts",
    "identity_before",
    "identity_after",
    "mba_row_is_safe_auto_accept",
    "mba_fuzzy_has_direct_geo",
    "fuzzy_requires_direct_geo'=>true",
]
for needle in required:
    assert needle in src, needle

assert src.index("$db->commit();") < src.index("anex_post_commit_readback_failed")
assert "starKey" not in src
assert "curl" not in src.lower()
assert "http://" not in src and "https://" not in src
assert "INSERT INTO anex_hotel_search_mappings" in src
assert "UPDATE andromeda_hotel_identities" in src
assert "DELETE FROM" not in src.upper()
assert "UPDATE catalog_hotels" not in src
assert "INSERT INTO catalog_hotels" not in src
print('hotel_match_current_bulk_accept_source_test: ok')
