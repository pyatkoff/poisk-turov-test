import copy
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT=Path(__file__).resolve().parents[1]
PATH=ROOT/'scripts/diagnostics/search3_direct_anex_runtime_probe.py'
PHP=PATH.with_suffix('.php')
SPEC=importlib.util.spec_from_file_location('probe',PATH)
probe=importlib.util.module_from_spec(SPEC);SPEC.loader.exec_module(probe)

def event():
    return {'action':'created','issue':{'number':3419},'comment':{'id':1,'body':probe.COMMAND,
        'author_association':'OWNER','user':{'login':'pyatkoff','id':226193297}}}
def env():
    return {'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1','GITHUB_REPOSITORY':probe.REPO,
        'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff'}
def receipt():
    return {'operation':probe.OPERATION,'source':probe.SOURCE,'status':'failed','stage':'search_core',
        'client_requests':4,'database_writes':0,'quote_calls':0,'replay_allowed':False}

class ProbeTest(unittest.TestCase):
    def php(self,expression,cwd=None):
        return subprocess.run(['php','-r','require '+json.dumps(str(PHP))+';'+expression],
            text=True,capture_output=True,check=True,cwd=cwd).stdout
    def test_exact_owner_only(self):
        self.assertTrue(probe.authorize(event(),env()))
        for key,value in [('GITHUB_RUN_ATTEMPT','2'),('GITHUB_REF','refs/heads/test'),
            ('GITHUB_ACTOR','intruder'),('GITHUB_TRIGGERING_ACTOR','intruder'),('GITHUB_REPOSITORY','other/repo'),
            ('GITHUB_EVENT_NAME','pull_request')]:
            e=env();e[key]=value;self.assertFalse(probe.authorize(event(),e))
        for path,value in [(('action',),'edited'),(('issue','number'),2530),
            (('comment','body'),probe.COMMAND+' '),(('comment','author_association'),'MEMBER'),
            (('comment','user','id'),1)]:
            e=event();x=e
            for k in path[:-1]: x=x[k]
            x[path[-1]]=value;self.assertFalse(probe.authorize(e,env()))
        e=event();e['issue']['pull_request']={};self.assertFalse(probe.authorize(e,env()))
    def test_redacts_php_exception_and_transport(self):
        out=json.loads(self.php('echo json_encode(s3_anex_error(new RuntimeException("SECRET token URL"),'
            '["action"=>"SearchTour_PRICES","http_status"=>502,"response_bytes"=>5,"url"=>"SECRET","supplier_code"=>"SECRET"]));'))
        self.assertEqual(out,{'exception':'RuntimeException','reason':'internal_error','action':'SearchTour_PRICES',
            'http_status':502,'response_bytes':5})
    def test_retains_actual_safe_error_code(self):
        out=json.loads(self.php('echo json_encode(s3_anex_error(new RuntimeException("ANEX_SUPPLIER_ERROR"),'
            '["action"=>"SearchTour_STATES","http_status"=>200,"supplier_code"=>101]));'))
        self.assertEqual(out['reason'],'ANEX_SUPPLIER_ERROR');self.assertEqual(out['supplier_code'],101)
    def test_driver_independent_redaction(self):
        v=receipt();v['token']='SECRET';v['failure']={'reason':'ANEX_HTTP_ERROR','http_status':502,
            'action':'SearchTour_PRICES','url':'SECRET','exception':'RuntimeException','curl_errno':0}
        clean=probe.sanitize(v)
        self.assertNotIn('SECRET',json.dumps(clean));self.assertEqual(clean['failure']['http_status'],502)
    def test_budget_and_money_boundaries(self):
        for key,value in [('client_requests',13),('database_writes',1),('quote_calls',1),('replay_allowed',True),
                          ('client_requests',True),('source','unknown')]:
            v=receipt();v[key]=value
            with self.assertRaises(ValueError):probe.sanitize(v)
    def test_durable_no_replay(self):
        with tempfile.TemporaryDirectory() as d:
            f=Path(d)/'reservation.json'
            out=self.php('s3_anex_durable('+json.dumps(str(f))+',["state"=>"reserved"]);'
                'try{s3_anex_durable('+json.dumps(str(f))+',["state"=>"overwrite"]);}catch(Throwable $e){echo $e->getMessage();}')
            self.assertEqual(out,'operation_exists_no_replay');self.assertEqual(json.loads(f.read_text())['state'],'reserved')
    def test_preflight_blocks_without_supplier(self):
        with tempfile.TemporaryDirectory() as d:
            out=probe.sanitize(json.loads(self.php('search3_direct_anex_runtime_main();',d)))
            self.assertEqual(out['status'],'blocked');self.assertEqual(out['client_requests'],0)
            self.assertEqual(out['failure']['reason'],'project_invalid');self.assertEqual(list(Path(d).iterdir()),[])
    def test_source_contract(self):
        text=PHP.read_text()
        self.assertEqual(text.count('anytour_anex_search3_run('),1)
        self.assertIn("START TRANSACTION READ ONLY",text)
        self.assertIn('$diagnostics,null,$state,\'all\'',text)
        self.assertLess(text.index("s3_anex_verify($target);"),text.index("$client=new AnyTourAnexClient"))
        self.assertLess(text.index("$dir.'/reservation.json'"),text.index("$client=new AnyTourAnexClient"))
        self.assertNotIn('anytour_anex_search3_http(',text)
        self.assertNotIn('additionalPricesDaily(',text)
        self.assertNotIn('->exec(\'INSERT',text)
        self.assertIn('timeout=300',PATH.read_text())

if __name__=='__main__':unittest.main()
