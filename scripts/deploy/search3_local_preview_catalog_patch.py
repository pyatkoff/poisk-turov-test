"""One fixed LOCAL composition from authenticated Git blobs and donor bytes.

The original 833-file predecessor is reconstructed and checked against its
native SOURCE controls, LOCAL archive, and observed installed inventory before
one reviewed helper is replaced. No fetched source code is executed, no expired
artifact is downloaded, and this module has no command or server entry point.
"""
from __future__ import annotations

import base64
from concurrent.futures import ThreadPoolExecutor
import gzip
import hashlib
import io
import json
from pathlib import Path
import re
import shutil
import tarfile
import tempfile

import search3_local_preview as original_local
import search3_local_preview_update_remote as remote
from search3_preview_remote import verify_payload
from search3_local_preview_update_remote import (
    COMPOSITION, CATALOG_TRANSFORM, HELPER_PATH, BASE_SOURCE_SHA,
    BASE_SOURCE_TREE, BASE_DEPLOY_RUN, BASE_INVENTORY_SHA256,
    BASE_LOCAL_ARCHIVE_SHA256, DONOR_SOURCE_SHA, DONOR_SOURCE_TREE,
    DONOR_BUILD_RUN, DONOR_ARTIFACT_ID, DONOR_ZIP_SHA256,
    DONOR_FILE_COUNT, DONOR_MANIFEST_SHA256, DONOR_CHECKSUMS_SHA256,
    DONOR_ARCHIVE_SHA256, donor_artifact_controls,
    BEFORE_HELPER_SHA256, BEFORE_HELPER_SIZE, AFTER_HELPER_SHA256,
    AFTER_HELPER_SIZE, CANDIDATE_INVENTORY_SHA256, composition_record,
    digest, inventory, json_bytes, need, safe_name,
)

TRANSFORM = CATALOG_TRANSFORM
BASE_SOURCE_MANIFEST_SHA256 = '9c3d20dbc1cf6dc828adc97a76ee3c2c5aba7b50a1c8e0bcbaf20c0bd45e7434'
BASE_SOURCE_CHECKSUMS_SHA256 = 'fc8e9f9e9365d3c60cf301c56104765e63e5a0873329e5bd687fccab38a4e764'
BASE_LOCAL_MANIFEST_SHA256 = 'd7bf8ce9c9990432dbac1a0ba7d50b044fb37d701eaedb24eb5ec7bc5bdca557'
BASE_LOCAL_CHECKSUMS_SHA256 = '8d8273fe548e5def634f870ffac92c25b7a124d9481518f13ea0a94c6bdc19f5'
BASE_SOURCE_ZIP_SHA256 = '9f76fd78b72e316cbcf821d9db523e60cf74f50f2b127d7ddef1d3799e13ef44'
BASE_BUILD_RUN = 36564947475
BASE_ARTIFACT_ID = 11032055571
BASE_RELEASE_SHA = '4f1668c3d823c21f0c01ed8297deca25bea905a3'
RAW_FILE_COUNT = 831
FILE_COUNT = 833
MISSING_BLOB_COUNT = 50
MISSING_BLOB_BYTES = 1254694
RECIPE_BLOBS = {
    'scripts/build/search3_site_preview_isolation.py': '11bc3c357f69044ecbe2eae1736414cb016a43f0',
    '.github/workflows/build-search3-whole-site-preview.yml': 'c5dc0de7790eff018b007c7d80044eb54bc150bb',
}
EXCLUDED = frozenset(('api.php', 'api-v2.php', 'lead-adapter.php',
    'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php',
    'lead-price-v1.php', 'lead-idempotency-v1.php'))
HTACCESS = (b'Options -Indexes\n<FilesMatch "\\.php$">\n  Require all denied\n'
    b'</FilesMatch>\n<Files "index.php">\n  Require all granted\n</Files>\n'
    b'<Files "bundle-v1.php">\n  Require all granted\n</Files>\n'
    b'<Files "preview-lead-disabled.php">\n  Require all granted\n</Files>\n')


