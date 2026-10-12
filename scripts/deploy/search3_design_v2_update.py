"""Retain then independently rederive a bounded update of NEW design preview.

No source runs with SSH credentials. All non-visual donor bytes must still match
the original accepted artifact, including supplier, price, lead, logo and fonts.
"""
from __future__ import annotations
import copy
import io
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import shutil
import sys
import tarfile
import zipfile
import gzip
import base64
import subprocess
from search3_design_v2_preview import (REPO, RELEASE, OLD_ROUTE, ROUTE_TRANSFORMS,
    LEDGER_PATH, LEDGER_JS, gzip_lengths, render_checks, live_checks)
from search3_design_v2_update_remote import (ROUTE, INVARIANTS, ORIGINAL_SOURCE, PREDECESSOR_SOURCE, COMPLETED_SOURCES,
    ORIGINAL_BUILD, ORIGINAL_ARTIFACT, ORIGINAL_ZIP, SOURCE_CHANGES, ROUTE_CHANGES,
    digest, inventory, json_bytes, need, verify, predecessor, native_acceptance, accepted_native,
    native_graph, NATIVE_BUILD_WORKFLOW, NATIVE_REQUIRED_CHECKS, NATIVE_ADDED, NATIVE_REPLACED,
    NATIVE_MANIFEST, NATIVE_ROUTE_CHANGES)
from search3_design_v2_preview_remote import SOURCE_TREE as ORIGINAL_TREE

PREFIX = '/update-search3-design-v2-preview '
REQUIRED_CHECKS = NATIVE_REQUIRED_CHECKS


def checked_request(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == '226193297', 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('repository', {}).get('full_name') == REPO and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == 226193297 and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'new_command_only')
    issue = event.get('issue', {}); c = event.get('comment', {})
    need(issue.get('number') == 4217 and 'pull_request' not in issue, 'coordination_only')
    need(c.get('user', {}).get('id') == 226193297 and c.get('author_association') == 'OWNER', 'comment_owner')
    body = c.get('body', '')
    need(isinstance(body, str) and body.startswith(PREFIX) and '\n' not in body and '\r' not in body, 'command_syntax')
    parts = body[len(PREFIX):].split(' ')
    need(len(parts) == 4 and all(re.fullmatch('[0-9a-f]{40}', x) for x in parts[:2])
         and all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:]), 'exact_pins')
    need(parts[0] not in COMPLETED_SOURCES, 'new_source_required')
    q = {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
        'artifact_id': int(parts[3]), 'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1}
    accepted_native(q, ('source_sha', 'build_run', 'artifact_id'))
    return q


def strict_checks(api, source):
    body = api.get('/commits/' + source + '/check-runs?per_page=100')
    need(body.get('total_count', 0) <= 100, 'check_pagination_required')
    latest = {}
    for c in body['check_runs']:
        if c['name'] not in latest or (c.get('started_at') or '') > (latest[c['name']].get('started_at') or ''):
            latest[c['name']] = c
    need(REQUIRED_CHECKS <= latest.keys(), 'required_visual_checks_missing')
    for name in REQUIRED_CHECKS:
        c = latest[name]
        need(c.get('head_sha') == source and c.get('status') == 'completed'
             and c.get('conclusion') == 'success' and c.get('app', {}).get('slug') == 'github-actions', 'required_visual_check_not_success:' + name)


def authorized():
    from search3_preview_publish import Github
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_request(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == event['comment']['body'] and fresh.get('user', {}).get('id') == 226193297, 'command_revoked')
    tree, zip_hash = native_provenance(api, q, env['GITHUB_SHA'])
    strict_checks(api, q['source_sha'])
    return q, api, tree, zip_hash


def native_provenance(api, q, control_sha):
    """Native-only verifier; shared publisher provenance is not relaxed."""
    pin = accepted_native(q, ('source_sha', 'build_run', 'artifact_id'))
    need(api.get('/git/ref/heads/main')['object']['sha'] == control_sha, 'control_main_changed')
    need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed')
    source = api.get('/git/commits/' + q['source_sha']); release = api.get('/git/commits/' + q['release_sha'])
    need(source['tree']['sha'] == pin['source_tree'], 'native_source_tree_pin')
    relation = api.get('/compare/' + q['source_sha'] + '...' + q['release_sha'])
    need(relation.get('status') in ('identical', 'ahead') and relation.get('behind_by') == 0, 'source_not_in_release')
    if source['tree']['sha'] != release['tree']['sha']:
        def projection(tree):
            value = api.get('/git/trees/' + tree + '?recursive=1')
            need(value.get('truncated') is False, 'native_tree_truncated')
            return {row['path']: (row['sha'], row['mode'], row['type']) for row in value['tree']
                    if row['type'] != 'tree' and row['path'] != 'AUTOPILOT_STATE.json' and not row['path'].startswith('docs/')}
        need(projection(source['tree']['sha']) == projection(release['tree']['sha']), 'release_runtime_differs')
    run = api.get('/actions/runs/' + str(q['build_run']))
    need(run.get('head_sha') == q['source_sha'] and run.get('path') == NATIVE_BUILD_WORKFLOW
         and run.get('event') == 'pull_request' and run.get('status') == 'completed'
         and run.get('conclusion') == 'success' and run.get('run_attempt') == 1
         and run.get('repository', {}).get('full_name') == REPO
         and run.get('head_repository', {}).get('full_name') == REPO, 'native_build_exact_source_required')
    strict_checks(api, q['source_sha'])
    checks = api.get('/commits/' + q['source_sha'] + '/check-runs?per_page=100')
    need(checks.get('total_count', 0) <= 100 and all(row.get('head_sha') == q['source_sha']
         and row.get('status') == 'completed' and row.get('conclusion') in ('success', 'skipped', 'neutral')
         for row in checks['check_runs']), 'native_source_check_not_green')
    meta = api.get('/actions/artifacts/' + str(q['artifact_id']))
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['build_run']
         and meta['workflow_run'].get('head_sha') == q['source_sha']
         and meta.get('name') == f"search3-design-v2-native-{q['source_sha']}-{q['build_run']}"
         and meta.get('digest') == 'sha256:' + pin['source_ZIP_sha256'], 'native_artifact_pin')
    return pin['source_tree'], pin['source_ZIP_sha256']


