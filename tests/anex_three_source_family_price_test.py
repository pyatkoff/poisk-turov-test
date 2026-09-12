from pathlib import Path
import importlib.util
import sys
import unittest

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


if __name__=='__main__': unittest.main()
