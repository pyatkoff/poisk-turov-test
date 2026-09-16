#!/usr/bin/env python3
from pathlib import Path
p=Path('scripts/diagnostics/andromeda_current_organic_cohort.py').read_text()
for required in ('api-andromeda-quote-preview.php','getMTime()<$producerMtime','final_price_verified','served_price_observation','relative_delta_bps',"'supplier_calls'=>0","'database_access'=>false","'remote_writes'=>0"):
    assert required in p, required
for forbidden in ('curl_','PDO','mysqli_','INSERT ','UPDATE ','DELETE ','bron_ticket'):
    assert forbidden not in p, forbidden
assert 'supplier_offer_id' in p and 'private_leak' in p
print('Andromeda current organic cohort: read-only aggregate contract PASS')