def prepare_native_zip(content, request, source_tree, expected_digest, directory):
    """Read retained review bytes only; never run artifact code in a secret job."""
    pin = accepted_native(request, ('source_sha', 'build_run', 'artifact_id'))
    need(source_tree == pin['source_tree'] and digest(content) == expected_digest == pin['source_ZIP_sha256'], 'native_ZIP_pin')
    need(not directory.exists(), 'native_extraction_exists')
    with zipfile.ZipFile(io.BytesIO(content)) as archive:
        rows = archive.infolist(); names = set(); total = 0
        need(len(rows) <= 12000, 'native_ZIP_members')
        for row in rows:
            from search3_design_v2_preview_remote import safe_name
            name = str(safe_name(row.filename.rstrip('/')))
            need(name not in names, 'native_ZIP_duplicate'); names.add(name)
            need((row.external_attr >> 16) & 0o170000 in (0, 0o100000, 0o040000)
                 and not row.flag_bits & 1, 'native_ZIP_type')
            need(row.file_size <= 8 * 1024 * 1024, 'native_ZIP_file_size'); total += row.file_size
            need(total <= 64 * 1024 * 1024, 'native_ZIP_total_size')
            if not row.is_dir():
                need(name.startswith('payload/') or name.startswith('evidence/')
                     or name in ('control/inventory.json', 'control/native-build.json'), 'native_ZIP_scope')
        need({'control/inventory.json', 'control/native-build.json', 'evidence/acceptance.json', 'evidence/fixture.json'} <= names, 'native_ZIP_control')
        meta = json.loads(archive.read('control/inventory.json'))
        need(meta.get('schema') == 1 and meta.get('source_sha') == request['source_sha']
             and meta.get('source_tree_sha') == source_tree and str(meta.get('run_id')) == str(request['build_run'])
             and str(meta.get('run_attempt')) == '1'
             and meta.get('donor_source_sha') == '00fe6734c08acf234756bb59e5b61dbfecf41941'
             and meta.get('intended_route') == ROUTE + 'visual-search/'
             and meta.get('status') == 'review-artifact-not-published'
             and meta.get('publication_authority') is False
             and meta.get('canonical_route_rewrites_applied') is False, 'native_review_inventory_identity')
        files = {}
        for row in meta['files']:
            name = str(safe_name(row['path']))
            need(name not in files and isinstance(row.get('sha256'), str) and re.fullmatch('[0-9a-f]{64}', row['sha256'])
                 and type(row.get('bytes')) is int and 0 <= row['bytes'] <= 8 * 1024 * 1024, 'native_review_inventory_row')
            files[name] = row['sha256']
        need(meta.get('file_count') == len(files) == 845 + len(NATIVE_ADDED), 'native_review_inventory_count')
        actual_names = {row.filename[len('payload/'):] for row in rows if row.filename.startswith('payload/') and not row.is_dir()}
        need(actual_names == files.keys(), 'native_review_inventory_files')
        directory.mkdir(mode=0o700); payload = directory / 'release/payload'; payload.mkdir(parents=True)
        for row in meta['files']:
            value = archive.read('payload/' + row['path'])
            need(len(value) == row['bytes'] and digest(value) == row['sha256'], 'native_review_file_hash:' + row['path'])
            target = payload / row['path']; target.parent.mkdir(parents=True, exist_ok=True); target.write_bytes(value)
        native = native_graph(payload)
        need(json.loads(archive.read('control/native-build.json')) == native, 'native_build_manifest_readback')
        native_evidence(archive, request, native)
    control = directory / 'release/control'; control.mkdir()
    manifest = {'schema_version': 1, 'source_sha': request['source_sha'], 'source_tree_sha': source_tree,
        'files': [{'path': row['path'], 'sha256': row['sha256'], 'size': row['bytes']} for row in meta['files']],
        'file_count': len(files)}
    (control / 'manifest.json').write_bytes(json_bytes(manifest))
    return files


