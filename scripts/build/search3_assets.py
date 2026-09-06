#!/usr/bin/env python3
"""Build Search3's existing public assets from ordered, private source modules."""
import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
JS_INCLUDE = re.compile(rb'(?m)^[ \t]*/\* @include ([a-zA-Z0-9_./-]+\.js) \*/\r?\n')
CSS_STRING = re.compile(rb'/\* @css-string ([a-zA-Z0-9_./-]+\.css) \*/ ""')


def compact_javascript(outputs):
    """Print all JS with pinned build-only tools and an independent syntax guard."""
    scripts = {name: content.decode('utf-8') for name, content in outputs.items()
               if name.endswith('.js')}
    result = subprocess.run(
        ['node', str(ROOT / 'scripts/build/search3-js/compact.cjs')],
        input=json.dumps(scripts), text=True, capture_output=True, timeout=60)
    if result.returncode:
        hint = ('Install pinned JS build tools with npm ci --prefix '
                'scripts/build/search3-js --ignore-scripts.\n'
                if 'Cannot find module' in result.stderr else '')
        raise ValueError(hint + result.stderr)
    compacted = json.loads(result.stdout)
    if set(compacted) != set(scripts) or any(not isinstance(value, str) for value in compacted.values()):
        raise ValueError('Invalid Search3 JS compaction output')
    return {name: compacted[name].encode('utf-8') if name in compacted else content
            for name, content in outputs.items()}


def compact_css_comments(content, trim_indentation=False):
    """Keep source notes private; retain CSS token separators and license notes.

    Empty comments preserve tokenization even in ``red/**/blue``. Strings and
    escaped characters are copied verbatim. Optional indentation trimming retains
    the preceding newline separator and all whitespace inside strings/comments.
    """
    text = content.decode('utf-8')
    chunks, index, quote = [], 0, None
    while index < len(text):
        char = text[index]
        if char == '\\':
            end = index + 1
            while end < min(index + 7, len(text)) and text[end] in '0123456789abcdefABCDEF':
                end += 1
            if end == index + 1:
                end = min(index + 2, len(text))
            elif end < len(text) and text[end] in ' \t\r\n\f':
                end += 1
            if text[end - 1:end + 1] == '\r\n':
                end += 1
            chunks.append(text[index:end])
            index = end
            continue
        if quote:
            chunks.append(char)
            if char == quote:
                quote = None
            index += 1
            continue
        if char in ('"', "'"):
            quote = char
        elif text.startswith('/*', index):
            end = text.find('*/', index + 2)
            if end < 0:
                raise ValueError('Unterminated Search3 CSS comment')
            comment = text[index:end + 2]
            retain = (comment.startswith('/*!') or any(
                marker in comment.lower() for marker in ('@license', 'copyright', 'sourcemappingurl')))
            chunks.append(comment if retain else '/**/')
            index = end + 2
            continue
        chunks.append(char)
        index += 1
        if trim_indentation and not quote and char in '\r\n\f':
            while index < len(text) and text[index] in ' \t':
                index += 1
    return ''.join(chunks).encode('utf-8')


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
    def read_part(part, suffix):
        path = source / part
        if (not path.resolve().is_relative_to(source.resolve())
                or path.suffix != suffix or part in used):
            raise ValueError('Invalid or repeated Search3 source: ' + part)
        used.add(part)
        content = path.read_bytes()
        if suffix == '.js':
            # Private source composition: no new scope, global, request or runtime loader.
            content = JS_INCLUDE.sub(lambda match: read_part(match[1].decode(), suffix), content)
            def css_string(match):
                css = read_part(match[1].decode(), '.css')
                css = compact_css_comments(css, trim_indentation=True).decode('utf-8')
                return json.dumps(css, ensure_ascii=True).encode('ascii')
            content = CSS_STRING.sub(css_string, content)
        return content

    for name, parts in manifest['assets'].items():
        if Path(name).name != name or not name.startswith('search3-') or not parts:
            raise ValueError('Invalid Search3 output: ' + name)
        chunks = [read_part(part, Path(name).suffix) for part in parts]
        content = b''.join(chunks)
        outputs[name] = compact_css_comments(content, trim_indentation=True) if name.endswith('.css') else content
    actual = {str(p.relative_to(source)) for p in source.rglob('*')
              if p.is_file() and p.suffix in ('.css', '.js')}
    if used != actual:
        raise ValueError('Unlisted Search3 modules: ' + ', '.join(sorted(actual - used)))
    return compact_javascript(outputs), reviewed_path, reviewed


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
