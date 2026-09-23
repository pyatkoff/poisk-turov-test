#!/usr/bin/env python3
import importlib.util
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_common4_resume_salvage_v1.py'
s=importlib.util.spec_from_file_location('x',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
assert m.CHILD_RE.fullmatch('hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n899-v1')
assert not m.CHILD_RE.fullmatch('hotel-match-live30-common4-continuation-resume-1971-20260924-r2-n138-v1')
assert m.id_digest([3,1,2])==m.id_digest([1,2,3])
print('MATCH_COMMON4_RESUME_SALVAGE_V1_TEST_OK')