def native_evidence(archive, request, native):
    """Rendered fixture proof, never a statement of live supplier acceptance."""
    report = json.loads(archive.read('evidence/acceptance.json'))
    need(report.get('schema') == 1 and report.get('sourceSHA') == request['source_sha']
         and report.get('donorSource') == native['donorSource']
         and report.get('compiledNativeManifest') == native and report.get('failures') == []
         and report.get('status') == 'passed'
         and report.get('requiredWidths') == [360, 390, 430, 768, 1280]
         and report.get('requiredTextPercents') == [100, 200]
         and report.get('expectedPairedScreens') == ['search', 'results', 'hotel', 'tour', 'lead'], 'native_rendered_identity')
    matrix = {(width, percent) for width in (360, 390, 430, 768, 1280) for percent in (100, 200)}
    cases = report.get('cases'); need(isinstance(cases, list) and len(cases) == len(matrix), 'native_rendered_matrix')
    seen = set(); frames = set()
    proof = {'passiveHydrationSearches': 0, 'formSubmitSearches': 1, 'localFilterExtraSearches': 0,
        'exactLoadedOfferRetained': True, 'flightChoiceIndex': 0, 'selectedFullTotal': 112000,
        'contactPayloadFullTotal': 112000, 'contactPayloadFlightChoiceIndex': 0,
        'contactPreviewCount': 1, 'realLeadSubmissions': 0, 'backForwardRetainedSelection': True,
        'backForwardExtraSearches': 0, 'errorRetainedRows': 4, 'explicitSearchRetryCount': 1,
        'retryRows': 4, 'favoriteStoragePreserved': True}
    for case in cases:
        key = (case.get('width'), case.get('textPercent'))
        need(key in matrix and key not in seen and not case.get('error')
             and case.get('errors') == [] and case.get('unexpectedRequests') == [], 'native_rendered_case')
        seen.add(key); actual = case.get('proof', {})
        need(all(type(actual.get(k)) is type(value) and actual.get(k) == value for k, value in proof.items()), 'native_rendered_journey')
        required = {f'{side}-{screen}-{key[0]}-text{key[1]}.png'
                    for side in ('native', 'donor') for screen in ('search', 'results', 'hotel', 'tour', 'lead')}
        required.add(f'native-error-{key[0]}-text{key[1]}.png')
        case_frames = case.get('frames'); need(isinstance(case_frames, list) and len(case_frames) == len(required), 'native_rendered_frames')
        case_seen = set()
        for frame in case_frames:
            name = frame.get('file')
            need(name in required and name not in case_seen and name not in frames, 'native_rendered_frame_inventory')
            case_seen.add(name); frames.add(name)
            value = archive.read('evidence/' + name)
            need(value.startswith(b'\x89PNG\r\n\x1a\n') and digest(value) == frame.get('sha256'), 'native_rendered_frame_hash')
            need(frame.get('viewportWidth') == key[0] and frame.get('textPercent') == key[1]
                 and frame.get('side') == name.split('-', 1)[0], 'native_rendered_frame_scope')
            if frame['side'] == 'native':
                need(frame.get('overflow') is False and type(frame.get('documentWidth')) in (int, float)
                     and frame['documentWidth'] <= key[0] + 1 and frame.get('stageHidden') is False, 'native_rendered_frame_geometry')
                prices = frame.get('prices')
                need(isinstance(prices, list), 'native_price_measurements_required')
                if name.split('-')[1] in ('results', 'hotel', 'tour', 'error'):
                    need(bool(prices), 'native_visible_price_measurements_required')
                for price in prices:
                    need(isinstance(price, dict) and price.get('overflow') is False
                         and price.get('fragmented') is False and price.get('textScaleVerified') is True,
                         'native_price_readability_required')
                    font, baseline = price.get('fontSize'), price.get('baselineFontSize')
                    need(type(font) in (int, float) and type(baseline) in (int, float)
                         and font > 0 and baseline > 0
                         and abs(font - baseline * (2 if key[1] == 200 else 1)) < 0.5,
                         'native_actual_text_scale_required')
        need(case_seen == required, 'native_rendered_required_frames')
    need(seen == matrix, 'native_rendered_matrix')
    evidence_files = {row.filename[len('evidence/'):] for row in archive.infolist()
                      if row.filename.startswith('evidence/') and not row.is_dir()}
    need(evidence_files == frames | {'acceptance.json', 'fixture.json'}, 'native_rendered_evidence_inventory')


