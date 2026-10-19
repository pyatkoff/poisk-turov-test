#!/usr/bin/env python3
import importlib.util
from pathlib import Path

p = Path("scripts/diagnostics/hotel_match_samo_business_live30_common4_wave5_recovery_v14.py")
spec = importlib.util.spec_from_file_location("recovery", p)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

m.self_test()
assert m.OP == "hotel-match-samo-business-live30-common4-wave5-recovery-1971-20260925-v14"
assert m.EXPECTED_MANIFEST_SHA == "5542a3dc0ba8924f19f108ff7826e1628f66128e328bac0cdfc6f595f7329bc4"
print("MATCH_SAMO_BUSINESS_LIVE30_WAVE5_RECOVERY_V14_TEST_OK")
