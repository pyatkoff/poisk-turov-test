"""One reviewed update of the existing separate design preview; no other target.

The predecessor is the published form/contrast native update, not a caller-selected path.
The backup and durable receipt survive failures; unknown state never authorizes replay.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import re
import shutil
import sys
import tempfile
from html.parser import HTMLParser
import posixpath
from search3_design_v2_preview_remote import (Site as CreateSite, ROUTE, INVARIANTS,
    SOURCE_SHA as ORIGINAL_SOURCE, BUILD_RUN as ORIGINAL_BUILD, ARTIFACT_ID as ORIGINAL_ARTIFACT,
    SOURCE_ZIP_SHA256 as ORIGINAL_ZIP, digest, inventory, json_bytes, need, safe_extract, safe_name)

ORIGINAL_PUBLISH_RUN = 38173086001
PREDECESSOR_SOURCE = 'af7ec8fd2a8af89efdf0253619ae9bd63ac756ba'
PREDECESSOR_RUN = 38484937090
PREDECESSOR_DIGEST = '1d7edf46bb1462628b98cb3cb6567620f3ef12315699d01ccd2f0bf4a123cef5'
# Explicit history must not change when the next predecessor is selected.
COMPLETED_SOURCES = (ORIGINAL_SOURCE, '950c9b5c0a00430c5b5a711d1b95bc04449d069d',
    '634d1c97fd7bf499107d82036588f4bd77176d93',
    '2eb237639c6c53c38f27d71684de43384842daf7',
    'a0e5f1e7c0083b45678df5837c7faf1cad8751f6',
    '16196410a8cb90b10e8d06b7ac8b38d4ce7d235d',
    'b53d5ca0bf541e66b684018fb7db332de8fefaf3',
    '4955ee078aa0827f745d737681b4ed9187a274bd',
    '0674fa6cc7744ee21cb073b284f089c20171f38e',
    '8c2d0308eb29e26d2819342d10501db1ed82e3db',
    '9fec34cfeb5a7c97659b16188404ae02c166c770',
    '20c6488c01d764ce9bdc19c7eb71f01108efbda5',
    '9de4cdb3d044b060b039abc096f9351466147d86',
    '5a84a0d66394116f9845de3565563ce1d025b6bc',
    '75e52f2597172469fbcb6f742dc8c7b5934cd173',
    '660a760d9d0a0c0121d05d53bdc0895689600bc8',
    '08a99f2dfe2f420c9c03a6eb451d00e9a04e5026',
    'a3b0fa5125adb1a90b32a49d5f27a6b8852f5510',
    'd341f7648749f2f8a3272a1e51e2d923c1db80a6',
    '002dc0d636c5ad6e5597d37259baba574fe9ed88',
    'f4b343ecce2411899ce2f2138c8fc5f64ea27eb5',
    '67468aa3ba24526ee7c32d3e57bf2d7a28bd9190',
    'e42e2a19c134a79127cb22a6dd26fc1abcd19e41',
    '2da88e41fb436acefd293066bcc98db013e07ee0',
    'fbbd23e39c4fa71f8ba9500048ef4f2a3c2f1735',
    '740ed0b20596f901a1d6c15d238cec527633ce80',
    'b8b14e89931fbaab363895065d53ed7063339eb3',
    'af7ec8fd2a8af89efdf0253619ae9bd63ac756ba')
COMPLETED_PUBLISH_RUNS = (ORIGINAL_PUBLISH_RUN, 38180461319, 38191104207,
    38197437835, 38203171197, 38207863194, 38237246171, 38238503101,
    38255222254, 38269206819, 38276638172, 38290989467, 38312997899,
    38320934646, 38331033682, 38336349915, 38354248961, 38363323689, 38388948150, 38404222823,
    38413259110, 38420411530, 38429562174, 38437139126, 38444580624, 38451804327,
    38464277088, 38477454593, 38484937090)
PREDECESSOR_OWNER = {'run': PREDECESSOR_RUN, 'source': PREDECESSOR_SOURCE,
    'digest': PREDECESSOR_DIGEST, 'status': 'published'}
SOURCE_CHANGES = ('visual-search/app.js', 'visual-search/asset-size.json',
    'visual-search/hotel-details-v1.js',
    'visual-search/index.html', 'visual-search/migration-manifest.json',
    'visual-search/mobile-controls-v1.css', 'visual-search/offer-list-v1.js',
    'visual-search/styles.css')
ROUTE_CHANGES = tuple(sorted(('site-path-v1.php', 'visual-search/index.php',
    'prototype-search/config.js', 'prototype-search/data.js', 'search3-canonical-profiles-v1.js',
    'data/local-tv-catalog-read-v1.php', 'visual-search/asset-size.json')))

# Exact reviewed source and receiving release; activation requires fresh workflow gates.
NATIVE_ACCEPTANCE = {
    'source_sha': '41dd5a9c013b9431f68e38b85de5b7a72ae6848d',
    'source_tree': 'da0ccbd1a9967910b153de60d61d153d4078034f',
    'release_sha': '5a0a20807728801c22ed47707a0ab0f86b8eb393',
    'release_tree': 'da0ccbd1a9967910b153de60d61d153d4078034f',
    'release_parent': '97c527abe0cf9998271743c56b676a53882efa75',
    'build_run': 38492367428,
    'artifact_id': 11862560888,
    'source_ZIP_sha256': 'd63f9aa40c76cce2344af172687555cf96f80909bd56a9cf6d2b7daaa260e072',
    'entry_html_sha256': '1d2cf3076fbb949d0787ca82d7cfb4b442ba002364cc4b35f2be947ec490e8a7',
    'entry_php_sha256': 'c68afd233c54629b080744e47b31ea331a0c431137bbcc2b6b2439ecf3b80843',
    'source_manifest_sha256': 'c3698bb0a9ef40b624f7534c300f123f82e15606c23da8cf29d7139974cf4c20',
}
NATIVE_BUILD_WORKFLOW = '.github/workflows/search3-design-v2-native.yml'
NATIVE_REQUIRED_CHECKS = frozenset(('guard', 'native-acceptance'))
NATIVE_MANIFEST = 'visual-search/native-v2/source-manifest.json'
NATIVE_ASSETS = tuple('visual-search/native-v2/' + name for name in (
    'bootstrap.js', 'bridge.js', 'donor.css', 'frontend.js', 'lucide-LICENSE',
    'lucide.min.js', 'product-desktop.css', 'tour.js', 'viewport.css'))
NATIVE_ADDED = tuple(sorted((*NATIVE_ASSETS, NATIVE_MANIFEST)))
NATIVE_REPLACED = ('visual-search/index.html', 'visual-search/index.php')
NATIVE_ROUTE_CHANGES = tuple(sorted((set(ROUTE_CHANGES) - {'visual-search/index.php'}) | {NATIVE_MANIFEST}))
# Only the reviewed tour-layout CSS, result-action copy and generated manifest differ from
# the exact 855-file published predecessor.
# Historical baseline allowances do not authorize fresh legacy/provider/brand changes.
NATIVE_SUCCESSOR_CHANGES = tuple(sorted('visual-search/native-v2/' + name for name in (
    'frontend.js', 'source-manifest.json', 'viewport.css')))
NATIVE_SCRIPTS = (
    '../prototype-search/config.js', '../runtime-v3.js', '../lead-search-context.js', '../lead-form-guard-v1.js',
    './lead-runtime-v1.js', '../search3-canonical-profiles-v1.js', '../search3-local-db-provider-v1.js',
    '../prototype-search/data.js', '../prototype-search/calendar-exact-hotel-v1.js',
    '../prototype-search/source-receipt-v1.js', '../prototype-search/lead.js',
    './native-v2/lucide.min.js', './native-v2/bridge.js', './native-v2/tour.js',
    './native-v2/frontend.js', './native-v2/bootstrap.js')
NATIVE_STYLES = ('./native-v2/donor.css', './native-v2/product-desktop.css', './native-v2/viewport.css')
NATIVE_DONOR = {'anytour-fragment.html': 'cc4dde79750e45cbb9e236fd909e9050c44ab0d14c68ad9b5470b9d368c3e2fb',
    'product-desktop.css': 'ab55fb2b1c8857d3d62e3826325c0f3bd4629418a555e5f021c95ab8f2995848'}
NATIVE_BRAND = {'donor.css': 'fc695b915d1907b4c6ea422fa6a1c4fec4a26c30e851c38b3b68c16f82a3bbb8',
    'product-desktop.css': NATIVE_DONOR['product-desktop.css'],
    'lucide.min.js': 'ab85225ecc1daa2033607856f03dd2dabcbc08e2d260a5b97e17c690aa929766',
    'lucide-LICENSE': 'b495047bd93a9b06913511076f504daba17d5bbeb3e0650f3bb53a4220329c57'}
# The existing native entry already has its exact fixed design-v2 boundary.
# Only these existing canonical JavaScript route literals affect its graph.
NATIVE_SCRIPT_REWRITES = (
    ('prototype-search/config.js', b'search3-(?:next|local)-candidate', b'search3-(?:design-v2|local)-candidate'),
    ('prototype-search/data.js', b'/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php',
     b'/_preview/search3-design-v2-candidate/data/local-tv-catalog-read-v1.php'),
    ('prototype-search/data.js', b"'/_preview/search3-next-candidate/'", b"'/_preview/search3-design-v2-candidate/'"),
    ('search3-canonical-profiles-v1.js', b'/_preview/search3-next-candidate/visual-search/',
     b'/_preview/search3-design-v2-candidate/visual-search/'))


def native_acceptance():
    pin = NATIVE_ACCEPTANCE
    keys = {'source_sha', 'source_tree', 'release_sha', 'release_tree', 'release_parent',
            'build_run', 'artifact_id', 'source_ZIP_sha256',
            'entry_html_sha256', 'entry_php_sha256', 'source_manifest_sha256'}
    need(isinstance(pin, dict) and set(pin) == keys, 'native_acceptance_not_pinned')
    need(all(isinstance(pin[k], str) and re.fullmatch('[0-9a-f]{40}', pin[k])
             for k in ('source_sha', 'source_tree', 'release_sha', 'release_tree', 'release_parent')),
         'native_source_pin')
    need(pin['source_sha'] not in COMPLETED_SOURCES, 'native_completed_source_pin')
    need(all(type(pin[k]) is int and 0 < pin[k] < 10**15 for k in ('build_run', 'artifact_id')), 'native_build_pin')
    need(all(isinstance(pin[k], str) and re.fullmatch('[0-9a-f]{64}', pin[k])
             for k in keys - {'source_sha', 'source_tree', 'release_sha', 'release_tree', 'release_parent',
                              'build_run', 'artifact_id'}), 'native_hash_pin')
    return pin


def accepted_native(q, fields=('source_sha', 'source_tree', 'build_run', 'artifact_id', 'source_ZIP_sha256')):
    pin = native_acceptance()
    need(all(q.get(k) == pin[k] for k in fields), 'accepted_native_source_only')
    return pin


class NativeEntry(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True); self.scripts = []; self.styles = []; self.inline = False; self.in_script = False

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        need(len(values) == len(attrs), 'native_duplicate_attribute')
        if tag == 'script':
            need('src' in values and 'defer' in values, 'native_external_deferred_script_required')
            self.scripts.append(values['src']); self.in_script = True
        if tag == 'link' and values.get('rel') == 'stylesheet': self.styles.append(values.get('href'))
        need(tag != 'style', 'native_inline_style_owner')

    def handle_endtag(self, tag):
        if tag == 'script': self.in_script = False

    def handle_data(self, data):
        if self.in_script and data.strip(): self.inline = True


def native_graph(payload, rewritten=False, pinned=True):
    pin = native_acceptance() if pinned else None
    raw = (payload / NATIVE_MANIFEST).read_bytes(); manifest = json.loads(raw)
    need(set(manifest) == {'schema', 'donorSource', 'donor', 'status', 'legacyPresentationLoaded',
        'mockSimulatorLoaded', 'isolatedOverlay', 'canonicalDependenciesRewritten', 'entry', 'graph', 'files'}, 'native_manifest_fields')
    need(manifest['schema'] == 2 and manifest['donorSource'] == '00fe6734c08acf234756bb59e5b61dbfecf41941'
         and manifest['donor'] == NATIVE_DONOR and manifest['status'] == 'native-source-candidate-not-published'
         and manifest['legacyPresentationLoaded'] is False and manifest['mockSimulatorLoaded'] is False
         and manifest['isolatedOverlay'] is True and manifest['canonicalDependenciesRewritten'] is rewritten, 'native_manifest_identity')
    html = (payload / 'visual-search/index.html').read_bytes(); php = (payload / 'visual-search/index.php').read_bytes()
    need(manifest['entry'] == {'html': digest(html), 'php': digest(php)}, 'native_entry_hash')
    if pin:
        need(digest(html) == pin['entry_html_sha256'] and digest(php) == pin['entry_php_sha256'], 'native_entry_pin')
    entry = NativeEntry(); entry.feed(html.decode()); entry.close()
    need(not entry.inline and tuple(entry.scripts) == NATIVE_SCRIPTS and tuple(entry.styles) == NATIVE_STYLES, 'native_exact_entry_graph')
    need(b'<meta name="robots" content="noindex,nofollow,noarchive">' in html
         and b'id="design-v2-native-root"' in html, 'native_html_identity')
    need(set(manifest['graph']) == {'scripts', 'styles'}, 'native_graph_fields')
    for kind, sources in (('scripts', NATIVE_SCRIPTS), ('styles', NATIVE_STYLES)):
        expected = []
        for src in sources:
            name = posixpath.normpath(posixpath.join('visual-search', src)); safe_name(name)
            content = (payload / name).read_bytes()
            expected.append({'src': src, 'path': name, 'sha256': digest(content), 'bytes': len(content)})
        need(manifest['graph'][kind] == expected, 'native_graph_checksum:' + kind)
    names = {Path(name).name for name in NATIVE_ASSETS}
    need(set(manifest['files']) == names, 'native_asset_inventory')
    for name in NATIVE_ASSETS:
        need(manifest['files'][Path(name).name] == digest((payload / name).read_bytes()), 'native_asset_hash:' + name)
    for name, value in NATIVE_BRAND.items(): need(manifest['files'][name] == value, 'native_donor_asset:' + name)
    need({n for n in inventory(payload) if n.startswith('visual-search/native-v2/')} == set(NATIVE_ADDED), 'native_added_inventory')
    if pin:
        original = json.loads(raw)
        if rewritten:
            original['canonicalDependenciesRewritten'] = False
            for row in original['graph']['scripts']:
                content = (payload / row['path']).read_bytes()
                for name, old, new in reversed(NATIVE_SCRIPT_REWRITES):
                    if row['path'] == name:
                        need(content.count(new) == 1 and old not in content, 'native_route_reverse:' + name)
                        content = content.replace(new, old)
                row.update(sha256=digest(content), bytes=len(content))
        source_raw = (json.dumps(original, ensure_ascii=False, indent=2) + '\n').encode()
        need(digest(source_raw) == pin['source_manifest_sha256'], 'native_source_manifest_pin')
    return manifest


def validate(q):
    for k in ('source_sha', 'source_tree', 'release_sha'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{40}', q[k]), 'invalid_' + k)
    need(q['source_sha'] not in COMPLETED_SOURCES, 'new_source_required')
    for k in ('artifact_id', 'build_run', 'deploy_run'):
        need(type(q.get(k)) is int and 0 < q[k] < 10**15, 'invalid_' + k)
    for k in ('archive_sha256', 'manifest_sha256', 'payload_sha256', 'source_ZIP_sha256', 'target_digest'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{64}', q[k]), 'invalid_' + k)
    need(q.get('attempt') == 1 and q['deploy_run'] not in COMPLETED_PUBLISH_RUNS, 'no_replay')
    accepted_native(q)
    need(type(q.get('file_count')) is int and q['file_count'] == 845 + len(NATIVE_ADDED), 'invalid_file_count')


def predecessor(before):
    need(isinstance(before, dict) and before.get('target') == PREDECESSOR_DIGEST
         and before.get('owner') == PREDECESSOR_OWNER, 'exact_published_predecessor_required')


def predecessor_delta(before, after):
    need(isinstance(before, dict) and isinstance(after, dict)
         and len(before) == len(after) == 845 + len(NATIVE_ADDED)
         and before.keys() == after.keys(), 'successor_inventory_drift')
    need(digest(json_bytes(before)) == PREDECESSOR_DIGEST, 'successor_predecessor_drift')
    changes = tuple(sorted(n for n in before if before[n] != after[n]))
    need(changes == NATIVE_SUCCESSOR_CHANGES, 'unreviewed_predecessor_delta')
    return changes


def verify(root, q):
    validate(q)
    body = (root / 'control/manifest.json').read_bytes()
    checksums = (root / 'control/payload.sha256').read_bytes()
    need(digest(body) == q['manifest_sha256'] and digest(checksums) == q['payload_sha256'], 'control_hash')
    m = json.loads(body)
    need(m.get('schema_version') == 1 and m.get('target') == 'search3-design-v2-preview'
         and m.get('route') == ROUTE and m.get('source_sha') == q['source_sha']
         and m.get('source_tree_sha') == q['source_tree'] and m.get('invariants') == INVARIANTS, 'manifest_identity_or_invariants')
    changes = m.get('derived_from', {}).get('changed_source_payload_files')
    need(isinstance(changes, list) and changes == sorted(set(changes))
         and set(NATIVE_ADDED) <= set(changes)
         and set(changes) <= set((*SOURCE_CHANGES, *NATIVE_REPLACED, *NATIVE_ADDED)), 'unreviewed_source_delta')
    need(m.get('derived_from') == {'artifact_id': q['artifact_id'], 'build_run': q['build_run'],
        'ZIP_sha256': q['source_ZIP_sha256'], 'transform': 'design-v2-native-route-and-ledger-v2',
        'changed_payload_files': list(NATIVE_ROUTE_CHANGES), 'changed_source_payload_files': changes,
        'baseline': {'source_sha': ORIGINAL_SOURCE, 'build_run': ORIGINAL_BUILD,
            'artifact_id': ORIGINAL_ARTIFACT, 'ZIP_sha256': ORIGINAL_ZIP}}, 'derivation_identity')
    files = {}
    for f in m['files']:
        name = str(safe_name(f['path']))
        need(name not in files and re.fullmatch('[0-9a-f]{64}', f['sha256']), 'duplicate_or_bad_hash')
        need((root / 'payload' / name).stat().st_size == f['size'], 'file_size')
        files[name] = f['sha256']
    need(len(files) == m['file_count'] == q['file_count'], 'file_count')
    need(inventory(root / 'payload') == files and digest(json_bytes(files)) == q['target_digest'], 'payload_hashes')
    need(checksums == ''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode(), 'checksums_inventory')
    need(all(p not in files for p in ('config.php', 'api.php', 'api-v2.php', 'lead-adapter.php',
        'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php')), 'protected_payload_endpoint')
    need(all(p in files for p in ('.htaccess', 'preview-lead-disabled.php', 'search-page-v2.php',
        'data/hotel-details-read-v1.php', 'data/hotel-presentation-read-v1.php', 'poisk-turov/index.php')), 'missing_guard_or_catalog')
    need('metrikaCounter=0' in (root / 'payload/search-page-v2.php').read_text(), 'counter_not_zero')
    need('#^(/_preview/search3-design-v2-candidate)(?:/|$)#' in (root / 'payload/site-path-v1.php').read_text(), 'wrong_route_helper')
    visual = (root / 'payload/visual-search/index.php').read_text()
    need(visual.count("$entry = '/_preview/search3-design-v2-candidate/visual-search/';") == 1
         and "if ($script !== $entry . 'index.php') {" in visual
         and '/_preview/search3-next-candidate/visual-search/' not in visual, 'wrong_visual_route_guard')
    native_graph(root / 'payload', rewritten=True)
    return files


class Site(CreateSite):
    def receipt(self, q):
        return self.parent / ('.search3-design-v2-update-receipt-' + str(q['deploy_run']))

    def write_owner(self, value, q):
        p = self.parent / ('.search3-design-v2-update-owner-' + str(q['deploy_run']))
        fd = os.open(p, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(fd, 'wb') as out:
            out.write(json_bytes(value)); out.flush(); os.fsync(out.fileno())
        p.replace(self.owner)

    def activate(self, q):
        validate(q); predecessor(q.get('before'))
        archive = Path(q['archive'])
        need(re.fullmatch(r'/tmp/search3-design-v2-update\.[A-Za-z0-9_-]+\.tar\.gz', str(archive)), 'upload_path')
        need(archive.is_file() and not archive.is_symlink() and digest(archive.read_bytes()) == q['archive_sha256'], 'upload_digest')
        stage = self.parent / ('.search3-design-v2-update-stage-' + str(q['deploy_run']))
        with self.lock():
            need(self.snapshot() == q['before'], 'predecessor_changed')
            receipt = self.receipt(q)
            need(not receipt.exists() and not receipt.is_symlink(), 'previous_outcome_unknown')
            receipt.mkdir(mode=0o700)
            (receipt / 'request.json').write_bytes(json_bytes(q))
            (receipt / 'predecessor-owner.json').write_bytes(json_bytes(q['before']['owner']))
            try:
                safe_extract(archive, stage)
                files = verify(stage, q)
                changes = predecessor_delta(inventory(self.target), files)
                shutil.copyfile(stage / 'control/manifest.json', receipt / 'manifest.json')
                need(self.snapshot() == q['before'], 'predecessor_changed')
                self.target.rename(receipt / 'predecessor')
                (stage / 'payload').rename(self.target)
                self.write_owner({'run': q['deploy_run'], 'source': q['source_sha'],
                    'digest': q['target_digest'], 'status': 'activated'}, q)
                need(inventory(self.target) == files and self.protected() == q['before']['protected'], 'activation_readback')
                return {'status': 'activated', 'files': len(files), 'predecessor_retained': True,
                    'changed_payload_files': list(changes)}
            finally:
                if stage.exists(): shutil.rmtree(stage)
                archive.unlink(missing_ok=True)

    def finish(self, q, rollback=False):
        validate(q); predecessor(q.get('before'))
        with self.lock():
            receipt = self.receipt(q)
            need(receipt.is_dir() and not receipt.is_symlink(), 'outcome_unknown')
            need((receipt / 'request.json').read_bytes() == json_bytes(q), 'request_changed')
            need(not (receipt / 'result.json').exists(), 'transaction_already_finished')
            owner = json.loads(self.owner.read_text()) if self.owner.exists() else None
            backup = receipt / 'predecessor'
            current = digest(json_bytes(inventory(self.target))) if self.target.exists() else None
            new_owner = {'run': q['deploy_run'], 'source': q['source_sha'],
                'digest': q['target_digest'], 'status': 'activated'}
            if rollback:
                if owner == PREDECESSOR_OWNER and current == PREDECESSOR_DIGEST and not backup.exists():
                    need(self.snapshot() == q['before'], 'outcome_unknown')
                    result = {'status': 'not_activated'}
                else:
                    need(owner in (PREDECESSOR_OWNER, new_owner), 'different_owner')
                    need(backup.is_dir() and not backup.is_symlink()
                         and digest(json_bytes(inventory(backup))) == PREDECESSOR_DIGEST
                         and (receipt / 'predecessor-owner.json').read_bytes() == json_bytes(PREDECESSOR_OWNER), 'backup_changed')
                    need(current in (None, q['target_digest']), 'modified_target')
                    if current is None: need(owner == PREDECESSOR_OWNER, 'outcome_unknown')
                    if self.target.exists(): self.target.rename(receipt / 'rejected')
                    backup.rename(self.target)
                    self.write_owner(PREDECESSOR_OWNER, q)
                    need(self.snapshot() == q['before'], 'rollback_readback')
                    result = {'status': 'rolled_back_to_exact_predecessor', 'source_sha': PREDECESSOR_SOURCE}
            else:
                need(owner == new_owner and current == q['target_digest'], 'different_owner_or_modified_target')
                need(backup.is_dir() and not backup.is_symlink()
                     and digest(json_bytes(inventory(backup))) == PREDECESSOR_DIGEST, 'backup_changed')
                need(self.protected() == q['before']['protected'], 'production_or_existing_preview_drift')
                self.write_owner({**new_owner, 'status': 'published'}, q)
                result = {'status': 'published', 'source_sha': q['source_sha'], 'route': ROUTE,
                    'existing_preview_unchanged': True, 'predecessor_retained': True}
            (receipt / 'result.json').write_bytes(json_bytes(result))
            return result


def main():
    need(len(sys.argv) == 3 and len(sys.argv[2]) < 131072, 'usage')
    action = sys.argv[1]; q = json.loads(sys.argv[2]); site = Site(Path.home() / 'www/anytoour.ru')
    if action == 'snapshot': result = site.snapshot()
    elif action in ('bind', 'unbind'): result = site.binding(q, action == 'unbind')
    elif action == 'upload':
        fd, name = tempfile.mkstemp(prefix='search3-design-v2-update.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
        result = {'archive': name}
    elif action == 'activate': result = site.activate(q)
    elif action in ('complete', 'rollback'): result = site.finish(q, action == 'rollback')
    else: raise ValueError('unknown_action')
    print(json.dumps(result, sort_keys=True))


if __name__ == '__main__': main()
