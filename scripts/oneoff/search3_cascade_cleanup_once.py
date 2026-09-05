"""One-time, pinned Search3 cascade cleanup; tinycss2 is audit-only."""
from pathlib import Path
from collections import defaultdict
import hashlib, json, re, subprocess
import tinycss2 as css

BASE = '03e7422eb3efa2cf4382e89bfdb610d9dadc7bd1'
ROOT = Path.cwd()
CONTRACT = ROOT / 'docs/project/search3-cascade-sections.json'
ASSET = ROOT / 'v2/search3-results-filters-v1.css'
SAFE = {'font-size', 'height', 'min-height', 'max-height', 'width', 'min-width', 'max-width',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'row-gap', 'column-gap'}
LENGTH = re.compile(r'(?:0|[0-9]+(?:\.[0-9]+)?px|\.[0-9]+px)\Z')
MARKER = re.compile(r'/\* donor:([^\s*]+) @ ([0-9a-f]{40}) \*/')


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def blob(raw):
    return hashlib.sha1(f'blob {len(raw)}\0'.encode() + raw).hexdigest()


def records(text):
    out, empty = [], []
    def visit(nodes, context=()):
        for node in nodes:
            if node.type in ('comment', 'whitespace'):
                continue
            if node.type == 'at-rule' and node.lower_at_keyword == 'media' and node.content is not None:
                children = css.parse_rule_list(node.content, skip_comments=True, skip_whitespace=True)
                if not children:
                    empty.append(node)
                visit(children, context + (css.serialize(node.prelude).strip(),))
            elif node.type == 'qualified-rule':
                selector = css.serialize(node.prelude).strip()
                declarations = css.parse_declaration_list(node.content, skip_comments=True, skip_whitespace=True)
                if not declarations:
                    empty.append(node)
                for decl in declarations:
                    if decl.type != 'declaration':
                        raise ValueError('Unexpected declaration node: ' + decl.type)
                    out.append((context, selector, decl.lower_name, css.serialize(decl.value).strip(), decl.important, decl))
            else:
                raise ValueError('Unsupported cascade node: ' + node.type)
    visit(css.parse_stylesheet(text, skip_comments=True, skip_whitespace=True))
    return out, empty


def last_stream(rows):
    seen, out = set(), []
    for row in reversed(rows):
        key = row[:3] + (row[4],)
        if key not in seen:
            seen.add(key)
            out.append(row[:5])
    return list(reversed(out))


def offset(text, node):
    lines = text.splitlines(keepends=True)
    return sum(map(len, lines[:node.source_line-1])) + node.source_column-1


def strip_empty(text):
    _, nodes = records(text)
    spans = []
    for node in nodes:
        start = offset(text, node)
        end = text.index('}', text.index('{', start)) + 1
        body = text[text.index('{', start)+1:end-1]
        assert not css.parse_component_value_list(body, skip_comments=True) or all(
            tok.type == 'whitespace' for tok in css.parse_component_value_list(body, skip_comments=True))
        spans.append((start, end))
    for start, end in sorted(spans, reverse=True):
        text = text[:start] + text[end:]
    return text, len(spans)


def stats(raw):
    return {'bytes': len(raw), 'lines': len(raw.splitlines()), 'sha256': sha(raw)}


