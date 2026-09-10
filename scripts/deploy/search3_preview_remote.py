"""Fixed-target Search3 preview transaction; never installs production files.

CLI root is deliberately not configurable. Pure helpers/Site are also used by
local filesystem tests; no source from a release artifact is executed here.
"""
from __future__ import annotations
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

ROUTE = '/_preview/search3-site-candidate/'
REQUIRED_PROTECTED = ('index.php', 'search-page-v2.php', 'poisk-turov/index.php',
    'assets.php', 'api-v2.php', 'lead-adapter-v2.php', 'lead-bridge-v1.php',
    'lead-receiver-v1.php', 'lead-price-v1.php', 'lead-idempotency-v1.php',
    'analytics-config.php', 'bundle-manifest-v1.php', 'config.php')
OPTIONAL_PROTECTED = ('robots.txt', 'sitemap.xml', '.htaccess', 'seo-config.php',
    'seo-launch-slice-v1.php', '_preview/search3-anex-candidate/api-andromeda-search3-preview.php',
    '_preview/search3-anex-candidate/app/integrations/andromeda-selected-offer.php')
INVARIANTS = {'production_lead_delivery': False, 'preview_metrika_counter': 0,
    'production_document_root_bootstrap': False, 'external_consultant_widget': False,
    'production_metrika_changes': False, 'production_api_path': '/api-v2.php',
    'preview_lead_path': ROUTE + 'preview-lead-disabled.php'}


def need(ok, reason):
    if not ok:
        raise ValueError(reason)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def json_bytes(value):
    return (json.dumps(value, sort_keys=True, ensure_ascii=False, separators=(',', ':')) + '\n').encode()


def safe_name(name):
    p = PurePosixPath(name)
    need(bool(name) and not p.is_absolute() and '..' not in p.parts and '\\' not in name
         and str(p) == name and '\x00' not in name, 'unsafe_path')
    return p


def inventory(root):
    need(root.is_dir() and not root.is_symlink(), 'invalid_inventory_root')
    result = {}
    for path in sorted(root.rglob('*')):
        need(not path.is_symlink(), 'symlink_in_payload')
        if path.is_file():
            result[path.relative_to(root).as_posix()] = digest(path.read_bytes())
        else:
            need(path.is_dir(), 'special_file_in_payload')
    return result


def safe_extract(archive, target):
    need(not target.exists(), 'extraction_target_exists')
    with tarfile.open(archive, 'r:gz') as src:
        members = src.getmembers()
        need(len(members) <= 12000, 'archive_member_limit')
        seen = set(); total = 0
        for m in members:
            name = m.name.rstrip('/'); p = safe_name(name)
            need(p.parts[0] in ('payload', 'control') and name not in seen, 'archive_path_or_duplicate')
            need(m.isdir() or m.isreg(), 'archive_link_or_special_file')
            need(m.size <= 8 * 1024 * 1024, 'archive_file_limit')
            seen.add(name); total += m.size
        need(total <= 64 * 1024 * 1024, 'archive_size_limit')
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


def verify_payload(root, request):
    control = root / 'control'
    need(digest((control / 'manifest.json').read_bytes()) == request['manifest_sha256'], 'manifest_digest')
    need(digest((control / 'payload.sha256').read_bytes()) == request['payload_sha256'], 'checksums_digest')
    manifest = json.loads((control / 'manifest.json').read_text())
    need(manifest.get('schema_version') == 1 and manifest.get('target') == 'search3-whole-site-preview'
         and manifest.get('route') == ROUTE and manifest.get('source_sha') == request['source_sha']
         and manifest.get('source_tree_sha') == request['source_tree'], 'manifest_identity')
    need(manifest.get('invariants') == INVARIANTS, 'manifest_invariants')
    entries = manifest['files']; expected = {}
    for entry in entries:
        name = str(safe_name(entry['path']))
        need(name not in expected and re.fullmatch('[0-9a-f]{64}', entry['sha256']), 'manifest_duplicate_or_hash')
        expected[name] = entry['sha256']
    need(len(expected) == manifest['file_count'] == request['file_count'], 'manifest_file_count')
    actual = inventory(root / 'payload')
    need(actual == expected, 'payload_file_hashes')
    for entry in entries:
        need((root / 'payload' / entry['path']).stat().st_size == entry['size'], 'payload_size')
    checksum_text = ''.join(f'{expected[p]}  ./{p}\n' for p in sorted(expected))
    need((control / 'payload.sha256').read_text() == checksum_text, 'checksum_inventory')
    for absent in ('config.php', 'api.php', 'api-v2.php', 'lead-adapter.php', 'lead-adapter-v2.php',
                   'lead-bridge-v1.php', 'lead-receiver-v1.php'):
        need(absent not in actual, 'protected_endpoint_in_payload')
    for present in ('.htaccess', 'preview-lead-disabled.php', 'poisk-turov-old/index.php', 'search-page-v2.php'):
        need(present in actual, 'missing_preview_guard')
    need('metrikaCounter=0' in (root / 'payload/search-page-v2.php').read_text(), 'preview_counter')
    return actual


