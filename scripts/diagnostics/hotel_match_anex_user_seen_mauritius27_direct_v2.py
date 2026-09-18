#!/usr/bin/env python3
import importlib.util, json, os, pathlib, sys

HERE = pathlib.Path(__file__).resolve().parent
BASE_FILE = HERE / 'hotel_match_anex_user_seen_uae12_direct_v1.py'
EXPECTED_BASE_BLOB = 'fc005a518bcccb19e775526a8123927807fe5b5b'
OP = 'hotel-match-anex-user-seen-mauritius27-direct-1971-20260919-v2'
TARGET_IDS = [11745,11751,11754,11756,11758,11761,11770,11775,11788,11789,26841,64582,11769,11771,11792,11802,11805,15788,15794,26842,28452,35164,35170,44413,60234,68977,106703]
KNOWN_FLOOR = 184
OP_CAP = 280
CONTEXT = {
    'departureId': 1, 'countryId': 27,
    'dateFrom': '2026-10-28', 'dateTo': '2026-11-03',
    'nightsFrom': 7, 'nightsTo': 10,
    'adults': 2, 'currency': 'RUB', 'onlyCharter': False,
    'operatorIds': [13], 'hotelIds': TARGET_IDS,
}


def git_blob_sha(path: pathlib.Path) -> str:
    import hashlib
    raw = path.read_bytes()
    return hashlib.sha1(b'blob ' + str(len(raw)).encode() + b'\0' + raw).hexdigest()


def load_base():
    if not BASE_FILE.is_file() or git_blob_sha(BASE_FILE) != EXPECTED_BASE_BLOB:
        raise RuntimeError('pinned_base_mismatch')
    spec = importlib.util.spec_from_file_location('uae12_direct_v1_pinned', BASE_FILE)
    if spec is None or spec.loader is None:
        raise RuntimeError('module_load')
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def write_locked_compat(f, obj):
    text = json.dumps(obj, ensure_ascii=False, sort_keys=True) + '\n'
    f.seek(0); f.truncate(0)
    if 'b' in getattr(f, 'mode', ''):
        raw = text.encode(); n = f.write(raw); expected = len(raw)
    else:
        n = f.write(text); expected = len(text)
    if n != expected:
        raise RuntimeError('locked_write_short')
    f.flush(); os.fsync(f.fileno())


def patch(mod):
    mod.OP = OP
    mod.TARGET_IDS = list(TARGET_IDS)
    mod.KNOWN_FLOOR = KNOWN_FLOOR
    mod.OP_CAP = OP_CAP
    mod.write_locked = write_locked_compat
    original_http = mod.http_call

    def http_call(token, home, lane, action, path, params=None, **kwargs):
        if action == 'search_start' and path == '/tours/search':
            params = dict(CONTEXT)
        if lane == 'UAE12':
            lane = 'MAURITIUS27'
        return original_http(token, home, lane, action, path, params, **kwargs)

    mod.http_call = http_call
    return mod


def self_test(mod):
    assert len(TARGET_IDS) == 27 and len(set(TARGET_IDS)) == 27
    assert mod.OP == OP and mod.TARGET_IDS == TARGET_IDS
    assert mod.KNOWN_FLOOR == 184 and mod.OP_CAP == 280 and mod.DAILY_LIMIT == 3000
    assert mod.write_locked is write_locked_compat
    assert CONTEXT['operatorIds'] == [13] and CONTEXT['countryId'] == 27
    assert CONTEXT['dateFrom'] == '2026-10-28' and CONTEXT['dateTo'] == '2026-11-03'
    assert CONTEXT['nightsFrom'] == 7 and CONTEXT['nightsTo'] == 10
    assert mod.room_raw({'roomName':'FAMILY DELUXE SEA VIEW ROOM'}) == ['FAMILY DELUXE SEA VIEW ROOM']
    _, ids = mod.link_evidence({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=5844'})
    assert ids == [5844]
    print('MATCH_ANEX_MAURITIUS27_DIRECT_V2_SELFTEST_OK')


def main():
    mod = patch(load_base())
    if '--self-test' in sys.argv:
        self_test(mod); return 0
    if '--execute' not in sys.argv:
        raise RuntimeError('disabled')
    return mod.main()


if __name__ == '__main__':
    sys.exit(main())
