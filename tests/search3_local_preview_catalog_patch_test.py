"""Fixed catalog composition contracts and an explicit native-artifact check.

The default suite is offline. --native-artifact reads only the fixed GitHub
artifact/blobs, renders the actual composed payload locally, and exercises the
existing transaction against temporary filesystem fixtures, never an SSH host.
"""
import base64
import copy
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import sys
import tarfile
import tempfile
import unittest
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_local_preview_catalog_patch as patch
import search3_local_preview_update_remote as remote


def request():
    return {'composition': patch.COMPOSITION, 'operation': 'update',
        'source_sha': patch.DONOR_SOURCE_SHA, 'source_tree': patch.DONOR_SOURCE_TREE,
        'release_sha': 'dbd36be23294e9591c11bbd897bc7fa0ac526355',
        'previous_source_sha': patch.BASE_SOURCE_SHA, 'artifact_id': patch.DONOR_ARTIFACT_ID,
        'build_run': patch.DONOR_BUILD_RUN, 'deploy_run': 999999, 'attempt': 1,
        **patch.donor_artifact_controls()}


def source_tree():
    entries = [{'path': name, 'mode': '100644', 'type': 'blob', 'sha': sha, 'size': 1}
               for name, sha in patch.RECIPE_BLOBS.items()]
    entries += [{'path': f'v2/f{n:04}.txt', 'mode': '100644', 'type': 'blob',
                 'sha': 'a' * 40, 'size': 0} for n in range(831)]
    entries += [{'path': f'outside/f{n:04}.txt', 'mode': '100644', 'type': 'blob',
                 'sha': 'b' * 40, 'size': 0} for n in range(1037)]
    return {'sha': patch.BASE_SOURCE_TREE, 'truncated': False, 'tree': entries}


class MetadataAPI:
    def __init__(self):
        self.commit = {'sha': patch.BASE_SOURCE_SHA, 'tree': {'sha': patch.BASE_SOURCE_TREE}}
        self.tree = source_tree(); self.calls = []

    def get(self, path):
        self.calls.append(path)
        if path == '/git/commits/' + patch.BASE_SOURCE_SHA:
            return copy.deepcopy(self.commit)
        if path == '/git/trees/' + patch.BASE_SOURCE_TREE + '?recursive=1':
            return copy.deepcopy(self.tree)
        raise AssertionError('Unexpected network path: ' + path)


