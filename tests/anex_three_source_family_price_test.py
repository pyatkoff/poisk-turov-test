from pathlib import Path
import importlib.util
import sys
import tempfile
import unittest
from unittest import mock

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
SCRIPT=DIAG/'anex_three_source_family_price.py'


def load():
    sys.path.insert(0,str(DIAG))
    try:
        spec=importlib.util.spec_from_file_location('family',SCRIPT)
        module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module
    finally:
        sys.path.pop(0)


def completed_case(m,case):
    return {'schema_version':1,'experiment_id':m.EXPERIMENT,'case_id':case,'automatic_retry':False,
            'booking_calls':0,'broninit_calls':0,'mapping_writes':0,'status':'completed',
            'supplier_effect':'read_only_search_completed','subject':{'local_hotel_id':1239,'anex_hotel_id':8652,
            'andromeda_hotel_id':'76957','hotel_name':'HEDEF RESORT HOTEL','selection_basis':'current_unique_triple_mapping',
            'anex_observation_count':66},'offers':[]}


class FamilyPriceTest(unittest.TestCase):
    def test_source_is_one_exact_family_scenario(self):
        m=load();source=m.source();marker="const ANEX_THREE_PRICE_EXPERIMENT = 'anex_three_source_family_price_20260913_v1';"
        self.assertEqual(source.count(marker),1);family=marker+source.split(marker,1)[1]
        self.assertIn('2026-10-29',family);self.assertIn('20261029',family)
        self.assertIn("'nightsFrom'=>9,'nightsTo'=>9",family);self.assertIn("'nights_from'=>9,'nights_till'=>9",family)
        self.assertIn("'childs'=>[7]",family);self.assertIn("'AGES'=>'7'",family);self.assertIn("'children'=>1",family)
        for leaked in ('2026-09-27','20260927',"'nightsFrom'=>7","'nights_from'=>7","'childs'=>[]","'CHILD'=>0"):
            self.assertNotIn(leaked,family)
        for forbidden in ("->request('AdditionalPricesDaily'",'bron_ticket','->bron(','broninit(','->calc(','get_flights('):
            self.assertNotIn(forbidden,family)

    def test_resolver_coverage_resolves_and_fails_ambiguous(self):
        m=load()
        common={'local_hotel_id':1,'date':'2026-10-29','nights':9,'adults':2,'children':1,'meal_family':'ai',
                'room_norm':'standard','placement_norm':'dbl','currency':'RUB'}
        results={'anex':{'status':'completed','offers':[dict(common,provider='anex',price='119448',fuel_charge=None)]},
                 'tourvisor':{'status':'completed','offers':[dict(common,provider='tourvisor',price='140294',fuel_charge='20846')]}}
        report=m.resolver_coverage(results)
        self.assertEqual(report['resolved_base_count'],1);self.assertEqual(report['ambiguous_base_count'],0)
        self.assertFalse(report['runtime_arithmetic_authorized'])
        results['tourvisor']['offers'].append(dict(common,provider='tourvisor',price='150000',fuel_charge='30552'))
        report=m.resolver_coverage(results)
        self.assertEqual(report['resolved_base_count'],0);self.assertEqual(report['ambiguous_base_count'],1)

    def test_context_mismatch_does_not_resolve(self):
        m=load()
        a={'local_hotel_id':1,'date':'2026-10-29','nights':9,'adults':2,'children':1,'meal_family':'ai','room_norm':'a','placement_norm':'dbl','currency':'RUB','price':'100000'}
        t=dict(a,room_norm='b',price='120000',fuel_charge='20000')
        report=m.resolver_coverage({'anex':{'status':'completed','offers':[a]},'tourvisor':{'status':'completed','offers':[t]}})
        self.assertEqual(report['exact_pair_count'],0);self.assertEqual(report['resolved_base_count'],0)

    def test_resume_reuses_completed_prefix_without_supplier_replay(self):
        m=load()
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);prior=root/'prior';output=root/'output';prior.mkdir()
            m.base.save(prior/'anex.json',completed_case(m,'anex'))
            calls=[]
            def live(_php,request):
                calls.append(request['case_id']);return completed_case(m,request['case_id'])
            with mock.patch.object(m,'source',return_value='php'),mock.patch.object(m.base,'ssh_php_no_mux',side_effect=live):
                report=m.run(output,prior)
            self.assertEqual(calls,['andromeda','tourvisor'])
            self.assertEqual(report['status'],'completed')
            self.assertEqual(report['resume_completed_cases'],['anex'])
            self.assertFalse(report['supplier_replay_requested'])
            self.assertEqual((output/'anex.json').read_text(),(prior/'anex.json').read_text())

    def test_transport_failure_preserves_completed_resume_prefix(self):
        m=load()
        SSHBatchError=type('SSHBatchError',(Exception,),{})
        error=SSHBatchError('closed');error.reason_code='ssh_connection_closed';error.attempts=2
        error.progress={'tcp_connected':True,'authenticated':False,'multiplexing_seen':False,'command_sent':False,'remote_exit_seen':False}
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);prior=root/'prior';output=root/'output';prior.mkdir()
            m.base.save(prior/'anex.json',completed_case(m,'anex'))
            with mock.patch.object(m,'source',return_value='php'),mock.patch.object(m.base,'ssh_php_no_mux',side_effect=error):
                report=m.run(output,prior)
            self.assertEqual(report['status'],'transport_unconfirmed')
            self.assertEqual(report['case_statuses'],{'anex':'completed'})
            self.assertEqual(report['missing_cases'],['andromeda','tourvisor'])
            self.assertEqual(report['transport_failure']['reason_code'],'ssh_connection_closed')
            self.assertTrue((output/'report.json').is_file())
            self.assertTrue((output/'failure.json').is_file())


if __name__=='__main__': unittest.main()
