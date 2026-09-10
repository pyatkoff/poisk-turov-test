"""Exercise actual remote PHP locally; no SSH, supplier or persistent DB.

With previous/details arguments use pinned installed-source files. Without them,
predecessors are clearly synthetic filesystem fixtures. Candidate is the real #1901 handoff.
"""
import argparse
import base64
import copy
import fcntl
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', root / 'scripts/diagnostics/andromeda_detail_publish.py')
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)
parser = argparse.ArgumentParser()
parser.add_argument('handoff', type=Path)
parser.add_argument('--previous', type=Path)
parser.add_argument('--details', type=Path)
args = parser.parse_args()
wire_files = publisher.load_handoff(args.handoff)
files = {p: base64.b64decode(b) for p, b in wire_files.items()}
old_before, old_support = copy.deepcopy(publisher.BEFORE), copy.deepcopy(publisher.SUPPORT)


def source_path(path):
    return path if path.startswith('app/') else 'v2/' + path


def digest(data, kind):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest() if kind == 'git_blob' else hashlib.sha256(data).hexdigest()


class InstallTest(unittest.TestCase):
    def setUp(self):
        publisher.BEFORE = copy.deepcopy(old_before)
        publisher.SUPPORT = copy.deepcopy(old_support)
        self.temp = tempfile.TemporaryDirectory()
        self.home = Path(self.temp.name)
        self.project = self.home / 'www/anytoour.ru'
        self.target = self.project / '_preview/search3-anex-candidate'
        (self.target / 'app/integrations').mkdir(parents=True)
        self.private = self.home / '.anytoour-andromeda'
        self.private.mkdir()
        self.release = self.private / ('package-install-1901-' + publisher.SOURCE_SHA)
        self.previous = {}
        for mapping in (publisher.BEFORE, publisher.SUPPORT):
            for path, expected in mapping.items():
                if expected is None:
                    continue
                if args.previous and args.details:
                    source = args.details if path in ('api-andromeda-search3-preview.php', 'app/integrations/andromeda-selected-offer.php') else args.previous
                    data = (source / source_path(path)).read_bytes()
                    self.assertEqual(expected[1], digest(data, expected[0]), path)
                elif path == 'app/integrations/andromeda-selected-offer.php':
                    data = files[path]
                else:
                    data = ('<?php /* SYNTHETIC previous ' + path + ' */').encode()
                    mapping[path] = [expected[0], digest(data, expected[0])]
                (self.target / path).write_bytes(data)
                (self.target / path).chmod(0o644)
                self.previous[path] = data
        # Sentinels model protected files outside the exact installation paths.
        self.protected = [self.project / 'index.php', self.target / '.andromeda-private.php', self.private / 'unknown-existing-package.json']
        for p in self.protected:
            p.write_bytes(b'PRIVATE SENTINEL -- never read into a receipt')

    def tearDown(self):
        publisher.BEFORE, publisher.SUPPORT = copy.deepcopy(old_before), copy.deepcopy(old_support)
        for p in self.protected:
            self.assertEqual(p.read_bytes(), b'PRIVATE SENTINEL -- never read into a receipt')
        self.temp.cleanup()

    def run_remote(self, candidate=None):
        request = {'files': wire_files if candidate is None else candidate}
        run = subprocess.run(['php', '-d', 'allow_url_fopen=0', '-r', publisher.remote_source()],
            cwd=self.project, input=json.dumps(request).encode(), stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
        self.assertEqual(0, run.returncode, run.stderr)
        self.assertFalse(run.stderr, run.stderr)
        result = json.loads(run.stdout)
        self.assertNotIn('PRIVATE SENTINEL', run.stdout.decode())
        return result

    def unchanged(self):
        for path, data in self.previous.items():
            self.assertEqual(data, (self.target / path).read_bytes())
        for path, before in publisher.BEFORE.items():
            if before is None:
                self.assertFalse((self.target / path).exists())

    def test_install_backup_readback_and_noop(self):
        selector = self.target / 'app/integrations/andromeda-selected-offer.php'
        inode = selector.stat().st_ino
        done = self.run_remote()
        self.assertEqual('published', done['status'])
        self.assertEqual(publisher.FILES, done['sha256'])
        self.assertEqual(5, len(done['changed_paths']))
        self.assertEqual('api-andromeda-search3-preview.php', done['changed_paths'][-1])
        self.assertEqual(inode, selector.stat().st_ino)
        for path, data in files.items():
            self.assertEqual(data, (self.target / path).read_bytes())
            self.assertEqual(0o644, (self.target / path).stat().st_mode & 0o777)
        for path in done['backup_sha256']:
            if publisher.BEFORE[path] is not None:
                backup = self.release / ('previous-' + Path(path).name)
                self.assertEqual(self.previous[path], backup.read_bytes())
                self.assertEqual(0o600, backup.stat().st_mode & 0o777)
        reservation = json.loads((self.release / 'reservation.json').read_text())
        self.assertEqual(publisher.BEFORE, reservation['before'])
        self.assertEqual(publisher.SUPPORT, reservation['support'])
        self.assertEqual(done, json.loads((self.release / 'completed.json').read_text()))
        inodes = {p: (self.target / p).stat().st_ino for p in files}
        self.assertEqual('already_published', self.run_remote()['status'])
        self.assertEqual(inodes, {p: (self.target / p).stat().st_ino for p in files})
        self.assertFalse(done['selection_enabled'] or done['package_captured'] or done['quote_verified'])
        self.assertEqual(0, done['supplier_calls'] + done['database_writes'])

    def test_candidate_hash_refused_before_reservation(self):
        bad = dict(wire_files); bad['api-andromeda-search3-preview.php'] = base64.b64encode(b'bad').decode()
        self.assertEqual('candidate_hash', self.run_remote(bad)['reason'])
        self.assertFalse(self.release.exists()); self.unchanged()

    def test_extra_path_refused(self):
        bad = dict(wire_files); bad['../../index.php'] = 'YQ=='
        self.assertEqual('candidate_paths', self.run_remote(bad)['reason'])
        self.assertFalse(self.release.exists()); self.unchanged()

    def test_drift_refused_before_reservation(self):
        p = self.target / 'app/integrations/andromeda-client.php'; p.write_bytes(b'later writer')
        self.assertEqual('predecessor_changed', self.run_remote()['reason'])
        self.assertFalse(self.release.exists()); self.assertEqual(b'later writer', p.read_bytes())

    def test_new_module_already_exists_is_not_replaced(self):
        p = self.target / 'app/integrations/andromeda-package-capture.php'; p.write_bytes(b'unknown module')
        self.assertEqual('predecessor_changed', self.run_remote()['reason'])
        self.assertFalse(self.release.exists()); self.assertEqual(b'unknown module', p.read_bytes())

    def test_support_changed_refused(self):
        p = self.target / 'app/integrations/andromeda-offer-store.php'; p.write_bytes(b'unknown support')
        self.assertEqual('support_changed', self.run_remote()['reason'])
        self.assertFalse(self.release.exists())

    def test_unknown_reservation_never_replayed(self):
        self.release.mkdir(mode=0o700)
        (self.release / 'reservation.json').write_text('{"status":"unknown"}')
        self.assertEqual('previous_outcome_unknown', self.run_remote()['reason'])
        self.unchanged()

    def test_incomplete_install_never_replayed(self):
        self.assertEqual('published', self.run_remote()['status'])
        (self.release / 'completed.json').unlink()
        self.assertEqual('previous_outcome_unknown', self.run_remote()['reason'])
        for p, data in files.items():
            self.assertEqual(data, (self.target / p).read_bytes())

    def test_later_writer_after_completion_never_overwritten(self):
        self.assertEqual('published', self.run_remote()['status'])
        p = self.target / 'api-andromeda-search3-preview.php'; p.write_bytes(b'later writer')
        self.assertEqual('previous_outcome_unknown', self.run_remote()['reason'])
        self.assertEqual(b'later writer', p.read_bytes())

    def test_symlink_new_target_refused(self):
        p = self.target / 'app/integrations/andromeda-package-capture.php'; p.symlink_to(self.protected[0])
        self.assertEqual('invalid_target', self.run_remote()['reason'])
        self.assertFalse(self.release.exists())

    def test_lock_not_stolen(self):
        with (self.private / 'grouped-search-update.lock').open('w') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.assertEqual('lock_busy', self.run_remote()['reason'])
        self.assertFalse(self.release.exists()); self.unchanged()

    def test_lock_symlink_refused(self):
        (self.private / 'grouped-search-update.lock').symlink_to(self.protected[0])
        self.assertEqual('invalid_lock', self.run_remote()['reason'])
        self.assertFalse(self.release.exists()); self.unchanged()

    def test_source_handoff_hash_and_inventory(self):
        self.assertEqual(wire_files, publisher.load_handoff(args.handoff))
        import shutil
        clone = self.home / 'handoff'; shutil.copytree(args.handoff, clone)
        (clone / 'v2/api-andromeda-search3-preview.php').write_bytes(b'bad')
        with self.assertRaisesRegex(ValueError, 'candidate_hash'):
            publisher.load_handoff(clone)
        (clone / 'unexpected').write_bytes(b'extra')
        with self.assertRaisesRegex(ValueError, 'handoff_inventory'):
            publisher.load_handoff(clone)

    def test_source_handoff_wrong_receipt(self):
        import shutil
        clone = self.home / 'handoff'; shutil.copytree(args.handoff, clone)
        p = clone / 'receipt.json'; r = json.loads(p.read_text()); r['source'] = '0' * 40; p.write_text(json.dumps(r))
        with self.assertRaisesRegex(ValueError, 'handoff_receipt'):
            publisher.load_handoff(clone)


unittest.main(argv=['publisher-test'], verbosity=2)
