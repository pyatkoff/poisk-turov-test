#!/usr/bin/env python3
"""Build Search3's existing public assets from ordered, private source modules."""
import argparse
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def assemble(root):
    source = root / 'src/search3'
    manifest = json.loads((source / 'manifest.json').read_text())
    reviewed_path = root / 'docs/project/search3-production-import.json'
    reviewed = json.loads(reviewed_path.read_text())
    if manifest.get('schema_version') != 1:
        raise ValueError('Unsupported Search3 source manifest')
    if set(manifest['assets']) != set(reviewed['assets']):
        raise ValueError('Source outputs must match the eight reviewed public assets')
    outputs, used = {}, set()
    for name, parts in manifest['assets'].items():
        if Path(name).name != name or not name.startswith('search3-') or not parts:
            raise ValueError('Invalid Search3 output: ' + name)
        chunks = []
        for part in parts:
            path = source / part
            if (not path.resolve().is_relative_to(source.resolve())
                    or path.suffix != Path(name).suffix or part in used):
                raise ValueError('Invalid or repeated Search3 source: ' + part)
            used.add(part)
            chunks.append(path.read_bytes())
        outputs[name] = b''.join(chunks)
    actual = {str(p.relative_to(source)) for p in source.rglob('*')
              if p.is_file() and p.suffix in ('.css', '.js')}
    if used != actual:
        raise ValueError('Unlisted Search3 modules: ' + ', '.join(sorted(actual - used)))
    return outputs, reviewed_path, reviewed


def build(root=ROOT, write=False):
    # Read and validate every module before touching any generated file.
    outputs, reviewed_path, reviewed = assemble(root)
    stale = []
    for name, content in outputs.items():
        output = root / 'v2' / name
        digest = hashlib.sha256(content).hexdigest()
        if not output.exists() or output.read_bytes() != content:
            stale.append(name)
        if reviewed['assets'][name]['productionSha256'] != digest:
            stale.append(name + ' (review manifest)')
    if not write:
        if stale:
            raise ValueError('Generated assets differ; run python3 scripts/build/search3_assets.py --write: '
                             + ', '.join(stale))
        return len(outputs)
    for name, content in outputs.items():
        output = root / 'v2' / name
        if not output.exists() or output.read_bytes() != content:
            output.write_bytes(content)
        reviewed['assets'][name]['productionSha256'] = hashlib.sha256(content).hexdigest()
    # Do not rewrite unrelated metadata or any protected-runtime fingerprint.
    rendered = json.dumps(reviewed, ensure_ascii=False, indent=2) + '\n'
    if reviewed_path.read_text() != rendered:
        reviewed_path.write_text(rendered)
    return len(outputs)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--check', action='store_true', help='Verify checked-in output (default)')
    mode.add_argument('--write', action='store_true', help='Rebuild public assets and their hashes')
    args = parser.parse_args()
    try:
        count = build(write=args.write)
    except (ValueError, OSError, KeyError) as error:
        parser.exit(1, str(error) + '\n')
    print(f'SEARCH3_SOURCE_BUILD_OK assets={count} mode={"write" if args.write else "check"}')
