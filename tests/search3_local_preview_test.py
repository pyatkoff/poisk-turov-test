"""Offline authorization, deterministic derivation and create-only transactions."""
import copy
import io
import json
from pathlib import Path
import shutil
import sys
import tarfile
import tempfile
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_local_preview as pub
import search3_local_preview_remote as remote


def request():
    return dict(source_sha='a'*40, release_sha='b'*40, source_tree='c'*40, artifact_id=123,
        build_run=456, deploy_run=789, attempt=1, archive_sha256='0'*64,
        manifest_sha256='0'*64, payload_sha256='0'*64, file_count=10)


def fixture(root):
    p = root / 'payload'; c = root / 'control'; p.mkdir(parents=True); c.mkdir()
    files = {'.htaccess': 'Require all denied', 'preview-lead-disabled.php': '<?php http_response_code(403);',
        'search-page-v2.php': '<?php $metrikaCounter=0;', 'data/hotel-details-read-v1.php': '<?php',
        'data/hotel-presentation-read-v1.php': '<?php', 'poisk-turov/index.php': '<?php',
        'site-path-v1.php': "<?php // #^(/_preview/search3-site-candidate)(?:/|$)#\n"}
    for n, text in files.items():
        dest=p/n; dest.parent.mkdir(parents=True, exist_ok=True); dest.write_text(text)
    (c/'manifest.json').write_bytes(remote.json_bytes({'schema_version':1, 'source_sha':'a'*40,
        'source_tree_sha':'c'*40, 'route':pub.OLD_ROUTE, 'target':'search3-whole-site-preview'}))
    (c/'payload.sha256').write_text('original fixture')


