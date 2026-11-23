"""Offline tests for the separate fixed-target local-preview update transaction."""
import copy, json, os, sys, tempfile, unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/deploy'))
import search3_local_preview as base
import search3_local_preview_update as pub
import search3_local_preview_update_remote as remote

PREVIOUS='9'*40

def source_fixture(root):
    p=root/'payload'; c=root/'control'; p.mkdir(parents=True); c.mkdir()
    files={'.htaccess':'Require all denied','preview-lead-disabled.php':'<?php http_response_code(403);','search-page-v2.php':'<?php $metrikaCounter=0;','data/hotel-details-read-v1.php':'<?php','data/hotel-presentation-read-v1.php':'<?php','poisk-turov/index.php':'<?php','site-path-v1.php':"<?php // #^(/_preview/search3-site-candidate)(?:/|$)#\n"}
    for n,text in files.items(): dest=p/n; dest.parent.mkdir(parents=True,exist_ok=True); dest.write_text(text)
    (c/'manifest.json').write_bytes(remote.json_bytes({'schema_version':1,'source_sha':'a'*40,'source_tree_sha':'c'*40,'route':base.OLD_ROUTE,'target':'search3-whole-site-preview'})); (c/'payload.sha256').write_text('fixture')
def request():
    return dict(source_sha='a'*40,release_sha='b'*40,source_tree='c'*40,artifact_id=123,build_run=456,deploy_run=789,attempt=1,archive_sha256='0'*64,manifest_sha256='0'*64,payload_sha256='0'*64,file_count=10,previous_source_sha=PREVIOUS,operation='update')