def main():
    contract = json.loads(CONTRACT.read_text())
    source = ROOT / contract['source_root']
    names = contract['sections']
    before_parts = [(source / name).read_bytes() for name in names]
    before = b''.join(before_parts)
    assert len(before) == 78306 and blob(before) == '9b3583c4261dda23109b369595cb955aa473b7fb'
    original_asset = ASSET.read_bytes()
    assert sha(original_asset) == 'b94f15eaa963b5ccb7437553b1ac7ee9c10bd6dd9a20d038994287f2c702d202'
    assert original_asset.count(before) == 1
    text = before.decode('utf-8')
    baseline, empty_before = records(text)
    groups = defaultdict(list)
    for row in baseline:
        groups[row[:3] + (row[4],)].append(row)
    removed, spans = [], []
    for key, group in groups.items():
        winner = group[-1]
        if key[2] not in SAFE or not key[3] or not LENGTH.fullmatch(winner[3]):
            continue
        for row in group[:-1]:
            if not LENGTH.fullmatch(row[3]):
                continue
            decl = row[5]
            start = offset(text, decl)
            match = re.match(r'[^;{}]+;?', text[start:])
            assert match and match.group().split(':', 1)[0].strip() == row[2]
            spans.append((start, start + len(match.group())))
            removed.append({'media': list(row[0]), 'selector': row[1], 'property': row[2],
                            'old': row[3], 'line': decl.source_line, 'winner': winner[3],
                            'winner_line': winner[5].source_line})
    assert len(removed) == 127
    removed_starts = {start for start, _ in spans}
    expected_rows = [row[:5] for row in baseline if offset(text, row[5]) not in removed_starts]
    for start, end in sorted(spans, reverse=True):
        text = text[:start] + text[end:]
    after_rows, _ = records(text)
    assert [row[:5] for row in after_rows] == expected_rows, 'Surviving declarations changed'
    assert last_stream(after_rows) == last_stream(baseline), 'Winning declaration stream changed'
    markers = list(MARKER.finditer(text))
    assert [m.group(1) for m in markers] == names
    starts = [0] + [m.start()-2 for m in markers[1:]]
    ends = starts[1:] + [len(text)]
    obsolete = (
        '/* Footer should finish the page, not become another hero. */',
        '/* Footer should not dominate short preview states. */',
        '/* maket7 desktop ends with a slim utility footer, not the full marketing footer. */',
    )
    report_parts, empty_count = [], 0
    after_parts = []
    for name, start, end, old in zip(names, starts, ends, before_parts):
        part, count = strip_empty(text[start:end])
        empty_count += count
        for comment in obsolete:
            part = part.replace(comment, '')
        part = '\n\n' + re.sub(r'\n{3,}', '\n\n', part.strip('\n')) + '\n'
        new = part.encode('utf-8')
        assert new.endswith(b'\n') and not new.endswith(b'\n\n')
        (source / name).write_bytes(new)
        after_parts.append(new)
        report_parts.append({'name': name, 'before': stats(old), 'after': stats(new)})
    after = b''.join(after_parts)
    final_rows, empty_after = records(after.decode())
    assert not empty_after
    assert [row[:5] for row in final_rows] == [row[:5] for row in after_rows]
    assert last_stream(final_rows) == last_stream(baseline)
    contract['combined_bytes'] = len(after)
    contract['combined_git_blob_sha'] = blob(after)
    CONTRACT.write_text(json.dumps(contract, ensure_ascii=False, indent=2) + '\n')
    subprocess.run(['python3', 'scripts/build/search3_assets.py', '--write'], check=True)
    result_asset = ASSET.read_bytes()
    assert result_asset == original_asset.replace(before, after)
    report = {'baseline': BASE, 'scope': contract['source_root'], 'method':
              'Audit-only tinycss2. Remove only earlier !important numeric-px longhands with the same literal selector, media stack and property as a later valid numeric-px declaration. No shorthand, variable, fallback keyword, selector, media or priority rewrites. Preserve all surviving declaration order; compare exact last-occurrence declaration streams. Remove parsed empty blocks, three orphan footer comments and surplus blank lines. No project dependency.',
              'parser_version': css.__version__, 'declarations_before': len(baseline),
              'declarations_after': len(final_rows), 'removed_declarations': len(removed),
              'removed_empty_blocks': empty_count, 'empty_blocks_before': len(empty_before),
              'cascade_before': stats(before), 'cascade_after': stats(after),
              'asset_before': stats(original_asset), 'asset_after': stats(result_asset),
              'winning_stream_sha256': sha(json.dumps(last_stream(baseline), ensure_ascii=False).encode()),
              'sections': report_parts, 'removed': sorted(removed, key=lambda x:(x['line'], x['property']))}
    (ROOT / 'docs/project/search3-cascade-cleanup.json').write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    test = ROOT / 'tests/search3_production_presentation_test.py'
    content = test.read_text()
    assert 'import re\n' not in content
    content = content.replace('import json\n', 'import json\nimport re\n', 1)
    anchor = '    def test_cascade_module_eof_contract(self):\n'
    added = '''    def test_cascade_has_no_empty_media_rules(self):
        contract = json.loads((ROOT / 'docs/project/search3-cascade-sections.json').read_text())
        for name in contract['sections']:
            with self.subTest(name=name):
                source = (ROOT / contract['source_root'] / name).read_text()
                # Strip complete CSS comments without crossing their closing delimiter.
                source = re.sub(r'/\\*[^*]*\\*+(?:[^/*][^*]*\\*+)*/', '', source)
                self.assertNotRegex(source, r'(?m)^[ \\t]*@media[^{};]+\\{\\s*\\}')

'''
    assert content.count(anchor) == 1
    test.write_text(content.replace(anchor, added + anchor))
    readme = source / 'README.md'
    readme.write_text(readme.read_text() + '''\n## Numeric-longhand cleanup after the byte-identical split\n\nThe 78,306-byte blob above is the historical split baseline at `03e7422e`,\nnot the current cleaned size. The current combined bytes/hash are owned by\n`docs/project/search3-cascade-sections.json`.\n`docs/project/search3-cascade-cleanup.json` records every removed declaration\nand its later same-selector/media/importance winner. Only basic numeric-px\nlonghands were pruned; shorthands, variables, fallback keywords and different\nmedia contexts were not merged. Surviving declarations retain their exact order.\nThe public bundle was rebuilt atomically with its import hash. This pass does\nnot change the JS assets, protected contracts or deployment permissions.\n''')
    print(json.dumps({k:v for k,v in report.items() if k not in ('removed','sections')},ensure_ascii=False,indent=2))


if __name__ == '__main__':
    main()
