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
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_design_v2_preview as pub
import search3_design_v2_preview_remote as remote


class LiveReadback(unittest.TestCase):
    def test_local_boundary_uses_canonical_query_and_rejects_exposure(self):
        assets = {name: b'checked asset' for name in (
            'search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css')}
        assets.update({'visual-search/asset-' + str(i) + '.js': b'visual asset' for i in range(29)})
        assets['prototype-search/data.js'] = b'canonical data owner'
        files = {name: remote.digest(body) for name, body in assets.items()}
        html = ('<meta name="robots" content="noindex,follow,max-image-preview:large' +
            ' id="tourSearch" metrikaCounter:0 leadApi:"' + pub.ROUTE + 'preview-lead-disabled.php"')
        canonical = pub.ROUTE + 'data/hotel-details-read-v1.php?catalog=anytour&anytourHotelId=1'
        catalog = pub.ROUTE+'data/local-tv-catalog-read-v1.php?oldLocalHotelIds%5B%5D=1'
        responses = {
            catalog: (200,json.dumps({'ok':True,'source':'anytour-local-tv-catalog-v1','catalog':'local-tv',
                                     'requestedIds':[1],'items':[],'links':[],'missingIds':[1]})),
            pub.ROUTE + 'visual-search/': (200, '<meta name="robots" content="noindex, nofollow, noarchive" app.js?v=0123456789ab <script src="../prototype-search/data.js?v=0123456789ab"></script><script src="./live-bridge.js?v=0123456789ab"></script>'),
            pub.ROUTE + 'data/hotel-details-read-v1.php': (400, '{"ok":false,"error":"Invalid hotel id"}'),
            canonical: (403, '{"ok":false,"error":"Canonical catalogue is isolated to local preview"}'),
            pub.ROUTE + 'data/search3-local-results-read-v1.php':
                (403, '{"ok":false,"error":"Local DB results are isolated to local preview"}'),
            pub.ROUTE + 'preview-lead-disabled.php': (403, 'PREVIEW_LEAD_DISABLED'),
        }
        def http(path, data=None, binary=False):
            if path in responses: return responses[path]
            for name, body in assets.items():
                if path == pub.ROUTE + name + '?sha=' + files[name]: return 200, body
            if path.endswith('.php'): return 403, 'denied'
            return 200, html
        with patch('search3_preview_publish.http', side_effect=http) as request:
            self.assertEqual(pub.live_checks(files)['LOCAL_only_data_HTTP'], 403)
            self.assertIn(catalog,[call.args[0] for call in request.call_args_list])
            self.assertIn(canonical, [call.args[0] for call in request.call_args_list])
            self.assertNotIn(pub.ROUTE + 'data/hotel-details-read-v1.php',
                [call.args[0] for call in request.call_args_list])
            responses[pub.ROUTE+'visual-search/'] = (200,
                '<meta name="robots" content="noindex, nofollow, noarchive" '
                'app.js?v=0123456789ab '
                '<script src="../prototype-search/data.js?v=0123456789ab"></script>'
                '<script src="./live-bridge.js?v=0123456789ab"></script>')
            self.assertTrue(pub.live_checks(files)['visual_live_connected'])
            self.assertIn(pub.ROUTE+'prototype-search/data.js?sha='+files['prototype-search/data.js'],
                [call.args[0] for call in request.call_args_list])
            assets['prototype-search/data.js'] = b'foreign dependency'
            with self.assertRaisesRegex(ValueError,'visual_dependency_hash'):
                pub.live_checks(files)
            assets['prototype-search/data.js'] = b'canonical data owner'
            for status in (400,403,503):
                previous=responses[catalog]; responses[catalog]=(status,previous[1])
                with self.assertRaisesRegex(ValueError,'new_catalog_read_boundary'): pub.live_checks(files)
                responses[catalog]=previous
            previous=responses[pub.ROUTE+'visual-search/']
            responses[pub.ROUTE+'visual-search/']=(200,'<meta name="robots" content="noindex, nofollow, noarchive" app.js?v=0123456789ab Живой поиск ещё не подключён')
            with self.assertRaisesRegex(ValueError,'visual_live_entry'): pub.live_checks(files)
            responses[pub.ROUTE+'visual-search/']=previous
            for response in [(200, '{"ok":true}'), (403, '{"ok":false,"error":"unrelated"}')]:
                responses[canonical] = response
                with self.subTest(response=response), self.assertRaisesRegex(ValueError, 'LOCAL_only_data_boundary'):
                    pub.live_checks(files)


