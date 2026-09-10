#!/usr/bin/env python3
from pathlib import Path
import ast,sys,unittest
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import hotel_observed_bulk_reconcile as bulk
class T(unittest.TestCase):
 def test_fixed_operation(self): self.assertEqual(bulk.OPERATION_ID,'hotel-observed-bulk-1759-20260911-v1')
 def test_supported_transport(self):
  tree=ast.parse(Path(bulk.__file__).read_text());text=Path(bulk.__file__).read_text();self.assertIn('maximum_bytes=4000000',text);self.assertNotIn('262144',text)
 def test_no_supplier_surface(self):
  text=Path(bulk.__file__).with_suffix('.php').read_text();self.assertNotIn('AnyTourAnexClient',text);self.assertNotIn('AnyTourAndromedaClient',text);self.assertNotIn('PRICES',text);self.assertIn("supplier_calls'=>0",text)
if __name__=='__main__':unittest.main(verbosity=2)
