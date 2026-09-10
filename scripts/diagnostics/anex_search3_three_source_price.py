#!/usr/bin/env python3
"""Run one fixed ANEX-only price comparison across direct ANEX, Andromeda and Tourvisor.

The remote PHP owns durable case reservations. This coordinator never retries an unknown
case and only persists the sanitized result returned by the server runner.
"""
import json
import os
from pathlib import Path
import shlex
import sys
import tempfile

import anex_search3_gap_queue as gaps

EXPERIMENT = 'anex_three_source_price_20260911_v1'
CASES = ('anex', 'andromeda', 'tourvisor')
SPEC = {
    'experiment_id': EXPERIMENT,
    'country': 'Turkey',
    'date': '2026-09-20',
    'nights': 7,
    'adults': 2,
    'child_ages': [],
    'meal_family': 'ai',
    'currency': 'RUB',
}


def source() -> str:
    here = Path(__file__).resolve().parent
    old = (here / 'anex_search3_paired_runner.php').read_text()
    new = (here / 'anex_search3_three_source_price.php').read_text()
    if not old.startswith('<?php') or not new.startswith('<?php'):
        raise ValueError('three_source_php_header')
    return "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n" + old[5:] + '\n' + new[5:]


def ssh_php_no_mux(source_text, request, maximum_bytes=4000000):
    """Dedicated transport for this experiment after live preflight proved mux unhealthy.

    Server-side case reservations remain the no-replay authority. The client-side retry
    helper may retry only a TCP close before authentication/command, as in existing INT jobs.
    """
    if maximum_bytes not in (65536, 4000000):
        raise ValueError('unsupported_diagnostic_response_limit')
    names = ('ANYTOOUR_DEPLOY_SSH_KEY','ANYTOOUR_DEPLOY_HOST','ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(name, '').strip() for name in names):
        raise ValueError('missing_ssh_configuration')
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid_ssh_target')
    with tempfile.TemporaryDirectory(prefix='anex-three-price-', dir=os.environ.get('RUNNER_TEMP')) as temp:
        key = Path(temp) / 'ssh_key'
        key.write_text(os.environ[names[0]].rstrip() + '\n')
        key.chmod(0o600)
        command = [
            'ssh','-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
            '-o','ControlMaster=no','-o','ControlPath=none',
            '-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile=' + str(Path(temp)/'known_hosts'),
            '-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-o','LogLevel=DEBUG1',
            '-l',user,host,'cd "$HOME/www/anytoour.ru" && php -r ' + shlex.quote(source_text),
        ]
        env = {k:v for k,v in os.environ.items() if k not in names and not k.startswith('ANEX_') and not k.startswith('ANDROMEDA_')}
        result, _ = gaps.run_ssh(command, json.dumps(request, ensure_ascii=False), env)
    if len(result.stdout.encode('utf-8')) > maximum_bytes:
        raise ValueError('diagnostic_response_too_large')
    return json.loads(result.stdout)


def validate_case(value, case_id):
    if not isinstance(value, dict) or value.get('schema_version') != 1 or value.get('experiment_id') != EXPERIMENT \
            or value.get('case_id') != case_id or value.get('automatic_retry') is not False \
            or value.get('booking_calls') != 0 or value.get('broninit_calls') != 0 or value.get('mapping_writes') != 0:
        raise ValueError('three_source_case_invalid')
    status = value.get('status')
    if status == 'unknown':
        if value.get('supplier_effect') != 'unknown':
            raise ValueError('three_source_unknown_invalid')
        return value
    if status != 'completed' or value.get('supplier_effect') != 'read_only_search_completed' \
            or not isinstance(value.get('subject'), dict) or not isinstance(value.get('offers'), list) \
            or len(value['offers']) > 2000:
        raise ValueError('three_source_case_invalid')
    subject = value['subject']
    required = {'local_hotel_id','anex_hotel_id','andromeda_hotel_id','hotel_name','selection_basis','anex_observation_count'}
    if set(subject) != required or subject.get('selection_basis') != 'current_unique_triple_mapping':
        raise ValueError('three_source_subject_invalid')
    for row in value['offers']:
        if not isinstance(row, dict) or row.get('provider') != case_id or row.get('local_hotel_id') != subject['local_hotel_id'] \
                or row.get('date') != SPEC['date'] or row.get('nights') != SPEC['nights'] \
                or row.get('adults') != SPEC['adults'] or row.get('children') != 0 \
                or row.get('meal_family') != 'ai' or row.get('currency') != 'RUB' \
                or row.get('fuel_inclusion_verified') is not False or row.get('final_price_verified') is not False:
            raise ValueError('three_source_offer_invalid')
        if not isinstance(row.get('price'), str) or not row['price'] or not isinstance(row.get('room_norm'), str) \
                or not isinstance(row.get('placement_norm'), str):
            raise ValueError('three_source_offer_invalid')
    return value


def aligned_key(row):
    return (row['local_hotel_id'], row['date'], row['nights'], row['adults'], row['children'],
            row['meal_family'], row['room_norm'])


def compare(results):
    completed = {case: value for case, value in results.items() if value.get('status') == 'completed'}
    index = {case: {} for case in CASES}
    for case, value in completed.items():
        for row in value['offers']:
            index[case].setdefault(aligned_key(row), []).append(row)
    triple_keys = set(index['anex']) & set(index['andromeda']) & set(index['tourvisor'])
    triples = []
    for key in sorted(triple_keys, key=str):
        rows = {case: index[case][key] for case in CASES}
        triples.append({
            'basis': 'same_current_triple_mapped_hotel_date_party_ai_and_exact_room',
            'placement_compared_but_not_identity_key': True,
            'identical_supplier_package_verified': False,
            'fuel_inclusion_verified': False,
            'key': {'local_hotel_id': key[0], 'date': key[1], 'nights': key[2], 'adults': key[3],
                    'children': key[4], 'meal_family': key[5], 'room_norm': key[6]},
            'offers': rows,
        })
    minima = {}
    for case, value in completed.items():
        prices = [row for row in value['offers'] if row.get('price')]
        minima[case] = min(prices, key=lambda r: float(r['price'])) if prices else None
    subjects = [value.get('subject') for value in completed.values()]
    return {
        'same_subject_across_completed_cases': bool(subjects) and all(item == subjects[0] for item in subjects),
        'completed_cases': sorted(completed),
        'aligned_three_source_tour_count': len(triples),
        'aligned_three_source_examples': triples[:20],
        'source_minima_for_same_hotel': minima,
        'interpretation': 'aligned tours are comparison candidates only; price overlap never accepts hotel or supplier-package identity and fuel is never added automatically',
    }


def save(path: Path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    temp = path.with_suffix(path.suffix + '.tmp')
    temp.write_text(json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    temp.replace(path)
    if json.loads(path.read_text()) != value:
        raise ValueError('three_source_report_readback')


def transport_failure(exc):
    allowed_reasons = {
        'response_size_limit','ssh_exit_nonzero','ssh_authentication_failed','ssh_host_key_rejected',
        'ssh_connection_timeout','ssh_connection_refused','ssh_name_resolution_failed','ssh_network_unreachable',
        'ssh_session_rejected','ssh_connection_closed',
    }
    progress = getattr(exc, 'progress', None)
    progress_keys = ('tcp_connected','authenticated','multiplexing_seen','command_sent','remote_exit_seen')
    clean_progress = {key: bool(progress.get(key)) for key in progress_keys} if isinstance(progress, dict) else {key: False for key in progress_keys}
    reason = getattr(exc, 'reason_code', None)
    attempts = getattr(exc, 'attempts', None)
    return {
        'status': 'transport_unconfirmed','error_kind': type(exc).__name__ if type(exc).__name__ == 'SSHBatchError' else 'other',
        'reason_code': reason if reason in allowed_reasons else 'other','ssh_progress': clean_progress,
        'ssh_attempts': attempts if type(attempts) is int and 1 <= attempts <= 2 else None,
        'automatic_retry': False,'supplier_replay_requested': False,
    }


def run(output: Path):
    php = source(); results = {}
    for case in CASES:
        request = dict(SPEC, case_id=case)
        value = validate_case(ssh_php_no_mux(php, request, maximum_bytes=4000000), case)
        results[case] = value
        save(output / f'{case}.json', value)
        if value['status'] == 'unknown':
            break
    report = {
        'schema_version': 1,'experiment_id': EXPERIMENT,'spec': SPEC,
        'case_statuses': {case: value['status'] for case, value in results.items()},'comparison': compare(results),
        'fuel_policy': {
            'tourvisor': 'store price and fuelCharge separately; official UI/docs call displayed price final, no arithmetic here',
            'anex': 'search price unverified; AdditionalPricesDaily is separate pending live contract evidence',
            'andromeda': 'action=price has no documented separate fuel field; search price remains unverified',
        },
        'transport_policy': 'ControlMaster=no after supplier-free preflight 34540781410: no_mux ok, isolated_mux failed before command',
        'effects': {'booking_calls': 0, 'broninit_calls': 0, 'mapping_writes': 0},'unknown_replay_allowed': False,
    }
    save(output / 'report.json', report)
    return report


def main():
    if len(sys.argv) != 2:
        raise SystemExit('usage: anex_search3_three_source_price.py OUTPUT_DIR')
    output = Path(sys.argv[1])
    try:
        print(json.dumps(run(output), ensure_ascii=False, sort_keys=True))
    except Exception as exc:
        report = transport_failure(exc) if type(exc).__name__ == 'SSHBatchError' else {
            'status':'unconfirmed','error_kind':type(exc).__name__ if type(exc).__name__ in {'ValueError','RuntimeError','JSONDecodeError'} else 'other',
            'automatic_retry':False,'supplier_replay_requested':False,
        }
        try: save(output / 'failure.json', report)
        except Exception: pass
        print(json.dumps(report, sort_keys=True)); raise SystemExit(1) from None


if __name__ == '__main__':
    main()