def request():
    return dict(source_sha=pub.SOURCE_SHA, release_sha='b'*40, source_tree=pub.SOURCE_TREE, artifact_id=pub.ARTIFACT_ID,
        build_run=pub.BUILD_RUN, deploy_run=789, attempt=1, archive_sha256='0'*64,
        manifest_sha256='0'*64, payload_sha256='0'*64, file_count=10)


def fixture(root):
    p = root / 'payload'; c = root / 'control'; p.mkdir(parents=True); c.mkdir()
    files = {'.htaccess': 'Require all denied', 'preview-lead-disabled.php': '<?php http_response_code(403);',
        'search-page-v2.php': '<?php $metrikaCounter=0;', 'data/hotel-details-read-v1.php': '<?php',
        'data/hotel-presentation-read-v1.php': '<?php', 'poisk-turov/index.php': '<?php',
        'visual-search/index.php': "<?php if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/')) {http_response_code(403);exit;}",
        'site-path-v1.php': "<?php // #^(/_preview/search3-site-candidate)(?:/|$)#\n"}
    for name,old,_ in pub.ROUTE_TRANSFORMS:
        if name not in files: files[name]=''
        if old.decode() not in files[name]: files[name]+=old.decode()+'\n'
    rows=[]
    compressed=pub.gzip_lengths([files[n].encode() for n in pub.LEDGER_JS])
    for i,n in enumerate(pub.LEDGER_JS):
        body=files[n].encode()
        rows.append({'src':'../'+n,'source':n,'target':n,'private_function_bindings':['preserved'],
                     'source_sha256':'e'*64,'sha256':remote.digest(body),'raw':len(body)+7,
                     'served':len(body),'gzip_before':compressed[i]+4,'gzip_after':compressed[i]})
    graphs={'live':[f['src'] for f in rows],'offline':[],'ondemand':[]}
    totals={}
    for name in ('live','offline','ondemand','complete_live','complete_offline'):
        selected=rows if name in ('live','complete_live') else []
        totals[name]={k:sum(f[k] for f in selected) for k in ('raw','served','gzip_before','gzip_after')}
    ledger={'schema':1,'tool':'fixture-node-zlib9','policy':'preserved','graphs':graphs,'totals':totals,
            'files':rows,'css':{'history':'byte values preserved'}}
    files[pub.LEDGER_PATH]=json.dumps(ledger,ensure_ascii=False,indent=2)+'\n'
    files.update({f'visual-search/fixture-{i}.js': 'fixture' for i in range(845-len(files))})
    for n, text in files.items():
        dest=p/n; dest.parent.mkdir(parents=True, exist_ok=True); dest.write_text(text)
    (c/'manifest.json').write_bytes(remote.json_bytes({'schema_version':1, 'source_sha':pub.SOURCE_SHA,
        'source_tree_sha':pub.SOURCE_TREE, 'route':pub.OLD_ROUTE, 'target':'search3-whole-site-preview'}))
    (c/'payload.sha256').write_text('original fixture')