def expected_native_render(payload):
    html = (payload / 'visual-search/index.html').read_text()
    def version(match):
        from search3_design_v2_preview_remote import safe_name
        import posixpath
        name = posixpath.normpath(posixpath.join('visual-search', match[2])); safe_name(name)
        file = payload / name
        need(file.is_file() and not file.is_symlink(), 'native_render_asset_missing')
        return match[1] + '="' + match[2] + '?v=' + digest(file.read_bytes())[:12] + '"'
    return re.sub(r'\b(src|href)="(\.\.?/[^"?]+\.(?:js|css))"', version, html)


def native_render_checks(root):
    """The same isolated page/boundary gates, with exact native entry readback."""
    payload = root / 'payload'; native_graph(payload, rewritten=True)
    empty = root / 'empty-document-root'; empty.mkdir()
    php = "$_SERVER['DOCUMENT_ROOT']=$argv[1]; $_SERVER['HTTP_HOST']='anytoour.ru'; $_SERVER['SCRIPT_NAME']=$argv[2]; include $argv[3];"
    def render(script, file):
        result = subprocess.run(['php', '-d', 'display_errors=1', '-r', php, str(empty), script, str(file)],
                                capture_output=True, text=True, timeout=30)
        need(result.returncode == 0 and 'Fatal error' not in result.stdout, 'native_local_render')
        return result.stdout
    try:
        for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
            html = render(ROUTE + route + 'index.php', payload / route / 'index.php')
            need('<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'local_noindex:' + route)
            if route in ('poisk-turov/', 'poisk-turov-old/'):
                for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                    need(marker in html, 'local_search_guard')
                need('web-consultant/widget.js' not in html and OLD_ROUTE not in html, 'local_wrong_preview')
        entry = payload / 'visual-search/index.php'
        need(render(ROUTE + 'visual-search/index.php', entry) == expected_native_render(payload), 'native_exact_PHP_render')
        for wrong in ('/poisk-turov/index.php', '/_preview/search3-next-candidate/visual-search/index.php',
                      '/_preview/search3-local-candidate/visual-search/index.php',
                      '/_preview/search3-site-candidate/visual-search/index.php',
                      ROUTE + 'visual-search/other.php'):
            need(render(wrong, entry) == 'This entry is available only in the design-v2 preview.', 'native_negative_entry:' + wrong)
    finally:
        empty.rmdir()


def native_live_checks(files):
    from search3_preview_publish import http
    for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
        status, html = http(ROUTE + route)
        need(status == 200 and '<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'live_route:' + route)
        if route in ('poisk-turov/', 'poisk-turov-old/'):
            for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                need(marker in html, 'live_search_guard')
            need(OLD_ROUTE not in html and 'web-consultant/widget.js' not in html, 'live_wrong_preview')
    status, text = http(ROUTE + 'preview-lead-disabled.php', b'{"synthetic":true}')
    need(status == 403 and 'PREVIEW_LEAD_DISABLED' in text, 'lead_not_disabled')
    for name in ('assets.php', 'search-page-v2.php', 'seo-config.php', 'data/db-v1.php', 'data/hotel-presentation-read-v1.php'):
        need(http(ROUTE + name)[0] == 403, 'internal_PHP_exposed:' + name)
    need(http(ROUTE + 'config.php')[0] in (403, 404), 'config_exposed')
    for path, message in {
        'data/hotel-details-read-v1.php?catalog=anytour&anytourHotelId=1': 'Canonical catalogue is isolated to local preview',
        'data/search3-local-results-read-v1.php': 'Local DB results are isolated to local preview',
    }.items():
        status, body = http(ROUTE + path)
        need(status == 403 and json.loads(body) == {'ok': False, 'error': message}, 'LOCAL_only_data_boundary:' + path)
    status, body = http(ROUTE + 'data/local-tv-catalog-read-v1.php?oldLocalHotelIds%5B%5D=1'); catalog = json.loads(body)
    need(status == 200 and catalog.get('ok') is True and catalog.get('source') == 'anytour-local-tv-catalog-v1'
         and catalog.get('catalog') == 'local-tv' and catalog.get('requestedIds') == [1]
         and all(isinstance(catalog.get(k), list) for k in ('items', 'links', 'missingIds')), 'new_catalog_read_boundary')
    status, html = http(ROUTE + 'visual-search/')
    need(status == 200 and '<meta name="robots" content="noindex,nofollow,noarchive">' in html
         and 'id="design-v2-native-root"' in html, 'native_live_entry_identity')
    from search3_design_v2_update_remote import NativeEntry, NATIVE_SCRIPTS, NATIVE_STYLES
    import posixpath
    entry = NativeEntry(); entry.feed(html); entry.close()
    def served(src):
        name = posixpath.normpath(posixpath.join('visual-search', src))
        need(name in files, 'native_live_dependency_inventory')
        return src + '?v=' + files[name][:12]
    need(not entry.inline and tuple(entry.scripts) == tuple(served(src) for src in NATIVE_SCRIPTS)
         and tuple(entry.styles) == tuple(served(src) for src in NATIVE_STYLES), 'native_live_exact_graph')
    targets = {name for name in files if name.startswith('visual-search/') and not name.endswith('.php')}
    targets |= {posixpath.normpath(posixpath.join('visual-search', src)) for src in (*NATIVE_SCRIPTS, *NATIVE_STYLES)}
    targets |= {'search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css'}
    for name in sorted(targets):
        status, value = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(value) == files[name], 'native_live_asset_hash:' + name)
    need(http('/')[0] == 200 and http('/poisk-turov/')[0] == 200, 'production_pages_unhealthy')
    return {'routes_200': 9, 'noindex': True, 'counter': 0, 'lead_HTTP': 403,
        'LOCAL_only_data_HTTP': 403, 'LOCAL_TV_catalog_HTTP': 200, 'supplier_searches': 0, 'real_leads': 0,
        'visual_route_200': True, 'native_graph_verified': True, 'visual_assets_verified': len(targets)}


def update_ledger(source, output):
    ledger = json.loads((source / 'payload' / LEDGER_PATH).read_bytes()); original = copy.deepcopy(ledger)
    entries = {f['target']: f for f in ledger['files']}
    need(len(entries) == len(ledger['files']) and all(n in entries for n in LEDGER_JS), 'served_ledger_inventory')
    before = [(source / 'payload' / n).read_bytes() for n in LEDGER_JS]
    after = [(output / 'payload' / n).read_bytes() for n in LEDGER_JS]
    lengths = gzip_lengths(before + after)
    for i, name in enumerate(LEDGER_JS):
        f = entries[name]
        need(f['sha256'] == digest(before[i]) and f['served'] == len(before[i])
             and f['gzip_after'] == lengths[i], 'source_ledger_accounting:' + name)
        f.update(sha256=digest(after[i]), served=len(after[i]), gzip_after=lengths[i + len(LEDGER_JS)])
    graphs = {**ledger['graphs'], 'complete_live': ledger['graphs']['live'] + ledger['graphs']['ondemand'],
        'complete_offline': ledger['graphs']['offline'] + ledger['graphs']['ondemand']}
    for name, graph in graphs.items():
        for field in ('served', 'gzip_after'):
            ledger['totals'][name][field] = sum(f[field] for f in ledger['files'] if f['src'] in graph)
    check = copy.deepcopy(ledger)
    for i, f in enumerate(check['files']):
        if f['target'] in LEDGER_JS:
            for field in ('sha256', 'served', 'gzip_after'): f[field] = original['files'][i][field]
    for name in ('live', 'complete_live'):
        for field in ('served', 'gzip_after'): check['totals'][name][field] = original['totals'][name][field]
    need(check == original, 'unexpected_ledger_delta')
    (output / 'payload' / LEDGER_PATH).write_bytes((json.dumps(ledger, ensure_ascii=False, indent=2) + '\n').encode())


def derive(source, baseline, output, request, zip_digest):
    need(not output.exists(), 'derived_output_exists')
    original = inventory(baseline / 'payload'); current = inventory(source / 'payload')
    accepted_native(request, ('source_sha', 'build_run', 'artifact_id'))
    need(len(original) == 845 and set(current) == set(original) | set(NATIVE_ADDED)
         and not set(original) & set(NATIVE_ADDED), 'source_inventory_drift')
    changes = sorted(n for n in current if n not in original or current[n] != original[n])
    need(set(NATIVE_ADDED) <= set(changes)
         and set(changes) <= set((*SOURCE_CHANGES, *NATIVE_REPLACED, *NATIVE_ADDED)), 'unreviewed_source_delta')
    native_graph(source / 'payload')
    shutil.copytree(source, output)
    for name, old, new in ROUTE_TRANSFORMS:
        if name == 'visual-search/index.php': continue  # Exact native entry is already design-v2 fenced and hash-pinned.
        p = output / 'payload' / name; before = p.read_bytes()
        need(before.count(old) == 1 and new not in before, 'route_source_drift:' + name)
        p.write_bytes(before.replace(old, new))
    update_ledger(source, output)
    update_native_ledger(output)
    for p in (output / 'payload').rglob('*'):
        if p.is_file(): need(OLD_ROUTE.rstrip('/').encode() not in p.read_bytes(), 'residual_old_preview_route')
    files = inventory(output / 'payload')
    need(current.keys() == files.keys() and {n for n in files if files[n] != current[n]} == set(NATIVE_ROUTE_CHANGES), 'unexpected_payload_delta')
    m = json.loads((source / 'control/manifest.json').read_bytes())
    m.update(target='search3-design-v2-preview', route=ROUTE, invariants=INVARIANTS,
        derived_from={'artifact_id': request['artifact_id'], 'build_run': request['build_run'],
            'ZIP_sha256': zip_digest, 'transform': 'design-v2-native-route-and-ledger-v2',
            'changed_payload_files': list(NATIVE_ROUTE_CHANGES), 'changed_source_payload_files': changes,
            'baseline': {'source_sha': ORIGINAL_SOURCE, 'build_run': ORIGINAL_BUILD,
                'artifact_id': ORIGINAL_ARTIFACT, 'ZIP_sha256': ORIGINAL_ZIP}})
    m['files'] = [{'path': n, 'sha256': h, 'size': (output / 'payload' / n).stat().st_size} for n, h in files.items()]
    m['file_count'] = len(files)
    (output / 'control/manifest.json').write_bytes(json_bytes(m))
    (output / 'control/payload.sha256').write_bytes(''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode())
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode='w', format=tarfile.USTAR_FORMAT) as t:
        for name in ('control/manifest.json', 'control/payload.sha256', *('payload/' + n for n in files)):
            data = (output / name).read_bytes(); info = tarfile.TarInfo(name)
            info.size = len(data); info.mode = 0o644; info.mtime = 0
            t.addfile(info, io.BytesIO(data))
    archive = gzip.compress(raw.getvalue(), mtime=0)
    q = {**request, 'source_ZIP_sha256': zip_digest, 'archive_sha256': digest(archive),
        'manifest_sha256': digest((output / 'control/manifest.json').read_bytes()),
        'payload_sha256': digest((output / 'control/payload.sha256').read_bytes()),
        'target_digest': digest(json_bytes(files)), 'file_count': len(files)}
    verify(output, q)
    return q, archive


