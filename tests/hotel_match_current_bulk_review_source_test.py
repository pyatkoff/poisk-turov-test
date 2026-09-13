#!/usr/bin/env python3
from pathlib import Path
import re

SOURCE = Path('scripts/diagnostics/hotel_match_current_bulk_review.php').read_text(encoding='utf-8')

assert "hotel-match-current-bulk-review-1971-20260911-v1" in SOURCE
assert "START TRANSACTION READ ONLY" in SOURCE
assert "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ" in SOURCE
assert "database_writes'=>0" in SOURCE
assert "supplier_calls'=>0" in SOURCE
assert "coordinate_conflict_auto_block_m'=>5000" in SOURCE
assert "MBR_CORE8 = [1=>'Египет',2=>'Таиланд',4=>'Турция',8=>'Мальдивы',9=>'ОАЭ',10=>'Куба',12=>'Шри-Ланка',16=>'Вьетнам']" in SOURCE
assert "['category','star','stars','starName','star_name']" in SOURCE
assert "starKey" not in SOURCE
assert "manual_decisions_overwritten'=>false" in SOURCE
assert "pair_exclusions_overwritten'=>false" in SOURCE
assert "existing_mappings_overwritten'=>false" in SOURCE
assert "tourvisor_anex_operator_link_hotelcode_needed" in SOURCE
assert "third_link_gaps" in SOURCE
assert "auto_accept" in SOURCE and "needs_extra_evidence" in SOURCE and "hard_conflict" in SOURCE and "manual_last" in SOURCE

# The review script is a current-state reader/planner only. It must not contain
# executable data-changing SQL. Transaction control itself is intentionally allowed.
for pattern in (
    r"\bINSERT\s+INTO\b",
    r"\bUPDATE\s+[A-Za-z_`]",
    r"\bDELETE\s+FROM\b",
    r"\bREPLACE\s+INTO\b",
    r"\bCREATE\s+(?:TABLE|INDEX)\b",
    r"\bALTER\s+TABLE\b",
    r"\bDROP\s+(?:TABLE|INDEX)\b",
):
    assert not re.search(pattern, SOURCE, re.I), pattern

# No supplier/network implementation belongs in this pass.
for token in ('curl_exec', 'file_get_contents(\'http', 'file_get_contents("http', 'agent.anextour.ru/search/tour?', 'tourvisor.ru/search.php'):
    assert token not in SOURCE

print('hotel_match_current_bulk_review_source_test: ok')
