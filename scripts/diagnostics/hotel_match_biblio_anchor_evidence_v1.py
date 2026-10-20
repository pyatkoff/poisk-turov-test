#!/usr/bin/env python3
"""Reproducible, OFFLINE ONLY review of retained MATCH hotel identity evidence.
No supplier/DB clients, no write plan and no automatic acceptance. Python stdlib.
"""
from __future__ import annotations
import argparse, collections, hashlib, json, re, unicodedata, zipfile
from pathlib import Path

OP = 'hotel-match-biblio-anchor-evidence-1971-20260922-v1'
INPUTS = {
 'current': ('match-bg-current-dossier-terminal.zip', '2d7efa9e652a561928f72cb940e3c0d70c6eac6b18b132d93b90cf22e61d2c42', 'result.json', '84cc7d2097c191a2bfc64983a57ccfb63fe38775e6ecd0c8c9db1255f1efb6ce'),
 'registry': ('match-identities-retained-snapshot.zip', 'bf216bbe9159c4243c3884a055316cdc61f601aede30d931de48e0c60e629e1d', 'andromeda_hotel_identities.json', '2135ee882e58ad2524075897239556c683860bfef8cc5d7fe4db496a50244a1d'),
}
# Row-specific review assertions, NOT a global identity policy or mutating plan.
RISKS = {
 803: ('cross_resort_review', '2000037124', '8561', 'Phuket/Patong vs Khao-Lak; preserve both IDs for authorized repair review.'),
 2219: ('cross_resort_review', '2000091694', '119332', 'My Home 3* / Fatih vs My Home Resort 5* / Avsallar, Alanya.'),
 54633: ('cross_resort_review', '43156', '280652', 'Aspen Istanbul Old City / Fatih vs Aspen / Antalya.'),
 475: ('distinct_property_variant_review', '2000059367', '2000068784', 'Family 3* source assigned to Palma de Mirette 4*; local474 is a separate Family3* candidate. Also review operator315/694852 and operator342/12214; do not move them by transitivity.'),
}

def need(ok: bool, reason: str) -> None:
 if not ok: raise ValueError(reason)

def digest(raw: bytes) -> str:
 return hashlib.sha256(raw).hexdigest()

def read_inputs(root: Path) -> tuple[dict, dict]:
 out = {}
 for key, (file, archive_hash, member, member_hash) in INPUTS.items():
  raw = (root / file).read_bytes(); need(digest(raw) == archive_hash, 'archive hash: '+key)
  with zipfile.ZipFile(root / file) as z:
   need(z.getinfo(member).file_size <= 20_000_000, 'member limit')
   data = z.read(member)
   if key == 'current':
    receipt = json.loads(z.read('receipt.json'))
    need(receipt['result_sha256'] == digest(data), 'CURRENT receipt')
  need(digest(data) == member_hash, 'member hash: '+key); out[key] = json.loads(data)
 need(out['current']['source_sha'] == '8b15323f18189db09c1c3591182c167ba8c5243a', 'CURRENT source')
 need(out['current']['state'] == 'completed_read_only', 'CURRENT terminal')
 return out['current'], out['registry']

def norm(s: str) -> str:
 # Punctuation/diacritics/generic HOTEL only. Never remove BEACH/FAMILY/GRAND/etc.
 s = ''.join(c for c in unicodedata.normalize('NFKD', s.casefold()) if not unicodedata.combining(c))
 return ' '.join(w for w in re.findall(r'[^\W_]+', s, re.UNICODE) if w not in {'hotel', 'hotels', 'отель'})

def aliases(s: str) -> set[str]:
 # The EX marker alone is not proof of every fuzzy name or changed qualifier.
 m = re.search(r'\(\s*ex\.?\s*(.*?)\)', s, re.I)
 parts = [s] if not m else [s[:m.start()].strip(), m.group(1).strip()]
 return {norm(x) for x in parts if norm(x)}

def sources(e: object, depth: int = 0) -> list[dict]:
 need(depth <= 16, 'evidence nesting')
 if isinstance(e, list): return [s for x in e for s in sources(x, depth + 1)]
 if not isinstance(e, dict): return []
 out = [e['source']] if isinstance(e.get('source'), dict) else []
 for key, value in e.items():
  if key != 'source' and isinstance(value, (dict, list)): out.extend(sources(value, depth + 1))
 return out

