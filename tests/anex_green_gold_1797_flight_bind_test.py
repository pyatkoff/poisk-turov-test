from pathlib import Path
import importlib.util

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/'scripts'/'diagnostics'/'anex_green_gold_1797_flight_bind.py'
TEXT=SCRIPT.read_text()


def load():
    spec=importlib.util.spec_from_file_location('bind1797',SCRIPT)
    mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod);return mod


def test_static_boundaries():
    assert "program':'1797'" in TEXT
    assert "'25084'" in TEXT and '21753' in TEXT
    assert 'PC1457' in TEXT and 'PC1456' in TEXT
    assert '13262754554359' in TEXT and "'29184'" in TEXT and "'133310'" in TEXT
    for forbidden in ('bron_ticket','->bron(','broninit(','->calc(','AdditionalPricesDaily','OPERATORS=5'):
        assert forbidden not in TEXT


def test_summary_exact_pair():
    mod=load()
    value={'selected_concrete':{'kind':'concrete','supplier_tour_program_id':'1797','currency':'RUB','price':'133310'},'anex_requests':6,'anex_flights':{'routes':[
        {'date':'12.10.2026','options':[{'name':'PC 1457','carrier':'Pegasus Airlines','departure_airport':'VKO','departure_time':'05:30','arrival_airport':'BJV','arrival_time':'09:45'}]},
        {'date':'19.10.2026','options':[{'name':'PC1456','carrier':'Pegasus Airlines','departure_airport':'BJV','departure_time':'23:55','arrival_airport':'VKO','arrival_time':'04:25'}]},
    ]}}
    report=mod.summarize(value)
    assert report['outbound_pc1457_match'] is True
    assert report['return_pc1456_match'] is True
    assert report['exact_default_flight_pair_match'] is True
    assert report['production_price_arithmetic_applied'] is False


def test_summary_does_not_infer_partial_pair():
    mod=load()
    value={'selected_concrete':{'kind':'concrete','supplier_tour_program_id':'1797','currency':'RUB'},'anex_requests':6,'anex_flights':{'routes':[{'date':'12.10.2026','options':[{'name':'PC1457','departure_airport':'VKO','arrival_airport':'BJV'}]}]}}
    report=mod.summarize(value)
    assert report['outbound_pc1457_match'] is True
    assert report['return_pc1456_match'] is False
    assert report['exact_default_flight_pair_match'] is False
