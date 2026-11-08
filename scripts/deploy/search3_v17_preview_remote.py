"""Create-only transactions for two fixed Search3 preview routes.

No configurable destination, production writes, source execution or overwrite.
An existing/unknown target requires a separate reviewed update, never takeover.
"""
from __future__ import annotations
import ctypes
from enum import Enum
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import sys
import tarfile
import tempfile
from contextlib import contextmanager

class Preview(Enum):
    NEXT = 'next'
    EARLY_INTENT = 'early-intent'

    @property
    def stem(self):
        return 'search3-' + self.value

    @property
    def name_on_site(self):
        return self.stem + '-candidate'

    @property
    def route(self):
        return '/_preview/' + self.name_on_site + '/'


ROUTE = Preview.NEXT.route
NAME = Preview.NEXT.name_on_site
EARLY_INTENT_PINS = {
    'source_sha': '8aeee2a4fef23024b95aa5a809ed6a1bd6fa2950',
    'source_tree': '65d3165fb2ea0d2a8a2c584b11f45055eeea8159',
    'build_run': 42471104556,
    'artifact_id': 13612859870,
    'source_ZIP_sha256': '7e125c03398f5b9823fb768bc7ebdb481364da6e4d3c05d9cf9b816e53f0b9c5',
}
REQUIRED = ('index.php', 'search-page-v2.php', 'poisk-turov/index.php', 'assets.php',
    'api-v2.php', 'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php',
    'lead-price-v1.php', 'lead-idempotency-v1.php', 'analytics-config.php',
    'bundle-manifest-v1.php', 'config.php')
OPTIONAL = ('robots.txt', 'sitemap.xml', '.htaccess', 'seo-config.php', 'seo-launch-slice-v1.php',
    '_preview/search3-anex-candidate/api-andromeda-search3-preview.php',
    '_preview/search3-anex-candidate/app/integrations/andromeda-selected-offer.php')
