"""Fixed next-preview update and cross-target isolation; no network or server access."""
import copy, os, tempfile, unittest
from pathlib import Path
import search3_local_preview_update_test as local_tests
from search3_local_preview_update_test import request, PREVIOUS
import search3_local_preview_update as pub
import search3_local_preview_update_remote as remote
import search3_v17_preview as target


class NextUpdateContracts(local_tests.UpdateContracts):
    def setUp(self):
        remote.select_target('local')
        super().setUp()
        self.q, self.archive = target.derive(self.source, self.root/'next-derived',
            {**request(), 'preview_target':'next'}, 'd'*64)
        self.event['comment']['body'] = self.event['comment']['body'].replace(pub.PREFIX, pub.NEXT_PREFIX)
        root = self.site.root
        (root/'_preview/search3-next-candidate').mkdir()
        (root/'_preview/search3-next-candidate/old.txt').write_text('published predecessor')
        (root/'_preview/search3-v18-candidate').mkdir()
        (root/'_preview/search3-v18-candidate/keep').write_text('v18')
        remote.select_target('next')
        self.site = remote.Site(root)
        old_digest = remote.digest(remote.json_bytes(remote.inventory(self.site.target)))
        self.site.owner.write_bytes(remote.json_bytes({'run':111,'source':PREVIOUS,
            'digest':old_digest,'status':'published'}))

    def tearDown(self):
        remote.select_target('local')
        super().tearDown()

    def staged(self):
        fd, name = tempfile.mkstemp(prefix='search3-next-update.', suffix='.tar.gz', dir='/tmp')
        os.close(fd); self.upload = Path(name); self.upload.write_bytes(self.archive)
        return {**self.q, 'archive':name, 'before':self.site.snapshot()}

    def test_owner_command(self):
        q = pub.checked_request(self.event,self.env)
        self.assertEqual(q['preview_target'],'next')
        self.assertEqual(q['previous_source_sha'],PREVIOUS)
        self.assertEqual(pub.target_functions(q)[0],target.derive)
        local = copy.deepcopy(self.event)
        local['comment']['body'] = local['comment']['body'].replace(pub.NEXT_PREFIX,pub.PREFIX)
        self.assertNotIn('preview_target',pub.checked_request(local,self.env))

    def test_command_rejects_create_replay_path_and_bad_pins(self):
        good = self.event['comment']['body']
        bad = [good+'\n', good+' /tmp/x', good.replace(pub.NEXT_PREFIX,'/create-search3-next-preview '),
               good.replace(pub.NEXT_PREFIX,'/update-search3-production-preview '),
               good.replace(' 456 ',' 0 '),good.replace('a'*40,PREVIOUS,1),good.replace(PREVIOUS,'main')]
        for body in bad:
            event=copy.deepcopy(self.event); event['comment']['body']=body
            with self.subTest(body=body), self.assertRaises(ValueError): pub.checked_request(event,self.env)

    def test_existing_lock_not_stolen(self):
        q=self.staged(); (self.site.parent/'.search3-next-lock').mkdir()
        with self.assertRaises(FileExistsError): self.site.activate_update(q)

    def test_other_preview_lock_blocks_update(self):
        q=self.staged(); (self.site.parent/'.search3-local-lock').mkdir()
        with self.assertRaisesRegex(ValueError,'existing_preview_busy'): self.site.activate_update(q)
        self.assertEqual((self.site.target/'old.txt').read_text(),'published predecessor')

    def test_binding_fixed_namespace(self):
        q={'name':'search3-next-update-bind-789-'+'e'*24+'.txt','nonce':'f'*64}
        self.site.binding(q); self.site.binding(q,True)
        for name in ('../index.php',q['name'].replace('next','local')):
            with self.assertRaises(ValueError): self.site.binding({**q,'name':name})

    def test_wrong_target_request_cannot_swap(self):
        q=self.staged()
        for value in ('local','/tmp/next','production'):
            with self.assertRaisesRegex(ValueError,'wrong_preview_target'):
                self.site.activate_update({**q,'preview_target':value})
        self.assertEqual((self.site.target/'old.txt').read_text(),'published predecessor')

    def test_cross_target_manifest_denied(self):
        local_root=self.root/'derived'
        with self.assertRaisesRegex(ValueError,'manifest_identity'):
            remote.verify(local_root,{**self.q,'preview_target':'next',
                'manifest_sha256':remote.digest((local_root/'control/manifest.json').read_bytes()),
                'payload_sha256':remote.digest((local_root/'control/payload.sha256').read_bytes())})

    def test_unknown_target_denied(self):
        for value in ('production','../local',None):
            with self.assertRaisesRegex(ValueError,'unknown_preview_target'): remote.select_target(value)


if __name__=='__main__':
    unittest.main()
