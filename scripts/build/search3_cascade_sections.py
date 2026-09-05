#!/usr/bin/env python3
"""Inspect Search3 cascade compatibility CSS as stable donor sections."""
import argparse
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'src/search3/styles/cascade-compatibility.css'
CONTRACT = ROOT / 'docs/project/search3-cascade-sections.json'
MARKER = re.compile(rb'/\* donor:([^\s*]+) @ ([0-9a-f]{40}) \*/')


def inspect_sections():
    raw = SOURCE.read_bytes()
    contract = json.loads(CONTRACT.read_text())
    if contract.get('schema_version') != 1:
        raise ValueError('Unsupported cascade section contract')
    if contract.get('source') != 'src/search3/styles/cascade-compatibility.css':
        raise ValueError('Unexpected cascade source path')

    matches = list(MARKER.finditer(raw))
    if not matches:
        raise ValueError('No donor section markers found')
    if raw[:matches[0].start()].strip():
        raise ValueError('Unexpected CSS before first donor section marker')

    names = [match.group(1).decode() for match in matches]
    if names != contract.get('sections'):
        raise ValueError(
            'Cascade donor order changed: expected '
            + ', '.join(contract.get('sections', []))
            + '; actual '
            + ', '.join(names)
        )

    expected_donor = contract.get('donor_source_sha')
    rows = []
    for index, match in enumerate(matches):
        donor_sha = match.group(2).decode()
        if expected_donor and donor_sha != expected_donor:
            raise ValueError(f'Unexpected donor SHA for {names[index]}: {donor_sha}')
        start = match.start()
        end = matches[index + 1].start() if index + 1 < len(matches) else len(raw)
        segment = raw[start:end]
        start_line = raw.count(b'\n', 0, start) + 1
        end_line = raw.count(b'\n', 0, end) + (0 if end and raw[end - 1:end] == b'\n' else 1)
        rows.append({
            'name': names[index],
            'donorSha': donor_sha,
            'startByte': start,
            'endByte': end,
            'bytes': len(segment),
            'startLine': start_line,
            'endLine': end_line,
            'sha256': hashlib.sha256(segment).hexdigest(),
        })

    if sum(row['bytes'] for row in rows) != len(raw) - matches[0].start():
        raise ValueError('Cascade section byte accounting mismatch')

    return raw, rows


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Validate the checked-in section contract')
    parser.add_argument('--json', action='store_true', help='Print the current section map as JSON')
    args = parser.parse_args()

    raw, rows = inspect_sections()
    if args.json:
        print(json.dumps({
            'source': str(SOURCE.relative_to(ROOT)),
            'bytes': len(raw),
            'sections': rows,
        }, ensure_ascii=False, indent=2))
        return

    largest = max(rows, key=lambda row: row['bytes'])
    print(
        'SEARCH3_CASCADE_SECTIONS_OK '
        f'sections={len(rows)} bytes={len(raw)} '
        f'largest={largest["name"]}:{largest["bytes"]}'
    )


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError, json.JSONDecodeError) as error:
        raise SystemExit(str(error))
