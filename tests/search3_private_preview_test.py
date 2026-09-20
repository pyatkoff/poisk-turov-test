"""Private gateway, exact draft provenance and create-only rollback acceptance."""
import copy
import http.client
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import unittest
import urllib.parse

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/deploy'))
import search3_private_preview as pub
import search3_private_preview_remote as remote
from search3_local_preview_remote import REQUIRED
from search3_local_preview_test import fixture, request


class PrivatePreview(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.home = Path(self.temp.name)
        self.root = self.home / 'www/anytoour.ru'
        (self.root / '_preview/search3-site-candidate').mkdir(parents=True)
        for name in REQUIRED:
            p = self.root / name
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text('protected:' + name)
        self.auth = Path(os.environ['SEARCH3_OWNER_AUTH_REFERENCE']).resolve()
        self.assertEqual(remote.digest(self.auth.read_bytes()), remote.AUTH_HASH)
        auth = self.root / remote.AUTH
        auth.parent.mkdir(parents=True)
        shutil.copyfile(self.auth, auth)
        self.account = self.home / '.anytoour-anex/review-owner'
        self.account.mkdir(mode=0o700, parents=True)
        self.password = 'fixture-owner-password-2026'
        self.php("require $argv[1]; AnexReviewOwnerAuth::bootstrap($argv[2],$argv[3],str_repeat('a',64),time()+600); "
                 "(new AnexReviewOwnerAuth($argv[2],$argv[3]))->enroll(str_repeat('a',64),$argv[4]);",
                 str(auth), str(self.account), str(self.root), self.password)
        self.source = self.home / 'original'
        fixture(self.source)
        (self.source / 'payload/poisk-turov/index.php').write_text('<?php echo \'<form id="tourSearch">Private tour search</form>\';')
        (self.source / 'payload/search3-entry-v1.css').write_text('body{color:#2743cb}')
        self.q, self.archive = pub.derive(self.source, self.home / 'derived', request(), 'd'*64)
        self.site = remote.Site(self.root)
        self.upload = None
        self.process = None

    def php(self, code, *args):
        return subprocess.run(['php', '-n', '-r', code, *args], check=True, capture_output=True, text=True).stdout

    def tearDown(self):
        if self.process:
            self.process.terminate()
            self.process.wait(timeout=5)
        if self.upload:
            self.upload.unlink(missing_ok=True)
        self.temp.cleanup()

    def stage(self):
        fd, filename = tempfile.mkstemp(prefix='search3-private.', suffix='.tar.gz', dir='/tmp')
        os.close(fd)
        self.upload = Path(filename)
        self.upload.write_bytes(self.archive)
        q = {**self.q, 'archive': filename, 'before': self.site.snapshot()}
        self.site.activate(q)
        return q

    def test_deterministic_derivation_and_private_transaction(self):
        other, archive = pub.derive(self.source, self.home / 'again', request(), 'd'*64)
        self.assertEqual((other, archive), (self.q, self.archive))
        before = remote.inventory(self.source / 'payload')
        after = remote.inventory(self.home / 'derived/payload')
        self.assertEqual({n for n in before if before[n] != after[n]}, {'site-path-v1.php'})
        q = self.stage()
        self.assertEqual(set(remote.inventory(self.site.target)), {'index.php', '.htaccess'})
        self.assertFalse((self.site.target / 'search3-entry-v1.css').exists())
        self.assertEqual(self.site.private_inventory()['payload'], after)
        self.assertEqual(self.site.finish(q)['status'], 'published_private')
        with self.assertRaisesRegex(ValueError, 'create_only'):
            next_q = {**q, 'before': self.site.snapshot()}
            self.upload.write_bytes(self.archive)
            self.site.activate(next_q)
        self.assertEqual(self.site.finish(q, rollback=True)['status'], 'rolled_back_to_absence')
        self.assertEqual(self.site.snapshot(), q['before'])

    def test_tampered_control_and_payload_rejected(self):
        gate = self.home / 'derived/control/gate.php'
        gate.write_text('<?php echo "unprotected";')
        with self.assertRaisesRegex(ValueError, 'gate_hash'):
            remote.verify(self.home / 'derived', self.q)

    def test_protected_gate_over_http(self):
        self.stage()
        router = self.home / 'router.php'
        router.write_text("<?php $_SERVER['HTTP_HOST']='anytoour.ru'; $_SERVER['HTTPS']='on'; "
            "require $_SERVER['DOCUMENT_ROOT'].'/_preview/search3-v17-candidate/index.php';")
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        self.process = subprocess.Popen(['php', '-n', '-S', '127.0.0.1:'+str(port), '-t', str(self.root), str(router)],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for _ in range(40):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        cookie = ''

        def get(path, method='GET', form=None):
            nonlocal cookie
            headers = {'Cookie': cookie}
            body = None
            if form is not None:
                body = urllib.parse.urlencode(form)
                headers.update({'Content-Type': 'application/x-www-form-urlencoded', 'Origin': 'https://anytoour.ru'})
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
            conn.request(method, remote.ROUTE + path, body, headers)
            response = conn.getresponse()
            hdr = dict(response.getheaders())
            data = response.read().decode()
            status = response.status
            conn.close()
            if 'Set-Cookie' in hdr:
                cookie = hdr['Set-Cookie'].split(';', 1)[0]
            self.assertIn('no-store', hdr.get('Cache-Control', ''))
            return status, hdr, data

        status, headers, html = get('poisk-turov/')
        self.assertEqual(status, 401)
        self.assertNotIn('id="tourSearch"', html)
        self.assertIn('; secure;', headers.get('Set-Cookie', '').lower())
        self.assertIn('HttpOnly', headers['Set-Cookie'])
        self.assertIn('SameSite=Strict', headers['Set-Cookie'])
        self.assertIn('path='+remote.ROUTE, headers['Set-Cookie'])
        csrf = re.search('name="csrf" value="([a-f0-9]+)"', html)[1]
        original_cookie = cookie
        for path in ('search3-entry-v1.css', 'search-page-v2.php', 'bundle-v1.php', 'config.php'):
            self.assertEqual(get(path)[0], 401)
        self.assertEqual(get('_login', 'POST', {'password': self.password, 'csrf': 'bad'})[0], 403)
        self.assertEqual(get('_login', 'POST', {'password': self.password, 'csrf': csrf})[0], 303)
        self.assertNotEqual(cookie, original_cookie, 'login must rotate the session')
        self.assertIn('id="tourSearch"', get('poisk-turov/')[2])
        self.assertEqual(get('search3-entry-v1.css')[2], 'body{color:#2743cb}')
        self.assertEqual(get('search-page-v2.php')[0], 403)
        self.assertEqual(get('data/search3-local-results-read-v1.php')[0], 404)
        for path in ('../config.php', '%2e%2e/config.php', '.htaccess'):
            self.assertEqual(get(path)[0], 404)
        state = json.loads((self.account / 'owner.json').read_bytes())
        state['credential_version'] = 'e'*32
        (self.account / 'owner.json').write_text(json.dumps(state))
        self.assertEqual(get('poisk-turov/')[0], 401, 'credential rotation retires private sessions')


class Authorization(unittest.TestCase):
    def test_exact_owner_command(self):
        event = {'repository': {'full_name': pub.REPO, 'id': 1345518271},
            'sender': {'id': 226193297, 'login': 'pyatkoff'}, 'action': 'created', 'issue': {'number': 2530},
            'comment': {'user': {'id': 226193297}, 'author_association': 'OWNER',
                'body': pub.PREFIX + 'a'*40 + ' ' + 'b'*40 + ' 456 123'}}
        env = {'GITHUB_REPOSITORY': pub.REPO, 'GITHUB_REF': 'refs/heads/main', 'GITHUB_ACTOR': 'pyatkoff',
            'GITHUB_TRIGGERING_ACTOR': 'pyatkoff', 'GITHUB_ACTOR_ID': '226193297', 'GITHUB_RUN_ATTEMPT': '1',
            'GITHUB_EVENT_NAME': 'issue_comment', 'GITHUB_RUN_ID': '789'}
        self.assertEqual(pub.checked_request(event, env)['artifact_id'], 123)
        for key, val in [('GITHUB_ACTOR', 'other'), ('GITHUB_REF', 'refs/heads/work'), ('GITHUB_RUN_ATTEMPT', '2')]:
            with self.assertRaises(ValueError):
                pub.checked_request(event, {**env, key: val})
        event['comment']['body'] += ' /poisk-turov/'
        with self.assertRaises(ValueError):
            pub.checked_request(event, env)

    def test_draft_provenance_cannot_expand_to_server_or_API_changes(self):
        q = request()
        checks = [{'name': n, 'head_sha': q['source_sha'], 'status': 'completed', 'conclusion': 'success',
            'app': {'slug': 'github-actions'}} for n in ('guard', 'build-preview-artifact')]
        class API:
            def __init__(self):
                self.files = [{'filename': 'v2/index.php', 'status': 'modified'}]
            def get(self, path):
                if path == '/git/ref/heads/main': return {'object': {'sha': 'f'*40}}
                if path.startswith('/git/ref/heads/'): return {'object': {'sha': q['release_sha']}}
                if path == '/pulls/3214': return {'state': 'open', 'user': {'id': 226193297},
                    'head': {'sha': q['source_sha'], 'ref': 'feat/search3-v17-entry-migration-20260920', 'repo': {'full_name': pub.REPO}},
                    'base': {'ref': pub.RELEASE}}
                if path.startswith('/compare/'): return {'status': 'ahead', 'files': self.files}
                if path.startswith('/git/commits/'): return {'tree': {'sha': q['source_tree']}}
                if path.startswith('/actions/runs/'): return {'head_sha': q['source_sha'],
                    'path': '.github/workflows/build-search3-whole-site-preview.yml', 'event': 'pull_request',
                    'status': 'completed', 'conclusion': 'success', 'run_attempt': 1,
                    'repository': {'full_name': pub.REPO}, 'head_repository': {'full_name': pub.REPO}}
                if path.startswith('/commits/'): return {'total_count': 2, 'check_runs': checks}
                return {'expired': False, 'workflow_run': {'id': q['build_run'], 'head_sha': q['source_sha']},
                    'name': f"search3-site-preview-{q['source_sha']}-{q['build_run']}-1", 'digest': 'sha256:'+'d'*64}
        api = API()
        self.assertEqual(pub.verify_private_provenance(api, q, 'f'*40), ('c'*40, 'd'*64))
        for filename in ('scripts/deploy/search3_preview_publish.py', 'v2/api-v2.php', '.github/workflows/build-search3-whole-site-preview.yml'):
            api.files = [{'filename': filename, 'status': 'modified'}]
            with self.assertRaisesRegex(ValueError, 'bounded_source_diff'):
                pub.verify_private_provenance(api, q, 'f'*40)
        api.files = [{'filename': 'v2/index.php', 'status': 'modified'}]
        checks[0]['conclusion'] = 'failure'
        with self.assertRaisesRegex(ValueError, 'not_green'):
            pub.verify_private_provenance(api, q, 'f'*40)


if __name__ == '__main__':
    unittest.main()
