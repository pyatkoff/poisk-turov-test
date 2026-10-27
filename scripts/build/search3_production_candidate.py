#!/usr/bin/env python3
"""Prepare an offline Search3 frontend candidate; never deploy or activate it.

The existing NEXT source owns the UI. This bounded transform retains its live
script order and cold owners, while giving the generated production entry its
own route. Backend endpoints, server configuration and the live predecessor are
external dependencies, not files in this artifact.
"""
from __future__ import annotations

import argparse
import gzip
import hashlib
from html.parser import HTMLParser
import importlib.util
import io
import json
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import sys
import tarfile
import tempfile
from urllib.parse import urlsplit


_snapshot_path = Path(__file__).resolve().parents[1] / 'deploy/search3_production_snapshot.py'
_spec = importlib.util.spec_from_file_location('search3_candidate_snapshot', _snapshot_path)
_snapshot = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_snapshot)

STATE = 'PREPARED_NOT_ACTIVATED'
ROUTE = '/poisk-turov/'
CANONICAL = 'https://anytoour.ru' + ROUTE
ARCHIVE = 'search3-production-candidate.tar.gz'
LIVE = (
    '../prototype-search/config.js', '../runtime-v3.js', '../lead-search-context.js',
    './lead-runtime-v1.js', '../search3-canonical-profiles-v1.js',
    '../search3-local-db-provider-v1.js', '../prototype-search/data.js',
    '../prototype-search/calendar-exact-hotel-v1.js', '../prototype-search/source-receipt-v1.js',
    '../prototype-search/search-lifecycle-v1.js', '../prototype-search/lead.js',
    '../prototype-search/hotel-popularity-v1.js', './live-bridge.js', './flight-picker-v18.js',
    '../prototype-search/messenger-context-v1.js', '../prototype-search/messenger-flow-v1.js',
    './filter-panel-v1.js', './app.js',
)
COLD = ('./offer-list-v1.js', './hotel-details-v1.js', './flight-picker-ui-v1.js')
PRODUCTION_ONLY = ('./production-query-v1.js',)
PRODUCTION_GRAPH = LIVE[:-1] + PRODUCTION_ONLY + LIVE[-1:]
STYLES = ('./styles.css', './mobile-controls-v1.css')
OFFLINE = ('./fixture-data.js', './local-db-parser.js', './recorded-data.js',
           '../prototype-search/search-lifecycle-v1.js', './flight-picker-v18.js',
           './preview-lead.js', './filter-panel-v1.js', './app.js')
ASSET_SUFFIXES = {'.svg', '.png', '.jpg', '.jpeg', '.webp', '.gif', '.woff', '.woff2', '.ttf', '.txt'}
API = '/api-v2.php'
INT_API = '/_preview/search3-anex-candidate/api-anex-search3-preview.php'
DEPENDENCIES = (
    ('public_api', API),
    ('private_tv_anex_and_messenger_handoff', INT_API),
    ('existing_next_hotel_content', '/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php'),
    ('departure_catalogue', '/data/departures-v1.php'),
    ('local_destination_catalogue', '/_preview/search3-local-candidate/data/search3-destination-read-v1.php'),
    ('local_calendar', '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'),
    ('local_canonical_catalogue', '/_preview/search3-local-candidate/data/hotel-details-read-v1.php'),
    ('hotel_suggestions', '/data/hotel-search-v1.php'),
)


class CandidateError(ValueError):
    pass


def need(condition, message):
    if not condition:
        raise CandidateError(message)


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def encoded(value) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()


def relative(value: str) -> str:
    need(isinstance(value, str) and re.fullmatch(r'[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*', value), 'unsafe_relative_path')
    need(all(part not in ('.', '..') for part in value.split('/')), 'unsafe_relative_path')
    return _snapshot.safe_relative(value)


def source_path(root: Path, value: str) -> Path:
    try:
        return _snapshot.reject_symlink_components(root, relative(value))
    except _snapshot.SnapshotError as exc:
        raise CandidateError(str(exc)) from exc


def script_path(ref: str) -> tuple[str, str, str]:
    need(bool(re.fullmatch(r'\.{1,2}/[A-Za-z0-9_-]+(?:/[A-Za-z0-9_.-]+)*\.js', ref)), 'unsafe_script_reference')
    if ref.startswith('../'):
        tail = relative(ref[3:])
        return 'v2/' + tail, 'search3-runtime/' + tail, '../search3-runtime/' + tail
    tail = relative(ref[2:])
    return 'v2/visual-search/' + tail, 'poisk-turov/' + tail, './' + tail