def git_blob(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def checked_request(q, zip_digest):
    remote.catalog_request(q, complete=False)
    need(q.get('source_tree') == DONOR_SOURCE_TREE and zip_digest == DONOR_ZIP_SHA256,
         'catalog_donor_identity')
    for key, expected in donor_artifact_controls().items():
        need(type(q.get(key)) is type(expected) and q[key] == expected, 'catalog_donor_controls')
    for key in ('build_run', 'artifact_id', 'deploy_run'):
        need(type(q.get(key)) is int and 0 < q[key] < 10**15, 'catalog_request_integer')
    need(type(q.get('attempt')) is int and q['attempt'] == 1 and q.get('operation') == 'update',
         'catalog_request_no_replay')
    need(isinstance(q.get('release_sha'), str) and re.fullmatch('[0-9a-f]{40}', q['release_sha']),
         'catalog_release_pin')
    need('source_ZIP_sha256' not in q or q['source_ZIP_sha256'] == DONOR_ZIP_SHA256,
         'catalog_donor_ZIP')
    need(remote.TARGET == 'local', 'catalog_local_only')


def source_entries(api):
    commit = api.get('/git/commits/' + BASE_SOURCE_SHA)
    need(commit.get('sha') == BASE_SOURCE_SHA and commit.get('tree', {}).get('sha') == BASE_SOURCE_TREE,
         'catalog_source_commit')
    body = api.get('/git/trees/' + BASE_SOURCE_TREE + '?recursive=1')
    need(body.get('sha') == BASE_SOURCE_TREE and body.get('truncated') is False
         and isinstance(body.get('tree'), list) and len(body['tree']) == 1870,
         'catalog_source_tree')
    seen = set(); entries = []; code = {}
    for row in body['tree']:
        need(isinstance(row, dict) and isinstance(row.get('path'), str), 'catalog_tree_row')
        path = str(safe_name(row['path']))
        need(path not in seen and row.get('type') in ('blob', 'tree'), 'catalog_tree_duplicate_or_type')
        seen.add(path)
        if path in RECIPE_BLOBS:
            need(row.get('type') == 'blob' and row.get('mode') == '100644'
                 and row.get('sha') == RECIPE_BLOBS[path], 'catalog_recipe_code')
            code[path] = row['sha']
        if not path.startswith('v2/') or row['type'] != 'blob':
            continue
        relative = path[3:]; parts = safe_name(relative).parts
        if '_preview' in parts[:-1] or parts[-1].endswith('.md') or parts[-1] in EXCLUDED:
            continue
        need(row.get('mode') == '100644' and isinstance(row.get('sha'), str)
             and re.fullmatch('[0-9a-f]{40}', row['sha'])
             and type(row.get('size')) is int and 0 <= row['size'] <= 8 * 1024 * 1024,
             'catalog_raw_blob_identity')
        entries.append({'path': relative, 'sha': row['sha'], 'size': row['size']})
    need(code == RECIPE_BLOBS and len(entries) == RAW_FILE_COUNT
         and sum(x['size'] for x in entries) <= 64 * 1024 * 1024, 'catalog_raw_inventory')
    return sorted(entries, key=lambda x: x['path'])


def decode_blob(value, expected):
    need(isinstance(value, dict) and value.get('encoding') == 'base64'
         and value.get('sha') == expected['sha'] and type(value.get('size')) is int
         and value['size'] == expected['size'] and isinstance(value.get('content'), str)
         and len(value['content']) <= 12 * 1024 * 1024, 'catalog_blob_identity')
    try:
        data = base64.b64decode(''.join(value['content'].split()), validate=True)
    except (ValueError, TypeError) as exc:
        raise ValueError('catalog_blob_encoding') from exc
    need(len(data) == expected['size'] and git_blob(data) == expected['sha'], 'catalog_blob_bytes')
    return data


def replace_once(path, before, after):
    value = path.read_text(encoding='utf-8')
    need(value.count(before) == 1, 'catalog_isolation_drift:' + path.name)
    path.write_text(value.replace(before, after), encoding='utf-8')


def isolate(payload):
    """Exact reviewed SOURCE isolation; fetched Python is never executed."""
    search = payload / 'search-page-v2.php'
    replace_once(search, """$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$bitrixProlog = $docRoot . '/bitrix/modules/main/include/prolog_before.php';
if ($docRoot !== '' && is_file($bitrixProlog)) require($bitrixProlog);
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once($siteConf);
""", "// Preview: do not bootstrap the production document root.\n"
        "define('V2_ANDROMEDA_API_PUBLIC_PATH', '/_preview/search3-anex-candidate/api-andromeda-search3-preview.php');\n")
    replace_once(search, '$metrikaCounter=v2_metrika_counter_id();', '$metrikaCounter=0;')
    replace_once(search, '<script src="https://app.anytoour.ru/web-consultant/widget.js" async></script>', '')
    home_bootstrap = """$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$siteConf = $docRoot . '/site_conf.php';
if ($docRoot !== '' && is_file($siteConf)) require_once $siteConf;
"""
    for name in ('index.php', 'home-entry-v1.php'):
        replace_once(payload / name, home_bootstrap, '// Preview: use verified source defaults, not production site_conf.\n')
    replace_once(payload / 'site-page-shell-v1.php', """  $docRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
  $siteConf=$docRoot.'/site_conf.php'; if($docRoot!==''&&is_file($siteConf)) require $siteConf;
""", '  // Preview: do not execute production site_conf.\n')
    legacy = payload / 'poisk-turov-old/index.php'
    replace_once(legacy, "define('V2_PUBLIC_BASE_PATH', '');", """require_once dirname(__DIR__) . '/site-path-v1.php';
define('V2_PUBLIC_BASE_PATH', v2_site_base_path());
define('V2_API_PUBLIC_PATH', '/api-v2.php');
define('V2_LEAD_PUBLIC_PATH', v2_site_href('/preview-lead-disabled.php'));""")
    replace_once(legacy, "require dirname(__DIR__) . '/index.php';", "require dirname(__DIR__) . '/search-page-v2.php';")


def source_controls(root):
    payload = root / 'payload'; control = root / 'control'; control.mkdir()
    files = [{'path': p.relative_to(payload).as_posix(), 'sha256': digest(p.read_bytes()),
              'size': p.stat().st_size} for p in sorted(payload.rglob('*')) if p.is_file()]
    need(len(files) == FILE_COUNT, 'catalog_source_file_count')
    manifest = {'schema_version': 1, 'source_sha': BASE_SOURCE_SHA,
        'source_tree_sha': BASE_SOURCE_TREE, 'target': 'search3-whole-site-preview',
        'route': '/_preview/search3-site-candidate/', 'file_count': FILE_COUNT,
        'invariants': {**original_local.INVARIANTS,
            'preview_lead_path': '/_preview/search3-site-candidate/preview-lead-disabled.php'},
        'files': files}
    mb = (json.dumps(manifest, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()
    by_name = {f['path']: f['sha256'] for f in files}
    cb = ''.join(f'{by_name[n]}  ./{n}\n' for n in sorted(by_name)).encode()
    need(digest(mb) == BASE_SOURCE_MANIFEST_SHA256 and digest(cb) == BASE_SOURCE_CHECKSUMS_SHA256,
         'catalog_original_source_controls')
    (control / 'manifest.json').write_bytes(mb); (control / 'payload.sha256').write_bytes(cb)


def verify_base(root, ready, archive):
    need(digest(archive) == BASE_LOCAL_ARCHIVE_SHA256, 'catalog_original_local_archive')
    need(ready['manifest_sha256'] == BASE_LOCAL_MANIFEST_SHA256
         and ready['payload_sha256'] == BASE_LOCAL_CHECKSUMS_SHA256
         and ready['file_count'] == FILE_COUNT, 'catalog_original_local_controls')
    files = inventory(root / 'payload')
    need(len(files) == FILE_COUNT and digest(json_bytes(files)) == BASE_INVENTORY_SHA256,
         'catalog_observed_inventory')
    helper = root / 'payload' / HELPER_PATH
    need(files.get(HELPER_PATH) == BEFORE_HELPER_SHA256 and helper.stat().st_size == BEFORE_HELPER_SIZE,
         'catalog_helper_preimage')
    return files


def recover_base(donor, staging, api):
    entries = source_entries(api)
    pool = {}
    for path in sorted((donor / 'payload').rglob('*')):
        need(not path.is_symlink() and (path.is_dir() or path.is_file()), 'catalog_donor_file_type')
        if path.is_file():
            data = path.read_bytes(); pool.setdefault(git_blob(data), data)
    missing = [x for x in entries if x['sha'] not in pool]
    need(len(missing) == MISSING_BLOB_COUNT and sum(x['size'] for x in missing) == MISSING_BLOB_BYTES,
         'catalog_recovery_scope')
    def fetch(entry):
        return entry['sha'], decode_blob(api.get('/git/blobs/' + entry['sha']), entry)
    # A fixed finite batch through the existing JSON reader; no new transport.
    with ThreadPoolExecutor(max_workers=4) as workers:
        for key, value in workers.map(fetch, missing):
            pool[key] = value
    source = staging / 'source'; payload = source / 'payload'; payload.mkdir(parents=True)
    for entry in entries:
        data = pool[entry['sha']]
        need(len(data) == entry['size'] and git_blob(data) == entry['sha'], 'catalog_raw_bytes')
        path = payload.joinpath(*safe_name(entry['path']).parts)
        path.parent.mkdir(parents=True, exist_ok=True); path.write_bytes(data)
    shutil.copyfile(payload / 'index.php', payload / 'search-page-v2.php')
    shutil.copyfile(payload / 'home-entry-v1.php', payload / 'index.php')
    isolate(payload); (payload / '.htaccess').write_bytes(HTACCESS); source_controls(source)
    # This historical envelope is used only by the pure deterministic derive.
    # It is not a current command, fresh artifact admission, or old operation replay.
    historical = {'source_sha': BASE_SOURCE_SHA, 'source_tree': BASE_SOURCE_TREE,
        'release_sha': BASE_RELEASE_SHA, 'artifact_id': BASE_ARTIFACT_ID,
        'build_run': BASE_BUILD_RUN, 'deploy_run': BASE_DEPLOY_RUN, 'attempt': 1}
    target = staging / 'local'
    ready, archive = original_local.derive(source, target, historical, BASE_SOURCE_ZIP_SHA256)
    return target, verify_base(target, ready, archive)


def pack(root, files):
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode='w', format=tarfile.USTAR_FORMAT) as archive:
        for name in ('control/manifest.json', 'control/payload.sha256', *('payload/' + n for n in files)):
            data = (root / name).read_bytes(); info = tarfile.TarInfo(name)
            info.size = len(data); info.mode = 0o644; info.mtime = 0
            archive.addfile(info, io.BytesIO(data))
    return gzip.compress(raw.getvalue(), mtime=0)


def derive(source_release, output, request, zip_digest, api):
    checked_request(request, zip_digest)
    source_release = Path(source_release); output = Path(output)
    need(source_release.is_dir() and not source_release.is_symlink()
         and not output.exists() and not output.is_symlink(), 'catalog_work_paths')
    need(output.parent.is_dir() and not output.parent.is_symlink()
         and source_release.resolve() not in output.resolve().parents,
         'catalog_output_not_source')
    verify_payload(source_release, request)
    helper = (source_release / 'payload' / HELPER_PATH).read_bytes()
    need(len(helper) == AFTER_HELPER_SIZE and digest(helper) == AFTER_HELPER_SHA256,
         'catalog_donor_helper')
    with tempfile.TemporaryDirectory(prefix='.catalog-recover-', dir=output.parent) as work:
        base, before = recover_base(source_release, Path(work), api)
        shutil.copytree(base, output)
    (output / 'payload' / HELPER_PATH).write_bytes(helper)
    files = inventory(output / 'payload')
    need(set(files) == set(before) and len(files) == FILE_COUNT
         and {n for n in files if files[n] != before[n]} == {HELPER_PATH}
         and digest(json_bytes(files)) == CANDIDATE_INVENTORY_SHA256,
         'catalog_complement_changed')
    manifest = json.loads((output / 'control/manifest.json').read_bytes())
    manifest.update(source_sha=DONOR_SOURCE_SHA, source_tree_sha=DONOR_SOURCE_TREE,
        derived_from={'artifact_id': DONOR_ARTIFACT_ID, 'build_run': DONOR_BUILD_RUN,
                      'ZIP_sha256': DONOR_ZIP_SHA256, 'transform': TRANSFORM},
        composition=composition_record(), donor_artifact_controls=donor_artifact_controls(),
        files=[{'path': n, 'sha256': h, 'size': (output / 'payload' / n).stat().st_size}
               for n, h in files.items()], file_count=FILE_COUNT)
    mb = json_bytes(manifest); cb = ''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode()
    (output / 'control/manifest.json').write_bytes(mb); (output / 'control/payload.sha256').write_bytes(cb)
    archive = pack(output, files)
    ready = {**request, 'source_ZIP_sha256': zip_digest, 'archive_sha256': digest(archive),
             'manifest_sha256': digest(mb), 'payload_sha256': digest(cb), 'file_count': FILE_COUNT,
             'donor_artifact_controls': donor_artifact_controls()}
    remote.verify(output, ready)
    return ready, archive
