from pathlib import Path
import importlib.util
import unittest

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/'scripts'/'diagnostics'/'anex_green_gold_1797_flight_bind.py'
TEXT=SCRIPT.read_text()


def load():
    spec=importlib.util.spec_from_file_location('bind1797',SCRIPT)
    mod=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


class AnexGreenGold1797FlightBindTest(unittest.TestCase):
    def test_static_boundaries(self):
        self.assertIn("program':'1797'",TEXT)
        self.assertIn("'25084'",TEXT)
        self.assertIn('21753',TEXT)
        self.assertIn('PC1457',TEXT)
        self.assertIn('PC1456',TEXT)
        self.assertIn('13262754554359',TEXT)
        self.assertIn("'29184'",TEXT)
        self.assertIn("'133310'",TEXT)
        for forbidden in ('bron_ticket','->bron(','broninit(','->calc(','AdditionalPricesDaily','OPERATORS=5'):
            self.assertNotIn(forbidden,TEXT)

    def test_summary_exact_pair(self):
        mod=load()
        value={'selected_concrete':{'kind':'concrete','supplier_tour_program_id':'1797','currency':'RUB','price':'133310'},'anex_requests':6,'anex_flights':{'routes':[
            {'date':'12.10.2026','options':[{'name':'PC 1457','carrier':'Pegasus Airlines','departure_airport':'VKO','departure_time':'05:30','arrival_airport':'BJV','arrival_time':'09:45'}]},
            {'date':'19.10.2026','options':[{'name':'PC1456','carrier':'Pegasus Airlines','departure_airport':'BJV','departure_time':'23:55','arrival_airport':'VKO','arrival_time':'04:25'}]},
        ]}}
        report=mod.summarize(value)
        self.assertTrue(report['outbound_pc1457_match'])
        self.assertTrue(report['return_pc1456_match'])
        self.assertTrue(report['exact_default_flight_pair_match'])
        self.assertFalse(report['production_price_arithmetic_applied'])

    def test_summary_does_not_infer_partial_pair(self):
        mod=load()
        value={'selected_concrete':{'kind':'concrete','supplier_tour_program_id':'1797','currency':'RUB'},'anex_requests':6,'anex_flights':{'routes':[{'date':'12.10.2026','options':[{'name':'PC1457','departure_airport':'VKO','arrival_airport':'BJV'}]}]}}
        report=mod.summarize(value)
        self.assertTrue(report['outbound_pc1457_match'])
        self.assertFalse(report['return_pc1456_match'])
        self.assertFalse(report['exact_default_flight_pair_match'])


if __name__=='__main__':
    unittest.main()