class UpdateContracts(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory(); self.root=Path(self.tmp.name); self.source=self.root/'original'; source_fixture(self.source)
        q0, self.archive=base.derive(self.source,self.root/'derived',request(),'d'*64); self.q={**q0,'previous_source_sha':PREVIOUS,'operation':'update'}
        self.event={'repository':{'full_name':base.REPO,'id':1345518271},'sender':{'login':'pyatkoff','id':226193297},'action':'created','issue':{'number':4709},'comment':{'user':{'id':226193297},'author_association':'OWNER','body':pub.PREFIX+'a'*40+' '+'b'*40+' 456 123 '+PREVIOUS}}
        self.env={'GITHUB_REPOSITORY':base.REPO,'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_ACTOR_ID':'226193297','GITHUB_RUN_ATTEMPT':'1','GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ID':'789'}
        site=self.root/'anytoour.ru'; (site/'_preview/search3-site-candidate').mkdir(parents=True); (site/'_preview/search3-site-candidate/keep').write_text('site-preview')
        for name in remote.REQUIRED:
            p=site/name; p.parent.mkdir(parents=True,exist_ok=True); p.write_text('protected:'+name)
        target=site/'_preview/search3-local-candidate'; target.mkdir(); (target/'old.txt').write_text('published predecessor')
        self.site=remote.Site(site); old_digest=remote.digest(remote.json_bytes(remote.inventory(target))); self.site.owner.write_bytes(remote.json_bytes({'run':111,'source':PREVIOUS,'digest':old_digest,'status':'published'})); self.upload=None
    def tearDown(self):
        if self.upload: self.upload.unlink(missing_ok=True)
        self.tmp.cleanup()
    def staged(self):
        fd,name=tempfile.mkstemp(prefix='search3-local-update.',suffix='.tar.gz',dir='/tmp'); os.close(fd); self.upload=Path(name); self.upload.write_bytes(self.archive); return {**self.q,'archive':name,'before':self.site.snapshot()}
    def test_owner_command(self):
        q=pub.checked_request(self.event,self.env); self.assertEqual(q['previous_source_sha'],PREVIOUS); self.assertEqual(q['operation'],'update')
        self.assertNotIn('composition',q)
    def test_old_coordination_journals_cannot_authorize_updates(self):
        for number in (4217, 3419, 2530, 996):
            event=copy.deepcopy(self.event); event['issue']['number']=number
            with self.subTest(issue=number), self.assertRaisesRegex(ValueError, 'coordination_only'):
                pub.checked_request(event,self.env)
    def test_command_rejects_create_replay_path_and_bad_pins(self):
        good=self.event['comment']['body']
        bad=[good+'\n',good+' /tmp/x',good.replace(pub.PREFIX,'/create-search3-local-preview '),good.replace(' 456 ',' 0 '),good.replace('a'*40,PREVIOUS,1),good.replace(PREVIOUS,'main')]
        for body in bad:
            event=copy.deepcopy(self.event); event['comment']['body']=body
            with self.subTest(body=body),self.assertRaises(ValueError): pub.checked_request(event,self.env)
    def test_environment_guards(self):
        for k,v in [('GITHUB_REF','refs/heads/work'),('GITHUB_ACTOR','x'),('GITHUB_TRIGGERING_ACTOR','x'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','pull_request')]:
            with self.subTest(k=k),self.assertRaises(ValueError): pub.checked_request(self.event,{**self.env,k:v})
    def test_update_activate_complete_retains_predecessor(self):
        q=self.staged(); before=q['before']; self.assertEqual(self.site.activate_update(q)['status'],'activated'); receipt=self.site.receipt(q); self.assertTrue((receipt/'previous-payload/old.txt').exists()); result=self.site.finish_update(q); self.assertEqual(result['status'],'published'); self.assertEqual(result['previous_source_sha'],PREVIOUS); self.assertNotEqual(self.site.snapshot()['target'],before['target']); self.assertEqual(self.site.protected(),before['protected'])
    def test_update_rollback_restores_exact_predecessor(self):
        q=self.staged(); before=q['before']; self.site.activate_update(q); result=self.site.finish_update(q,True); self.assertEqual(result['status'],'rolled_back_to_predecessor'); self.assertEqual(self.site.snapshot(),before); self.assertEqual((self.site.target/'old.txt').read_text(),'published predecessor')
    def test_wrong_previous_source_denied_before_swap(self):
        q=self.staged(); q['previous_source_sha']='8'*40
        with self.assertRaisesRegex(ValueError,'published_predecessor'): self.site.activate_update(q)
        self.assertEqual((self.site.target/'old.txt').read_text(),'published predecessor')
    def test_modified_target_denied(self):
        q=self.staged(); (self.site.target/'foreign').write_text('drift')
        with self.assertRaisesRegex(ValueError,'predecessor_changed|published_predecessor'): self.site.activate_update(q)
    def test_foreign_owner_denied(self):
        q=self.staged(); self.site.owner.write_bytes(remote.json_bytes({'run':111,'source':PREVIOUS,'digest':q['before']['target'],'status':'foreign'}))
        with self.assertRaisesRegex(ValueError,'predecessor_changed|published_predecessor'): self.site.activate_update(q)
    def test_replay_receipt_denied(self):
        q=self.staged(); self.site.receipt(q).mkdir()
        with self.assertRaisesRegex(ValueError,'previous_outcome_unknown'): self.site.activate_update(q)
    def test_active_replacement_tamper_blocks_complete_and_rollback(self):
        q=self.staged(); self.site.activate_update(q); (self.site.target/'foreign').write_text('drift')
        with self.assertRaisesRegex(ValueError,'modified_target'): self.site.finish_update(q)
        with self.assertRaisesRegex(ValueError,'modified_target'): self.site.finish_update(q,True)
    def test_expected_owner_provenance_tamper_blocks_completion_and_rollback(self):
        q=self.staged(); self.site.activate_update(q)
        p=self.site.receipt(q)/'expected-owner.json'; owner=json.loads(p.read_bytes())
        owner['composition']={'mode':'forged'}; p.write_bytes(remote.json_bytes(owner))
        with self.assertRaisesRegex(ValueError,'activation_composition_changed'): self.site.finish_update(q)
        with self.assertRaisesRegex(ValueError,'activation_composition_changed'): self.site.finish_update(q,True)
    def test_protected_or_neighbor_preview_drift_blocks_completion(self):
        q=self.staged(); self.site.activate_update(q); (self.site.parent/'search3-site-candidate/keep').write_text('drift')
        with self.assertRaisesRegex(ValueError,'production_or_existing_preview_drift'): self.site.finish_update(q)
    def test_existing_lock_not_stolen(self):
        q=self.staged(); (self.site.parent/'.search3-local-lock').mkdir()
        with self.assertRaises(FileExistsError): self.site.activate_update(q)
    def test_binding_fixed_namespace(self):
        q={'name':'search3-local-update-bind-789-'+'e'*24+'.txt','nonce':'f'*64}; self.site.binding(q); self.site.binding(q,True)
        with self.assertRaises(ValueError): self.site.binding({**q,'name':'../index.php'})
    def test_validate_no_path_or_replay(self):
        for k,v in [('attempt',2),('operation','create'),('source_sha',PREVIOUS),('previous_source_sha','main'),('file_count',0),('artifact_id',True)]:
            with self.subTest(k=k),self.assertRaises(ValueError): remote.validate({**self.q,k:v})

class CatalogCompositionContracts(unittest.TestCase):
    def setUp(self):
        remote.select_target("local")
        UpdateContracts.setUp(self)
    tearDown=UpdateContracts.tearDown
    staged=UpdateContracts.staged
    def catalog_command(self):
        return (pub.PREFIX+remote.DONOR_SOURCE_SHA+' '+'b'*40+' '+str(remote.DONOR_BUILD_RUN)
                +' '+str(remote.DONOR_ARTIFACT_ID)+' '+remote.BASE_SOURCE_SHA+' '+remote.COMPOSITION)
    def catalog_request(self):
        return {**self.q,'source_sha':remote.DONOR_SOURCE_SHA,'source_tree':remote.DONOR_SOURCE_TREE,
                'build_run':remote.DONOR_BUILD_RUN,'artifact_id':remote.DONOR_ARTIFACT_ID,
                'source_ZIP_sha256':remote.DONOR_ZIP_SHA256,'previous_source_sha':remote.BASE_SOURCE_SHA,
                'file_count':833,'composition':remote.COMPOSITION,
                'donor_artifact_controls':remote.donor_artifact_controls()}
    def catalog_snapshot(self):
        return {'target':remote.BASE_INVENTORY_SHA256,
                'owner':{'run':remote.BASE_DEPLOY_RUN,'source':remote.BASE_SOURCE_SHA,
                         'digest':remote.BASE_INVENTORY_SHA256,'status':'published'}}
    def test_catalog_command_requires_explicit_fixed_mode_and_pins(self):
        event=copy.deepcopy(self.event); event['comment']['body']=self.catalog_command()
        q=pub.checked_request(event,self.env)
        self.assertEqual(q['composition'],remote.COMPOSITION)
        self.assertEqual(q['previous_source_sha'],remote.BASE_SOURCE_SHA)
        for before,after in [(remote.DONOR_SOURCE_SHA,'a'*40),
                             (remote.BASE_SOURCE_SHA,PREVIOUS),
                             (str(remote.DONOR_BUILD_RUN),'456'),
                             (str(remote.DONOR_ARTIFACT_ID),'123'),
                             (remote.COMPOSITION,'arbitrary-patch'),
                             (pub.PREFIX,pub.NEXT_PREFIX)]:
            event['comment']['body']=self.catalog_command().replace(before,after,1)
            with self.subTest(before=before),self.assertRaises(ValueError):
                pub.checked_request(event,self.env)
    def test_catalog_remote_rejects_wrong_tree_zip_count_target_or_mode(self):
        q=self.catalog_request(); remote.validate(q)
        for k,v in [('source_tree','c'*40),('source_ZIP_sha256','d'*64),('file_count',862),
                    ('preview_target','next'),('composition','local-route-v1'),
                    ('composition',None),('previous_source_sha',PREVIOUS),
                    ('source_sha','a'*40),('build_run',456),('artifact_id',123),
                    ('donor_artifact_controls',{}),
                    ('donor_artifact_controls',{**remote.donor_artifact_controls(),'file_count':833})]:
            with self.subTest(k=k,v=v),self.assertRaises(ValueError):
                remote.validate({**q,k:v})
    def test_catalog_exact_predecessor_binds_run_and_whole_inventory(self):
        q=self.catalog_request(); before=self.catalog_snapshot()
        remote.catalog_predecessor(q,before)
        for field,value in [('run',remote.BASE_DEPLOY_RUN+1),('run',float(remote.BASE_DEPLOY_RUN)),('source',PREVIOUS),
                            ('status','activated'),('digest','f'*64)]:
            changed=copy.deepcopy(before); changed['owner'][field]=value
            with self.subTest(field=field),self.assertRaisesRegex(ValueError,'catalog_exact_predecessor'):
                remote.catalog_predecessor(q,changed)
        changed=copy.deepcopy(before)
        changed['target']=changed['owner']['digest']='f'*64
        with self.assertRaisesRegex(ValueError,'catalog_exact_predecessor'):
            remote.catalog_predecessor(q,changed)
    def test_catalog_server_rejects_a_self_consistent_wrong_predecessor_before_receipt(self):
        q={**self.catalog_request(),'archive':self.staged()['archive']}
        owner=json.loads(self.site.owner.read_bytes())
        owner.update(run=remote.BASE_DEPLOY_RUN,source=remote.BASE_SOURCE_SHA)
        self.site.owner.write_bytes(remote.json_bytes(owner)); q['before']=self.site.snapshot()
        with self.assertRaisesRegex(ValueError,'catalog_exact_predecessor'):
            self.site.activate_update(q)
        self.assertFalse(self.site.receipt(q).exists())
        self.assertEqual((self.site.target/'old.txt').read_text(),'published predecessor')
    def test_catalog_owner_records_both_origins_without_changing_normal_owner(self):
        files={'file':'a'*64}; q=self.catalog_request()
        composite=remote.expected_owner(q,files)
        self.assertEqual(composite['source'],remote.DONOR_SOURCE_SHA)
        self.assertEqual(composite['transform'],remote.CATALOG_TRANSFORM)
        self.assertEqual(composite['composition'],remote.composition_record())
        self.assertEqual(composite['composition']['predecessor']['deploy_run'],remote.BASE_DEPLOY_RUN)
        self.assertEqual(set(remote.expected_owner(self.q,files)),{'run','source','digest','status'})
        composite['composition']['delta']['path']='foreign.php'
        self.assertEqual(remote.composition_record()['delta']['path'],remote.HELPER_PATH)

if __name__=='__main__': unittest.main()