class Contracts(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(); self.root=Path(self.tmp.name)
        self.source=self.root/'original'; fixture(self.source)
        self.q,self.archive=pub.derive(self.source,self.root/'derived',request(),'d'*64)
        self.event={'repository':{'full_name':pub.REPO,'id':1345518271}, 'sender':{'login':'pyatkoff','id':226193297},
            'action':'created','issue':{'number':2530},'comment':{'user':{'id':226193297},'author_association':'OWNER',
            'body':pub.PREFIX+'a'*40+' '+'b'*40+' 456 123'}}
        self.env={'GITHUB_REPOSITORY':pub.REPO,'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff',
            'GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_ACTOR_ID':'226193297','GITHUB_RUN_ATTEMPT':'1',
            'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ID':'789'}
        site_root=self.root/'anytoour.ru'; (site_root/'_preview/search3-site-candidate').mkdir(parents=True)
        (site_root/'_preview/search3-site-candidate/keep.txt').write_text('existing preview')
        for name in remote.REQUIRED:
            p=site_root/name; p.parent.mkdir(parents=True,exist_ok=True); p.write_text('protected:'+name)
        self.site=remote.Site(site_root)
        self.upload=None

    def tearDown(self):
        if self.upload: self.upload.unlink(missing_ok=True)
        self.tmp.cleanup()

    def staged(self):
        fd,name=tempfile.mkstemp(prefix='search3-local.',suffix='.tar.gz',dir='/tmp')
        import os
        os.close(fd); self.upload=Path(name); self.upload.write_bytes(self.archive)
        return {**self.q,'archive':name,'before':self.site.snapshot()}

    def test_owner_command(self):
        q=pub.checked_request(self.event,self.env)
        self.assertEqual(q['source_sha'],'a'*40); self.assertEqual(q['artifact_id'],123)

    def test_wrong_environment_denied(self):
        for key,value in [('GITHUB_REF','refs/heads/work'),('GITHUB_ACTOR','other'),('GITHUB_TRIGGERING_ACTOR','other'),
            ('GITHUB_ACTOR_ID','0'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','pull_request'),('GITHUB_REPOSITORY','other/repo')]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                pub.checked_request(self.event,{**self.env,key:value})

    def test_wrong_event_denied(self):
        for path,value in [(('issue','number'),996),(('issue','pull_request'),{}),(('sender','id'),1),
            (('comment','author_association'),'MEMBER'),(('comment','user'),{'id':1}),(('repository','id'),1)]:
            event=copy.deepcopy(self.event); event[path[0]][path[1]]=value
            with self.subTest(path=path), self.assertRaises(ValueError): pub.checked_request(event,self.env)

    def test_command_syntax_and_no_arbitrary_target(self):
        good=self.event['comment']['body']
        for body in [good+'\n',good+' /poisk-turov/',good.replace(pub.PREFIX,'/publish-search3-preview '),
                     good.replace(' 456 ',' 0 '),good+';id',good.replace('a'*40,'main'),good.replace(' 456 ','  456 ')]:
            event=copy.deepcopy(self.event); event['comment']['body']=body
            with self.subTest(body=body),self.assertRaises(ValueError): pub.checked_request(event,self.env)

    def test_exact_one_file_derivative_and_determinism(self):
        before=remote.inventory(self.source/'payload'); after=remote.inventory(self.root/'derived/payload')
        self.assertEqual(set(before),set(after))
        self.assertEqual({k for k in before if before[k]!=after[k]},{'site-path-v1.php'})
        q2,a2=pub.derive(self.source,self.root/'again',request(),'d'*64)
        self.assertEqual(q2,self.q); self.assertEqual(a2,self.archive)
        self.assertEqual(remote.verify(self.root/'derived',self.q),after)

    def test_unknown_transform_fails(self):
        (self.source/'payload/site-path-v1.php').write_text('<?php // different source')
        with self.assertRaisesRegex(ValueError,'route_source_drift'):
            pub.derive(self.source,self.root/'bad',request(),'d'*64)

    def test_hidden_old_route_fails(self):
        (self.source/'payload/extra.js').write_text(pub.OLD_ROUTE)
        with self.assertRaisesRegex(ValueError,'residual_old_preview_route'):
            pub.derive(self.source,self.root/'bad',request(),'d'*64)

    def test_tampered_payload_and_manifest_fail(self):
        (self.root/'derived/payload/poisk-turov/index.php').write_text('tampered')
        with self.assertRaises(ValueError): remote.verify(self.root/'derived',self.q)
        with self.assertRaises(ValueError): remote.verify(self.root/'derived',{**self.q,'manifest_sha256':'f'*64})

    def test_validated_request_no_path_or_replay(self):
        for key,value in [('attempt',2),('source_sha','main'),('file_count',0),('artifact_id',True),('archive_sha256','x')]:
            with self.subTest(key=key), self.assertRaises(ValueError): remote.validate({**self.q,key:value})

    def test_extract_and_verify_real_archive(self):
        q=self.staged(); remote.safe_extract(self.upload,self.root/'extracted')
        self.assertEqual(remote.verify(self.root/'extracted',q),remote.inventory(self.root/'derived/payload'))

    def test_archive_paths_links_duplicates(self):
        for kind in ('traversal','absolute','link','duplicate'):
            f=self.root/(kind+'.tar.gz')
            with tarfile.open(f,'w:gz') as t:
                info=tarfile.TarInfo({'traversal':'payload/../../outside','absolute':'/payload/x'}.get(kind,'payload/x'))
                if kind=='link': info.type=tarfile.SYMTYPE; info.linkname='/etc/passwd'
                else: info.size=1
                t.addfile(info,None if kind=='link' else io.BytesIO(b'x'))
                if kind=='duplicate': t.addfile(info,io.BytesIO(b'x'))
            with self.subTest(kind=kind),self.assertRaises(ValueError): remote.safe_extract(f,self.root/('extract-'+kind))

    def test_create_complete_existing_preview_untouched(self):
        q=self.staged(); protected=q['before']['protected']
        self.assertEqual(self.site.activate(q)['status'],'activated')
        self.assertEqual(self.site.finish(q)['status'],'published')
        self.assertEqual(self.site.protected(),protected)
        self.assertEqual((self.site.parent/'search3-site-candidate/keep.txt').read_text(),'existing preview')

    def test_rollback_only_new_target(self):
        q=self.staged(); self.site.activate(q)
        self.assertEqual(self.site.finish(q,True)['status'],'rolled_back_to_absence')
        self.assertEqual(self.site.snapshot(),q['before'])

    def test_existing_target_never_overwritten(self):
        self.site.target.mkdir(); (self.site.target/'keep').write_text('existing local')
        q=self.staged()
        with self.assertRaisesRegex(ValueError,'create_only'): self.site.activate(q)
        self.assertEqual((self.site.target/'keep').read_text(),'existing local')

    def test_symlink_target_denied(self):
        self.site.target.symlink_to(self.site.parent/'search3-site-candidate',target_is_directory=True)
        with self.assertRaises(ValueError): remote.Site(self.site.root)

    def test_old_preview_change_blocks_activation(self):
        q=self.staged(); (self.site.parent/'search3-site-candidate/keep.txt').write_text('new writer')
        with self.assertRaisesRegex(ValueError,'predecessor_changed'): self.site.activate(q)
        self.assertFalse(self.site.target.exists())

    def test_target_change_blocks_rollback(self):
        q=self.staged(); self.site.activate(q); (self.site.target/'foreign').write_text('other writer')
        with self.assertRaisesRegex(ValueError,'modified_target'): self.site.finish(q,True)
        self.assertTrue(self.site.target.exists())

    def test_existing_locks_not_stolen(self):
        q=self.staged(); (self.site.parent/'.search3-local-lock').mkdir()
        with self.assertRaises(FileExistsError): self.site.activate(q)
        self.assertTrue((self.site.parent/'.search3-local-lock').is_dir())

    def test_binding_exact_nonce_only(self):
        q={'name':'search3-local-bind-789-'+'e'*24+'.txt','nonce':'f'*64}
        self.site.binding(q)
        with self.assertRaises(ValueError): self.site.binding({**q,'nonce':'0'*64},True)
        self.site.binding(q,True)
        self.assertFalse((self.site.parent/q['name']).exists())
        with self.assertRaises(ValueError): self.site.binding({**q,'name':'../index.php'})


if __name__=='__main__': unittest.main()
