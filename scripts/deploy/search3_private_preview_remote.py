"""Create-only private candidate. Payload never enters the web document root."""
from __future__ import annotations
from contextlib import contextmanager
import json
import os
from pathlib import Path
import re
import shutil
import sys
import tempfile
from search3_local_preview_remote import (Site as PublicSite, digest, inventory,
    json_bytes, need, safe_extract, safe_name, validate)

ROUTE = '/_preview/search3-v17-candidate/'
NAME = 'search3-v17-candidate'
PRIVATE = '.anytoour-search3-v17'
AUTH = '_preview/search3-anex-candidate/app/admin/anex-review/owner-auth.php'
AUTH_HASH = '6f81a21490f641a9310dbf4594d1d95d43727d88989ee3f5dfb70d8db1c45cab'
PUBLIC_INDEX = b"<?php\ndeclare(strict_types=1);\nrequire dirname(__DIR__, 4) . '/.anytoour-search3-v17/control/gate.php';\n"
PUBLIC_HTACCESS = b"Options -Indexes\nDirectoryIndex index.php\nRewriteEngine On\nRewriteRule ^index\\.php$ - [L]\nRewriteRule ^ index.php [END]\n"
INVARIANTS = {'production_lead_delivery': False, 'preview_metrika_counter': 0,
    'production_document_root_bootstrap': False, 'external_consultant_widget': False,
    'production_metrika_changes': False, 'production_api_path': '/api-v2.php',
    'preview_lead_path': ROUTE + 'preview-lead-disabled.php', 'authentication_required': True,
    'payload_outside_document_root': True}


def verify(root, q):
    validate(q)
    manifest = (root / 'control/manifest.json').read_bytes()
    checksums = (root / 'control/payload.sha256').read_bytes()
    need(digest(manifest) == q['manifest_sha256'] and digest(checksums) == q['payload_sha256'], 'control_hash')
    need(digest((root / 'control/gate.php').read_bytes()) == q['gate_sha256'], 'gate_hash')
    need((root / 'control/public-index.php').read_bytes() == PUBLIC_INDEX
         and (root / 'control/public.htaccess').read_bytes() == PUBLIC_HTACCESS, 'public_gateway_only')
    need(set(inventory(root / 'control')) == {'manifest.json', 'payload.sha256', 'gate.php', 'public-index.php', 'public.htaccess'}, 'control_inventory')
    m = json.loads(manifest)
    need(m.get('schema_version') == 1 and m.get('target') == 'search3-private-preview'
         and m.get('route') == ROUTE and m.get('source_sha') == q['source_sha']
         and m.get('source_tree_sha') == q['source_tree'] and m.get('invariants') == INVARIANTS, 'manifest_identity')
    need(m.get('derived_from') == {'artifact_id': q['artifact_id'], 'build_run': q['build_run'],
        'ZIP_sha256': q['source_ZIP_sha256'], 'transform': 'private-route-v1'}, 'derivation_identity')
    expected = {}
    for item in m['files']:
        name = str(safe_name(item['path']))
        need(name not in expected and re.fullmatch('[0-9a-f]{64}', item['sha256']), 'manifest_file')
        need((root / 'payload' / name).stat().st_size == item['size'], 'file_size')
        expected[name] = item['sha256']
    need(len(expected) == m['file_count'] == q['file_count'] and inventory(root / 'payload') == expected, 'payload_hashes')
    need(checksums == ''.join(f'{expected[n]}  ./{n}\n' for n in sorted(expected)).encode(), 'checksum_inventory')
    need(all(n not in expected for n in ('config.php', 'api.php', 'api-v2.php', 'lead-adapter.php',
        'lead-adapter-v2.php', 'lead-bridge-v1.php', 'lead-receiver-v1.php')), 'protected_payload_endpoint')
    need('metrikaCounter=0' in (root / 'payload/search-page-v2.php').read_text(), 'counter_not_zero')
    need('#^(/_preview/search3-v17-candidate)(?:/|$)#' in (root / 'payload/site-path-v1.php').read_text(), 'private_route')
    return expected