def update_native_ledger(output):
    payload = output / 'payload'; path = payload / NATIVE_MANIFEST; value = json.loads(path.read_bytes())
    value['canonicalDependenciesRewritten'] = True
    for kind in ('scripts', 'styles'):
        for row in value['graph'][kind]:
            content = (payload / row['path']).read_bytes(); row.update(sha256=digest(content), bytes=len(content))
    path.write_bytes((json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode())
    native_graph(payload, rewritten=True)


def prepare_inputs(api, q, tree, zip_hash, work):
    from search3_preview_publish import prepare_zip
    prepare_native_zip(api.download(q['artifact_id']), q, tree, zip_hash, work / 'original')
    baseline = {'source_sha': ORIGINAL_SOURCE, 'release_sha': ORIGINAL_SOURCE,
        'artifact_id': ORIGINAL_ARTIFACT, 'build_run': ORIGINAL_BUILD,
        'deploy_run': q['deploy_run'], 'attempt': 1}
    prepare_zip(api.download(ORIGINAL_ARTIFACT), baseline, ORIGINAL_TREE, ORIGINAL_ZIP, work / 'baseline')
    return derive(work / 'original/release', work / 'baseline/release', work / 'derived', q, zip_hash)


def prepare():
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-prepare'; work.mkdir(mode=0o700)
    ready, archive = prepare_inputs(api, q, tree, zip_hash, work)
    native_render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'design-v2-update.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_not_published', 'route': ROUTE, 'archive_sha256': ready['archive_sha256']}))


