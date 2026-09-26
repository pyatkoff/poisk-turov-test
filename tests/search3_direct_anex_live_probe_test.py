import importlib.util, json, unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def module(name):
    spec=importlib.util.spec_from_file_location(name,ROOT/'scripts/diagnostics'/('search3_direct_anex_live_probe'+('_gate' if name=='gate' else '')+'.py'))
    value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value
g=module('gate');p=module('probe')
class T(unittest.TestCase):
 def test_exact(self):
  e={'action':'created','issue':{'number':3419},'comment':{'id':1,'body':g.COMMAND,'author_association':'OWNER','user':{'login':'pyatkoff','id':226193297}}}
  env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_RUN_ATTEMPT':'1','GITHUB_REPOSITORY':g.REPOSITORY,'GITHUB_REF':'refs/heads/main','GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff'}
  self.assertEqual(g.authorize(e,env),(True,'authorized'))
  for old in ('v1','v2'):
   e['comment']['body']='/search3-direct-anex-live-probe-'+old
   self.assertEqual(g.authorize(e,env),(False,'wrong_command'))
  e['comment']['body']=g.COMMAND;env['GITHUB_RUN_ATTEMPT']='2'
  self.assertEqual(g.authorize(e,env),(False,'wrong_run'))
 def test_only_counts_retained(self):
  result=p.summarize(200,{'ok':True,'token':'SECRET','data':{'provider':'anex','search_ref':'SECRET',
   'hotels':[{'id':123,'tours':[{'id':'SECRET'},{'payload':'SECRET'}]},{'tours':[{}]}],
   'pages_read':1,'first_page_only':True}})
  self.assertEqual(result,{'status':'complete','httpStatus':200,'ok':True,'provider':'anex','hotels':2,
   'offers':3,'pages_read':1,'first_page_only':True})
  self.assertNotIn('SECRET',json.dumps(result))
 def test_unknown_error_not_exported(self):
  for error in ('SECRET',{'message':'SECRET'},['SECRET']):
   result=p.summarize(502,{'ok':False,'error':error})
   self.assertEqual(result['error'],'other_error');self.assertNotIn('SECRET',json.dumps(result))
 def test_field_types(self):
  result=p.summarize(200,{'ok':'true','data':{'pages_read':True,'first_page_only':'true','received_offers':-1}})
  self.assertEqual(result,{'status':'complete','httpStatus':200,'ok':None})
 def test_empty_is_not_invented_offer(self):
  self.assertEqual(p.summarize(200,{'ok':True,'data':{'provider':'anex','hotels':[]}})['offers'],0)
if __name__=='__main__':unittest.main()
