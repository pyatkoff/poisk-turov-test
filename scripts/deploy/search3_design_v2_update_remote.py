"""One reviewed update of the existing separate design preview; no other target.

The predecessor is the terminal final-reference restoration, not a caller-selected path.
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
from search3_design_v2_preview_remote import (Site as CreateSite, ROUTE, INVARIANTS,
    SOURCE_SHA as ORIGINAL_SOURCE, BUILD_RUN as ORIGINAL_BUILD, ARTIFACT_ID as ORIGINAL_ARTIFACT,
    SOURCE_ZIP_SHA256 as ORIGINAL_ZIP, digest, inventory, json_bytes, need, safe_extract, safe_name)

ORIGINAL_PUBLISH_RUN = 38173086001
PREDECESSOR_SOURCE = '634d1c97fd7bf499107d82036588f4bd77176d93'
PREDECESSOR_RUN = 38191104207
PREDECESSOR_DIGEST = '6a2bf4b0e7defb8b19b8b1a62cf23b9eddcd28d49321bfee13edfc4e275cffcc'
COMPLETED_SOURCES = (ORIGINAL_SOURCE, '950c9b5c0a00430c5b5a711d1b95bc04449d069d', PREDECESSOR_SOURCE)
COMPLETED_PUBLISH_RUNS = (ORIGINAL_PUBLISH_RUN, 38180461319, PREDECESSOR_RUN)
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


def validate(q):
    for k in ('source_sha', 'source_tree', 'release_sha'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{40}', q[k]), 'invalid_' + k)
    need(q['source_sha'] not in COMPLETED_SOURCES, 'new_source_required')
    for k in ('artifact_id', 'build_run', 'deploy_run'):
        need(type(q.get(k)) is int and 0 < q[k] < 10**15, 'invalid_' + k)
    for k in ('archive_sha256', 'manifest_sha256', 'payload_sha256', 'source_ZIP_sha256', 'target_digest'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{64}', q[k]), 'invalid_' + k)
    need(q.get('attempt') == 1 and q['deploy_run'] not in COMPLETED_PUBLISH_RUNS, 'no_replay')
    need(type(q.get('file_count')) is int and q['file_count'] == 845, 'invalid_file_count')


def predecessor(before):
    need(isinstance(before, dict) and before.get('target') == PREDECESSOR_DIGEST
         and before.get('owner') == PREDECESSOR_OWNER, 'exact_published_predecessor_required')


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
         and bool(changes) and set(changes) <= set(SOURCE_CHANGES), 'unreviewed_source_delta')
    need(m.get('derived_from') == {'artifact_id': q['artifact_id'], 'build_run': q['build_run'],
        'ZIP_sha256': q['source_ZIP_sha256'], 'transform': 'design-v2-route-and-ledger-v1',
        'changed_payload_files': list(ROUTE_CHANGES), 'changed_source_payload_files': changes,
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
    need(visual.count("if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-design-v2-candidate/visual-search/')) {") == 1
         and '/_preview/search3-next-candidate/visual-search/' not in visual, 'wrong_visual_route_guard')
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
                shutil.copyfile(stage / 'control/manifest.json', receipt / 'manifest.json')
                need(self.snapshot() == q['before'], 'predecessor_changed')
                self.target.rename(receipt / 'predecessor')
                (stage / 'payload').rename(self.target)
                self.write_owner({'run': q['deploy_run'], 'source': q['source_sha'],
                    'digest': q['target_digest'], 'status': 'activated'}, q)
                need(inventory(self.target) == files and self.protected() == q['before']['protected'], 'activation_readback')
                return {'status': 'activated', 'files': len(files), 'predecessor_retained': True}
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
