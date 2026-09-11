from pathlib import Path

src = Path('scripts/diagnostics/hotel_match_current_bulk_accept.php').read_text(encoding='utf-8')

required = [
    "hotel-match-current-bulk-accept-1971-20260911-v2",
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
    "mba_cross_provider_bridge",
    "mba_reverse_anex_bridge",
    "mba_provider_bridge",
    "mba_bridge_index",
    "cross_provider_anex_tourvisor_strict_name",
    "cross_provider_andromeda_tourvisor_strict_name",
    "andromeda_tourvisor_existing_local",
    "mbr_local_sets",
    "fc_key($name,true,false)",
    "cross_provider_single_token_requires_direct_geo'=>true",
    "reverse_cross_provider_requires_existing_andromeda_tv_local'=>true",
    "anex_reverse_bridge_pair_excluded",
    "planned_classes",
]
for needle in required:
    assert needle in src, needle

assert src.index("$db->commit();") < src.index("anex_post_commit_readback_failed")
assert src.count("$db->commit();") == 1
assert "starKey" not in src
assert "curl" not in src.lower()
assert "http://" not in src and "https://" not in src
assert "INSERT INTO anex_hotel_search_mappings" in src
assert "UPDATE andromeda_hotel_identities" in src
assert "DELETE FROM" not in src.upper()
assert "UPDATE catalog_hotels" not in src
assert "INSERT INTO catalog_hotels" not in src

# Both provider bridge directions require unique normalized-name evidence and block
# hard coordinate conflicts; weak one-token names require direct geo evidence.
assert "if (count($candidateIds) !== 1) return null" in src
assert "if ($guard['coordinate_conflict']) return null" in src
assert "if ($maxTokens < 2 && !$directGeo) return null" in src
assert "$distance !== null && (int)$distance <= 1000" in src

# Reverse ANEX bridge uses only already accepted Andromeda+Tourvisor locals from
# before ANEX writes and rechecks explicit ANEX/local pair exclusions.
assert "[, $andromedaLocalBefore] = mbr_local_sets($db)" in src
assert "mba_bridge_index($andromedaLocalBefore,$hotels,$names)" in src
assert "isset($excluded[$id][$target])" in src

print('hotel_match_current_bulk_accept_source_test: ok')