class Site(PublicSite):
    def __init__(self, root):
        super().__init__(root)
        self.home = self.root.parent.parent
        self.target = self.parent / NAME
        self.private = self.home / PRIVATE
        self.owner = self.home / (PRIVATE + '-owner.json')
        for p in (self.home, self.target, self.private, self.owner):
            need(not p.is_symlink(), 'private_path_link')
        need(self.root == self.home / 'www/anytoour.ru', 'fixed_project_root')

    @contextmanager
    def lock(self):
        p = self.home / (PRIVATE + '-lock')
        p.mkdir(mode=0o700)
        try:
            yield
        finally:
            p.rmdir()

    def protected(self):
        result = super().protected()
        local = self.parent / 'search3-local-candidate'
        result['preview:search3-local-candidate'] = digest(json_bytes(inventory(local))) if local.exists() else None
        auth = self.root / AUTH
        need(auth.is_file() and auth.resolve() == auth and digest(auth.read_bytes()) == AUTH_HASH, 'existing_owner_auth_version')
        account = self.home / '.anytoour-anex/review-owner'
        need(account.resolve() == account and account.is_dir() and account.stat().st_mode & 0o777 == 0o700, 'existing_owner_account')
        for name in ('owner.json', 'owner.lock'):
            p = account / name
            need(p.is_file() and p.resolve() == p and p.stat().st_mode & 0o777 == 0o600
                 and p.stat().st_nlink == 1 and p.stat().st_size < 16384, 'existing_owner_state')
        state = json.loads((account / 'owner.json').read_bytes())
        need(state.get('schema') == 1 and isinstance(state.get('password_hash'), str)
             and state.get('setup_hash') is None and state.get('setup_expires_at') == 0, 'owner_not_enrolled')
        result['owner_auth_source'] = AUTH_HASH
        return result

    def private_inventory(self):
        if not self.private.exists():
            return None
        need(self.private.is_dir() and not self.private.is_symlink(), 'private_root')
        need({p.name for p in self.private.iterdir()} <= {'payload', 'control', 'sessions'}, 'private_top_level')
        # Session files change on legitimate requests; code and payload remain immutable.
        return {'payload': inventory(self.private / 'payload'), 'control': inventory(self.private / 'control')}

    def snapshot(self):
        return {'protected': self.protected(), 'target': inventory(self.target) if self.target.exists() else None,
            'private': self.private_inventory(), 'owner': json.loads(self.owner.read_bytes()) if self.owner.exists() else None}

    def activate(self, q):
        validate(q)
        archive = Path(q['archive'])
        need(re.fullmatch(r'/tmp/search3-private\.[A-Za-z0-9_-]+\.tar\.gz', str(archive)), 'upload_path')
        need(archive.is_file() and not archive.is_symlink() and digest(archive.read_bytes()) == q['archive_sha256'], 'upload_digest')
        stage = self.home / (PRIVATE + '-stage-' + str(q['deploy_run']))
        public_stage = self.parent / ('.search3-v17-stage-' + str(q['deploy_run']))
        with self.lock():
            need(self.snapshot() == q['before'], 'predecessor_changed')
            need(all(q['before'][k] is None for k in ('target', 'private', 'owner')), 'create_only_target_exists')
            need(not stage.exists() and not public_stage.exists() and not public_stage.is_symlink(), 'stage_exists')
            safe_extract(archive, stage)
            try:
                files = verify(stage, q)
                (stage / 'sessions').mkdir(mode=0o700)
                public_stage.mkdir(mode=0o755)
                (public_stage / 'index.php').write_bytes(PUBLIC_INDEX)
                (public_stage / '.htaccess').write_bytes(PUBLIC_HTACCESS)
                owner = {'run': q['deploy_run'], 'source': q['source_sha'], 'status': 'activating',
                    'public': inventory(public_stage), 'private': {'payload': files, 'control': inventory(stage / 'control')}}
                need(self.snapshot() == q['before'], 'predecessor_changed')
                fd = os.open(self.owner, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
                with os.fdopen(fd, 'wb') as out:
                    out.write(json_bytes(owner))
                stage.rename(self.private)
                public_stage.rename(self.target)
                need(self.private_inventory() == owner['private'] and inventory(self.target) == owner['public'], 'activation_readback')
                need(self.protected() == q['before']['protected'], 'protected_drift')
                return {'status': 'activated_private', 'payload_files': len(files), 'public_files': 2}
            finally:
                for p in (stage, public_stage):
                    if p.exists():
                        shutil.rmtree(p)
                archive.unlink(missing_ok=True)

    def finish(self, q, rollback=False):
        validate(q)
        with self.lock():
            if not self.owner.exists():
                need(self.snapshot() == q['before'], 'outcome_unknown')
                return {'status': 'not_activated'}
            owner = json.loads(self.owner.read_bytes())
            need(owner.get('run') == q['deploy_run'] and owner.get('source') == q['source_sha'], 'different_owner')
            if self.target.exists():
                need(inventory(self.target) == owner['public'], 'public_target_modified')
            if self.private.exists():
                need(self.private_inventory() == owner['private'], 'private_target_modified')
            need(self.protected() == q['before']['protected'], 'protected_drift')
            if rollback:
                need(all(q['before'][k] is None for k in ('target', 'private', 'owner')), 'not_new_target')
                for p in (self.target, self.private):
                    if p.exists():
                        shutil.rmtree(p)
                self.owner.unlink()
                need(self.snapshot() == q['before'], 'rollback_readback')
                return {'status': 'rolled_back_to_absence'}
            need(self.target.is_dir() and self.private.is_dir(), 'incomplete_activation')
            owner['status'] = 'published_private'
            self.owner.write_bytes(json_bytes(owner))
            return {'status': 'published_private', 'route': ROUTE, 'source_sha': q['source_sha'], 'production_unchanged': True}


def main():
    need(len(sys.argv) == 3 and len(sys.argv[2]) < 131072, 'usage')
    action = sys.argv[1]
    q = json.loads(sys.argv[2])
    site = Site(Path.home() / 'www/anytoour.ru')
    if action == 'snapshot':
        result = site.snapshot()
    elif action in ('bind', 'unbind'):
        result = site.binding(q, action == 'unbind')
    elif action == 'upload':
        fd, name = tempfile.mkstemp(prefix='search3-private.', suffix='.tar.gz', dir='/tmp')
        os.close(fd)
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