INVARIANTS = {'production_lead_delivery': False, 'preview_metrika_counter': 0,
    'production_document_root_bootstrap': False, 'external_consultant_widget': False,
    'production_metrika_changes': False, 'production_api_path': '/api-v2.php',
    'preview_lead_path': ROUTE + 'preview-lead-disabled.php'}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def json_bytes(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')) + '\n').encode()


def inventory(root):
    need(root.is_dir() and not root.is_symlink(), 'invalid_inventory_root')
    result = {}
    for p in sorted(root.rglob('*')):
        need(not p.is_symlink() and (p.is_dir() or p.is_file()), 'linked_or_special_file')
        if p.is_file():
            result[p.relative_to(root).as_posix()] = digest(p.read_bytes())
    return result


def safe_name(name):
    p = PurePosixPath(name)
    need(bool(name) and not p.is_absolute() and '..' not in p.parts and '\\' not in name
         and str(p) == name and '\x00' not in name, 'unsafe_archive_path')
    return p


def safe_extract(archive, target):
    need(not target.exists() and not target.is_symlink(), 'extraction_exists')
    with tarfile.open(archive, 'r:gz') as src:
        members = src.getmembers(); seen = set(); total = 0
        need(len(members) <= 12000, 'archive_member_limit')
        for m in members:
            name = m.name.rstrip('/'); p = safe_name(name)
            need(p.parts[0] in ('payload', 'control') and name not in seen, 'archive_inventory')
            need(m.isdir() or m.isreg(), 'archive_link_or_special')
            need(m.size <= 8 * 1024 * 1024, 'archive_file_limit')
            seen.add(name); total += m.size
        need(total <= 64 * 1024 * 1024, 'archive_total_limit')
        target.mkdir(mode=0o700)
        for m in members:
            dest = target.joinpath(*safe_name(m.name.rstrip('/')).parts)
            if m.isdir():
                dest.mkdir(mode=0o755, parents=True, exist_ok=True)
            else:
                dest.parent.mkdir(mode=0o755, parents=True, exist_ok=True)
                with src.extractfile(m) as inp, dest.open('xb') as out:
                    shutil.copyfileobj(inp, out)
                dest.chmod(0o644)


def validate(q, preview=Preview.NEXT):
    for k in ('source_sha', 'source_tree', 'release_sha'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{40}', q[k]), 'invalid_' + k)
    for k in ('artifact_id', 'build_run', 'deploy_run'):
        need(type(q.get(k)) is int and 0 < q[k] < 10**15, 'invalid_' + k)
    for k in ('archive_sha256', 'manifest_sha256', 'payload_sha256', 'source_ZIP_sha256'):
        need(isinstance(q.get(k), str) and re.fullmatch('[0-9a-f]{64}', q[k]), 'invalid_' + k)
    need(q.get('attempt') == 1, 'no_replay')
    need(type(q.get('file_count')) is int and 0 < q['file_count'] < 10000, 'invalid_file_count')
    if preview is Preview.EARLY_INTENT:
        need(all(q.get(k) == v for k, v in EARLY_INTENT_PINS.items()), 'early_intent_pins')


def verify(root, q, preview=Preview.NEXT):
    validate(q, preview)
    manifest_bytes = (root / 'control/manifest.json').read_bytes()
    checksums = (root / 'control/payload.sha256').read_bytes()
    need(digest(manifest_bytes) == q['manifest_sha256'] and digest(checksums) == q['payload_sha256'], 'control_hash')
    m = json.loads(manifest_bytes)
    need(m.get('schema_version') == 1 and m.get('target') == preview.stem + '-preview'
         and m.get('route') == preview.route and m.get('source_sha') == q['source_sha']
         and m.get('source_tree_sha') == q['source_tree'], 'manifest_identity')
    need(m.get('invariants') == {**INVARIANTS, 'preview_lead_path': preview.route + 'preview-lead-disabled.php'},
         'manifest_invariants')
    need(m.get('derived_from') == {'artifact_id': q['artifact_id'], 'build_run': q['build_run'],
                                 'ZIP_sha256': q['source_ZIP_sha256'], 'transform': preview.value + '-route-v1'}, 'derivation_identity')
    expected = {}
    for f in m['files']:
        name = str(safe_name(f['path']))
        need(name not in expected and re.fullmatch('[0-9a-f]{64}', f['sha256']), 'duplicate_or_bad_hash')
        need((root / 'payload' / name).stat().st_size == f['size'], 'file_size')
        expected[name] = f['sha256']
    need(len(expected) == m['file_count'] == q['file_count'], 'file_count')
    need(inventory(root / 'payload') == expected, 'payload_hashes')
    need(checksums == ''.join(f'{expected[n]}  ./{n}\n' for n in sorted(expected)).encode(), 'checksums_inventory')
    need(all(p not in expected for p in ('config.php', 'api.php', 'api-v2.php', 'lead-adapter.php',
        'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php')), 'protected_payload_endpoint')
    if preview is Preview.EARLY_INTENT:
        need(not any(name.startswith('visual-search/') for name in expected), 'next_visual_present')
    need(all(p in expected for p in ('.htaccess', 'preview-lead-disabled.php', 'search-page-v2.php',
        'data/hotel-details-read-v1.php', 'data/hotel-presentation-read-v1.php', 'poisk-turov/index.php')), 'missing_guard_or_catalog')
    need('metrikaCounter=0' in (root / 'payload/search-page-v2.php').read_text(), 'counter_not_zero')
    need('#^(' + preview.route.rstrip('/') + ')(?:/|$)#' in (root / 'payload/site-path-v1.php').read_text(), 'wrong_route_helper')
    return expected


def rename_new(source, target):
    # On Linux, plain rename can replace an empty directory created after the
    # preflight. Refuse any target at the atomic rename itself.
    current_directory = -100  # AT_FDCWD
    no_replace = 1  # RENAME_NOREPLACE
    rename = ctypes.CDLL(None, use_errno=True).renameat2
    rename.argtypes = [ctypes.c_int, ctypes.c_char_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_uint]
    if rename(current_directory, os.fsencode(source), current_directory, os.fsencode(target), no_replace):
        error = ctypes.get_errno()
        raise OSError(error, os.strerror(error), str(target))


class Site:
    def __init__(self, root, preview=Preview.NEXT):
        self.preview = preview
        self.root = Path(root); self.parent = self.root / '_preview'; self.target = self.parent / preview.name_on_site
        for p in (self.root, self.parent):
            need(p.is_dir() and not p.is_symlink(), 'invalid_site_root')
        need(self.root.name == 'anytoour.ru', 'wrong_project')
        self.owner = self.parent / ('.' + preview.stem + '-owner')
        need(not self.target.is_symlink() and not self.owner.is_symlink(), 'target_or_owner_link')

    @contextmanager
    def lock(self):
        p = self.parent / ('.' + self.preview.stem + '-lock')
        busy = ['site', 'local', 'v17', 'v18']
        if self.preview is Preview.EARLY_INTENT:
            busy += ['next', 'design-v2']
        exists = os.path.lexists if self.preview is Preview.EARLY_INTENT else Path.exists
        need(not any(exists(self.parent / ('.search3-' + name + '-lock')) for name in busy),
             'existing_preview_busy')
        p.mkdir(mode=0o700)
        try:
            yield
        finally:
            p.rmdir()

    def protected(self):
        result = {}
        for name in REQUIRED + OPTIONAL:
            p = self.root / name
            need(all(not self.root.joinpath(*Path(name).parts[:n]).is_symlink()
                     for n in range(1, len(Path(name).parts) + 1)), 'protected_link')
            if p.exists():
                need(p.is_file(), 'protected_not_file'); result[name] = digest(p.read_bytes())
            else:
                need(name not in REQUIRED, 'protected_missing'); result[name] = None
        siblings = ['search3-site-candidate', 'search3-candidate', 'search3-local-candidate', 'search3-v17-candidate', 'search3-v18-candidate']
        if self.preview is Preview.EARLY_INTENT:
            siblings += [Preview.NEXT.name_on_site, 'search3-design-v2-candidate']
        for name in siblings:
            p = self.parent / name
            if self.preview is Preview.EARLY_INTENT:
                need(not p.is_symlink(), 'protected_link')
            result['preview:' + name] = digest(json_bytes(inventory(p))) if p.exists() else None
        return result

    def snapshot(self):
        return {'protected': self.protected(),
                'target': digest(json_bytes(inventory(self.target))) if self.target.exists() else None,
                'owner': json.loads(self.owner.read_text()) if self.owner.exists() else None}

    def binding(self, q, remove=False):
        name = q['name']; nonce = q['nonce']
        need(re.fullmatch(re.escape(self.preview.stem) + r'-bind-[1-9][0-9]*-[0-9a-f]{24}\.txt', name)
             and re.fullmatch('[0-9a-f]{64}', nonce), 'binding_input')
        p = self.parent / name
        if remove:
            need(p.is_file() and not p.is_symlink() and p.read_text() == nonce, 'binding_owner'); p.unlink()
        else:
            fd = os.open(p, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o644)
            with os.fdopen(fd, 'w') as out:
                out.write(nonce)
        return {'status': 'removed' if remove else 'bound'}

    def receipt(self, q):
        return self.parent / ('.' + self.preview.stem + '-receipt-' + str(q['deploy_run']))

    def recorded_activation(self, q):
        receipt = self.receipt(q)
        need(receipt.is_dir() and not receipt.is_symlink(), 'missing_activation_receipt')
        request = receipt / 'request.json'
        activation = receipt / 'activation.json'
        need(all(path.is_file() and not path.is_symlink() for path in (request, activation)),
             'missing_activation_record')
        need(json.loads(request.read_bytes()) == q, 'different_activation_request')
        record = json.loads(activation.read_bytes())
        target = self.target.lstat()
        need(self.target.is_dir() and not self.target.is_symlink()
             and (target.st_dev, target.st_ino) == (record['device'], record['inode']), 'different_target')
        return record

    def activate(self, q):
        validate(q, self.preview); archive = Path(q['archive'])
        need(re.fullmatch('/tmp/' + re.escape(self.preview.stem) + r'\.[A-Za-z0-9_-]+\.tar\.gz', str(archive)), 'upload_path')
        need(archive.is_file() and not archive.is_symlink() and digest(archive.read_bytes()) == q['archive_sha256'], 'upload_digest')
        stage = self.parent / ('.' + self.preview.stem + '-stage-' + str(q['deploy_run']))
        with self.lock():
            need(self.snapshot() == q['before'], 'predecessor_changed')
            need(not self.target.exists() and not self.target.is_symlink() and not self.owner.exists(), 'create_only_target_exists')
            receipt = self.receipt(q)
            need(not os.path.lexists(receipt) and not os.path.lexists(stage), 'previous_outcome_unknown')
            receipt.mkdir(mode=0o700)
            (receipt / 'request.json').write_bytes(json_bytes(q))
            try:
                safe_extract(archive, stage)
                files = verify(stage, q, self.preview)
                need(self.snapshot() == q['before'], 'predecessor_changed')
                payload_digest = digest(json_bytes(files))
                owner = json_bytes({'run': q['deploy_run'], 'source': q['source_sha'],
                    'digest': payload_digest, 'status': 'activated'})
                if self.preview is Preview.EARLY_INTENT:
                    # Prepare a complete owner record before making the directory visible.
                    owner_stage = stage / 'owner.json'
                    owner_stage.write_bytes(owner); owner_stage.chmod(0o600)
                    staged = (stage / 'payload').stat()
                    # Rename preserves the identity; matching bytes alone do not prove ownership.
                    (receipt / 'activation.json').write_bytes(json_bytes({
                        'device': staged.st_dev, 'inode': staged.st_ino, 'digest': payload_digest}))
                    rename_new(stage / 'payload', self.target)
                    try:
                        rename_new(owner_stage, self.owner)
                    except OSError:
                        # No other operation may claim these bytes. Leave a receipt
                        # and quarantine only the exact tree installed by this run.
                        self.recorded_activation(q)
                        if inventory(self.target) == files:
                            rename_new(self.target, receipt / 'failed-payload')
                        raise
                else:
                    (stage / 'payload').rename(self.target)
                    self.owner.write_bytes(owner); self.owner.chmod(0o600)
                shutil.copyfile(stage / 'control/manifest.json', receipt / 'manifest.json')
                need(inventory(self.target) == files and self.protected() == q['before']['protected'], 'activation_readback')
                return {'status': 'activated', 'files': len(files)}
            finally:
                if stage.exists():
                    shutil.rmtree(stage)
                archive.unlink(missing_ok=True)

    def finish(self, q, rollback=False):
        validate(q, self.preview)
        with self.lock():
            need(not self.owner.is_symlink(), 'target_or_owner_link')
            owner = json.loads(self.owner.read_text()) if self.owner.exists() else None
            recover = self.preview is Preview.EARLY_INTENT and rollback and os.path.lexists(self.target)
            if owner is None:
                if not recover:
                    need(self.snapshot() == q['before'], 'outcome_unknown'); return {'status': 'not_activated'}
                expected = self.recorded_activation(q)['digest']
            else:
                need(owner.get('run') == q['deploy_run'] and owner.get('source') == q['source_sha'], 'different_owner')
                expected = owner['digest']
                if self.preview is Preview.EARLY_INTENT:
                    need(self.recorded_activation(q)['digest'] == expected, 'different_activation_digest')
            need(digest(json_bytes(inventory(self.target))) == expected, 'modified_target')
            if rollback:
                # Only remove the exact newly created target; never any predecessor.
                need(q['before']['target'] is None and q['before']['owner'] is None, 'not_new_target')
                shutil.rmtree(self.target)
                if owner is not None:
                    self.owner.unlink()
                need(self.snapshot() == q['before'], 'rollback_readback')
                result = {'status': 'rolled_back_to_absence'}
            else:
                need(self.protected() == q['before']['protected'], 'production_or_existing_preview_drift')
                owner['status'] = 'published'; self.owner.write_bytes(json_bytes(owner))
                result = {'status': 'published', 'source_sha': q['source_sha'], 'route': self.preview.route, 'existing_preview_unchanged': True}
            (self.receipt(q) / 'result.json').write_bytes(json_bytes(result))
            return result


def main():
    need(len(sys.argv) in (3, 4) and len(sys.argv[2]) < 131072, 'usage')
    need(len(sys.argv) == 3 or sys.argv[3] == Preview.EARLY_INTENT.value, 'fixed_target')
    preview = Preview.EARLY_INTENT if len(sys.argv) == 4 else Preview.NEXT
    action = sys.argv[1]; q = json.loads(sys.argv[2]); site = Site(Path.home() / 'www/anytoour.ru', preview)
    if action == 'snapshot':
        result = site.snapshot()
    elif action in ('bind', 'unbind'):
        result = site.binding(q, action == 'unbind')
    elif action == 'upload':
        fd, name = tempfile.mkstemp(prefix=preview.stem + '.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
        result = {'archive': name}
    elif action == 'activate':
        result = site.activate(q)
    elif action in ('complete', 'rollback'):
        result = site.finish(q, action == 'rollback')
    else:
        raise ValueError('unknown_action')
    print(json.dumps(result, sort_keys=True))


if __name__ == '__main__':
    main()
