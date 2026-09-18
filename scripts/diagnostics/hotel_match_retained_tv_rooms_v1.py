#!/usr/bin/env python3
"""Offline evidence receiver: union every retained page; never infer room mappings."""
from __future__ import annotations
import argparse
import hashlib
import json
import re
import unittest
import zipfile
from pathlib import Path
from urllib.parse import parse_qsl, urlsplit

OPERATION = 'hotel-match-anex-user-seen-uae12-direct-1971-20260919-v1'
RESULT_SHA = '247515ae8fbe431badbea6464b373c51044f3d2984ad10e976d6bc8ae4a38db2'
ARCHIVE_SHA = '44faab06cf32c822a223758770d99cd4396f6b544bf33d839c11a7c6b1d4a10a'
PAGES_SHA = 'a973ba2d2c553c9c70196d00caaa48b1e77aa5c1d5c6d079523ab7e74314371e'
IDS = (2522, 2565, 2582, 2590, 32230, 44669, 54603, 73055, 73509, 74083, 88323, 133061)

def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def encoded(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()

def room_key(raw: str) -> str:
    # Do not remove view, occupancy, bed/size/category, punctuation, or qualifiers.
    return ' '.join(re.sub(r'(?<!\w)(?:ROOM|НОМЕР|КОМНАТА)(?!\w)', ' ', raw.upper()).split())

def link_evidence(url: str) -> dict:
    p = urlsplit(url)
    if (p.scheme != 'https' or p.hostname != 'agent.anextour.ru' or
            p.path != '/search/tour' or p.username or p.password or p.port or p.fragment):
        raise ValueError('operator_link_origin')
    query = parse_qsl(p.query, keep_blank_values=True)
    if any(re.search('token|password|secret|auth|session', k, re.I) for k, _ in query):
        raise ValueError('operator_link_sensitive')
    values = [v for k, v in query if k.upper() == 'HOTELLIST']
    tokens = [token for value in values for token in value.split(',')]
    # Retain malformed, signed, empty and duplicate tokens instead of silently dropping.
    clean = len(values) == 1 and len(tokens) == 1 and bool(re.fullmatch(r'[1-9][0-9]{0,7}', tokens[0]))
    return {'raw_hotellist_values': values, 'raw_tokens': tokens,
            'classification': 'single_positive_anchor' if clean else 'hold_multi_or_unknown_tokens',
            'single_native_anex_id': int(tokens[0]) if clean else None,
            'safe_to_write_now': False}

def recover_pages(pages: list[list[dict]], allowed: set[int]) -> tuple[dict, list[int], set]:
    hotels: dict[int, dict] = {}
    seen_tours: dict[tuple[int, str], tuple] = {}
    page_totals = []
    for index, rows in enumerate(pages):
        if not isinstance(rows, list):
            raise ValueError('page_shape')
        for h in rows:
            hid = h.get('id')
            if hid not in allowed or isinstance(hid, bool) or h.get('country', {}).get('id') != 9:
                raise ValueError('hotel_scope')
            if hid not in hotels:
                hotels[hid] = {'tv_hotel_id': hid, 'name': h['name'], 'rooms': {}, 'tour_count': 0}
            target = hotels[hid]
            if target['name'] != h['name']:
                raise ValueError('hotel_name_changed')
            for tour in h.get('tours', []):
                if tour.get('operator', {}).get('id') != 13:
                    raise ValueError('foreign_operator')
                tid = tour.get('id'); raw = tour.get('roomType'); rid = tour.get('roomId')
                if not isinstance(tid, str) or not tid:
                    raise ValueError('tour_identity')
                if raw is not None and not isinstance(raw, str):
                    raise ValueError('room_name_type')
                if rid is not None and (not isinstance(rid, (str, int)) or isinstance(rid, bool)):
                    raise ValueError('room_id_type')
                tour_key = (hid, tid)
                room_signature = (raw, rid)
                if tour_key in seen_tours and seen_tours[tour_key] != room_signature:
                    raise ValueError('tour_room_changed')
                if tour_key not in seen_tours:
                    target['tour_count'] += 1
                seen_tours[tour_key] = room_signature
                if raw is None or not raw.strip():
                    continue
                rk = json.dumps([raw, rid], ensure_ascii=False)
                if rk not in target['rooms']:
                    target['rooms'][rk] = {'provider': 'tourvisor', 'operator_id': 13,
                                          'raw_name': raw, 'room_id': rid,
                                          'comparison_key': room_key(raw), 'page_indices': []}
                observed_pages = target['rooms'][rk]['page_indices']
                if index not in observed_pages:
                    observed_pages.append(index)
        page_totals.append(len(seen_tours))
    for hotel in hotels.values():
        hotel['rooms'] = sorted(hotel['rooms'].values(), key=lambda r: (r['raw_name'], str(r['room_id'])))
    return hotels, page_totals, set(seen_tours)

def receive(path: Path) -> dict:
    if path.is_dir():
        read = lambda name: (path / name).read_bytes()
        source_archive_sha = None
    else:
        archive = path.read_bytes()
        if sha(archive) != ARCHIVE_SHA:
            raise ValueError('archive_digest')
        z = zipfile.ZipFile(path)
        if len(z.namelist()) != len(set(z.namelist())):
            raise ValueError('duplicate_members')
        read = z.read
        source_archive_sha = ARCHIVE_SHA
    result_raw = read('remote/result.json')
    if sha(result_raw) != RESULT_SHA:
        raise ValueError('result_digest')
    result = json.loads(result_raw); receipt = json.loads(read('remote/receipt.json'))
    if (result['operation'] != OPERATION or receipt['operation'] != OPERATION or
            receipt['result_sha256'] != RESULT_SHA or result['state'] != 'completed_read_only' or
            result['full_drain'] is not True or result['no_replay'] is not True):
        raise ValueError('source_receipt')
    if result['mapping_writes'] != 0 or result['database_writes'] != 0:
        raise ValueError('unexpected_acquisition_write')
    rounds = result['rounds']
    if [x['round'] for x in rounds] != list(range(15)):
        raise ValueError('round_sequence')
    page_bytes = [read(f'remote/partial-{i}.json') for i in range(15)]
    manifest = {f'remote/partial-{i}.json': sha(b) for i, b in enumerate(page_bytes)}
    if sha(encoded(manifest)) != PAGES_SHA:
        raise ValueError('page_digest')
    hotels, totals, tours = recover_pages([json.loads(b) for b in page_bytes], set(IDS))
    if totals != [x['tours'] for x in rounds] or totals[-1] != 3961 or totals[-2] != totals[-1]:
        raise ValueError('union_drain_mismatch')
    details = {int(d['local_id']): d for d in result['details']}
    if len(result['details']) != len(details) or set(details) != set(hotels) or set(hotels) != set(IDS):
        raise ValueError('detail_cohort')
    outputs = []
    for hid in sorted(hotels):
        hotel = hotels[hid]; d = details[hid]
        if d['local_name'] != hotel['name'] or (hid, d['fresh_tour_id']) not in tours:
            raise ValueError('detail_hotel_tour_binding')
        links = [r['url'] for r in d['operator_link_fields'] if r['key'] == 'operatorLink']
        if len(links) != 1:
            raise ValueError('detail_link_count')
        hotel['native_link_evidence'] = link_evidence(links[0])
        hotel['initial_room_name_summary_count'] = len(d['tv_room_raw'])
        hotel['room_cross_provider_correspondences'] = 0
        hotel['safe_to_write_now'] = False
        outputs.append(hotel)
    result = {'source_operation': OPERATION, 'source_result_sha256': RESULT_SHA,
              'source_artifact_id': 10571026944, 'source_artifact_archive_sha256': ARCHIVE_SHA,
              'page_manifest_sha256': PAGES_SHA, 'page_sha256': manifest,
              'unique_hotel_count': len(hotels), 'unique_tour_count': len(tours),
              'hotel_local_raw_room_count': sum(len(h['rooms']) for h in outputs),
              'initial_room_name_summary_count': sum(h['initial_room_name_summary_count'] for h in outputs),
              'single_token_hotel_anchors': sum(h['native_link_evidence']['classification']=='single_positive_anchor' for h in outputs),
              'held_multi_token_hotel_anchors': sum(h['native_link_evidence']['classification']!='single_positive_anchor' for h in outputs),
              'room_cross_provider_correspondences': 0, 'hotels': outputs,
              'new_provider_calls': 0, 'database_writes': 0, 'safe_to_write_now': False}
    return result

class ReceiverTests(unittest.TestCase):
    def test_signed_tokens_never_disappear(self):
        r = link_evidence('https://agent.anextour.ru/search/tour?HOTELLIST=7717,-1025133')
        self.assertEqual(r['raw_tokens'], ['7717','-1025133'])
        self.assertIsNone(r['single_native_anex_id'])
    def test_single_and_unknown(self):
        self.assertEqual(link_evidence('https://agent.anextour.ru/search/tour?HOTELLIST=7713')['single_native_anex_id'],7713)
        for s in ['1,2','1,','0','-2','abc','1&HOTELLIST=1','']:
            self.assertIsNone(link_evidence('https://agent.anextour.ru/search/tour?HOTELLIST='+s)['single_native_anex_id'])
    def test_origin_and_sensitive(self):
        for u in ['http://agent.anextour.ru/search/tour?HOTELLIST=1','https://evil.invalid/search/tour?HOTELLIST=1','https://agent.anextour.ru/search/tour?HOTELLIST=1&token=x']:
            with self.assertRaises(ValueError): link_evidence(u)
    def test_room_semantics(self):
        self.assertEqual(room_key('FAMILY SEA VIEW DELUXE SUITE ROOM'),'FAMILY SEA VIEW DELUXE SUITE')
        self.assertEqual(room_key('НОМЕР FAMILY КОМНАТА'),'FAMILY')
        self.assertEqual(room_key('1 BEDROOM king/twin'),'1 BEDROOM KING/TWIN')
    @staticmethod
    def hotel(hid, tid, raw='FAMILY ROOM', rid=3, op=13):
        return {'id':hid,'country':{'id':9},'name':f'Hotel{hid}','tours':[{'id':tid,'operator':{'id':op},'roomType':raw,'roomId':rid}]}
    def test_all_pages_and_hotel_scope(self):
        a=self.hotel(1,'a');b=self.hotel(2,'b')
        h,totals,_=recover_pages([[a,b],[self.hotel(1,'c','SUITE')],[a]],{1,2})
        self.assertEqual(totals,[2,3,3]);self.assertEqual(sum(len(x['rooms']) for x in h.values()),3)
        self.assertEqual(h[1]['rooms'][0]['page_indices'],[0,2])
    def test_foreign_and_changed_tour(self):
        with self.assertRaises(ValueError): recover_pages([[self.hotel(1,'a',op=5)]],{1})
        with self.assertRaises(ValueError): recover_pages([[self.hotel(1,'a')],[self.hotel(1,'a','SUITE')]],{1})
    def test_large_union(self):
        rows=[self.hotel(i,str(i)) for i in range(1,2001)]
        h,totals,_=recover_pages([rows[:1000],rows[1000:],rows[:3]],set(range(1,2001)))
        self.assertEqual(len(h),2000);self.assertEqual(totals,[1000,2000,2000])

if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--self-test',action='store_true')
    parser.add_argument('--input',type=Path)
    parser.add_argument('--output',type=Path)
    args=parser.parse_args()
    if args.self_test:
        outcome=unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(ReceiverTests))
        if not outcome.wasSuccessful(): raise SystemExit(1)
    if args.input:
        data=receive(args.input)
        if not args.output: parser.error('--output required with --input')
        args.output.parent.mkdir(parents=True,exist_ok=True)
        with args.output.open('xb') as f: f.write(encoded(data))
        print(json.dumps({k:v for k,v in data.items() if k not in ['hotels','page_sha256']},sort_keys=True))
        print('RESULT_SHA256',sha(encoded(data)))
