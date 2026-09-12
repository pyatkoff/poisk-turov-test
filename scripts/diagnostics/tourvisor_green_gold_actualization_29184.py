#!/usr/bin/env python3
"""One-shot GREEN GOLD Tourvisor actualization for the observed ~29184 RUB fuel band."""
import importlib.util
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
SPEC = importlib.util.spec_from_file_location('tv_green_gold_base', HERE / 'tourvisor_green_gold_actualization.py')
if SPEC is None or SPEC.loader is None:
    raise RuntimeError('base_probe_import')
base = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(base)

base.EXPERIMENT = 'tourvisor_green_gold_actualization_29184_20260913_v1'
_original_php_source = base.php_source


def php_source_target():
    source = _original_php_source()
    old = "if(!$cand)throw new RuntimeException('TV_ACT_NO_TOUR');usort($cand,fn($a,$b)=>(float)$a['price']<=>(float)$b['price']);$search=$cand[0];$tid=$search['tour_id'];"
    new = "if(!$cand)throw new RuntimeException('TV_ACT_NO_TOUR');$target=array_values(array_filter($cand,static fn($r)=>abs((float)$r['fuel']-29184.0)<1.0));if(!$target)throw new RuntimeException('TV_ACT_TARGET_FUEL_MISSING');usort($target,fn($a,$b)=>(float)$a['price']<=>(float)$b['price']);$search=$target[0];$tid=$search['tour_id'];"
    if old not in source:
        raise RuntimeError('base_selector_contract_changed')
    source = source.replace(old, new, 1)
    old_exp = "'experiment_id'=>'tourvisor_green_gold_actualization_20260913_v1'"
    new_exp = "'experiment_id'=>'tourvisor_green_gold_actualization_29184_20260913_v1'"
    if old_exp not in source:
        raise RuntimeError('base_experiment_contract_changed')
    return source.replace(old_exp, new_exp, 1)


base.php_source = php_source_target

if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit('usage: tourvisor_green_gold_actualization_29184.py OUTPUT_DIR')
    base.run(Path(sys.argv[1]))
