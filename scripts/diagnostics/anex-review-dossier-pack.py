#!/usr/bin/env python3
"""Pack already saved observed triage; no network, supplier calls or state mutation."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import sys

MAX_BYTES = 8_000_000
MAX_ROWS = 1000

def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'), allow_nan=False)

def digest(raw):
    return hashlib.sha256(raw).hexdigest()

def no_duplicates(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError('duplicate_json_key')
        result[key] = value
    return result

def pack(raw, expected_sha, artifact_id):
    if len(raw) > MAX_BYTES or not re.fullmatch('[0-9a-f]{64}', expected_sha) or digest(raw) != expected_sha:
        raise ValueError('source_digest_or_bound')
    if type(artifact_id) is not int or not 0 < artifact_id <= 9_000_000_000_000_000:
        raise ValueError('artifact_identity')
    data = json.loads(raw, object_pairs_hook=no_duplicates)
    if (data.get('schema_version') != 1 or data.get('scope') != 'preview'
            or data.get('kind') != 'observed_review_dossiers'
            or not re.fullmatch('[0-9a-f]{40}', data.get('source_sha', ''))
            or not re.fullmatch('[0-9a-f]{64}', data.get('checkpoint_sha256', ''))):
        raise ValueError('source_contract')
    rows = data.get('rows')
    if not isinstance(rows, list) or len(rows) > MAX_ROWS or data.get('summary', {}).get('count') != len(rows):
        raise ValueError('source_count')
    packed, seen = [], set()
    for row in rows:
        identifier = row.get('anex_hotel_id')
        evidence = row.get('evidence', {})
        if (type(identifier) is not int or not 0 < identifier < 100_000_000 or identifier in seen
                or row.get('automatic_acceptance') is not False or row.get('automatic_retry') is not False
                or row.get('status') not in ('review', 'source_error', 'unmatched', 'protected')
                or evidence.get('external_id') != identifier or evidence.get('status') != row['status']
                or row.get('observation', {}).get('anex_hotel_id') != identifier
                or row.get('evidence_origin') not in ('live_checkpoint', 'legacy_checkpoint')):
            raise ValueError('row_contract')
        seen.add(identifier)
        evidence_json = canonical(evidence)
        evidence_sha = digest(evidence_json.encode())
        if evidence_sha != row.get('evidence_row_sha256'):
            raise ValueError('evidence_digest')
        row_json = canonical(row)
        if len(row_json.encode()) > 1_000_000:
            raise ValueError('row_bound')
        packed.append({'id': identifier, 'row_json': row_json, 'row_digest': digest(row_json.encode()),
                       'evidence_json': evidence_json, 'evidence_digest': evidence_sha})
    result = {'protocol_version': 1, 'kind': 'observed_dossier_import', 'scope': 'preview',
              'artifact_id': artifact_id, 'source_sha': data['source_sha'], 'source_digest': expected_sha,
              'checkpoint_digest': data['checkpoint_sha256'], 'rows': packed}
    if len(canonical(result).encode()) > 20_000_000:
        raise ValueError('envelope_bound')
    return result

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('triage', type=Path)
    parser.add_argument('--sha256', required=True)
    parser.add_argument('--artifact-id', required=True, type=int)
    args = parser.parse_args()
    # Bound reads before parsing. stdout is for the approved DB-helper transport.
    with args.triage.open('rb') as stream:
        raw = stream.read(MAX_BYTES + 1)
    print(canonical(pack(raw, args.sha256, args.artifact_id)))