def validate_request(q):
    for name in ('source_sha', 'source_tree', 'release_sha'):
        need(isinstance(q.get(name), str) and re.fullmatch('[0-9a-f]{40}', q[name]), 'invalid_' + name)
    for name in ('artifact_id', 'build_run', 'deploy_run'):
        need(type(q.get(name)) is int and 0 < q[name] < 10**15, 'invalid_' + name)
    need(q.get('attempt') == 1, 'replay_requires_new_authorization')
    for name in ('archive_sha256', 'manifest_sha256', 'payload_sha256'):
        need(isinstance(q.get(name), str) and re.fullmatch('[0-9a-f]{64}', q[name]), 'invalid_' + name)
    need(type(q.get('file_count')) is int and 0 < q['file_count'] < 10000, 'invalid_file_count')


class Site:
    def __init__(self, root):
        self.root = Path(root); self.parent = self.root / '_preview'
        self.target = self.parent / 'search3-site-candidate'
        for p in (self.root, self.parent, self.target):
            need(p.is_dir() and not p.is_symlink(), 'invalid_site_directory')
        need(self.root.name == 'anytoour.ru' and self.target.resolve() == self.root.resolve() / '_preview/search3-site-candidate', 'wrong_project')
        self.owner = self.parent / '.search3-site-owner'
        need(not self.owner.is_symlink(), 'owner_symlink')

    @contextmanager
    def lock(self):
        path = self.parent / '.search3-site-lock'
        path.mkdir(mode=0o700)  # Existing/unknown lock is never stolen.
        try:
            yield
        finally:
            path.rmdir()

    def protected(self):
        result = {}
        for name in REQUIRED_PROTECTED + OPTIONAL_PROTECTED:
            path = self.root / name
            # Every path component must stay within the fixed project without links.
            need(all(not self.root.joinpath(*Path(name).parts[:n]).is_symlink()
                     for n in range(1, len(Path(name).parts) + 1)), 'protected_symlink')
            if path.exists():
                need(path.is_file(), 'protected_not_file'); result[name] = digest(path.read_bytes())
            else:
                need(name not in REQUIRED_PROTECTED, 'protected_file_missing'); result[name] = None
        return result

    def snapshot(self):
        owner = json.loads(self.owner.read_text()) if self.owner.exists() else None
        return {'protected': self.protected(), 'target_digest': digest(json_bytes(inventory(self.target))), 'owner': owner}

    def paths(self, q):
        suffix = f"{q['deploy_run']}-1"
        return {k: self.parent / f'.search3-site-{k}-{suffix}' for k in ('stage', 'backup', 'failed', 'receipt', 'owner-backup')}

    def owns(self, q):
        owner = json.loads(self.owner.read_text()) if self.owner.is_file() else {}
        return all(owner.get(k) == q[k] for k in ('source_sha', 'artifact_id', 'deploy_run', 'payload_sha256'))

    def activate(self, q):
        validate_request(q)
        archive = Path(q['archive'])
        need(re.fullmatch(r'/tmp/search3-site\.[A-Za-z0-9_\-]+\.tar\.gz', str(archive)), 'upload_path')
        need(archive.is_file() and not archive.is_symlink() and digest(archive.read_bytes()) == q['archive_sha256'], 'upload_digest')
        p = self.paths(q)
        with self.lock():
            need(self.snapshot() == q['before'], 'predecessor_changed')
            for path in p.values():
                need(not path.exists() and not path.is_symlink(), 'previous_outcome_unknown')
            safe_extract(archive, p['stage'])
            saved = False; owner_saved = False; activated = False
            try:
                files = verify_payload(p['stage'], q)
                p['receipt'].mkdir(mode=0o700)
                shutil.copyfile(p['stage'] / 'control/manifest.json', p['receipt'] / 'manifest.json')
                (p['receipt'] / 'before.json').write_bytes(json_bytes(q['before']))
                (p['receipt'] / 'request.json').write_bytes(json_bytes({k:v for k,v in q.items() if k not in ('before', 'archive')}))
                if self.owner.exists():
                    self.owner.rename(p['owner-backup']); owner_saved = True
                self.target.rename(p['backup']); saved = True
                (p['stage'] / 'payload').rename(self.target); activated = True
                owner = {k: q[k] for k in ('source_sha', 'release_sha', 'artifact_id', 'build_run', 'deploy_run', 'payload_sha256')}
                owner.update({'schema_version': 1, 'deploy_attempt': 1, 'status': 'activated'})
                self.owner.write_bytes(json_bytes(owner)); self.owner.chmod(0o600)
                need(inventory(self.target) == files and self.protected() == q['before']['protected'], 'activation_readback')
                return {'status': 'activated', 'source_sha': q['source_sha'], 'file_count': len(files)}
            except Exception:
                if activated:
                    self.target.rename(p['failed'])
                if saved:
                    p['backup'].rename(self.target)
                if self.owner.exists() and (saved or owner_saved):
                    self.owner.unlink()
                if owner_saved:
                    p['owner-backup'].rename(self.owner)
                raise
            finally:
                if p['stage'].exists():
                    shutil.rmtree(p['stage'])
                archive.unlink(missing_ok=True)

    def complete(self, q):
        validate_request(q); p = self.paths(q)
        with self.lock():
            need(self.owns(q), 'completion_owner_changed')
            expected = {x['path']: x['sha256'] for x in json.loads((p['receipt'] / 'manifest.json').read_text())['files']}
            need(inventory(self.target) == expected, 'completion_payload_changed')
            need(self.protected() == q['before']['protected'], 'production_or_INT_changed')
            owner = json.loads(self.owner.read_text()); owner['status'] = 'published'
            self.owner.write_bytes(json_bytes(owner))
            (p['receipt'] / 'completed.json').write_bytes(json_bytes(owner))
            return {'status': 'published', 'source_sha': q['source_sha'], 'artifact_id': q['artifact_id'], 'rollback_retained': p['backup'].is_dir()}

    def rollback(self, q):
        validate_request(q); p = self.paths(q)
        with self.lock():
            if not self.owns(q):
                need(self.snapshot() == q['before'], 'rollback_owner_or_outcome_unknown')
                return {'status': 'not_activated'}
            expected = {x['path']: x['sha256'] for x in json.loads((p['receipt'] / 'manifest.json').read_text())['files']}
            need(inventory(self.target) == expected, 'rollback_refuses_modified_target')
            need(p['backup'].is_dir() and not p['backup'].is_symlink() and not p['failed'].exists(), 'rollback_backup_missing')
            self.target.rename(p['failed']); p['backup'].rename(self.target); self.owner.unlink()
            if p['owner-backup'].exists():
                need(not p['owner-backup'].is_symlink(), 'rollback_owner_symlink')
                p['owner-backup'].rename(self.owner)
            need(self.snapshot() == q['before'], 'rollback_readback')
            return {'status': 'rolled_back'}

    def binding(self, q, remove=False):
        name = q['name']; nonce = q['nonce']
        need(re.fullmatch(r'site-binding-[1-9][0-9]*-1-[0-9a-f]{24}\.txt', name) and re.fullmatch('[0-9a-f]{64}', nonce), 'binding_input')
        path = self.target / name
        if remove:
            need(path.is_file() and not path.is_symlink() and path.read_text() == nonce, 'binding_owner')
            path.unlink()
        else:
            fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o644)
            with os.fdopen(fd, 'w') as out:
                out.write(nonce)
        return {'status': 'removed' if remove else 'bound'}


def main():
    need(len(sys.argv) == 3, 'usage')
    action = sys.argv[1]; q = json.loads(sys.argv[2]); site = Site(Path.home() / 'www/anytoour.ru')
    if action == 'snapshot':
        result = site.snapshot()
    elif action == 'upload':
        fd, name = tempfile.mkstemp(prefix='search3-site.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
        result = {'archive': name}
    elif action in ('bind', 'unbind'):
        result = site.binding(q, remove=action == 'unbind')
    elif action in ('activate', 'complete', 'rollback'):
        result = getattr(site, action)(q)
    else:
        raise ValueError('unknown_action')
    print(json.dumps(result, sort_keys=True))


if __name__ == '__main__':
    main()