def canonical(row: dict) -> list[dict]:
 return [x for x in row['identities'] if x['supplier_namespace'] == 'andromeda_catalog' and x['decision_status'] == 'accepted']

def multi_class(row: dict) -> tuple[str, list[str]]:
 tv = row['tv_hotel_id']; ids = {x['external_hotel_id'] for x in canonical(row)}
 if tv in RISKS:
  category, suspect, comparator, reason = RISKS[tv]
  need({suspect, comparator} <= ids, 'risk membership changed')
  return category, [reason, 'Existing accepted rows require separate explicit repair authorization.']
 if row['manual']: return 'manual_decision_preserved', ['Do not override the saved manual decision.']
 local_aliases = aliases(row['catalog_hotel']['name'])
 groups = []
 for identity in canonical(row):
  aa = set()
  for source in identity['source_records']:
   for field in ('name', 'lName'):
    if isinstance(source.get(field), str): aa.update(aliases(source[field]))
  groups.append(aa)
 records = [s for i in canonical(row) for s in i['source_records']]
 same_geo = len({(s.get('stateKey'), s.get('townKey')) for s in records}) == 1
 stars = {str(s.get('starLName') or s.get('star') or '') for s in records}
 direct_alias = len(local_aliases) > 1 and all(g & local_aliases for g in groups)
 if direct_alias:
  return 'explicit_alias_evidence', [
   'Every accepted catalogue identity has an EXACT normalized current/EX-name match in the saved local card.',
   'Supplier geo same key pair.' if same_geo else 'Supplier town keys differ: requires independent district/region reconciliation.',
   'Supplier category labels agree.' if len(stars) == 1 else 'Supplier category labels differ: retain category review.',
   'Alias evidence is not authority to collapse or rewrite accepted catalogue IDs.']
 return 'name_geo_variant_review', [
  'No complete exact current/EX-name bridge for every accepted identity; preserve all raw qualifiers.',
  'Same supplier geo keys are supporting evidence only.' if same_geo else 'Different supplier geo keys; no automatic conflict or equivalence inference.',
  'Missing independent property coordinates/aliases may still be resolved technically, not automatically assigned to owner manual queue.']

