#!/usr/bin/env python3
import importlib.util
from pathlib import Path
p=Path('scripts/diagnostics/tourvisor_green_gold_actualization.py')
spec=importlib.util.spec_from_file_location('probe',p); m=importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
s=m.php_source()
assert m.EXPERIMENT=='tourvisor_green_gold_actualization_20260913_v1'
for x in ("/tours/search","/flights","fuelCharge","isDefault","surcharges","'currency'=>'RUB'","21753","2026-10-12"):
    assert x in s
for x in ('bron_ticket','->bron(','broninit(','->calc(','INSERT INTO','UPDATE '):
    assert x not in s
assert "production_price_arithmetic_applied'=>false" in s
assert "mapping_writes'=>0" in s and "booking_calls'=>0" in s
print('tourvisor green gold actualization static test: PASS')
