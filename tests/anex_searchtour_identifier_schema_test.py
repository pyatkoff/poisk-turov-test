from pathlib import Path
import importlib.util
import sys
import unittest

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
SCRIPT=DIAG/'anex_searchtour_identifier_schema.py'
TEXT=SCRIPT.read_text()


def load():
    sys.path.insert(0,str(DIAG))
    try:
        spec=importlib.util.spec_from_file_location('searchtour_id_schema',SCRIPT)
        mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod);return mod
    finally:
        if sys.path and sys.path[0]==str(DIAG):sys.path.pop(0)


class SearchTourIdentifierSchemaTest(unittest.TestCase):
    def test_static_boundaries(self):
        self.assertIn("anex_searchtour_identifier_schema_20260913_v1",TEXT)
        self.assertIn("'25084'",TEXT)
        self.assertIn('maximum_bytes=4000000',TEXT)
        self.assertIn("'additional_prices_requests'=>0",TEXT)
        self.assertIn("'tourvisor_requests'=>0",TEXT)
        self.assertIn("'andromeda_requests'=>0",TEXT)
        self.assertIn("'freight_monitor_requests'=>0",TEXT)
        for forbidden in ('AdditionalPricesDaily','FreightMonitor_FREIGHTSBYPACKET','->bron(','bron_ticket','broninit(','->calc('):
            self.assertNotIn(forbidden,TEXT)

    def test_summary_keeps_only_candidate_fields(self):
        mod=load()
        value={'status':'completed','anex_requests':5,'concrete_row_schemas':[
            {'price':'119448','tourKey':'2637','fields':[
                {'field':'tourKey','type':'int','candidate_value':2637},
                {'field':'packetKey','type':'int','candidate_value':91234},
                {'field':'room','type':'string'},
            ]}
        ]}
        report=mod.summarize(value)
        self.assertEqual(report['concrete_count'],1)
        fields={x['field'] for x in report['candidate_identifier_fields']}
        self.assertEqual(fields,{'tourKey','packetKey'})
        self.assertNotIn('CATCLAIM',fields)
        self.assertNotIn('id',fields)

    def test_validate_refuses_side_effects(self):
        mod=load()
        base={'schema_version':1,'experiment_id':mod.EXPERIMENT,'status':'completed','automatic_retry':False,'supplier_replay_allowed':False,
              'supplier_effect':'read_only_search_expand_identifier_schema_completed','anex_requests':5,'tourvisor_requests':0,'additional_prices_requests':0,
              'andromeda_requests':0,'freight_monitor_requests':0,'booking_calls':0,'broninit_calls':0,'mapping_writes':0,
              'concrete_row_schemas':[{'price':'1','tourKey':'2','fields':[]}]}
        self.assertIs(mod.validate(base),base)
        bad=dict(base);bad['additional_prices_requests']=1
        with self.assertRaises(ValueError):mod.validate(bad)


if __name__=='__main__':unittest.main()
