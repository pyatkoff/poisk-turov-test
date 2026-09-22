#!/usr/bin/env python3
import pathlib,re,unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
class RootGatewayInstallerTest(unittest.TestCase):
 def test_workflow_is_one_file_and_owner_gated(self):
  s=(ROOT/'.github/workflows/search3-root-gateway-install.yml').read_text()
  self.assertIn("github.event.issue.number == 3419",s);self.assertIn("startsWith(github.event.comment.body, '/install-search3-root-gateway ')",s)
  self.assertNotIn('deploy-anytoour.yml',s);self.assertIn('cancel-in-progress: false',s)
 def test_transaction_is_narrow(self):
  s=(ROOT/'scripts/deploy/search3_root_gateway_install.py').read_text()
  self.assertIn("contents/v2/api-v2.php?ref=",s);self.assertIn('predecessor_drift',s);self.assertIn('installed_hash',s)
  self.assertIn('api-v2.php?action=health',s);self.assertIn("'supplier_calls':0",s);self.assertIn("'real_leads':0",s)
  for forbidden in ('lead-adapter-v2.php','analytics-config.php','poisk-turov/index.php'):
   self.assertNotIn(forbidden,s)
 def test_release_gateway_has_5000_contract(self):
  s=(ROOT/'v2/api-v2.php').read_text()
  self.assertIn('function search_results_limit',s);self.assertIn("strict_int($value, 1, 5000, 'limit')",s)
  self.assertRegex(s,r"case 'search_results':[\s\S]{0,300}search_results_limit")
if __name__=='__main__':unittest.main()
