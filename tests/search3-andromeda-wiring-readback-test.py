#!/usr/bin/env python3
from pathlib import Path
p=Path('scripts/diagnostics/search3_andromeda_wiring_readback.py').read_text()
for x in ('search3-site-candidate','search3-anex-candidate','V2_ANDROMEDA_API_PUBLIC_PATH','andromeda-provider-v1.js','quoteEndpoint','listing_price_ref','api-andromeda-quote-preview.php',"'http_requests'=>0","'supplier_calls'=>0","'database_access'=>false","'remote_writes'=>0"):assert x in p,x
for x in ('curl_','http.client','urllib','PDO','mysqli_','INSERT ','UPDATE ','DELETE '):assert x not in p,x
print('Search3 Andromeda wiring readback: offline boundary PASS')