class References(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.scripts = []
        self.styles = []
        self.cold = []
        self.resources = []
        self.base = False

    def handle_starttag(self, tag, attrs):
        keys = [key for key, _ in attrs]
        need(len(keys) == len(set(keys)), 'duplicate_html_attribute')
        values = dict(attrs)
        need(not any(key.startswith('on') for key in keys), 'inline_event_attribute')
        if tag == 'base':
            self.base = True
        if tag == 'script':
            need(values.get('src') and 'defer' in values, 'unknown_script_shape')
            self.scripts.append(values['src'])
        if tag == 'link' and values.get('rel') == 'stylesheet':
            self.styles.append(values.get('href'))
        for key, value in attrs:
            if key in ('data-offer-list-src', 'data-hotel-details-src', 'data-flight-picker-src'):
                self.cold.append(value)
                self.resources.append(value)
            elif key == 'src' or key == 'href' and tag == 'link' and values.get('rel') != 'canonical':
                self.resources.append(value)


def substitute_once(pattern, replacement, text, label, *, flags=0):
    result, count = re.subn(pattern, replacement, text, flags=flags)
    need(count == 1, 'unknown_source_shape:' + label)
    return result


def graphs(php: str, html: str) -> tuple[str, ...]:
    matches = re.findall(r'\$scripts\s*=\s*\[([^]]*)\];', php, re.S)
    need(len(matches) == 1, 'unknown_live_graph')
    body = matches[0]
    paths = tuple(re.findall(r"'([^']+)'", body))
    need(re.sub(r"'[^']+'|[\s,]", '', body) == '', 'unknown_live_graph_expression')
    for ref in paths:
        script_path(ref)
    need(paths == LIVE, 'unreviewed_live_graph')
    refs = References()
    refs.feed(html)
    need(not refs.base and tuple(refs.cold) == COLD and tuple(refs.styles) == STYLES, 'unreviewed_cold_or_style_graph')
    need(tuple(refs.scripts) == OFFLINE, 'unknown_offline_graph_boundary')
    return paths


def entry_html(html: str, files: dict[str, bytes]) -> str:
    graph = []
    for ref in PRODUCTION_GRAPH:
        _, target, public = script_path(ref)
        attrs = ''
        if ref == '../prototype-search/config.js':
            attrs = ' data-local-tv-catalog="enabled" data-search3-messenger="enabled" data-search3-tv-session="enabled" data-search3-production="enabled"'
        graph.append('<script src="' + public + '?v=' + digest(files[target])[:12] + '"' + attrs + ' defer></script>')
    html = substitute_once(r'<script src="\./fixture-data\.js" defer></script>.*?<script src="\./app\.js" defer></script>', '\n  '.join(graph), html, 'offline_scripts', flags=re.S)
    html = substitute_once(r'<meta name="robots" content="noindex, nofollow, noarchive">', '', html, 'robots')
    html = substitute_once(r'<meta name="description" content="[^"]*">', '<meta name="description" content="Поиск туров AnyTour: выберите направление, даты и отель, затем передайте выбранный тур менеджеру в Telegram или MAX.">\n  <link rel="canonical" href="' + CANONICAL + '">', html, 'description')
    html = substitute_once(r'<details class="prototype-tools wrap">.*?</details>', '', html, 'prototype_tools', flags=re.S)
    html = substitute_once(r'<span class="private-badge"><span class="status-dot"></span>[^<]*</span>', '', html, 'preview_badge')
    html = substitute_once(r'<span>NEXT · макет [^<]*</span>', '', html, 'footer_version')
    html = substitute_once(r'Сохранённая выдача и демонстрационные сценарии\. Живой поиск ещё не подключён\. Заявки и оплата отключены\.', 'Актуальные предложения — Tourvisor и прямой ANEX. Календарь показывает цены прошлых поисков. Выбранный тур можно передать менеджеру в Telegram или MAX. Бронирование и оплата на сайте не выполняются.', html, 'search_disclosure')
    # The source stylesheet graph and cold modules remain beside the entry.
    def version(match):
        ref = match[2]
        target = 'poisk-turov/' + relative(ref[2:])
        need(target in files, 'missing_entry_resource:' + target)
        return match[1] + '="' + ref + '?v=' + digest(files[target])[:12] + '"'
    html = re.sub(r'\b(href|src|data-offer-list-src|data-hotel-details-src|data-flight-picker-src)="(\./[^"?]+\.(?:js|css|png|svg))"', version, html)
    refs = References()
    refs.feed(html)
    need(not refs.base and len(refs.scripts) == len(PRODUCTION_GRAPH), 'generated_graph_changed')
    need(html.count('<link rel="canonical"') == 1, 'duplicate_canonical')
    need(not any(marker in html for marker in ('fixture-data.js', 'recorded-data.js', 'preview-lead.js', 'fixture-scenario', 'prototype-tools', 'prototype-version', 'noindex', 'NEXT ·', '<?')), 'offline_or_preview_entry_retained')
    for ref in refs.resources:
        parsed = urlsplit(ref)
        need(not parsed.scheme and not parsed.netloc and not parsed.fragment, 'nonlocal_entry_resource')
        path = parsed.path
        target = 'search3-runtime/' + relative(path[len('../search3-runtime/'):]) if path.startswith('../search3-runtime/') else 'poisk-turov/' + relative(path[2:]) if path.startswith('./') else ''
        need(target in files, 'unresolved_entry_resource:' + path)
    return html


def generated_entry(html: str) -> bytes:
    guard = """<?php
// Offline-prepared candidate. Installation requires its separately reviewed cutover.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$scenarios = [];
$scenarioArray = false;
// Inspect the raw query before PHP's last-value/array normalization. The browser
// uses URLSearchParams.getAll(), so duplicate keys must never enable this entry.
foreach (explode('&', (string)($_SERVER['QUERY_STRING'] ?? '')) as $part) {
    $pair = explode('=', $part, 2);
    $key = urldecode($pair[0]);
    if ($key === 'scenario') $scenarios[] = urldecode($pair[1] ?? '');
    elseif (str_starts_with($key, 'scenario[')) $scenarioArray = true;
}
$scenarioAllowed = !$scenarioArray && (count($scenarios) === 0
    ? !array_key_exists('scenario', $_GET)
    : count($scenarios) === 1 && $scenarios[0] === 'live' && ($_GET['scenario'] ?? null) === 'live');
if (!in_array((string)($_SERVER['SCRIPT_NAME'] ?? ''), ['/poisk-turov/', '/poisk-turov/index.php'], true)
    || !$scenarioAllowed) {
    http_response_code(403);
    exit('Search3 production entry is unavailable for this route or scenario.');
}
?>
"""
    return (guard + html).encode()


def archive_bytes(payload: Path, control: Path) -> bytes:
    buffer = io.BytesIO()
    with gzip.GzipFile(fileobj=buffer, mode='wb', filename='', mtime=0) as compressed:
        with tarfile.open(fileobj=compressed, mode='w', format=tarfile.USTAR_FORMAT) as archive:
            for prefix, root in (('control', control), ('payload', payload)):
                for path in sorted(root.rglob('*')):
                    need(not path.is_symlink(), 'archive_symlink')
                    if not path.is_file():
                        continue
                    data = path.read_bytes()
                    entry = tarfile.TarInfo(prefix + '/' + path.relative_to(root).as_posix())
                    entry.size = len(data)
                    entry.mode = 0o644
                    entry.uid = entry.gid = entry.mtime = 0
                    entry.uname = entry.gname = ''
                    archive.addfile(entry, io.BytesIO(data))
    return buffer.getvalue()


def build(source_root: Path, output: Path, source_sha: str, source_tree: str | None = None) -> dict:
    need(bool(re.fullmatch('[0-9a-f]{40}', source_sha)), 'invalid_source_sha')
    need(source_tree is None or bool(re.fullmatch('[0-9a-f]{40}', source_tree)), 'invalid_source_tree')
    need(source_root.is_dir() and not source_root.is_symlink(), 'unsafe_source_root')
    source_root = source_root.resolve()
    output = output.absolute()
    need(not output.exists() and not output.is_symlink(), 'output_must_not_exist')
    need(output.parent.is_dir() and output.parent.resolve() == output.parent, 'unsafe_output_parent')
    need(not _snapshot.within(output, source_root / 'v2'), 'output_inside_source_assets')
    inputs = {}
    def read(name):
        path = source_path(source_root, name)
        data = path.read_bytes()
        inputs[name] = {'sha256': digest(data), 'size': len(data), 'mode': f'{stat.S_IMODE(path.stat().st_mode):04o}'}
        return data
    php = read('v2/visual-search/index.php').decode()
    html = read('v2/visual-search/index.html').decode()
    graphs(php, html)
    files = {}
    origins = {}
    for ref in LIVE + PRODUCTION_ONLY + COLD:
        source, target, _ = script_path(ref)
        need(target not in files, 'duplicate_payload_target')
        files[target] = read(source)
        origins[target] = source
    for ref in STYLES:
        target = 'poisk-turov/' + ref[2:]
        source = 'v2/visual-search/' + ref[2:]
        files[target] = read(source)
        origins[target] = source
    assets = source_root / 'v2/visual-search/assets'
    need(assets.is_dir() and not assets.is_symlink(), 'missing_visual_assets')
    for path in sorted(assets.rglob('*')):
        need(not path.is_symlink(), 'asset_symlink')
        if path.is_dir():
            continue
        need(path.is_file() and path.suffix.lower() in ASSET_SUFFIXES, 'unreviewed_visual_asset')
        source = path.relative_to(source_root).as_posix()
        target = 'poisk-turov/assets/' + relative(path.relative_to(assets).as_posix())
        files[target] = read(source)
        origins[target] = source
    config = files['search3-runtime/prototype-search/config.js'].decode()
    need(re.search(r"\bapi:'" + re.escape(API) + "'", config) and re.search(r"\banexApi:'" + re.escape(INT_API) + "'", config), 'unreviewed_api_endpoint')
    need('search3Production' in config and 'search3PhoneLeadEnabled=false' in config, 'production_runtime_policy_missing')
    for ref in STYLES:
        css = files['poisk-turov/' + ref[2:]].decode()
        for raw in re.findall(r'url\(([^)]*)\)', css):
            asset = raw.strip().strip('\"\'')
            if asset.startswith('data:'):
                continue
            need(asset.startswith('./assets/') and '?' not in asset and '#' not in asset, 'unreviewed_css_resource')
            need('poisk-turov/' + relative(asset[2:]) in files, 'missing_css_resource')
    html = entry_html(html, files)
    files['poisk-turov/index.php'] = generated_entry(html)
    manifest = {
        'schema_version': 1, 'state': STATE, 'activation_performed': False,
        'source_sha': source_sha, 'source_tree_sha': source_tree,
        'source_identity': 'caller_supplied_local_candidate; input hashes bind actual bytes',
        'route': ROUTE, 'canonical': CANONICAL,
        'script_order': [script_path(ref)[2] for ref in PRODUCTION_GRAPH],
        'production_only_scripts': list(PRODUCTION_ONLY),
        'cold_owners': list(COLD), 'stylesheets': list(STYLES), 'inputs': inputs,
        'external_dependencies': [{'role': role, 'path': path, 'same_origin': True, 'action': 'retain_existing; not included or modified'} for role, path in DEPENDENCIES],
        'phone_policy': 'Telegram/MAX or ordinary contact link; no production phone submission',
        'unverified': ['live predecessor', 'server configuration', 'backend deployment', 'current prices', 'TG/MAX delivery', 'production approval', 'production activation'],
        'rollback': 'existing scripts/deploy/search3_production_snapshot.py; server-local snapshot and restore drill remain separate',
        'file_count': len(files),
        'files': [{'path': path, 'sha256': digest(data), 'size': len(data), 'mode': '0644', 'source': origins.get(path)} for path, data in sorted(files.items())],
    }
    temporary = Path(tempfile.mkdtemp(prefix='.search3-candidate-', dir=output.parent))
    try:
        payload = temporary / 'payload'
        control = temporary / 'control'
        control.mkdir()
        for name, data in files.items():
            target = payload / relative(name)
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
            target.chmod(0o644)
        (control / 'manifest.json').write_bytes(encoded(manifest))
        checksums = ''.join(f"{row['sha256']}  ./{row['path']}\n" for row in manifest['files']).encode()
        (control / 'payload.sha256').write_bytes(checksums)
        for path in control.iterdir():
            path.chmod(0o644)
        packed = archive_bytes(payload, control)
        (temporary / ARCHIVE).write_bytes(packed)
        (temporary / (ARCHIVE + '.sha256')).write_text(digest(packed) + '  ' + ARCHIVE + '\n')
        # Recheck the input bytes before retaining a candidate from a live checkout.
        need(all(digest(source_path(source_root, name).read_bytes()) == info['sha256'] for name, info in inputs.items()), 'source_changed_during_build')
        verify(temporary)
        need(not output.exists() and not output.is_symlink(), 'output_appeared_during_build')
        temporary.rename(output)
    except Exception:
        shutil.rmtree(temporary, ignore_errors=True)
        raise
    return manifest


def verify(output: Path, expected_manifest_sha256: str | None = None) -> dict:
    need(output.is_dir() and not output.is_symlink(), 'unsafe_candidate_directory')
    manifest_path = source_path(output, 'control/manifest.json')
    raw = manifest_path.read_bytes()
    if expected_manifest_sha256 is not None:
        need(bool(re.fullmatch('[0-9a-f]{64}', expected_manifest_sha256)) and digest(raw) == expected_manifest_sha256, 'manifest_pin_mismatch')
    manifest = json.loads(raw)
    need(manifest.get('schema_version') == 1 and manifest.get('state') == STATE and manifest.get('activation_performed') is False and manifest.get('route') == ROUTE, 'candidate_manifest_identity')
    rows = manifest.get('files')
    need(isinstance(rows, list) and len(rows) == manifest.get('file_count') and rows, 'candidate_inventory')
    expected = {}
    for row in rows:
        name = relative(row['path'])
        need(name not in expected and (name.startswith('poisk-turov/') or name.startswith('search3-runtime/')), 'candidate_target')
        need(not name.endswith('.php') or name == 'poisk-turov/index.php', 'unexpected_php')
        need(not any(x in name for x in ('fixture-data', 'recorded-data', 'preview-lead', 'config.php', 'secret', '/.')), 'forbidden_candidate_file')
        path = source_path(output, 'payload/' + name)
        need(row.get('mode') == '0644' and stat.S_IMODE(path.stat().st_mode) == 0o644, 'candidate_mode:' + name)
        need(path.stat().st_size == row.get('size') and digest(path.read_bytes()) == row.get('sha256'), 'candidate_hash:' + name)
        expected[name] = row['sha256']
    actual = set()
    for path in (output / 'payload').rglob('*'):
        need(not path.is_symlink(), 'candidate_symlink')
        if path.is_file():
            actual.add(path.relative_to(output / 'payload').as_posix())
    need(actual == set(expected), 'candidate_extra_or_missing_file')
    need(source_path(output, 'control/payload.sha256').read_text() == ''.join(f'{expected[name]}  ./{name}\n' for name in sorted(expected)), 'candidate_checksums')
    packed = source_path(output, ARCHIVE).read_bytes()
    need(source_path(output, ARCHIVE + '.sha256').read_text() == digest(packed) + '  ' + ARCHIVE + '\n', 'archive_digest')
    need(packed == archive_bytes(output / 'payload', output / 'control'), 'archive_payload_drift')
    return manifest


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    create = sub.add_parser('build', help='prepare a new directory; no network or activation')
    create.add_argument('--source-root', type=Path, required=True)
    create.add_argument('--output', type=Path, required=True)
    create.add_argument('--source-sha', required=True)
    create.add_argument('--source-tree-sha')
    check = sub.add_parser('verify', help='verify retained candidate bytes and inventory')
    check.add_argument('--output', type=Path, required=True)
    check.add_argument('--expected-manifest-sha256')
    args = parser.parse_args()
    try:
        result = build(args.source_root, args.output, args.source_sha, args.source_tree_sha) if args.command == 'build' else verify(args.output, args.expected_manifest_sha256)
        print(json.dumps({'state': result['state'], 'route': result['route'], 'file_count': result['file_count'], 'source_sha': result['source_sha'], 'manifest_sha256': digest((args.output / 'control/manifest.json').read_bytes()), 'archive_sha256': digest((args.output / ARCHIVE).read_bytes()), 'activation_performed': False}, sort_keys=True))
        return 0
    except (CandidateError, _snapshot.SnapshotError, OSError, ValueError, KeyError) as exc:
        print('SEARCH3_CANDIDATE_ERROR: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
