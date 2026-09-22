#!/usr/bin/env python3
import pathlib,unittest
R=pathlib.Path(__file__).resolve().parents[1]
class T(unittest.TestCase):
 def test_narrow(self):
  w=(R/'.github/workflows/search3-root-gateway-install.yml').read_text();p=(R/'scripts/deploy/search3_root_gateway_install.py').read_text();g=(R/'v2/api-v2.php').read_text()
  self.assertIn("issue.number == 3419",w);self.assertIn("/install-search3-root-gateway ",w);self.assertIn("contents/v2/api-v2.php?ref=",p);self.assertIn("predecessor_drift",p);self.assertIn("installed_hash",p);self.assertIn("api-v2.php?action=health",p);self.assertNotIn("lead-adapter-v2.php",p);self.assertIn("strict_int($value, 1, 5000, 'limit')",g)
if __name__=='__main__':unittest.main()