def remote_source():
    # Trusted-main modules only; payload Python is never imported or executed.
    root = Path(__file__).parent
    base = (root / 'search3_design_v2_preview_remote.py').read_text()
    updater = (root / 'search3_design_v2_update_remote.py').read_text()
    return ('import sys, types\n'
        'm=types.ModuleType("search3_design_v2_preview_remote")\n'
        'sys.modules[m.__name__]=m\n'
        'exec(compile(' + repr(base) + ',"trusted-creator-remote","exec"),m.__dict__)\n'
        'exec(compile(' + repr(updater) + ',"trusted-update-remote","exec"))\n').encode()


def publish():
    from search3_preview_publish import command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-publish'; work.mkdir(mode=0o700)
    expected_q, archive = prepare_inputs(api, q, tree, zip_hash, work)
    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-design-v2-update-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id)); need('sha256:' + digest(packed) == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(io.BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'design-v2-update.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('design-v2-update.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')
    q = expected_q; (work / 'design-v2-update.tar.gz').write_bytes(archive)
    files = inventory(work / 'derived/payload')
    evidence = {'route': ROUTE, 'source_sha': q['source_sha'], 'release_sha': q['release_sha'],
        'source_artifact': q['artifact_id'], 'derived_artifact': int(artifact_id), 'derived_ZIP_sha256': digest(packed),
        'predecessor_source': PREDECESSOR_SOURCE, 'status': 'checked_not_published'}
    host = os.environ['PREVIEW_HOST']; user = os.environ['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'; raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw: raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    attempted = False
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
            '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        source = remote_source()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-design-v2-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
        remote('bind', binding)
        try:
            status, text = http('/_preview/' + binding['name'])
            need(status == 200 and text == binding['nonce'], 'SSH_HTTPS_binding')
        finally: remote('unbind', binding)
        q['before'] = remote('snapshot', {}); predecessor(q['before'])
        need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed_before_activation')
        need(api.get('/git/ref/heads/main')['object']['sha'] == os.environ['GITHUB_SHA'], 'main_changed_before_activation')
        q['archive'] = remote('upload', {})['archive']
        need(re.fullmatch(r'/tmp/search3-design-v2-update\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'design-v2-update.tar.gz'), user + '@' + host + ':' + q['archive']])
        attempted = True; evidence['activation'] = remote('activate', q)
        evidence.update(native_live_checks(files))
        evidence['completion'] = remote('complete', q)
        evidence.update(status='published', production_unchanged=True, existing_preview_unchanged=True)
    except Exception as exc:
        evidence.update(status='failed_not_accepted', reason=str(exc))
        if attempted:
            try: evidence['rollback'] = remote('rollback', q)
            except Exception as error: evidence['rollback'] = {'status': 'unknown_stop_no_replay', 'reason': str(error)}
        raise
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_bytes(json_bytes(evidence))
    print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    need(len(sys.argv) == 2 and sys.argv[1] in ('prepare', 'publish'), 'usage')
    {'prepare': prepare, 'publish': publish}[sys.argv[1]]()