class Contracts(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(); self.root=Path(self.tmp.name)
        self.source=self.root/'original'; fixture(self.source)
        self.ledger_pin=patch.object(pub,'SOURCE_LEDGER_SHA256',remote.digest((self.source/'payload'/pub.LEDGER_PATH).read_bytes()))
        self.ledger_pin.start()
        self.addCleanup(self.ledger_pin.stop)
        self.q,self.archive=pub.derive(self.source,self.root/'derived',request(),pub.SOURCE_ZIP_SHA256)
        self.event={'repository':{'full_name':pub.REPO,'id':1345518271}, 'sender':{'login':'pyatkoff','id':226193297},
            'action':'created','issue':{'number':4217},'comment':{'user':{'id':226193297},'author_association':'OWNER',
            'body':pub.PREFIX+pub.SOURCE_SHA+' '+'b'*40+f' {pub.BUILD_RUN} {pub.ARTIFACT_ID}'}}
        self.env={'GITHUB_REPOSITORY':pub.REPO,'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff',
            'GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_ACTOR_ID':'226193297','GITHUB_RUN_ATTEMPT':'1',
            'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ID':'789'}
        site_root=self.root/'anytoour.ru'; (site_root/'_preview/search3-site-candidate').mkdir(parents=True)
        (site_root/'_preview/search3-site-candidate/keep.txt').write_text('existing preview')
        (site_root/'_preview/search3-v17-candidate').mkdir()
        (site_root/'_preview/search3-v17-candidate/keep.txt').write_text('accepted v17 unchanged')
        (site_root/'_preview/search3-v18-candidate').mkdir()
        (site_root/'_preview/search3-v18-candidate/keep.txt').write_text('existing v18 unchanged')
        for name in remote.REQUIRED:
            p=site_root/name; p.parent.mkdir(parents=True,exist_ok=True); p.write_text('protected:'+name)
        (site_root/'_preview/search3-anex-candidate').mkdir()
        (site_root/'_preview/search3-anex-candidate/keep.txt').write_text('ANEX untouched')
        (site_root/'_preview/search3-next-candidate').mkdir()
        (site_root/'_preview/search3-next-candidate/keep.txt').write_text('old NEXT unchanged')
        self.site=remote.Site(site_root)
        self.upload=None

    def tearDown(self):
        if self.upload: self.upload.unlink(missing_ok=True)
        self.tmp.cleanup()

    def staged(self):
        if self.upload: self.upload.unlink(missing_ok=True)
        fd,name=tempfile.mkstemp(prefix='search3-design-v2.',suffix='.tar.gz',dir='/tmp')
        import os
        os.close(fd); self.upload=Path(name); self.upload.write_bytes(self.archive)
        return {**self.q,'archive':name,'before':self.site.snapshot()}

    def test_owner_command(self):
        q=pub.checked_request(self.event,self.env)
        self.assertEqual(remote.ROUTE,'/_preview/search3-design-v2-candidate/');
        self.assertIn('preview:search3-v17-candidate',self.site.protected());
        self.assertIn('preview:search3-v18-candidate',self.site.protected());
        self.assertIn('preview:search3-next-candidate',self.site.protected());
        self.assertEqual(q['source_sha'],pub.SOURCE_SHA); self.assertEqual(q['artifact_id'],pub.ARTIFACT_ID)

    def test_valid_but_unaccepted_donor_pins_denied(self):
        good=self.event['comment']['body']
        for body in (good.replace(pub.SOURCE_SHA,'f'*40),
                     good.replace(str(pub.BUILD_RUN),'888'),
                     good.replace(str(pub.ARTIFACT_ID),'999')):
            event=copy.deepcopy(self.event);event['comment']['body']=body
            with self.subTest(body=body),self.assertRaisesRegex(ValueError,'accepted_donor_only'):
                pub.checked_request(event,self.env)
        for field,value in (('source_sha','f'*40),('source_tree','e'*40),('artifact_id',999),
                            ('build_run',888),('source_ZIP_sha256','f'*64)):
            with self.subTest(field=field),self.assertRaisesRegex(ValueError,'accepted_donor_only'):
                remote.validate({**self.q,field:value})
        with self.assertRaisesRegex(ValueError,'accepted_donor_only'):
            pub.derive(self.source,self.root/'unacceptedZIP',request(),'f'*64)

    def test_wrong_environment_denied(self):
        for key,value in [('GITHUB_REF','refs/heads/work'),('GITHUB_ACTOR','other'),('GITHUB_TRIGGERING_ACTOR','other'),
            ('GITHUB_ACTOR_ID','0'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','pull_request'),('GITHUB_REPOSITORY','other/repo')]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                pub.checked_request(self.event,{**self.env,key:value})

    def test_wrong_event_denied(self):
        for path,value in [(('issue','number'),3419),(('issue','number'),2530),(('issue','number'),996),(('issue','pull_request'),{}),(('sender','id'),1),
            (('comment','author_association'),'MEMBER'),(('comment','user'),{'id':1}),(('repository','id'),1)]:
            event=copy.deepcopy(self.event); event[path[0]][path[1]]=value
            with self.subTest(path=path), self.assertRaises(ValueError): pub.checked_request(event,self.env)

    def test_command_syntax_and_no_arbitrary_target(self):
        good=self.event['comment']['body']
        for body in [good+'\n',good+' /poisk-turov/',good.replace(pub.PREFIX,'/publish-search3-preview '),
                     good.replace(pub.PREFIX,'/create-search3-v18-preview '),
                     good.replace(f' {pub.BUILD_RUN} ',' 0 '),good+';id',good.replace(pub.SOURCE_SHA,'main'),good.replace(f' {pub.BUILD_RUN} ',f'  {pub.BUILD_RUN} ')]:
            event=copy.deepcopy(self.event); event['comment']['body']=body
            with self.subTest(body=body),self.assertRaises(ValueError): pub.checked_request(event,self.env)

    def test_exact_seven_file_derivative_and_determinism(self):
        before=remote.inventory(self.source/'payload'); after=remote.inventory(self.root/'derived/payload')
        self.assertEqual(set(before),set(after))
        self.assertEqual({k for k in before if before[k]!=after[k]},set(pub.CHANGED_PAYLOAD_FILES))
        q2,a2=pub.derive(self.source,self.root/'again',request(),pub.SOURCE_ZIP_SHA256)
        self.assertEqual(q2,self.q); self.assertEqual(a2,self.archive)
        self.assertEqual(remote.verify(self.root/'derived',self.q),after)

    def test_unknown_transform_fails(self):
        (self.source/'payload/site-path-v1.php').write_text('<?php // different source')
        with self.assertRaisesRegex(ValueError,'route_source_drift'):
            pub.derive(self.source,self.root/'bad',request(),pub.SOURCE_ZIP_SHA256)

    def test_hidden_old_route_fails(self):
        (self.source/'payload/extra.js').write_text(pub.OLD_ROUTE)
        with self.assertRaisesRegex(ValueError,'residual_old_preview_route'):
            pub.derive(self.source,self.root/'bad',request(),pub.SOURCE_ZIP_SHA256)

    def test_visual_source_fallback_and_file_count_drift_denied(self):
        visual = self.source/'payload/visual-search/index.php'
        visual.write_text("<?php // /_preview/search3-next-candidate/visual-search/")
        with self.assertRaisesRegex(ValueError, 'wrong_visual_route_guard'):
            pub.derive(self.source,self.root/'badvisual',request(),pub.SOURCE_ZIP_SHA256)
        (self.source/'payload/visual-search/fixture-0.js').unlink()
        visual.write_text("<?php if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/')) {http_response_code(403);exit;}")
        with self.assertRaisesRegex(ValueError,'source_file_count'):
            pub.derive(self.source,self.root/'badcount',request(),pub.SOURCE_ZIP_SHA256)

    def test_remote_rejects_old_visual_guard_after_valid_control_hash(self):
        root = self.root/'derived'
        visual = root/'payload/visual-search/index.php'
        visual.write_text("<?php // '/_preview/search3-next-candidate/visual-search/'")
        files = remote.inventory(root/'payload')
        manifest = json.loads((root/'control/manifest.json').read_bytes())
        manifest['files'] = [{'path': n, 'sha256': h, 'size': (root/'payload'/n).stat().st_size} for n,h in files.items()]
        (root/'control/manifest.json').write_bytes(remote.json_bytes(manifest))
        (root/'control/payload.sha256').write_bytes(''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode())
        q = {**self.q, 'manifest_sha256': remote.digest((root/'control/manifest.json').read_bytes()),
             'payload_sha256': remote.digest((root/'control/payload.sha256').read_bytes())}
        with self.assertRaisesRegex(ValueError, 'wrong_visual_route_guard'):
            remote.verify(root,q)

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
        q=self.staged(); (self.site.parent/'.search3-design-v2-lock').mkdir()
        with self.assertRaises(FileExistsError): self.site.activate(q)
        self.assertTrue((self.site.parent/'.search3-design-v2-lock').is_dir())

    def test_existing_sibling_change_blocks_activation(self):
        for name in ('search3-next-candidate','search3-v17-candidate','search3-v18-candidate','search3-anex-candidate'):
            with self.subTest(name=name):
                q=self.staged()
                sibling=self.site.parent/name/'keep.txt'
                old=sibling.read_text(); sibling.write_text('foreign writer')
                with self.assertRaisesRegex(ValueError,'predecessor_changed'): self.site.activate(q)
                self.assertFalse(self.site.target.exists()); sibling.write_text(old)

    def test_all_existing_locks_refused_without_removal(self):
        for name in ('.search3-next-lock','.search3-site-lock','.search3-local-lock','.search3-v17-lock','.search3-v18-lock'):
            with self.subTest(name=name):
                q=self.staged(); lock=self.site.parent/name; lock.mkdir()
                try:
                    with self.assertRaisesRegex(ValueError,'existing_preview_busy'): self.site.activate(q)
                    self.assertTrue(lock.is_dir()); self.assertFalse(self.site.target.exists())
                finally: lock.rmdir()

    def test_completed_transaction_cannot_replay_or_rollback(self):
        q=self.staged(); self.site.activate(q); self.site.finish(q)
        for rollback in (False,True):
            with self.subTest(rollback=rollback),self.assertRaisesRegex(ValueError,'transaction_already_finished'):
                self.site.finish(q,rollback)
        self.assertTrue(self.site.target.exists()); self.assertEqual(self.site.protected(),q['before']['protected'])

    def test_binding_exact_nonce_only(self):
        q={'name':'search3-design-v2-bind-789-'+'e'*24+'.txt','nonce':'f'*64}
        self.site.binding(q)
        with self.assertRaises(ValueError): self.site.binding({**q,'nonce':'0'*64},True)
        self.site.binding(q,True)
        self.assertFalse((self.site.parent/q['name']).exists())
        with self.assertRaises(ValueError): self.site.binding({**q,'name':'../index.php'})


if __name__=='__main__': unittest.main()
