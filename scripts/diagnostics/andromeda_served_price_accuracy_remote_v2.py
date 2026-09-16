#!/usr/bin/env python3
"""Corrected read-only deployed capability probe for Andromeda served-price observations."""
import json
import sys
import andromeda_served_price_accuracy_remote as v1

# The 16-file publisher strips the `v2/` prefix, so the deployed producer is this root entrypoint.
_WRONG = "$target.'/api-andromeda-selected-quote.php',$target.'/app/integrations/andromeda-price-observation.php'"
_RIGHT = "$target.'/api-andromeda-quote-preview.php'"
if v1.PHP.count(_WRONG) != 1:
    raise RuntimeError('accuracy_v1_probe_shape_changed')
PHP = v1.PHP.replace(_WRONG, _RIGHT)
# Capability is true only when the actual deployed producer contains both the persisted field and producer call.
PHP = PHP.replace(
    "if(is_string($text)&&strpos($text,'served_price_observation')!==false)$runtimeSupports=true;",
    "if(is_string($text)&&strpos($text,'served_price_observation')!==false&&strpos($text,'AnyTourAndromedaPriceObservation::compareServed')!==false)$runtimeSupports=true;"
)
if PHP == v1.PHP or "api-andromeda-selected-quote.php" in PHP:
    raise RuntimeError('accuracy_v2_probe_not_applied')

validate = v1.validate


def main():
    if sys.argv != [sys.argv[0], '--read-only']:
        raise SystemExit('usage: andromeda_served_price_accuracy_remote_v2.py --read-only')
    from anex_search3_owner_decisions import ssh_php
    try:
        result = validate(ssh_php(PHP, {}))
    except Exception:
        result = {'status':'blocked','reason':'remote_outcome_unknown','supplier_calls':0,'database_access':False,'remote_writes':0}
    print(json.dumps(result, sort_keys=True, separators=(',', ':')))
    if result['status'] != 'ok':
        raise SystemExit('read-only accuracy inspection v2 unconfirmed; no retry')


if __name__ == '__main__':
    main()
