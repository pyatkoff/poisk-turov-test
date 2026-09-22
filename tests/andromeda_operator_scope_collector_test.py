#!/usr/bin/env python3
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[1]
COLLECTOR=ROOT/'scripts/ops/andromeda_local_offer_collect.php'

class OperatorScopeCollectorTest(unittest.TestCase):
    def test_single_operator_filter_is_local_search3_id(self):
        text=COLLECTOR.read_text()
        self.assertIn("$operatorRaw=$args['operator-id']??'';",text)
        self.assertIn("$operatorIds=[(string)$int($operatorRaw,1,999999999)]",text)
        self.assertIn("'operatorIds'=>$operatorIds",text)
        self.assertNotIn("'OPERATORS'=>$operatorRaw",text)
        self.assertNotIn("'andromeda_operator_ids'",text)

if __name__=='__main__':
    unittest.main()