class OfflineContracts(unittest.TestCase):
    def setUp(self):
        remote.select_target('local')

    def test_fixed_request_and_donor_controls(self):
        patch.checked_request(request(), patch.DONOR_ZIP_SHA256)
        for key, value in [('composition', 'generic-patch'), ('source_sha', 'a' * 40),
            ('source_tree', 'b' * 40), ('previous_source_sha', 'c' * 40),
            ('build_run', 1), ('artifact_id', 1), ('deploy_run', True), ('attempt', True),
            ('attempt', 2), ('operation', 'create'), ('preview_target', 'next'),
            ('release_sha', 'main'), ('manifest_sha256', '0' * 64),
            ('payload_sha256', '0' * 64), ('archive_sha256', '0' * 64), ('file_count', 833),
            ('source_ZIP_sha256', '0' * 64)]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                patch.checked_request({**request(), key: value}, patch.DONOR_ZIP_SHA256)
        with self.assertRaises(ValueError):
            patch.checked_request(request(), '0' * 64)

    def test_predecessor_tree_metadata_is_bounded_and_fixed(self):
        api = MetadataAPI(); result = patch.source_entries(api)
        self.assertEqual(len(result), 831)
        self.assertEqual(api.calls, ['/git/commits/' + patch.BASE_SOURCE_SHA,
                                     '/git/trees/' + patch.BASE_SOURCE_TREE + '?recursive=1'])
        changes = [('truncated', True), ('sha', '0' * 40), ('tree', [])]
        for key, value in changes:
            api = MetadataAPI(); api.tree[key] = value
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, 'catalog_source_tree'):
                patch.source_entries(api)
        for value in ('0' * 40, None):
            api = MetadataAPI(); api.commit['tree']['sha'] = value
            with self.subTest(commit_tree=value), self.assertRaisesRegex(ValueError, 'catalog_source_commit'):
                patch.source_entries(api)

    def test_tree_rejects_recipe_change_link_duplicate_path_and_size(self):
        for field, value in [('mode', '120000'), ('sha', 'x'), ('size', True),
                             ('size', 8 * 1024 * 1024 + 1), ('path', 'v2/../escape')]:
            api = MetadataAPI(); api.tree['tree'][2][field] = value
            with self.subTest(field=field), self.assertRaises(ValueError):
                patch.source_entries(api)
        api = MetadataAPI(); api.tree['tree'][0]['sha'] = '0' * 40
        with self.assertRaisesRegex(ValueError, 'catalog_recipe_code'):
            patch.source_entries(api)
        api = MetadataAPI(); api.tree['tree'][3]['path'] = api.tree['tree'][2]['path']
        with self.assertRaisesRegex(ValueError, 'catalog_tree_duplicate_or_type'):
            patch.source_entries(api)
        api = MetadataAPI(); api.tree['tree'][3]['type'] = 'commit'
        with self.assertRaisesRegex(ValueError, 'catalog_tree_duplicate_or_type'):
            patch.source_entries(api)

    def test_git_blob_requires_exact_bytes_identity_size_and_encoding(self):
        data = b'known metadata bytes\x00\xff\n'
        sha = hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()
        expected = {'sha': sha, 'size': len(data)}
        good = {**expected, 'encoding': 'base64', 'content': base64.b64encode(data).decode() + '\n'}
        self.assertEqual(patch.decode_blob(good, expected), data)
        for key, value in [('sha', 'f' * 40), ('size', True), ('size', len(data) + 1),
            ('encoding', 'utf-8'), ('content', '%%%'),
            ('content', base64.b64encode(b'x' * len(data)).decode())]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                patch.decode_blob({**good, key: value}, expected)

    def test_exact_isolation_never_accepts_missing_or_duplicated_source_literal(self):
        with tempfile.TemporaryDirectory() as work:
            path = Path(work) / 'source.php'
            for value in ('missing', 'needle needle'):
                path.write_text(value)
                with self.subTest(value=value), self.assertRaisesRegex(ValueError, 'catalog_isolation_drift'):
                    patch.replace_once(path, 'needle', 'replacement')
                self.assertEqual(path.read_text(), value)
            path.write_text('before needle after')
            patch.replace_once(path, 'needle', 'replacement')
            self.assertEqual(path.read_text(), 'before replacement after')

    def test_non_original_source_controls_fail_before_control_file_write(self):
        with tempfile.TemporaryDirectory() as work:
            root = Path(work); payload = root / 'payload'; payload.mkdir()
            for n in range(833):
                (payload / f'f{n:04}.txt').write_bytes(b'not original')
            with self.assertRaisesRegex(ValueError, 'catalog_original_source_controls'):
                patch.source_controls(root)
            self.assertEqual(list((root / 'control').iterdir()), [])

    def test_non_original_local_archive_cannot_supply_a_base(self):
        with tempfile.TemporaryDirectory() as work:
            with self.assertRaisesRegex(ValueError, 'catalog_original_local_archive'):
                patch.verify_base(Path(work), {}, b'different compressed bytes')

    def test_helper_pin_is_checked_before_git_recovery(self):
        with tempfile.TemporaryDirectory() as work:
            root = Path(work); donor = root / 'donor'; target = donor / 'payload' / patch.HELPER_PATH
            target.parent.mkdir(parents=True); target.write_bytes(b'x' * patch.AFTER_HELPER_SIZE)
            with mock.patch.object(patch, 'verify_payload'), mock.patch.object(patch, 'recover_base') as recover:
                with self.assertRaisesRegex(ValueError, 'catalog_donor_helper'):
                    patch.derive(donor, root / 'out', request(), patch.DONOR_ZIP_SHA256, MetadataAPI())
                recover.assert_not_called()

    def test_work_paths_reject_existing_output_and_source_symlink(self):
        with tempfile.TemporaryDirectory() as work:
            root = Path(work); source = root / 'source'; source.mkdir(); output = root / 'out'; output.mkdir()
            with self.assertRaisesRegex(ValueError, 'catalog_work_paths'):
                patch.derive(source, output, request(), patch.DONOR_ZIP_SHA256, MetadataAPI())
            linked = root / 'linked'; linked.symlink_to(source)
            with self.assertRaisesRegex(ValueError, 'catalog_work_paths'):
                patch.derive(linked, root / 'new', request(), patch.DONOR_ZIP_SHA256, MetadataAPI())

    def test_complete_archive_has_deterministic_members_and_metadata(self):
        with tempfile.TemporaryDirectory() as work:
            root = Path(work); (root / 'control').mkdir(); (root / 'payload').mkdir()
            (root / 'control/manifest.json').write_bytes(b'{}\n')
            (root / 'control/payload.sha256').write_bytes(b'fixture\n')
            (root / 'payload/first.txt').write_bytes(b'first')
            (root / 'payload/second.txt').write_bytes(b'second')
            files = patch.inventory(root / 'payload')
            archive = patch.pack(root, files); self.assertEqual(archive, patch.pack(root, files))
            with tarfile.open(fileobj=io.BytesIO(archive), mode='r:gz') as tar:
                self.assertEqual(tar.getnames(), ['control/manifest.json', 'control/payload.sha256',
                                                'payload/first.txt', 'payload/second.txt'])
                for member in tar.getmembers():
                    self.assertTrue(member.isfile())
                    self.assertEqual((member.mode, member.uid, member.gid, member.mtime), (0o644, 0, 0, 0))
                    self.assertEqual(tar.extractfile(member).read(), (root / member.name).read_bytes())

    def test_composition_record_is_fixed_fresh_and_shared_with_remote(self):
        first = patch.composition_record(); second = patch.composition_record()
        self.assertEqual(first, remote.composition_record())
        self.assertEqual(first['predecessor']['deploy_run'], 36565525012)
        self.assertEqual(first['delta']['path'], patch.HELPER_PATH)
        first['delta']['path'] = '../elsewhere'
        self.assertEqual(patch.composition_record(), second)


