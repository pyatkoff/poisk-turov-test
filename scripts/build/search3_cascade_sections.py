#!/usr/bin/env python3
"""Inspect Search3 cascade compatibility CSS as stable physical donor modules."""
import argparse
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
CONTRACT = ROOT / 'docs/project/search3-cascade-sections.json'
MARKER = re.compile(rb'/\* donor:([^\s*]+) @ ([0-9a-f]{40}) \*/')


def git_blob_sha(raw):
    header = f'blob {len(raw)}\0'.encode()
    return hashlib.sha1(header + raw).hexdigest()


def inspect_sections():
    contract = json.loads(CONTRACT.read_text())
    if contract.get('schema_version') != 2:
        raise ValueError('Unsupported cascade section contract')

    source_root_value = contract.get('source_root')
    if source_root_value != 'src/search3/styles/cascade':
        raise ValueError('Unexpected cascade source root')
    source_root = ROOT / source_root_value

    names = contract.get('sections') or []
    if not names:
        raise ValueError('Cascade section contract is empty')

    expected_donor = contract.get('donor_source_sha')
    rows = []
    chunks = []
    byte_cursor = 0
    line_cursor = 1
    for name in names:
        path = source_root / name
        segment = path.read_bytes()
        matches = list(MARKER.finditer(segment))
        if len(matches) != 1:
            raise ValueError(f'Expected one donor marker in {name}; found {len(matches)}')
        match = matches[0]
        if segment[:match.start()].strip():
            raise ValueError(f'Unexpected CSS before donor marker in {name}')
        marker_name = match.group(1).decode()
        donor_sha = match.group(2).decode()
        if marker_name != name:
            raise ValueError(f'Donor marker mismatch in {name}: {marker_name}')
        if expected_donor and donor_sha != expected_donor:
            raise ValueError(f'Unexpected donor SHA for {name}: {donor_sha}')

        line_count = segment.count(b'\n') + (0 if segment.endswith(b'\n') else 1)
        rows.append({
            'name': name,
            'donorSha': donor_sha,
            'startByte': byte_cursor,
            'endByte': byte_cursor + len(segment),
            'bytes': len(segment),
            'startLine': line_cursor,
            'endLine': line_cursor + line_count - 1,
            'sha256': hashlib.sha256(segment).hexdigest(),
            'gitBlobSha': git_blob_sha(segment),
        })
        chunks.append(segment)
        byte_cursor += len(segment)
        line_cursor += line_count

    raw = b''.join(chunks)
    expected_bytes = contract.get('combined_bytes')
    if expected_bytes is not None and len(raw) != expected_bytes:
        raise ValueError(f'Cascade combined byte count changed: expected {expected_bytes}; actual {len(raw)}')
    expected_blob = contract.get('combined_git_blob_sha')
    actual_blob = git_blob_sha(raw)
    if expected_blob and actual_blob != expected_blob:
        raise ValueError(f'Cascade combined blob changed: expected {expected_blob}; actual {actual_blob}')

    return source_root, raw, rows


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Validate the checked-in physical section contract')
    parser.add_argument('--json', action='store_true', help='Print the current section map as JSON')
    args = parser.parse_args()

    source_root, raw, rows = inspect_sections()
    if args.json:
        print(json.dumps({
            'sourceRoot': str(source_root.relative_to(ROOT)),
            'bytes': len(raw),
            'gitBlobSha': git_blob_sha(raw),
            'sections': rows,
        }, ensure_ascii=False, indent=2))
        return

    largest = max(rows, key=lambda row: row['bytes'])
    print(
        'SEARCH3_CASCADE_SECTIONS_OK '
        f'sections={len(rows)} bytes={len(raw)} '
        f'blob={git_blob_sha(raw)} '
        f'largest={largest["name"]}:{largest["bytes"]}'
    )


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError, json.JSONDecodeError) as error:
        raise SystemExit(str(error))