def build(current: dict, registry: dict) -> dict:
 rows = current['rows']; by_tv = {r['tv_hotel_id']: r for r in rows}
 need(len(rows) == len(by_tv) == 816, '816 distinct TV rows')
 need(sum(len(r['f4_candidates']) for r in rows) == 838, '838 F4 memberships')
 need(len({f for r in rows for f in r['f4_candidates']}) == 838, 'F4 collision')
 for row in rows:
  need(digest(row['operator_link'].encode()) == row['operator_link_sha256'], 'operator link hash')
  for i in row['identities']: need(i['recorded_evidence_hash_matches'] is True, 'CURRENT evidence hash flag')
 selected = [r for r in rows if r['anchor_class'] != 'single_accepted_catalog']
 need(len(selected) == 60, '56 multiple + 4 missing')
 old = {(i['supplier_namespace'], i['external_hotel_id']): i for i in registry['rows']}
 output = []
 for row in selected:
  tv = row['tv_hotel_id']; reasons = []; candidate = None
  if row['anchor_class'] == 'multiple_accepted_catalog': category, reasons = multi_class(row)
  elif tv == 474:
   category = 'candidate_occupied_requires_repair'
   candidate = {'namespace':'andromeda_catalog','id':'2000059367','current_local_target':475,
    'current_evidence_sha256':next(i['evidence_sha256'] for i in canonical(by_tv[475]) if i['external_hotel_id']=='2000059367')}
   reasons = ['Family3* source matches the distinct Family local card better than Palma4*, but its CURRENT accepted target is475.',
              'Do not append a duplicate or move the accepted row under a mass-accept authorization.']
  elif tv == 57656:
   category = 'saved_candidate_needs_current_validation'
   i = old[('andromeda_catalog','2000055481')]
   need(i['decision_status'] == 'pending' and i['local_hotel_id'] is None, 'historical Gosia state')
   need(digest(i['evidence_json'].encode()) == i['evidence_sha256'], 'Gosia evidence hash')
   candidate = {'namespace':'andromeda_catalog','id':i['external_hotel_id'], 'state_in_retained_snapshot':'pending',
    'evidence_sha256':i['evidence_sha256'], 'source_records':sources(json.loads(i['evidence_json'])),
    'rejected_distinct_variant':{'id':'2000073573','local_target':81379,'name':'Grand Gosia','category':'4*'}}
   reasons = ['Saved Gosia3* / Nha Trang vs CURRENT local GOSIA HOTEL NHA TRANG3*; GRAND must not be removed.',
    'Candidate state is from2026-09-21 snapshot, not a fresh read of the unbound source.',
    'Local snapshot is truncated100000/134476: not a global uniqueness proof. Need CURRENT source/target/manual/exclusion/geo/occupancy validation.']
  elif tv == 1299:
   category = 'no_equivalent_saved_candidate'
   candidate = {'rejected_distinct_variant':{'namespace':'andromeda_catalog','id':'2000035724','local_target':37372,'name':'Kleopatra Ada BEACH Hotel'}}
   reasons = ['Only saved near-name source includes BEACH and is already assigned to37372. Do not strip BEACH to match KLEOPATRA ADA HOTEL1299.']
  elif tv == 4307:
   category = 'no_equivalent_saved_candidate'
   reasons = ['No equivalent current/EX-name source found in retained registry. This does not prove absence from the full SAMO catalogue.',
    'Preserve BOTH original F4 codes. Do not pick one or repeat the consumed Tourvisor query.']
  else: raise ValueError('unexpected missing target')
  output.append({'tv_hotel_id':tv, 'classification':category, 'reasons':reasons,'candidate':candidate,
   'catalog_hotel':row['catalog_hotel'],'accepted_catalog_ids':row['accepted_catalog_ids'],
   'identities':row['identities'], 'manual':row['manual'], 'f4_candidates':row['f4_candidates'],
   'operator_link':row['operator_link'], 'operator_link_sha256':row['operator_link_sha256'],
   'search_id':row['search_id'], 'tour_id':row['tour_id'], 'batch':row['batch'],
   'safe_to_write_now':False, 'requires_supplier_replay':False})
 counts=dict(sorted(collections.Counter(r['classification'] for r in output).items()))
 return {'operation':OP,'state':'completed_offline_evidence','input_pins':INPUTS,
  'current_snapshot_at_utc':current['snapshot_at_utc'],'historical_registry_snapshot':'2026-09-21T15:00:25+00:00',
  'examined_hotels':len(output),'multiple_anchor_hotels':56,'missing_anchor_hotels':4,
  'counts':counts,'new_candidate_edges':1,'new_committed_mapping_rows':0,'new_committed_unique_hotels':0,
  'supplier_http_requests':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
  'scope_caveat':'60 targets from a specific816-hotel BG cohort; not global remaining hotels. All existing accepted/manual preserved.',
  'next_action':'CURRENT validate Gosia2000055481->TV57656; obtain exact repair authorization for accepted conflicts; resolve queued supplier access before any new BG GET.',
  'rows':output}

def self_test() -> None:
 need(norm('Grand Gosia') != norm('Gosia'), 'GRAND retained')
 need(norm('Kleopatra Ada Beach Hotel') != norm('Kleopatra Ada Hotel'), 'BEACH retained')
 for word in ('FAMILY','PREMIUM','ANNEX','GARDEN','RED','SUITE','DELUXE'):
  need(norm('Example '+word) != norm('Example'), word+' retained')
 need(aliases('New Hotel (EX. Old Hotel)') == {'new','old'}, 'explicit aliases')
 need(aliases('Grand Gosia') == {'grand gosia'}, 'no implicit shortening')
 need(sources({'prior_evidence':{'source':{'id':7}}}) == [{'id':7}], 'prior envelope')
 need(aliases('Gosia HOTEL NHA TRANG') != aliases('Gosia'), 'no global city-stripping')
 print('13 offline contract checks PASS')

if __name__ == '__main__':
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--self-test',action='store_true');p.add_argument('--input-dir',type=Path);p.add_argument('--output',type=Path)
 a=p.parse_args()
 if a.self_test: self_test()
 else:
  if a.input_dir is None or a.output is None:p.error('--input-dir and --output required')
  result=build(*read_inputs(a.input_dir));raw=(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
  with a.output.open('xb') as f:f.write(raw)
  print(json.dumps({'counts':result['counts'],'examined':60,'result_sha256':digest(raw),'bytes':len(raw),'mapping_writes':0},ensure_ascii=False))