def native_suite():
    """Only explicitly requested native mode creates this network-using test."""
    class NativeArtifact(unittest.TestCase):
        def test_exact_artifact_two_compositions_render_and_local_transactions(self):
            from search3_preview_publish import Github, prepare_zip
            import search3_local_preview as original_local
            raw_api = Github(os.environ.get('GH_TOKEN', ''))
            run = raw_api.get('/actions/runs/' + str(patch.DONOR_BUILD_RUN))
            self.assertEqual((run['head_sha'], run['run_attempt'], run['event'], run['status'], run['conclusion']),
                (patch.DONOR_SOURCE_SHA, 1, 'pull_request', 'completed', 'success'))
            self.assertEqual(run['path'], '.github/workflows/build-search3-whole-site-preview.yml')
            commit = raw_api.get('/git/commits/' + patch.DONOR_SOURCE_SHA)
            self.assertEqual(commit['tree']['sha'], patch.DONOR_SOURCE_TREE)
            meta = raw_api.get('/actions/artifacts/' + str(patch.DONOR_ARTIFACT_ID))
            self.assertFalse(meta['expired'])
            self.assertEqual(meta['workflow_run']['id'], patch.DONOR_BUILD_RUN)
            self.assertEqual(meta['workflow_run']['head_sha'], patch.DONOR_SOURCE_SHA)
            self.assertEqual(meta['name'], f'search3-site-preview-{patch.DONOR_SOURCE_SHA}-{patch.DONOR_BUILD_RUN}-1')
            self.assertEqual(meta['digest'], 'sha256:' + patch.DONOR_ZIP_SHA256)
            packed = raw_api.download(patch.DONOR_ARTIFACT_ID)
            self.assertEqual(patch.digest(packed), patch.DONOR_ZIP_SHA256)

            class RecordedAPI:
                def __init__(self): self.calls = []; self.values = {}
                def get(self, path):
                    value = raw_api.get(path)
                    self.calls.append(path); self.values[path] = value
                    return value

            api = RecordedAPI()
            with tempfile.TemporaryDirectory(prefix='catalog-native-') as work:
                root = Path(work); q = request()
                q['deploy_run'] = int(os.environ.get('GITHUB_RUN_ID', '999999'))
                prepare_zip(packed, q, patch.DONOR_SOURCE_TREE, patch.DONOR_ZIP_SHA256, root / 'original')
                original = root / 'original/release'; remote.select_target('local')
                ready, archive = patch.derive(original, root / 'candidate', q, patch.DONOR_ZIP_SHA256, api)
                self.assertEqual(len(api.calls), 52)
                again, second = patch.derive(original, root / 'second', q, patch.DONOR_ZIP_SHA256, api)
                self.assertEqual(len(api.calls), 104)
                self.assertEqual(ready, again); self.assertEqual(archive, second)
                files = remote.verify(root / 'candidate', ready)
                self.assertEqual(len(files), 833)
                self.assertEqual(patch.digest(patch.json_bytes(files)), patch.CANDIDATE_INVENTORY_SHA256)
                predecessor = {**files, patch.HELPER_PATH: patch.BEFORE_HELPER_SHA256}
                self.assertEqual(patch.digest(patch.json_bytes(predecessor)), patch.BASE_INVENTORY_SHA256)
                self.assertEqual(ready['donor_artifact_controls'], patch.donor_artifact_controls())
                original_local.render_checks(root / 'candidate')
                tree = api.values['/git/trees/' + patch.BASE_SOURCE_TREE + '?recursive=1']
                row = next(x for x in tree['tree'] if x['path'] == 'v2/' + patch.HELPER_PATH)
                old_helper = patch.decode_blob(api.values['/git/blobs/' + row['sha']], row)
                self.assertEqual(patch.digest(old_helper), patch.BEFORE_HELPER_SHA256)

                for rollback in (False, True):
                    site_root = root / ('rollback-fixture' if rollback else 'complete-fixture') / 'anytoour.ru'
                    neighbor = site_root / '_preview/search3-site-candidate'; neighbor.mkdir(parents=True)
                    (neighbor / 'keep').write_bytes(b'unchanged neighbor')
                    for name in remote.REQUIRED:
                        path = site_root / name; path.parent.mkdir(parents=True, exist_ok=True)
                        path.write_text('protected:' + name)
                    site = remote.Site(site_root)
                    shutil.copytree(root / 'candidate/payload', site.target)
                    (site.target / patch.HELPER_PATH).write_bytes(old_helper)
                    site.owner.write_bytes(patch.json_bytes({'run': patch.BASE_DEPLOY_RUN,
                        'source': patch.BASE_SOURCE_SHA, 'digest': patch.BASE_INVENTORY_SHA256,
                        'status': 'published'}))
                    before = site.snapshot()
                    fd, name = tempfile.mkstemp(prefix='search3-local-update.', suffix='.tar.gz', dir='/tmp')
                    os.close(fd); upload = Path(name); upload.write_bytes(archive)
                    try:
                        staged = {**ready, 'archive': name, 'before': before}
                        activation = site.activate_update(staged)
                        self.assertEqual(activation['files'], 833)
                        self.assertEqual(site.snapshot()['target'], patch.CANDIDATE_INVENTORY_SHA256)
                        self.assertEqual(site.protected(), before['protected'])
                        result = site.finish_update(staged, rollback)
                        if rollback:
                            self.assertEqual(result['status'], 'rolled_back_to_predecessor')
                            self.assertEqual(site.snapshot(), before)
                        else:
                            self.assertEqual(result['status'], 'published')
                            self.assertEqual(site.snapshot()['owner']['composition'], patch.composition_record())
                            backup = site.receipt(staged) / 'previous-payload'
                            self.assertEqual(patch.digest(patch.json_bytes(patch.inventory(backup))),
                                             patch.BASE_INVENTORY_SHA256)
                    finally:
                        upload.unlink(missing_ok=True)
                print(json.dumps({'status': 'CATALOG_PATCH_NATIVE_PASS', 'files': 833,
                    'predecessor_get_calls_per_composition': 52, 'compositions_equal': True,
                    'base_inventory_sha256': patch.BASE_INVENTORY_SHA256,
                    'candidate_inventory_sha256': patch.CANDIDATE_INVENTORY_SHA256,
                    'candidate_archive_sha256': ready['archive_sha256'],
                    'candidate_manifest_sha256': ready['manifest_sha256'],
                    'candidate_payload_sha256': ready['payload_sha256'],
                    'render_checks': 'passed', 'local_fixture_complete': 'passed',
                    'local_fixture_rollback': 'passed', 'server_connections': 0}, sort_keys=True))
    return unittest.defaultTestLoader.loadTestsFromTestCase(NativeArtifact)


if __name__ == '__main__':
    if sys.argv[1:] == ['--native-artifact']:
        result = unittest.TextTestRunner(verbosity=2).run(native_suite())
        raise SystemExit(0 if result.wasSuccessful() else 1)
    unittest.main()
