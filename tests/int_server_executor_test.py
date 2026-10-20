#!/usr/bin/env python3
import importlib.util
import copy
import hashlib
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

SCRIPT=Path(__file__).resolve().parents[1]/'scripts/deploy/int_server_executor.py'
spec=importlib.util.spec_from_file_location('int_server_executor',SCRIPT)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
SHA='cde183f7d33cef6bd1df4d0ff16dad0904d53570'

class ParseTest(unittest.TestCase):
    def test_install_runtime(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} install-runtime int-andromeda-runtime-install-20260922-v1')
        self.assertEqual('install-runtime',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_install_anex_preview(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} install-anex-preview int-anex-preview-install-20260925-v1')
        self.assertEqual('install-anex-preview',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-anex-preview int-andromeda-preview-install-20260925-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-anex-preview int-anex-preview-install-20260925-v1 extra')

    def test_install_andromeda_preview(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-preview int-andromeda-preview-install-20260923-v1')
        self.assertEqual('install-andromeda-preview',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-preview int-anex-preview-install-20260923-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-preview int-andromeda-preview-install-20260923-v1 extra')

    def test_install_andromeda_quote_preview(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-quote-preview int-andromeda-quote-preview-install-20260923-v1')
        self.assertEqual('install-andromeda-quote-preview',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-quote-preview int-anex-quote-preview-install-20260923-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} install-andromeda-quote-preview int-andromeda-quote-preview-install-20260923-v1 extra')

    def test_program_fuel_readback(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-andromeda-program-fuel-readback-20260923-v1')
        self.assertEqual('program-fuel-readback',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-anex-program-fuel-readback-20260923-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} program-fuel-readback int-andromeda-program-fuel-readback-20260923-v1 extra')

    def test_funsun_direction_fuel_seed(self):
        v=m.parse_command(
            f'/run-int-server-v1 {SHA} funsun-direction-fuel-seed '
            'int-andromeda-funsun-antalya-direction-fuel-seed-20260923-v1'
        )
        self.assertEqual('funsun-direction-fuel-seed',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        for bad in [
            f'/run-int-server-v1 {SHA} funsun-direction-fuel-seed int-anex-funsun-antalya-direction-fuel-seed-20260923-v1',
            f'/run-int-server-v1 {SHA} funsun-direction-fuel-seed int-andromeda-other-direction-fuel-seed-20260923-v1',
            f'/run-int-server-v1 {SHA} funsun-direction-fuel-seed int-andromeda-funsun-antalya-direction-fuel-seed-20260923-v1 extra',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)

    def test_funsun_direction_fx_seed(self):
        a='int-andromeda-funsun-antalya-fuel-probe-20260923-v1'
        b='int-andromeda-funsun-antalya-fuel-probe-20260923-v2'
        v=m.parse_command(
            f'/run-int-server-v1 {SHA} funsun-direction-fx-seed '
            f'int-andromeda-funsun-turkey-fx-seed-20260923-v1 {a} {b}'
        )
        self.assertEqual('funsun-direction-fx-seed',v['mode'])
        self.assertEqual(a,v['probe_operation_a']);self.assertEqual(b,v['probe_operation_b'])
        for bad in [
            f'/run-int-server-v1 {SHA} funsun-direction-fx-seed int-andromeda-funsun-turkey-fx-seed-20260923-v1 {a} {a}',
            f'/run-int-server-v1 {SHA} funsun-direction-fx-seed int-anex-funsun-turkey-fx-seed-20260923-v1 {a} {b}',
            f'/run-int-server-v1 {SHA} funsun-direction-fx-seed int-andromeda-funsun-turkey-seed-20260923-v1 {a} {b}',
            f'/run-int-server-v1 {SHA} funsun-direction-fx-seed int-andromeda-funsun-turkey-fx-seed-20260923-v1 int-anex-bad-20260923-v1 {b}',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)

    def test_operator_direction_fuel_readback(self):
        target='int-andromeda-funsun-turkey-autosave-20260923-v1'
        v=m.parse_command(
            f'/run-int-server-v1 {SHA} operator-direction-fuel-readback '
            f'int-andromeda-funsun-direction-readback-20260923-v1 {target} fun_and_sun 1 4'
        )
        self.assertEqual('operator-direction-fuel-readback',v['mode'])
        self.assertEqual(target,v['target_operation_id'])
        self.assertEqual('fun_and_sun',v['operator_family'])
        self.assertEqual(1,v['departure']);self.assertEqual(4,v['country'])
        for bad in [
            f'/run-int-server-v1 {SHA} operator-direction-fuel-readback int-anex-readback-20260923-v1 {target} fun_and_sun 1 4',
            f'/run-int-server-v1 {SHA} operator-direction-fuel-readback int-andromeda-readback-20260923-v1 {target} funsun 1 4',
            f'/run-int-server-v1 {SHA} operator-direction-fuel-readback int-andromeda-readback-20260923-v1 int-anex-bad-20260923-v1 fun_and_sun 1 4',
            f'/run-int-server-v1 {SHA} operator-direction-fuel-readback int-andromeda-readback-20260923-v1 {target} fun_and_sun 0 4',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)

    def test_funsun_direction_seed_source_is_bounded(self):
        seed=m.FUNSUN_DIRECTION_FUEL_SEED_PHP
        for required in [
            'int-andromeda-funsun-antalya-fuel-probe-20260923-v1',
            'int-andromeda-funsun-antalya-fuel-probe-20260923-v2',
            "program_key']??null)!=='114'", "tour_key']??null)!=='78'",
            "'amount']??null)!=='140'", "markup['amount']",
            "['adults'=>2,'children'=>0,'child_ages'=>[]]",
            "'per_person_one_way'", "'excluded'", "'102.7'",
            'AnyTourOperatorFuelRuleStoreV1::append',
            'AnyTourOperatorFuelRuleStoreV1::inputForTarget',
            "'final_price_verified'=>false",
        ]:
            self.assertIn(required,seed)
        for forbidden in ['curl_', 'http://', 'https://', 'PDO(', 'mysqli_', 'changeservice(', 'calc(', 'booking(']:
            self.assertNotIn(forbidden,seed)

    def test_program_fuel_probe(self):
        v=m.parse_command(
            f'/run-int-server-v1 {SHA} program-fuel-probe int-andromeda-funsun-antalya-fuel-probe-20260923-v1 '
            'int-andromeda-flight-observe-20260922-v2 funsun 114 78 0'
        )
        self.assertEqual('program-fuel-probe',v['mode'])
        self.assertEqual('funsun',v['operator_family'])
        self.assertEqual(114,v['program_key']);self.assertEqual(78,v['tour_key']);self.assertEqual(0,v['sample_index'])
        bad=[
            f'/run-int-server-v1 {SHA} program-fuel-probe int-anex-bad-20260923-v1 int-andromeda-flight-observe-20260922-v2 funsun 114 78 0',
            f'/run-int-server-v1 {SHA} program-fuel-probe int-andromeda-bad-20260923-v1 int-anex-target-20260922-v1 funsun 114 78 0',
            f'/run-int-server-v1 {SHA} program-fuel-probe int-andromeda-bad-20260923-v1 int-andromeda-flight-observe-20260922-v2 biblio 114 78 0',
            f'/run-int-server-v1 {SHA} program-fuel-probe int-andromeda-bad-20260923-v1 int-andromeda-flight-observe-20260922-v2 funsun 0 78 0',
            f'/run-int-server-v1 {SHA} program-fuel-probe int-andromeda-bad-20260923-v1 int-andromeda-flight-observe-20260922-v2 funsun 114 78 1001',
        ]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):m.parse_command(value)
    def test_anex(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 3')
        self.assertEqual(3,v['limit']);self.assertEqual('anex-demand',v['mode'])
    def test_anex_range(self):
        v=m.parse_command(
            f'/run-int-server-v1 {SHA} anex-range int-anex-mow-turkey-range-20260924-v4 '
            '1 4 2026-09-29 2026-10-19 7 2 - - 0'
        )
        self.assertEqual('anex-range',v['mode'])
        self.assertEqual(1,v['departure']);self.assertEqual(4,v['country'])
        self.assertEqual('2026-09-29',v['date_from']);self.assertEqual('2026-10-19',v['date_to'])
        self.assertEqual([],v['child_ages']);self.assertEqual('',v['meal']);self.assertEqual(0,v['region'])
        kids=m.parse_command(
            f'/run-int-server-v1 {SHA} anex-range int-anex-mow-turkey-family-20260924-v4 '
            '1 4 2026-10-01 2026-10-08 10 2 7,2 AI 15'
        )
        self.assertEqual([2,7],kids['child_ages']);self.assertEqual('AI',kids['meal']);self.assertEqual(15,kids['region'])
        bad=[
            f'/run-int-server-v1 {SHA} anex-range int-andromeda-bad-range-20260924-v4 1 4 2026-09-29 2026-10-19 7 2 - - 0',
            f'/run-int-server-v1 {SHA} anex-range int-anex-bad-range-20260924-v4 1 4 2026-10-19 2026-09-29 7 2 - - 0',
            f'/run-int-server-v1 {SHA} anex-range int-anex-bad-range-20260924-v4 1 4 2026-09-29 2026-10-20 7 2 - - 0',
            f'/run-int-server-v1 {SHA} anex-range int-anex-bad-range-20260924-v4 1 4 2026-09-29 2026-10-19 7 2 18 - 0',
            f'/run-int-server-v1 {SHA} anex-range int-anex-bad-range-20260924-v4 1 4 2026-09-29 2026-10-19 7 2 1,2,3,4 - 0',
        ]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):
                m.parse_command(value)
    def test_andromeda(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-10-10 2026-10-12 7 2 - 0 0')
        self.assertEqual('',v['meal']);self.assertEqual(0,v['max_captures'])
        bounded=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v2 1 4 2026-10-13 2026-10-14 7 2 - 0 10')
        self.assertEqual(10,bounded['max_captures'])
        scaled=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v4 1 4 2026-10-15 2026-10-16 7 2 - 0 30')
        self.assertEqual(30,scaled['max_captures'])
    def test_andromeda_operator_scope(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 43')
        self.assertEqual('andromeda-operator-scope',v['mode'])
        self.assertEqual(43,v['operator_id'])
        self.assertEqual(0,v['max_captures'])
        self.assertEqual('',v['meal'])

    def test_andromeda_operator_preflight(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 43')
        self.assertEqual('andromeda-operator-preflight',v['mode'])
        self.assertEqual(43,v['operator_id'])
        self.assertEqual('',v['meal'])
        self.assertNotIn('max_captures',v)

    def test_andromeda_external_group(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} andromeda-external-group int-andromeda-external-group-turkey-20260922-v1 1 4 2026-10-20 2026-10-20 7 2 - 0')
        self.assertEqual('andromeda-external-group',v['mode'])
        self.assertEqual(1,v['max_captures'])
        self.assertEqual(0,v['region'])

    def test_match_common4_acquire_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-acquire int-andromeda-match-common4-acquire-20260923-v1 0 100')
        self.assertEqual('match-common4-acquire',v['mode'])
        self.assertEqual(0,v['offset']);self.assertEqual(100,v['limit'])
        for bad in [
            f'/run-int-server-v1 {SHA} match-common4-readback int-anex-match-common4-readback-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-common4-readback int-andromeda-match-common4-readback-20260923-v1 0 101',
          f'/run-int-server-v1 {SHA} match-common4-acquire int-anex-match-common4-acquire-20260923-v1 0 100',
            f'/run-int-server-v1 {SHA} match-common4-acquire int-andromeda-match-common4-acquire-20260923-v1 0 101',
            f'/run-int-server-v1 {SHA} match-common4-acquire int-andromeda-match-common4-acquire-20260923-v1 1750 50',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)


    def test_match_common4_continuation_acquire_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-c0-20260923-v1 0 30')
        self.assertEqual('match-common4-continuation-acquire',v['mode'])
        self.assertEqual(0,v['offset']);self.assertEqual(30,v['limit'])
        wide=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-wide-20260923-v1 135 1214')
        self.assertEqual(135,wide['offset']);self.assertEqual(1214,wide['limit'])
        for bad in [
            f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-anex-match-common4-continuation-c0-20260923-v1 0 30',
            f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-c0-20260923-v1 0 1350',
            f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-c0-20260923-v1 1300 50',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)


    def test_match_common4_continuation_resume_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-resume-readback int-andromeda-match-common4-continuation-resume-readback-20260924-v1')
        self.assertEqual('match-common4-continuation-resume-readback',v['mode'])

    def test_match_common4_resume_salvage_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-resume-salvage int-andromeda-match-common4-resume-salvage-20260924-v1')
        self.assertEqual('match-common4-resume-salvage',v['mode'])

    def test_match_common4_mass_current_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-mass-current int-andromeda-match-common4-mass-current-20260924-v1')
        self.assertEqual('match-common4-mass-current',v['mode'])

    def test_match_common4_current_v2_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-current-v2 int-andromeda-match-common4-current-20260923-v1')
        self.assertEqual('match-common4-current-v2',v['mode'])
        self.assertEqual('int-andromeda-match-common4-current-20260923-v1',v['operation_id'])
        for bad in [
            f'/run-int-server-v1 {SHA} match-common4-current-v2 int-anex-match-common4-current-20260923-v1',
            f'/run-int-server-v1 {SHA} match-common4-current-v2 int-andromeda-match-common4-current-20260923-v1 extra',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)


    def test_match_common4_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-readback int-andromeda-match-common4-readback-20260923-v1 0 100')
        self.assertEqual('match-common4-readback',v['mode'])
        self.assertEqual(0,v['offset']);self.assertEqual(100,v['limit'])
        for bad in [
            f'/run-int-server-v1 {SHA} match-common4-readback int-anex-match-common4-readback-20260923-v1 0 100',
            f'/run-int-server-v1 {SHA} match-common4-readback int-andromeda-match-common4-readback-20260923-v1 0 101',
        ]:
            with self.subTest(bad=bad),self.assertRaises(ValueError):
                m.parse_command(bad)


    def test_match_live234_secondary_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-tv234-secondary int-andromeda-match-tv234-secondary-20260923-v1 0 78')
        self.assertEqual('match-tv234-secondary',v['mode'])
        self.assertEqual(0,v['offset']);self.assertEqual(78,v['limit'])

    def test_match_live234_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-tv234-readback int-andromeda-match-tv234-readback-20260923-v1 0 78')
        self.assertEqual('match-tv234-readback',v['mode'])
        self.assertEqual(0,v['offset']);self.assertEqual(78,v['limit'])

    def test_match_live942_modes(self):
        tv=m.parse_command(f'/run-int-server-v1 {SHA} match-tv942 int-anex-match-tv942-20260923-v1 0 350')
        self.assertEqual('match-tv942',tv['mode']);self.assertEqual(0,tv['offset']);self.assertEqual(350,tv['limit'])
        samo=m.parse_command(f'/run-int-server-v1 {SHA} match-samo942 int-andromeda-match-samo942-20260923-v1 700 242')
        self.assertEqual('match-samo942',samo['mode']);self.assertEqual(700,samo['offset']);self.assertEqual(242,samo['limit'])

    def test_match_secondary_audit_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-secondary-audit int-andromeda-match-secondary-audit-20260923-v1')
        self.assertEqual('match-secondary-audit',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_coverage_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-coverage int-andromeda-match-coverage-20260923-v1')
        self.assertEqual('match-coverage',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_coverage_v2_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-coverage-v2 int-andromeda-match-coverage-v2-20260923-v1')
        self.assertEqual('match-coverage-v2',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_coverage_v2_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-coverage-v2-readback int-andromeda-match-coverage-v2-readback-20260923-v1')
        self.assertEqual('match-coverage-v2-readback',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_samo_live30_persistence_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-samo-live30-persistence-readback int-andromeda-samo-live30-persistence-readback-20260929-v1')
        self.assertEqual('match-samo-live30-persistence-readback',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} match-samo-live30-persistence-readback int-anex-samo-live30-persistence-readback-20260929-v1')
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} match-samo-live30-persistence-readback int-andromeda-samo-live30-persistence-readback-20260929-v1 extra')

    def test_match_samo_live30_persistence_executor_contract(self):
        source=SCRIPT.read_text()
        for required in [
            "scripts/diagnostics/hotel_match_samo_live30_persistence_reconcile_v1.php",
            "def match_samo_live30_persistence(stage):",
            "HMSLPR_SELFTEST_OK",
            "historical_completeness_proven",
            "unknown_history_gap",
            "mode=='match-samo-live30-persistence-readback'",
        ]:
            self.assertIn(required,source)
        self.assertEqual(2,source.count("'match-coverage-v2-readback','match-samo-live30-persistence-readback','match-coverage-readback'"))
        self.assertIn("result['status']='reconciled_read_only'",source)

    def test_match_coverage_readback_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-coverage-readback int-andromeda-match-coverage-readback-20260923-v1')
        self.assertEqual('match-coverage-readback',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_tv942_reconcile_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-tv942-reconcile int-anex-match-tv942-reconcile-20260923-v1')
        self.assertEqual('match-tv942-reconcile',v['mode'])
        self.assertEqual(SHA,v['source_sha'])

    def test_match_tv942_write_mode(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} match-tv942-write int-anex-match-tv942-write-20260923-v1')
        self.assertEqual('match-tv942-write',v['mode'])
        self.assertEqual(SHA,v['source_sha'])
        with self.assertRaises(ValueError):
            m.parse_command(f'/run-int-server-v1 {SHA} match-tv942-write int-andromeda-match-tv942-write-20260923-v1')

    def test_match_readback_mode(self):
        tv=m.parse_command(f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 tv 0 350')
        self.assertEqual('match-readback',tv['mode']);self.assertEqual('tv',tv['lane'])
        self.assertEqual(0,tv['offset']);self.assertEqual(350,tv['limit'])
        samo=m.parse_command(f'/run-int-server-v1 {SHA} match-readback int-andromeda-match-samo942-readback-20260923-v1 samo 700 242')
        self.assertEqual('samo',samo['lane']);self.assertEqual(700,samo['offset']);self.assertEqual(242,samo['limit'])

    def test_local_readback(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} local-readback int-andromeda-local-readback-20260921-v1 1 4 2026-09-24 2026-09-25 7 2 - 0')
        self.assertEqual('local-readback',v['mode'])
        self.assertEqual(4,v['country'])
    def test_reconcile(self):
        v=m.parse_command(f'/run-int-server-v1 {SHA} reconcile int-andromeda-reconcile-turkey-20260921-v1 int-andromeda-current-turkey-20260921-v1')
        self.assertEqual('reconcile',v['mode'])
        self.assertEqual('int-andromeda-current-turkey-20260921-v1',v['target_operation_id'])
    def test_rejects_unsafe_or_unbounded(self):
        bad=[
          f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 0',
          f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260921-v1 21',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-99-10 2026-10-12 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v3 1 4 2026-10-13 2026-10-14 7 2 - 0 31',
          f'/run-int-server-v1 {SHA} andromeda-scope int-andromeda-current-scope-20260921-v1 1 4 2026-10-10 2026-10-12 7 2 ";rm" 0 0',
          f'/run-int-server-v1 {SHA[:-1]} anex-demand int-anex-current-demand-20260921-v1 3',
          f'/run-int-server-v1 {SHA} anex-demand ../../bad 3',
          f'/run-int-server-v1 {SHA} reconcile int-andromeda-reconcile-turkey-20260921-v1 ../../bad',
          f'/run-int-server-v1 {SHA} reconcile int-andromeda-current-turkey-20260921-v1 int-andromeda-current-turkey-20260921-v1',
          f'/run-int-server-v1 {SHA} install-runtime int-andromeda-runtime-install-20260922-v1 extra',
          f'/run-int-server-v1 {SHA} install-anex-preview int-anex-preview-install-20260925-v1 extra',
          f'/run-int-server-v1 {SHA} install-anex-preview int-andromeda-preview-install-20260925-v1',
          f'/run-int-server-v1 {SHA} install-andromeda-preview int-andromeda-preview-install-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} install-andromeda-preview int-anex-preview-install-20260923-v1',
          f'/run-int-server-v1 {SHA} andromeda-external-group int-andromeda-external-group-turkey-20260922-v1 1 4 2026-10-20 2026-10-20 7 2 - 0 2',
          f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-operator-scope int-andromeda-intourist-scope-20260922-v1 1 4 2026-10-11 2026-10-11 7 2 - 0 43 extra',
          f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 0',
          f'/run-int-server-v1 {SHA} andromeda-operator-preflight int-andromeda-intourist-preflight-20260923-v1 1 4 2026-10-18 2026-10-18 7 2 - 0 43 extra',
          f'/run-int-server-v1 {SHA} match-common4-acquire int-anex-match-common4-acquire-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-common4-acquire int-andromeda-match-common4-acquire-20260923-v1 0 101',
          f'/run-int-server-v1 {SHA} match-common4-acquire int-andromeda-match-common4-acquire-20260923-v1 1750 50',
          f'/run-int-server-v1 {SHA} match-tv234-secondary int-anex-match-tv234-secondary-20260923-v1 0 78',
          f'/run-int-server-v1 {SHA} match-tv234-secondary int-andromeda-match-tv234-secondary-20260923-v1 200 35',
          f'/run-int-server-v1 {SHA} match-tv234-secondary int-andromeda-match-tv234-secondary-20260923-v1 0 79',
          f'/run-int-server-v1 {SHA} match-tv942 int-andromeda-match-tv942-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-samo942 int-anex-match-samo942-20260923-v1 0 100',
          f'/run-int-server-v1 {SHA} match-tv942 int-anex-match-tv942-20260923-v1 900 43',
          f'/run-int-server-v1 {SHA} match-samo942 int-andromeda-match-samo942-20260923-v1 0 351',
          f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 nope 0 100',
          f'/run-int-server-v1 {SHA} match-readback int-andromeda-match-tv942-readback-20260923-v1 tv 0 100',
          f'/run-int-server-v1 {SHA} match-readback int-anex-match-tv942-readback-20260923-v1 tv 900 43',
          f'/run-int-server-v1 {SHA} match-secondary-audit int-anex-match-secondary-audit-20260923-v1',
          f'/run-int-server-v1 {SHA} match-secondary-audit int-andromeda-match-secondary-audit-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-coverage int-anex-match-coverage-20260923-v1',
          f'/run-int-server-v1 {SHA} match-coverage int-andromeda-match-coverage-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-coverage-v2 int-anex-match-coverage-v2-20260923-v1',
          f'/run-int-server-v1 {SHA} match-coverage-v2 int-andromeda-match-coverage-v2-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-coverage-v2-readback int-anex-match-coverage-v2-readback-20260923-v1',
          f'/run-int-server-v1 {SHA} match-coverage-v2-readback int-andromeda-match-coverage-v2-readback-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-coverage-readback int-anex-match-coverage-readback-20260923-v1',
          f'/run-int-server-v1 {SHA} match-coverage-readback int-andromeda-match-coverage-readback-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-tv942-reconcile int-andromeda-match-tv942-reconcile-20260923-v1',
          f'/run-int-server-v1 {SHA} match-tv942-reconcile int-anex-match-tv942-reconcile-20260923-v1 extra',
          f'/run-int-server-v1 {SHA} match-tv942-write int-anex-match-tv942-write-20260923-v1 extra',
        ]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):m.parse_command(value)


class ProgramFuelProbeSourceTest(unittest.TestCase):
    def test_generic_probe_source_is_bounded(self):
        source=SCRIPT.parents[2]/'scripts/diagnostics/int_andromeda_program_getflights_probe_v1.php'
        text=source.read_text()
        run=subprocess.run(['php','-l',str(source)],capture_output=True,text=True)
        self.assertEqual(0,run.returncode,run.stderr)
        for required in [
            "int-andromeda-program-getflights-probe-v1",
            "INT_PROGRAM_PROBE_TARGET_OPERATION","INT_PROGRAM_PROBE_OPERATOR_FAMILY",
            "INT_PROGRAM_PROBE_PROGRAM_KEY","INT_PROGRAM_PROBE_TOUR_KEY",
            "INT_PROGRAM_PROBE_SAMPLE_INDEX","distinct_spo",
            "anytour_andromeda_quote_supplier","->package(","->getFlights(",
            "cheapestRequiredFlightSelection","reportedFuelSurcharges",
            "'changeservice'=>0","'calc'=>0","'booking'=>0",
            "'database_writes'=>0","'final_price_verified'=>false",
        ]:
            self.assertIn(required,text)
        self.assertEqual(1,text.count("->getFlights("))
        for forbidden in ["->changeService(","->calc(","bron_ticket","action=bron","'supplier_offer_id'=>"]:
            self.assertNotIn(forbidden,text)

class CoordinatorTest(unittest.TestCase):
    def test_only_current_journal_can_authorize_a_server_command(self):
        body=f'/run-int-server-v1 {SHA} anex-demand int-anex-current-demand-20260922-v1 3'
        comment={'id':123,'body':body,'user':{'id':226193297},'author_association':'OWNER'}
        replies={
            '/issues/comments/123':comment,
            '/git/ref/heads/main':{'object':{'sha':'a'*40}},
            '/git/ref/heads/'+m.FEATURE:{'object':{'sha':SHA}},
        }
        with patch.object(m,'api_get',side_effect=lambda path,token:replies[path]) as api:
            event={'issue':{'number':4709},'comment':comment}
            self.assertEqual(m.checked_event('fixture',event,'a'*40)['source_sha'],SHA)
            for number in (4217,3419,2530,996,1646):
                api.reset_mock()
                event={'issue':{'number':number},'comment':comment}
                with self.subTest(issue=number),self.assertRaisesRegex(ValueError,'issue'):
                    m.checked_event('fixture',event,'a'*40)
                api.assert_not_called()

    def test_workflow_uses_the_same_current_journal(self):
        text=(SCRIPT.parents[2]/'.github/workflows/int-server-executor.yml').read_text()
        self.assertEqual(text.count('github.event.issue.number == 4709'),2)
        for number in (4217,3419,2530,996):
            self.assertNotIn(f'github.event.issue.number == {number}',text)
        spec=importlib.util.spec_from_file_location('direction_journal',SCRIPT.parents[2]/'scripts/diagnostics/int_funsun_direction_store_server_readback.py')
        direction=importlib.util.module_from_spec(spec);spec.loader.exec_module(direction)
        self.assertEqual(direction.ISSUE,m.ISSUE)


class CompletionTest(unittest.TestCase):
    ENTRYPOINT = m
    CONTROL = m

    def command(self):
        return {
            'source_sha': SHA, 'mode': 'andromeda-operator-preflight',
            'operation_id': 'int-andromeda-preflight-fixture-20261008-v1',
            'operator_id': 43,
        }

    def receipt(self):
        command = self.command()
        return {
            'schema_version': 1,
            **{key: command[key] for key in ('source_sha', 'mode', 'operation_id')},
            'status': 'preflight_complete',
            'operator_preflight': {
                'status': 'resolved', 'operator_id': '43',
                'operator_name': None, 'dictionary_operator_id': None,
                'andromeda_operators': '342',
                'supplier_calls': 0, 'database_writes': 0,
            },
            'supplier_calls': 0, 'database_writes': 0,
            'booking_calls': 0, 'lead_calls': 0,
            'before_db': {'rows_total': 57}, 'after_db': {'rows_total': 57},
            'production_before': {'index.php': 'b' * 64},
            'production_after': {'index.php': 'b' * 64},
            'production_unchanged': True,
        }

    def run_main(self, receipt, command=None):
        command = self.command() if command is None else command
        original = copy.deepcopy(receipt)
        output = io.StringIO()
        with patch.dict(os.environ, {
                'GH_TOKEN': 'fixture', 'GITHUB_SHA': 'a' * 40,
                'GITHUB_EVENT_PATH': '/unused-fixture-event.json',
            }), patch('sys.argv', ['executor', '--source-root', 'fixture-source']), \
                patch.object(Path, 'read_text', return_value='{}'), \
                patch.object(self.CONTROL, 'checked_event', return_value=command) as checked, \
                patch.object(self.CONTROL, 'execute', return_value=receipt) as execute, \
                patch.object(self.CONTROL, 'ensure_supplier_slot',
                             side_effect=AssertionError('unexpected supplier slot')) as slot, \
                patch('sys.stdout', output):
            try:
                self.ENTRYPOINT.main()
            except SystemExit as exc:
                code = exc.code
            else:
                code = 0
        checked.assert_called_once_with('fixture', {}, 'a' * 40)
        execute.assert_called_once_with(command, Path('fixture-source'))
        slot.assert_not_called()
        self.assertEqual(json.dumps(original, sort_keys=True) + '\n', output.getvalue())
        self.assertEqual(original, receipt)
        return code

    def test_resolved_preflight_prints_unchanged_receipt_once(self):
        self.assertEqual(0, self.run_main(self.receipt()))
        receipt = self.receipt()
        receipt['operator_preflight']['andromeda_operators'] = '342,115'
        self.assertEqual(0, self.run_main(receipt))

    def test_failed_or_unproven_preflight_still_exits_one(self):
        cases = []
        for status in ('blocked', 'unknown_no_replay', 'reserved'):
            cases.append((('status',), status))
        for status in ('failed', 'unknown', 'reserved'):
            cases.append((('operator_preflight', 'status'), status))
        cases.extend([
            (('source_sha',), 'd' * 40),
            (('operation_id',), 'int-andromeda-other-fixture-20261008-v1'),
            (('mode',), 'local-readback'),
            (('schema_version',), 2), (('schema_version',), True),
            (('operator_preflight', 'operator_id'), '44'),
            (('operator_preflight', 'operator_id'), 43),
            (('production_unchanged',), False),
            (('production_unchanged',), 1),
            (('after_db',), {'rows_total': 58}),
            (('production_after',), {'index.php': 'c' * 64}),
            (('before_db',), {}), (('after_db',), None),
            (('production_before',), {}), (('production_after',), None),
        ])
        for native in (None, 342, '', '0', '342,0', '342,', '-342', '3.42', ' 342'):
            cases.append((('operator_preflight', 'andromeda_operators'), native))
        for parent, keys in (
            ((), ('supplier_calls', 'database_writes', 'booking_calls', 'lead_calls')),
            (('operator_preflight',), ('supplier_calls', 'database_writes')),
        ):
            for key in keys:
                with self.subTest(missing_counter=parent + (key,)):
                    receipt = self.receipt()
                    target = receipt
                    for part in parent:
                        target = target[part]
                    del target[key]
                    self.assertEqual(1, self.run_main(receipt))
                for value in (1, None, False, 0.0):
                    cases.append((parent + (key,), value))
        for path, value in cases:
            with self.subTest(path=path, value=value):
                receipt = self.receipt()
                target = receipt
                for key in path[:-1]:
                    target = target[key]
                target[path[-1]] = value
                self.assertEqual(1, self.run_main(receipt))
        for key in ('operator_preflight', 'source_sha', 'operation_id', 'mode',
                    'schema_version', 'production_unchanged'):
            with self.subTest(missing=key):
                receipt = self.receipt()
                del receipt[key]
                self.assertEqual(1, self.run_main(receipt))
        command = self.command()
        command['mode'] = 'local-readback'
        receipt = self.receipt()
        receipt['mode'] = 'local-readback'
        self.assertEqual(1, self.run_main(receipt, command))
        for key, value in (('operator_id', 0), ('operator_id', True),
                           ('source_sha', None), ('operation_id', None)):
            with self.subTest(invalid_command_key=key, value=value):
                command = self.command()
                command[key] = value
                receipt = self.receipt()
                if key != 'operator_id':
                    receipt[key] = value
                self.assertEqual(1, self.run_main(receipt, command))

    def test_existing_success_statuses_are_unchanged(self):
        for status in ('complete', 'reconciled_read_only', 'installed'):
            with self.subTest(status=status):
                self.assertEqual(0, self.run_main({'status': status}))


class BundleTest(unittest.TestCase):
    def test_private_inventory_only(self):
        with tempfile.TemporaryDirectory() as td:
            root=Path(td);app=root/'app/integrations';app.mkdir(parents=True)
            for i in range(21):(app/f'x{i}.php').write_text('<?php\n')
            for rel in m.FIXED:
                p=root/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<?php\n')
            blob,manifest=m.bundle_source(root)
            self.assertGreater(len(blob),100)
            self.assertGreaterEqual(len(manifest),28)
            self.assertTrue(all(x.startswith('app/integrations/') or x in m.FIXED for x in manifest))
            self.assertFalse(any(x.startswith('.github/') for x in manifest))
            p=root/m.FIXED[0];p.unlink();p.symlink_to(app/'x0.php')
            with self.assertRaises(ValueError):m.bundle_source(root)

class RemoteTransportTest(unittest.TestCase):
    def test_embedded_remote_executor_command_stays_bounded(self):
        compressed=__import__('zlib').compress(m.REMOTE.encode(),9)
        encoded=__import__('base64').b64encode(compressed).decode()
        command="python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\"" + encoded + "\")))'"
        self.assertLessEqual(len(command.encode()),65536)
        self.assertEqual(m.REMOTE.encode(),__import__('zlib').decompress(__import__('base64').b64decode(encoded)))
        self.assertLess(len(compressed),len(m.REMOTE.encode()))

class InstallRuntimeTest(unittest.TestCase):
    def fixture(self, root: Path, operation: str, fail_target_lint: bool = False, mode: str = 'install-runtime'):
        source=root/'source';app=source/'app/integrations';app.mkdir(parents=True)
        for i in range(21):(app/f'x{i}.php').write_text('<?php\n')
        # The functional fuel consumer must be in the exact installed inventory.
        (app/'three-provider-fuel-evidence.php').write_text('<?php\n')
        for rel in m.FIXED:
            p=source/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<?php\n')
        if mode == 'install-anex-preview':
            (app/'anex-initial-week-gate.php').write_text('<?php\n')
        bundle,manifest=m.bundle_source(source)
        archive=root/'source.tar.gz';archive.write_bytes(bundle)
        home=root/'home';project=home/'www/anytoour.ru'
        runtime=project/'_preview/search3-anex-candidate'
        (runtime/'app/integrations').mkdir(parents=True)
        (runtime/'app/integrations/x0.php').write_text('<?php /* old */\n')
        if mode == 'install-anex-preview':
            (runtime/'api-anex-search3-preview.php').write_text('<?php /* old endpoint */\n')
        if mode == 'install-andromeda-preview':
            (runtime/'api-andromeda-search3-preview.php').write_text('<?php /* old endpoint */\n')
        if mode == 'install-andromeda-quote-preview':
            (runtime/'api-andromeda-quote-preview.php').write_text('<?php /* old quote endpoint */\n')
        bindir=root/'bin';bindir.mkdir()
        php=bindir/'php'
        php.write_text(
            '#!/bin/sh\n'
            'if [ "${FAKE_PHP_FAIL_TARGET:-0}" = 1 ] && echo "$2" | grep -q "/www/anytoour.ru/"; then exit 1; fi\n'
            'exit 0\n'
        )
        php.chmod(0o755)
        payload={'source_sha':SHA,'mode':mode,'operation_id':operation,
                 'archive':str(archive),'manifest_sha256':hashlib.sha256(
                     json.dumps(manifest,sort_keys=True,separators=(',',':')).encode()
                 ).hexdigest()}
        env=dict(os.environ,HOME=str(home),PATH=str(bindir)+os.pathsep+os.environ.get('PATH',''))
        if fail_target_lint:env['FAKE_PHP_FAIL_TARGET']='1'
        remote_py=root/'remote.py';remote_py.write_text(m.REMOTE)
        run=subprocess.run(['python3',str(remote_py)],input=json.dumps(payload),
                           text=True,capture_output=True,env=env,timeout=30)
        self.assertEqual('',run.stderr)
        self.assertEqual(0,run.returncode)
        return json.loads(run.stdout),home,project

    def test_exact_install_backup_and_readback(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(Path(td),'int-andromeda-runtime-install-20260922-v1')
            self.assertEqual('installed',result['status'])
            self.assertEqual(22,result['install']['files'])
            self.assertEqual('<?php\n',(project/'_preview/search3-anex-candidate/app/integrations/x0.php').read_text())
            backup=home/'.anytoour-int-executor/int-andromeda-runtime-install-20260922-v1/backup/app/integrations/x0.php'
            self.assertEqual('<?php /* old */\n',backup.read_text())
            self.assertTrue(result['public_ui_entrypoints_unchanged'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])
            self.assertFalse((project/'app').exists())

    def test_anex_preview_install_backup_and_readback(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-anex-preview-install-20260925-v1',mode='install-anex-preview')
            self.assertEqual('installed',result['status'])
            self.assertEqual(2,result['install']['files'])
            self.assertEqual(2,result['install']['changed_files'])
            self.assertEqual(1,result['install']['created_files'])
            endpoint=result['install']['endpoint']
            dependency=result['install']['dependency']
            self.assertEqual('v2/api-anex-search3-preview.php',endpoint['source'])
            self.assertEqual('api-anex-search3-preview.php',endpoint['target'])
            self.assertTrue(endpoint['changed'])
            self.assertEqual('app/integrations/anex-initial-week-gate.php',dependency['source'])
            self.assertEqual('app/integrations/anex-initial-week-gate.php',dependency['target'])
            self.assertTrue(dependency['changed'])
            runtime=project/'_preview/search3-anex-candidate'
            self.assertEqual('<?php\n',(runtime/'api-anex-search3-preview.php').read_text())
            self.assertEqual('<?php\n',(runtime/'app/integrations/anex-initial-week-gate.php').read_text())
            self.assertEqual('<?php /* old */\n',(runtime/'app/integrations/x0.php').read_text())
            backup=home/'.anytoour-int-executor/int-anex-preview-install-20260925-v1/backup/api-anex-search3-preview.php'
            self.assertEqual('<?php /* old endpoint */\n',backup.read_text())
            self.assertTrue(result['public_ui_entrypoints_unchanged'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])

    def test_andromeda_preview_install_backup_and_readback(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-andromeda-preview-install-20260923-v1',mode='install-andromeda-preview')
            self.assertEqual('installed',result['status'])
            self.assertEqual(23,result['install']['files'])
            endpoint=result['install']['endpoint']
            self.assertEqual('v2/api-andromeda-search3-preview.php',endpoint['source'])
            self.assertEqual('api-andromeda-search3-preview.php',endpoint['target'])
            self.assertTrue(endpoint['changed'])
            self.assertEqual('<?php\n',(project/'_preview/search3-anex-candidate/api-andromeda-search3-preview.php').read_text())
            backup=home/'.anytoour-int-executor/int-andromeda-preview-install-20260923-v1/backup/api-andromeda-search3-preview.php'
            self.assertEqual('<?php /* old endpoint */\n',backup.read_text())
            self.assertTrue(result['public_ui_entrypoints_unchanged'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])

    def test_andromeda_quote_preview_install_backup_and_readback(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-andromeda-quote-preview-install-20260923-v1',mode='install-andromeda-quote-preview')
            self.assertEqual('installed',result['status'])
            self.assertEqual(23,result['install']['files'])
            endpoint=result['install']['endpoint']
            self.assertEqual('v2/api-andromeda-quote-preview.php',endpoint['source'])
            self.assertEqual('api-andromeda-quote-preview.php',endpoint['target'])
            self.assertTrue(endpoint['changed'])
            self.assertEqual('<?php\n',(project/'_preview/search3-anex-candidate/api-andromeda-quote-preview.php').read_text())
            backup=home/'.anytoour-int-executor/int-andromeda-quote-preview-install-20260923-v1/backup/api-andromeda-quote-preview.php'
            self.assertEqual('<?php /* old quote endpoint */\n',backup.read_text())
            self.assertTrue(result['public_ui_entrypoints_unchanged'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])

    def test_anex_preview_post_install_failure_rolls_back_both_files(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-anex-preview-install-20260925-v2',True,mode='install-anex-preview')
            self.assertEqual('rolled_back',result['status'])
            self.assertEqual('complete',result['rollback']['status'])
            runtime=project/'_preview/search3-anex-candidate'
            self.assertEqual('<?php /* old endpoint */\n',(runtime/'api-anex-search3-preview.php').read_text())
            self.assertFalse((runtime/'app/integrations/anex-initial-week-gate.php').exists())
            self.assertEqual('<?php /* old */\n',(runtime/'app/integrations/x0.php').read_text())
            self.assertFalse(result['runtime_changed'])
            self.assertEqual(0,result['supplier_calls'])
            self.assertEqual(0,result['database_writes'])

    def test_post_install_failure_rolls_back_every_file(self):
        with tempfile.TemporaryDirectory() as td:
            result,home,project=self.fixture(
                Path(td),'int-andromeda-runtime-install-20260922-v2',True)
            self.assertEqual('rolled_back',result['status'])
            self.assertEqual('complete',result['rollback']['status'])
            self.assertEqual('<?php /* old */\n',(project/'_preview/search3-anex-candidate/app/integrations/x0.php').read_text())
            self.assertFalse((project/'_preview/search3-anex-candidate/app/integrations/x1.php').exists())
            self.assertFalse((project/'_preview/search3-anex-candidate/app/integrations/three-provider-fuel-evidence.php').exists())
            self.assertFalse(result['runtime_changed'])

class ContractTest(unittest.TestCase):
    def test_control_boundaries(self):
        text=SCRIPT.read_text()
        for x in ["ISSUE = 4709","OWNER_ID = 226193297","FEATURE = 'feature/anex-search-adapter-20260907'",
                  "operation_exists_no_replay","StrictHostKeyChecking=yes","production_unchanged","remote_command_size","zlib.compress",
                  "install-runtime","install-anex-preview","install-andromeda-preview","install-andromeda-quote-preview","install-plan.json","anex-preview-install-plan.json","preview-install-plan.json","quote-preview-install-plan.json","install-state.json","rollback_install",
                  "app/integrations/anex-initial-week-gate.php","v2/api-anex-search3-preview.php","api-anex-search3-preview.php","v2/api-andromeda-search3-preview.php","api-andromeda-search3-preview.php","v2/api-andromeda-quote-preview.php","api-andromeda-quote-preview.php",
                  "manifest_digest","public_ui_entrypoints_unchanged","three-provider-fuel-evidence.php",
                  "anex-range","anex_date_range","anex_local_offer_collect.php",
                  "anex_local_offer_demand_fill.php","andromeda_local_offer_collect.php",
                  "search3-local-results-read-v1.php","--max-captures=","non_external_only",
                  "andromeda-external-group","external_group_only",
                  "andromeda-operator-scope","--operator-id=","operatorId",
                  "andromeda-operator-preflight","operator_preflight","operator_not_loaded",
                  "operator_dictionary_missing","andromeda_operators","operator_preflight_db_drift",
                  "operator_name","dictionary_operator_id",
                  "reconcile_target","collector_stderr_sha256","skipped_after_collector_nonzero",
                  "php=\"$c=require $argv[1];","allowed_keys={'status'","name_sha256","top_level_keys",
                  "local-readback","local_readback_exit","stderr_sha256",
                  "program-fuel-readback","program_fuel_readback","program_fuel_readback_php_b64",
                  "program_fuel_readback_acceptance","program_fuel_readback_db_drift",
                  "int-program-fuel-readback-wrapper-v1","set_exception_handler","diagnostic_status","partial",
                  "program-fuel-probe","program_fuel_probe","program_fuel_probe_php_b64",
                  "program_fuel_probe_acceptance","program_fuel_probe_db_drift",
                  "sample_basis","distinct_spo","distinct_mapped_hotel",
                  "sample_distinct_mapped_hotel_index","retained_distinct_mapped_hotel_count",
                  "retained_distinct_spo_count","mapped_local_hotel","retained_freight_external",
                  "funsun-direction-fuel-seed","funsun_direction_fuel_seed",
                  "funsun_direction_fuel_seed_php_b64","direction_fuel_seed_acceptance",
                  "direction_fuel_seed_db_drift","int-funsun-direction-fuel-seed-v1",
                  "funsun-direction-fx-seed","funsun_direction_fx_seed",
                  "int_funsun_direction_fx_seed_v1.php","direction_fx_seed_acceptance",
                  "direction_fx_seed_db_drift","fuel_rule_writes","private_evidence_writes",
                  "operator-direction-fuel-readback","operator_direction_fuel_readback",
                  "int_operator_direction_fuel_mass_readback_v1.php","direction_fuel_readback_acceptance",
                  "direction_fuel_readback_db_drift","INT_DIRECTION_FUEL_READBACK_OPERATION",
                  "AnyTourOperatorFuelRuleStoreV1::append","per_person_one_way",
                  "LOCAL_READER_MISSING","LOCAL_DB_CONNECTION","LOCAL_DB_NOT_CONFIGURED",
                  "require_once $config","errorSha256","attempt_state","package_record",
                  "diagnostic_code","actualization","failure_class","actions_used",
                  "supplier_error_facts","reason_category","code_field","error_sha256",
                  "rejection_summary","ownership_class","missing_field","classified",
                  "match-common4-acquire","run_match_common4_acquire",
                  "match-common4-continuation-acquire","run_match_common4_continuation_acquire",
                  "hotel_match_live30_common4_continuation_acquire_v10.py",
                  "hotel-match-live30-common4-continuation-plan-1971-20260923-v9",
                  "ce464a7b71dc72cf425197c73c1b8a4770adaf586ee05d169f4c67f8fc43ccca",
                  "match_common4_continuation_plan_hash","match_common4_continuation_terminal_hash",
                  "match_common4_continuation_call_cap_guard",
                  "match-common4-readback","read_match_common4",
                  "match-common4-current-v2","run_match_common4_current_v2",
                  "hotel_match_live30_common4_current_v2.php",
                  "match_common4_current_v2_terminal_hash","match_common4_current_v2_authority_guard",
                  "match_common4_readback_hash","match_common4_readback_guard",
                  "hotel_match_live30_common4_plan_v1.php",
                  "hotel_match_live30_common4_acquire_v1.py",
                  "match_common4_terminal_hash","match_common4_call_cap_guard",
                  "match-tv234-secondary","run_match_tv234_secondary",
                  "hotel_match_live234_frontier_plan_v1.php",
                  "hotel_match_live234_tv_secondary_refresh_v1.py",
                  "match_tv234_terminal_hash",
                  "match-tv942","match-samo942","supplier_slot_busy",
                  "hotel_match_live942_frontier_plan_v1.php",
                  "hotel_match_live942_tv_anex_refresh_v1.py",
                  "hotel_match_live942_samo_anex_refresh_v1.php",
                  "hotel_match_live30_common4_remainder_v1.py",
                  "hotel_match_common4_resume_salvage_v1.py",
                  "hotel_match_common4_mass_current_v14.php",
                  ".anytoour-match/operations","match_terminal_hash",
                  "match-secondary-audit","run_match_secondary_audit",
                  "hotel_match_live_anex_samo_missing_secondary_audit_v1.php",
                  "match_secondary_terminal_hash",
                  "match-coverage","run_match_coverage",
                  "hotel_match_tv_samo_anex_coverage_v1.php",
                  "hotel_match_current_coverage_wrapper_v1.php",
                  "match_coverage_terminal_hash",
                  "match-coverage-readback","read_match_coverage",
                  "match_coverage_readback_hash","128*1024*1024",
                  "match-readback","match942_child_name","read_match942",
                  "match-tv942-reconcile","run_match_tv942_reconcile",
                  "hotel_match_live942_tv_candidate_reconcile_v1.php",
                  "match-tv942-write","run_match_tv942_write",
                  "hotel_match_live942_tv_writer_v1.php",
                  "match_tv_writer_manifest_hash","match_tv_writer_terminal_guard",
                  "install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview','match-coverage','match-coverage-v2','match-coverage-v2-readback','match-samo-live30-persistence-readback','match-coverage-readback','match-tv234-readback','match-tv234-secondary','match-common4-acquire','match-common4-continuation-acquire','match-common4-continuation-resume-day','match-common4-resume-readback','match-common4-readback','match-common4-current-v2','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942",
                  "provider_attempted_without_terminal","pre_provider_reservation_only",
                  "match_readback_hash"]:
            self.assertIn(x,text)
        self.assertIn("payload['operator_family']=='intourist'",text)
        self.assertIn("target.get('sample_basis')=='distinct_mapped_hotel'",text)
        self.assertIn("target.get('spo_key') is None",text)
        self.assertIn("target.get('sample_distinct_spo_index') is None",text)
        self.assertIn("if command['mode'] == 'match-common4-continuation-remainder':",text)
        for x in ['shell=True',"booking(","bron_ticket","workflow_dispatch("]:
            self.assertNotIn(x,text)

    def test_andromeda_supplier_slot_and_incomplete_receipt_contract(self):
        text=SCRIPT.read_text()
        for mode in ('andromeda-scope','andromeda-external-group','andromeda-operator-scope'):
            self.assertIn(mode,text)
        self.assertIn("collector_incomplete_safe=collector_incomplete_base and (",text)
        self.assertIn("collector.get('incomplete_reason')=='search_partial'",text)
        self.assertIn("collector.get('autosave_reason')=='cohort_incomplete'",text)
        self.assertIn("collector_incomplete_first_page=(",text)
        self.assertIn("collector.get('incomplete_reason')=='supplier_unavailable_before_first_page'",text)
        self.assertIn("collector.get('pages')==0 and collector.get('advertised_pages') is None",text)
        self.assertIn("collector.get('autosave_reason')=='supplier_unavailable'",text)
        self.assertIn("result['after_db']==result['before_db']",text)
        self.assertIn("skipped_after_collector_incomplete",text)
        self.assertIn("result['status']='incomplete'",text)
        self.assertIn("result['database_writes']=0",text)
        self.assertIn("'anex-range','andromeda-scope','andromeda-external-group','andromeda-operator-scope'",text)

class PassiveFuelReadinessTest(unittest.TestCase):
    def setUp(self):
        self.p = m.passive_fuel_readiness
        self.command = m.parse_command(f'{m.PREFIX}{self.p.SOURCE} {self.p.MODE} {self.p.OPERATION}')

    def test_scope_is_fixed_and_other_modes_unchanged(self):
        for parts in ([SHA,self.p.MODE,self.p.OPERATION],
                      [self.p.SOURCE,self.p.MODE,self.p.OPERATION.replace('-v1','-v2')],
                      [self.p.SOURCE,self.p.MODE,self.p.OPERATION,'extra']):
            with self.subTest(parts=parts),self.assertRaises(ValueError):m.parse_command(m.PREFIX+' '.join(parts))
        self.assertEqual('install-runtime',m.parse_command(f'{m.PREFIX}{SHA} install-runtime int-anex-unchanged-install-v1')['mode'])

    def reader(self, directory):
        import re
        import pathlib
        root = Path(directory)
        home = root/'account'; project = home/'www/anytoour.ru'
        project.mkdir(parents=True)
        def fail(reason):raise RuntimeError(reason)
        namespace = dict(home=home,project=project,os=os,pathlib=pathlib,re=re,
                         hashlib=hashlib,fail=fail)
        exec(self.p.REMOTE_READER, namespace)
        api = b"<?php require_once dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php';"
        (project/'api-v2.php').write_bytes(api)
        namespace['PF_PREDECESSOR'] = hashlib.sha256(api).hexdigest()
        db = b'<?php '+b' '.join(b'function '+name+b'() {}' for name in
            (b'v2_data_db_config',b'v2_data_db',b'v2_data_normalize_text',b'v2_data_slug'))
        (project/'data').mkdir(); (project/'data/db-v1.php').write_bytes(db)
        namespace['PF_DB'] = hashlib.sha256(db).hexdigest()
        store = project/'.private-fuel/searches';store.mkdir(parents=True)
        config = "<?php return ['token'=>'SECRET_MUST_STAY_PRIVATE','catalog_path'=>'"+str(store.parent/'catalog.json')+"'];"
        (project/'.andromeda-private.php').write_text(config)
        expected = dict(self.p.SOURCE_HASHES)
        for relative in expected:
            if not relative.startswith('app/'):continue
            path=project/relative;path.parent.mkdir(parents=True,exist_ok=True)
            data=('<?php /* '+relative+' */').encode();path.write_bytes(data)
            expected[relative]=hashlib.sha256(data).hexdigest()
        namespace['PF_EXPECTED']=expected
        return namespace,home,project

    def test_actual_reader_ready_and_no_secret_or_path_output(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            before={str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()}
            value=ns['passive_fuel_readiness']()
            self.assertEqual('ready',value['status'])
            self.assertTrue(value['helpers_exact'])
            self.assertEqual('root',value['selected_layout'])
            self.assertEqual(4,value['db_helper_declarations'])
            self.assertNotIn(str(home),json.dumps(value));self.assertNotIn('SECRET_MUST_STAY_PRIVATE',json.dumps(value))
            self.assertEqual(before,{str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()})
            self.assertEqual([],list((project/'.private-fuel/searches').iterdir()))

    def test_missing_helper_does_not_become_ready_or_create_file(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            path=project/'app/integrations/operator-fuel-rule-store.php';path.unlink()
            value=ns['passive_fuel_readiness']()
            self.assertEqual('not_ready',value['status']);self.assertFalse(value['helpers_exact'])
            self.assertIsNone(value['helper_layouts']['root']['app/integrations/operator-fuel-rule-store.php'])
            self.assertFalse(path.exists())

    def test_existing_parent_layout_is_gated_by_actual_gateway_binding(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            (project/'app').rename(project.parent/'app')
            self.assertEqual('parent',ns['passive_fuel_readiness']()['selected_layout'])
            (project/'api-v2.php').write_bytes(b'<?php // No parent dependency')
            value=ns['passive_fuel_readiness']()
            self.assertIsNone(value['selected_layout'])

    def test_symlink_ancestor_leaf_and_hardlink_fail_closed(self):
        for case in ('ancestor','leaf','hardlink'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project=self.reader(directory)
                path=project/'app/integrations/operator-fuel-rule-store.php'
                if case=='ancestor':
                    (project/'app').rename(project/'moved-app');(project/'app').symlink_to(project/'moved-app',target_is_directory=True)
                elif case=='leaf':
                    path.rename(project/'saved-helper');path.symlink_to(project/'saved-helper')
                else:os.link(path,project/'saved-helper')
                with self.assertRaises((RuntimeError,OSError)):ns['passive_fuel_readiness']()

    def test_config_is_never_executed_or_guessed(self):
        values=[None,b"<?php return ['catalog_path'=>getenv('SOME_SECRET')];",
                b"<?php return ['catalog_path'=>'/etc/catalog.json'];",
                b"<?php return ['catalog_path'=>'/irrelevant']; file_put_contents('/tmp/NEVER','bad');"]
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            for body in values:
                with self.subTest(body=body):
                    path=project/'.andromeda-private.php'
                    if body is None:path.unlink(missing_ok=True)
                    else:path.write_bytes(body)
                    value=ns['passive_fuel_readiness']()
                    self.assertEqual('not_ready',value['status']);self.assertFalse(value['store_directory_verified'])
            self.assertEqual([],list((project/'.private-fuel/searches').iterdir()))

    def test_change_between_snapshots_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            original=ns['pf_snapshot'];count=0
            def changed():
                nonlocal count
                count+=1
                if count==2:(project/'index.php').write_bytes(b'changed')
                return original()
            ns['pf_snapshot']=changed
            with self.assertRaisesRegex(RuntimeError,'passive_inventory_changed'):ns['passive_fuel_readiness']()

    def test_only_top_level_literal_catalog_key_is_authority(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            catalog=str(project/'.private-fuel/catalog.json')
            for body in ["<?php return ['other'=>['catalog_path'=>'"+catalog+"']];",
                         '<?php return ["other"=>"\'catalog_path\'=>\''+catalog+'\'"];',
                         "<?php return ['catalog_path'=>'"+catalog+"','catalog_path'=>'"+catalog+"'];",
                         '<?php return ["catalog_path"=>"$HOME/private/catalog.json"];',
                         "<?php return ['catalog_path'=>'"+catalog+"','unused'=>08];",
                         "<?php return ['catalog_path'=>'"+catalog+"','unused'=>09];"]:
                with self.subTest(body=body):
                    (project/'.andromeda-private.php').write_text(body)
                    self.assertEqual('not_ready',ns['passive_fuel_readiness']()['status'])

    def test_store_directory_identity_is_bound_across_snapshots(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            original=ns['pf_snapshot'];count=0
            def changed():
                nonlocal count
                count+=1
                if count==2:
                    path=project/'.private-fuel/searches'
                    path.rename(path.with_name('old-searches'));path.mkdir()
                return original()
            ns['pf_snapshot']=changed
            with self.assertRaisesRegex(RuntimeError,'passive_store_changed'):ns['passive_fuel_readiness']()

    def test_remote_keeps_other_modes_unchanged_and_excludes_db_collectors(self):
        import ast
        value=self.p.remote_program(m.REMOTE);ast.parse(value)
        self.assertEqual(2,value.count("if mode not in ('passive-fuel-readiness','reconcile',"))
        self.assertIn("files!="+repr(self.p.SOURCE_HASHES),value)
        self.assertNotIn('subprocess',self.p.REMOTE_READER)
        self.assertNotIn('glob(',self.p.REMOTE_READER)

    def test_completion_is_inventory_only_and_rejects_nonzero_actions(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project=self.reader(directory)
            value=ns['passive_fuel_readiness']()
            result={**self.command,'status':'complete','production_unchanged':True,'runtime_changed':False,
                    'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                    'passive_fuel_readiness':value}
            self.assertTrue(m.completed_result(self.command,result))
            value['status']='not_ready';self.assertTrue(m.completed_result(self.command,result))
            for key in ('supplier_calls','database_reads','database_writes','booking_calls','lead_calls'):
                bad=copy.deepcopy(result);bad[key]=1;self.assertFalse(m.completed_result(self.command,bad))
            bad=copy.deepcopy(result);bad['passive_fuel_readiness']['runtime_writes']=1
            self.assertFalse(m.completed_result(self.command,bad))
            bad=copy.deepcopy(result);bad['passive_fuel_readiness']['protected_after']={}
            self.assertFalse(m.completed_result(self.command,bad))

    def test_source_ci_and_one_created_comment_are_required(self):
        env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':m.REPO,'GITHUB_REF':'refs/heads/main',
             'GITHUB_WORKFLOW_REF':m.REPO+'/.github/workflows/int-server-executor.yml@refs/heads/main',
             'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_RUN_ATTEMPT':'1'}
        body=m.PREFIX+' '.join([self.p.SOURCE,self.p.MODE,self.p.OPERATION])
        event={'action':'created','repository':{'full_name':m.REPO},'comment':{'id':123}}
        checks={'total_count':5,'check_runs':[{'id':i,'status':'completed','conclusion':'success',
                'app':{'slug':'github-actions'}} for i in self.p.CHECK_IDS]}
        def api(path,token):return checks if '/check-runs?' in path else [{'id':123,'body':body}]
        self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX)
        for key,value in [('GITHUB_EVENT_NAME','workflow_dispatch'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_ACTOR','other')]:
            with self.subTest(key=key),self.assertRaises(ValueError):
                self.p.admission(api,event,'token',{**env,key:value},m.REPO,m.ISSUE,m.PREFIX)
        with self.assertRaisesRegex(ValueError,'no_replay'):
            self.p.admission(lambda path,token:checks if '/check-runs?' in path else
                [{'id':122,'body':body},{'id':123,'body':body}],event,'token',env,m.REPO,m.ISSUE,m.PREFIX)
        checks['check_runs'][0]['conclusion']='failure'
        with self.assertRaisesRegex(ValueError,'source_ci'):self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX)


class PrivatePassiveFuelReadinessTest(unittest.TestCase):
    def setUp(self):
        self.p=m.passive_fuel_readiness
        self.command=self.p.parse([self.p.PRIVATE_SOURCE,self.p.PRIVATE_MODE,self.p.PRIVATE_OPERATION])

    def reader(self,directory):
        fixture=PassiveFuelReadinessTest();fixture.setUp()
        ns,home,project=fixture.reader(directory)
        expected=ns['PF_EXPECTED'];predecessor=ns['PF_PREDECESSOR'];db=ns['PF_DB']
        runtime=project/'_preview/search3-anex-candidate';runtime.mkdir(parents=True)
        (project/'app').rename(runtime/'app')
        (project/'.andromeda-private.php').rename(runtime/'.andromeda-private.php')
        exec(self.p.PRIVATE_REMOTE_READER,ns)
        ns.update(PF_EXPECTED=expected,PF_PREDECESSOR=predecessor,PF_DB=db)
        return ns,home,project,runtime

    def test_fixed_private_scope_does_not_reopen_root_operation(self):
        self.assertEqual(self.command,m.parse_command(m.PREFIX+' '.join(self.command.values())))
        for parts in ([self.p.SOURCE,self.p.PRIVATE_MODE,self.p.PRIVATE_OPERATION],
                      [self.p.PRIVATE_SOURCE,self.p.MODE,self.p.OPERATION],
                      [self.p.PRIVATE_SOURCE,self.p.PRIVATE_MODE,self.p.OPERATION],
                      [self.p.PRIVATE_SOURCE,self.p.PRIVATE_MODE,self.p.PRIVATE_OPERATION,'extra']):
            with self.subTest(parts=parts),self.assertRaises(ValueError):self.p.parse(parts)
        self.assertEqual(self.p.SOURCE,self.p.parse([self.p.SOURCE,self.p.MODE,self.p.OPERATION])['source_sha'])

    def test_private_metadata_ready_without_secret_path_execution_or_writes(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime=self.reader(directory)
            # These old ROOT-layout paths must not be re-read or treated as authority.
            (project/'app').symlink_to('/no-such-root-helper')
            (project/'.andromeda-private.php').symlink_to('/no-such-root-config')
            before={str(p):p.read_bytes() for p in runtime.rglob('*') if p.is_file()}
            result=ns['passive_fuel_readiness']()
            self.assertEqual('ready',result['status'])
            self.assertEqual({'private_int'},set(result['helper_layouts']))
            self.assertEqual('private_int',result['selected_layout'])
            self.assertTrue(result['store_directory_owner_matches'])
            self.assertEqual(result['private_config_sha256'],result['private_config_record']['sha256'])
            self.assertNotIn(str(home),json.dumps(result));self.assertNotIn('SECRET_MUST_STAY_PRIVATE',json.dumps(result))
            self.assertEqual(before,{str(p):p.read_bytes() for p in runtime.rglob('*') if p.is_file()})
            self.assertEqual([],list((project/'.private-fuel/searches').iterdir()))

    def test_missing_private_files_fail_closed_without_fallback_or_creation(self):
        for relative in ('.andromeda-private.php','app/integrations/operator-fuel-rule-store.php'):
            with self.subTest(relative=relative),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime=self.reader(directory)
                path=runtime/relative;path.unlink()
                value=ns['passive_fuel_readiness']()
                self.assertEqual('not_ready',value['status']);self.assertFalse(path.exists())

    def test_private_symlink_ancestors_leaf_config_and_hardlink_are_rejected(self):
        for case in ('preview','runtime','app','leaf','config','hardlink'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime=self.reader(directory)
                relative={'preview':'_preview','runtime':'_preview/search3-anex-candidate',
                    'app':'_preview/search3-anex-candidate/app',
                    'config':'_preview/search3-anex-candidate/.andromeda-private.php'}.get(case,
                    '_preview/search3-anex-candidate/app/integrations/operator-fuel-rule-store.php')
                path=project/relative;saved=project/'saved-entry'
                if case=='hardlink':os.link(path,saved)
                else:path.rename(saved);path.symlink_to(saved,target_is_directory=saved.is_dir())
                with self.assertRaises((RuntimeError,OSError)):ns['passive_fuel_readiness']()

    def test_store_owner_mode_and_identity_are_verified_without_listing(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime=self.reader(directory)
            store=project/'.private-fuel/searches'
            for mode in (0o777,0o500,0o200):
                store.chmod(mode)
                self.assertEqual('not_ready',ns['passive_fuel_readiness']()['status'])
            store.chmod(0o700)
            with patch.object(os,'getuid',return_value=-1):
                result=ns['passive_fuel_readiness']()
                self.assertFalse(result['store_directory_owner_matches']);self.assertEqual('not_ready',result['status'])
            original=ns['pf_snapshot'];count=0
            def changed():
                nonlocal count
                count+=1
                if count==2:store.rename(store.with_name('old-searches'));store.mkdir()
                return original()
            ns['pf_snapshot']=changed
            with self.assertRaisesRegex(RuntimeError,'passive_store_changed'):ns['passive_fuel_readiness']()

    def test_private_config_stays_literal_only_and_fail_closed(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime=self.reader(directory)
            catalog=str(project/'.private-fuel/catalog.json')
            for body in ("<?php return ['catalog_path'=>getenv('TOKEN')];",
                         "<?php return ['nested'=>['catalog_path'=>'"+catalog+"']];",
                         "<?php return ['catalog_path'=>'/etc/catalog.json'];",
                         "<?php return ['catalog_path'=>'"+catalog+"','catalog_path'=>'"+catalog+"'];"):
                with self.subTest(body=body):
                    (runtime/'.andromeda-private.php').write_text(body)
                    self.assertEqual('not_ready',ns['passive_fuel_readiness']()['status'])

    def test_private_remote_has_exact_manifest_scope_and_no_db_collectors(self):
        import ast
        value=self.p.remote_program(m.REMOTE,self.command);ast.parse(value)
        self.assertEqual(2,value.count("if mode not in ('passive-fuel-private-readiness','reconcile',"))
        self.assertIn('files!='+repr(self.p.PRIVATE_SOURCE_HASHES),value)
        self.assertIn("runtime=project/'_preview/search3-anex-candidate'",value)
        self.assertNotIn("('root',project)",self.p.PRIVATE_REMOTE_READER)
        for forbidden in ('subprocess','glob(','iterdir(','.read_text(','.read_bytes('):
            self.assertNotIn(forbidden,self.p.PRIVATE_REMOTE_READER)

    def test_private_completion_rejects_shape_drift_and_nonzero_side_effects(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime=self.reader(directory)
            value=ns['passive_fuel_readiness']()
            result={**self.command,'status':'complete','production_unchanged':True,'runtime_changed':False,
                'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                'passive_fuel_readiness':value}
            self.assertTrue(m.completed_result(self.command,result))
            for key,bad in [('runtime_writes',1),('private_config_record',{'raw':'SECRET'}),
                            ('store_directory_owner_matches','yes'),('store_directory_mode','0700')]:
                changed=copy.deepcopy(result);changed['passive_fuel_readiness'][key]=bad
                self.assertFalse(m.completed_result(self.command,changed))
            value['status']='not_ready';self.assertTrue(m.completed_result(self.command,result))

    def test_private_admission_requires_own_source_ci_and_unique_private_command(self):
        env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':m.REPO,'GITHUB_REF':'refs/heads/main',
            'GITHUB_WORKFLOW_REF':m.REPO+'/.github/workflows/int-server-executor.yml@refs/heads/main',
            'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_RUN_ATTEMPT':'1'}
        body=m.PREFIX+' '.join(self.command.values())
        old=m.PREFIX+' '.join([self.p.SOURCE,self.p.MODE,self.p.OPERATION])
        event={'action':'created','repository':{'full_name':m.REPO},'comment':{'id':123}}
        checks={'total_count':5,'check_runs':[{'id':i,'status':'completed','conclusion':'success',
            'app':{'slug':'github-actions'}} for i in self.p.PRIVATE_CHECK_IDS]}
        def api(path,token):
            if '/check-runs?' in path:
                self.assertIn(self.p.PRIVATE_SOURCE_CHECK_COMMIT,path);return checks
            return [{'id':122,'body':old},{'id':123,'body':body}]
        self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)
        with self.assertRaisesRegex(ValueError,'no_replay'):
            self.p.admission(lambda path,token:checks if '/check-runs?' in path else
                [{'id':122,'body':body},{'id':123,'body':body}],event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)
        checks['check_runs'][0]['conclusion']='failure'
        with self.assertRaisesRegex(ValueError,'source_ci'):
            self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)


class PassiveFuelActivationPlanTest(unittest.TestCase):
    def setUp(self):
        self.p=m.passive_fuel_readiness
        self.command=self.p.parse([self.p.PLAN_SOURCE,self.p.PLAN_MODE,self.p.PLAN_OPERATION])

    def reader(self,directory):
        fixture=PrivatePassiveFuelReadinessTest();fixture.setUp()
        ns,home,project,runtime=fixture.reader(directory)
        db=ns['PF_DB'];api=ns['PF_PREDECESSOR'];expected=ns['PF_EXPECTED']
        exec(self.p.PLAN_REMOTE_READER,ns)
        ns['PP_DB']=db;ns['PF_EXPECTED']=expected
        ns['PP_TARGETS']['api-v2.php']=(api,ns['PP_TARGETS']['api-v2.php'][1])
        passive='app/integrations/tourvisor-operator-fuel-passive-intake.php'
        ns['PP_OLD_PASSIVE']=expected[passive]
        ns['PP_TARGETS']['_preview/search3-anex-candidate/'+passive]=(expected[passive],self.p.PRIVATE_SOURCE_HASHES[passive])
        (project/'config.php').write_bytes(b'<?php /* isolated config, never executed */')
        ns['PP_CONFIG']=hashlib.sha256((project/'config.php').read_bytes()).hexdigest()
        (runtime/'data').mkdir();(runtime/'data/db-v1.php').write_bytes((project/'data/db-v1.php').read_bytes())
        proxy=b"<?php\nreturn require dirname(__DIR__,4).'/.anytoour-andromeda/search3-preview.php';\n"
        (runtime/'.andromeda-private.php').write_bytes(proxy);(runtime/'.andromeda-private.php').chmod(0o600)
        private=home/'.anytoour-andromeda';private.mkdir(mode=0o700)
        (private/'searches').mkdir(mode=0o700)
        body="<?php return array ('enabled' => true, 'catalog_path' => '"+str(private/'catalog.json')+"', 'token'=>'SECRET_NEVER_EXPORT');"
        (private/'search3-preview.php').write_text(body);(private/'search3-preview.php').chmod(0o600)
        caller=b'<?php /* isolated compatible caller fixture */'
        (runtime/'api-v2.php').write_bytes(caller);ns['PP_CALLER']=hashlib.sha256(caller).hexdigest()
        return ns,home,project,runtime,private

    def test_only_fixed_new_plan_scope_and_old_policies_immutable(self):
        self.assertEqual(self.command,m.parse_command(m.PREFIX+' '.join(self.command.values())))
        for parts in ([self.p.SOURCE,self.p.PLAN_MODE,self.p.PLAN_OPERATION],
                      [self.p.PLAN_SOURCE,self.p.PLAN_MODE,self.p.PRIVATE_OPERATION],
                      [self.p.PLAN_SOURCE,self.p.PLAN_MODE,self.p.PLAN_OPERATION,'install']):
            with self.subTest(parts=parts),self.assertRaises(ValueError):self.p.parse(parts)
        self.assertEqual('670245c83b8fd47429a51a5d079e5106125476f9',self.p.SOURCE)
        self.assertEqual('266848221cc78b1299c675171d7fe53fbb7e0cce',self.p.PRIVATE_SOURCE)

    def test_plan_is_read_only_exact_scope_and_never_exports_private_material(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory)
            paths=[];original=ns['pf_read']
            def observe(path,*args,**kwargs):paths.append(path);return original(path,*args,**kwargs)
            ns['pf_read']=observe
            before={str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()}
            result=ns['passive_fuel_activation_plan']()
            self.assertEqual('ready',result['status']);self.assertEqual('verified_stock_binding',result['store_state'])
            self.assertEqual('compatible_pr4732',result['private_caller_state'])
            self.assertEqual(2,len(result['plan_files']))
            self.assertEqual({private/'search3-preview.php'},{p for p in paths if private in p.parents})
            self.assertNotIn('SECRET_NEVER_EXPORT',json.dumps(result));self.assertNotIn(str(home),json.dumps(result))
            self.assertEqual(before,{str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()})
            self.assertEqual([],list((private/'searches').iterdir()))

    def test_legacy_missing_or_unknown_private_caller_holds_without_extra_write(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory)
            path=runtime/'api-v2.php';data=path.read_bytes()
            ns['PP_LEGACY_CALLER']=hashlib.sha256(data).hexdigest();ns['PP_CALLER']='0'*64
            self.assertEqual('legacy_eager_dependency',ns['passive_fuel_activation_plan']()['private_caller_state'])
            path.write_bytes(b'<?php /* unreviewed */')
            self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])
            path.unlink();result=ns['passive_fuel_activation_plan']()
            self.assertEqual('missing',result['private_caller_state']);self.assertEqual('hold',result['status'])
            self.assertFalse(path.exists());self.assertEqual(2,len(result['plan_files']))

    def test_only_unambiguous_literal_enabled_catalog_binding_is_authority(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory)
            parser=ns['pp_literal_config'];catalog=str(private/'catalog.json')
            for wrapper in ("['enabled'=>true,'catalog_path'=>'PATH']","array('enabled'=>true,'catalog_path'=>'PATH',)"):
                self.assertEqual(catalog,parser(('<?php return '+wrapper.replace('PATH',catalog)+';').encode()))
            for raw in ("['enabled'=>true,'nested'=>['catalog_path'=>'PATH']]",
                        "['enabled'=>true,'catalog_path'=>'PATH','catalog_path'=>'PATH']",
                        "['enabled'=>true,'enabled'=>false,'catalog_path'=>'PATH']",
                        "['enabled'=>1,'catalog_path'=>'PATH']","['enabled'=>false,'catalog_path'=>'PATH']",
                        "['enabled'=>true,'catalog_path'=>getenv('SECRET')]",
                        "['enabled'=>true,'catalog_path'=>'PATH','bad'=>08]",
                        "['enabled'=>true,'catalog_path'=>'PATH']; file_put_contents('/tmp/NEVER','bad')",
                        "require '/arbitrary.php'"):
                with self.subTest(raw=raw):self.assertIsNone(parser(('<?php return '+raw.replace('PATH',catalog)+';').encode()))
            (private/'search3-preview.php').write_text("<?php return ['enabled'=>true,'catalog_path'=>'/etc/catalog.json'];")
            self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])

    def test_proxy_target_config_symlink_hardlink_and_ancestor_are_rejected(self):
        for case in ('ancestor','leaf','hardlink','store'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private=self.reader(directory)
                path=private if case=='ancestor' else private/'searches' if case=='store' else private/'search3-preview.php'
                saved=home/'saved-private'
                if case=='hardlink':os.link(path,saved)
                else:path.rename(saved);path.symlink_to(saved,target_is_directory=saved.is_dir())
                with self.assertRaises((RuntimeError,OSError)):ns['passive_fuel_activation_plan']()

    def test_missing_store_or_bad_mode_holds_without_creation(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory);store=private/'searches'
            for mode in (0o777,0o500,0o200):
                store.chmod(mode);self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])
            store.chmod(0o700)
            with patch.object(os,'getuid',return_value=-1):self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])
            store.rmdir();self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status']);self.assertFalse(store.exists())

    def test_private_caller_owned_db_dependency_must_be_proven_without_loading_it(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory);path=runtime/'data/db-v1.php'
            path.unlink();result=ns['passive_fuel_activation_plan']()
            self.assertEqual('compatible_pr4732',result['private_caller_state'])
            self.assertFalse(result['private_db_dependency_matches']);self.assertEqual('hold',result['status'])
            self.assertFalse(path.exists());self.assertEqual('not_executed',result['loaded_db_state'])
            path.write_bytes(b'<?php /* unreviewed DB helper */')
            self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])

    def test_config_store_or_private_caller_drift_is_terminal(self):
        for case in ('config','store','caller'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private=self.reader(directory);original=ns['pp_snapshot'];count=0
                def changed():
                    nonlocal count
                    count+=1
                    if count==2:
                        if case=='config':(private/'search3-preview.php').write_bytes(b'<?php return [];')
                        elif case=='caller':(runtime/'api-v2.php').write_bytes(b'changed')
                        else:(private/'searches').rename(private/'old-searches');(private/'searches').mkdir()
                    return original()
                ns['pp_snapshot']=changed
                with self.assertRaisesRegex(RuntimeError,'passive_plan_drift'):ns['passive_fuel_activation_plan']()

    def test_remote_registration_has_no_installer_php_or_db_fallthrough(self):
        import ast
        value=self.p.remote_program(m.REMOTE,self.command);ast.parse(value)
        self.assertEqual(2,value.count("if mode not in ('passive-fuel-activation-plan','reconcile',"))
        self.assertIn("result['passive_fuel_activation_plan']=passive_fuel_activation_plan()",value)
        self.assertIn('files!='+repr(self.p.PRIVATE_SOURCE_HASHES),value)
        for forbidden in ('subprocess','glob(','iterdir(','mkdir(','.write_','eval(','exec('):
            self.assertNotIn(forbidden,self.p.PLAN_REMOTE_READER)

    def test_completion_is_plan_only_strict_closed_shape_and_zero_side_effects(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private=self.reader(directory);value=ns['passive_fuel_activation_plan']()
            value['plan_files']['api-v2.php']['predecessor_sha256']='01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61'
            value['plan_files']['_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php']['predecessor_sha256']='be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22'
            result={**self.command,'status':'complete','runtime_changed':False,'production_unchanged':True,
                'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                'passive_fuel_activation_plan':value}
            self.assertTrue(m.completed_result(self.command,result))
            for key,bad in [('runtime_writes',1),('backing_config_record',{'raw':'SECRET'}),
                            ('private_caller_state','unrecognized'),('store_directory_owner_matches',False)]:
                changed=copy.deepcopy(result);changed['passive_fuel_activation_plan'][key]=bad
                self.assertFalse(m.completed_result(self.command,changed))
            value['status']='hold';value['private_caller_state']='unrecognized'
            self.assertTrue(m.completed_result(self.command,result))
            value['plan_files']['third.php']={};self.assertFalse(m.completed_result(self.command,result))

    def test_plan_admission_requires_own_unique_created_command_and_exact_candidate_ci(self):
        env={'GITHUB_EVENT_NAME':'issue_comment','GITHUB_REPOSITORY':m.REPO,'GITHUB_REF':'refs/heads/main',
            'GITHUB_WORKFLOW_REF':m.REPO+'/.github/workflows/int-server-executor.yml@refs/heads/main',
            'GITHUB_ACTOR':'pyatkoff','GITHUB_TRIGGERING_ACTOR':'pyatkoff','GITHUB_RUN_ATTEMPT':'1'}
        event={'action':'created','repository':{'full_name':m.REPO},'comment':{'id':123}}
        body=m.PREFIX+' '.join(self.command.values())
        checks={'total_count':5,'check_runs':[{'id':i,'status':'completed','conclusion':'success',
            'app':{'slug':'github-actions'}} for i in self.p.PRIVATE_CHECK_IDS]}
        old=[{'id':121,'body':m.PREFIX+' '.join([self.p.SOURCE,self.p.MODE,self.p.OPERATION])},
             {'id':122,'body':m.PREFIX+' '.join([self.p.PRIVATE_SOURCE,self.p.PRIVATE_MODE,self.p.PRIVATE_OPERATION])}]
        def api(path,token):
            if '/check-runs?' in path:
                self.assertIn(self.p.PRIVATE_SOURCE_CHECK_COMMIT,path);return checks
            return old+[{'id':123,'body':body}]
        self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)
        for event_change in ({**event,'action':'edited'},{**event,'repository':{'full_name':'other/repo'}}):
            with self.assertRaises(ValueError):self.p.admission(api,event_change,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)
        with self.assertRaisesRegex(ValueError,'no_replay'):
            self.p.admission(lambda path,token:checks if '/check-runs?' in path else
                old+[{'id':120,'body':body},{'id':123,'body':body}],event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)
        checks['check_runs'][0]['conclusion']='failure'
        with self.assertRaisesRegex(ValueError,'source_ci'):
            self.p.admission(api,event,'token',env,m.REPO,m.ISSUE,m.PREFIX,self.command)


class PassiveFuelActivationTransactionTest(unittest.TestCase):
    def setUp(self):
        self.p=m.passive_fuel_readiness
        self.command=self.p.parse([self.p.INSTALL_SOURCE,self.p.INSTALL_MODE,self.p.INSTALL_OPERATION])

    def fixture(self,directory):
        fixture=PassiveFuelActivationPlanTest();fixture.setUp()
        ns,home,project,runtime,private=fixture.reader(directory)
        constants={k:copy.deepcopy(v) for k,v in ns.items() if k.startswith(('PF_','PP_'))}
        exec(self.p.INSTALL_REMOTE,ns);ns.update(constants)
        ns['json']=json  # Same json global imported by the stock REMOTE.
        ns['operation']=self.p.INSTALL_OPERATION
        ns['fingerprints']=lambda:{p:hashlib.sha256((project/p).read_bytes()).hexdigest()
            if (project/p).is_file() else None for p in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')}
        ns['pi_lint']=lambda path:None
        op=home/'activation-test';op.mkdir(mode=0o700)
        stage=op/'source';(stage/'v2').mkdir(parents=True);(stage/'app/integrations').mkdir(parents=True)
        for rel,source in [('api-v2.php','v2/api-v2.php'),
                ('_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php',
                 'app/integrations/tourvisor-operator-fuel-passive-intake.php')]:
            data=b'<?php /* candidate '+source.encode()+b' */'
            (stage/source).write_bytes(data)
            ns['PP_TARGETS'][rel]=(ns['PP_TARGETS'][rel][0],hashlib.sha256(data).hexdigest())
        (private/'grouped-search-update.lock').touch(mode=0o600)
        return ns,home,project,runtime,private,stage,op

    def run_install(self,ns,stage,op):return ns['passive_fuel_activate'](stage,op,ns['fingerprints']())

    def test_install_is_exactly_two_files_dependency_first_with_durable_backups(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            before=ns['pp_snapshot']();calls=[];replace=os.replace
            def observed(src,dst,*args,**kwargs):
                calls.append(dst)
                if dst=='api-v2.php':
                    self.assertEqual((stage/'app/integrations/tourvisor-operator-fuel-passive-intake.php').read_bytes(),
                        (runtime/'app/integrations/tourvisor-operator-fuel-passive-intake.php').read_bytes())
                return replace(src,dst,*args,**kwargs)
            with patch.object(os,'replace',side_effect=observed):value=self.run_install(ns,stage,op)
            self.assertEqual('installed_verified',value['status']);self.assertEqual(2,value['runtime_writes'])
            self.assertEqual(['tourvisor-operator-fuel-passive-intake.php','api-v2.php'],calls)
            after=ns['pp_snapshot']()
            self.assertEqual({p for p in before[1] if before[1][p]!=after[1][p]},
                {'api-v2.php','helper:app/integrations/tourvisor-operator-fuel-passive-intake.php'})
            self.assertEqual(before[0]['api-v2.php'],(op/'activation-backup-1.php').read_bytes())
            self.assertTrue((op/'activation-verified.json').is_file());self.assertFalse(list(project.rglob('*.tmp')))
            self.assertNotIn('SECRET_NEVER_EXPORT',json.dumps(value));self.assertNotIn(str(home),json.dumps(value))
            self.assertEqual([],list((private/'searches').iterdir()))

    def test_missing_unknown_or_legacy_caller_is_hold_without_any_staging_or_write(self):
        for case in ('missing','unknown','legacy','db','store'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                if case=='missing':(runtime/'api-v2.php').unlink()
                elif case=='unknown':(runtime/'api-v2.php').write_bytes(b'unknown')
                elif case=='legacy':ns['PP_LEGACY_CALLER']=ns['PP_CALLER'];ns['PP_CALLER']='0'*64
                elif case=='db':(runtime/'data/db-v1.php').unlink()
                else:(private/'searches').rmdir()
                before={str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()}
                value=self.run_install(ns,stage,op)
                self.assertEqual(('hold',0,0),(value['status'],value['runtime_writes'],value['staging_file_writes']))
                self.assertEqual(before,{str(p):p.read_bytes() for p in home.rglob('*') if p.is_file()})

    def test_stock_lock_missing_or_busy_never_created_or_bypassed(self):
        import fcntl
        for case in ('missing','busy'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                path=private/'grouped-search-update.lock';fd=None
                if case=='missing':path.unlink()
                else:fd=os.open(path,os.O_RDWR);fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
                try:value=self.run_install(ns,stage,op)
                finally:
                    if fd is not None:os.close(fd)
                self.assertEqual('hold',value['status']);self.assertEqual(0,value['runtime_writes'])
                self.assertEqual(0,value['staging_file_writes']);self.assertFalse(value['backup_retained'])
                self.assertEqual(case!='missing',path.exists())

    def test_lock_symlink_hardlink_and_writable_shape_rejected(self):
        for case in ('symlink','hardlink','mode'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                path=private/'grouped-search-update.lock'
                if case=='mode':path.chmod(0o666)
                elif case=='hardlink':os.link(path,private/'other-lock')
                else:path.rename(private/'other-lock');path.symlink_to(private/'other-lock')
                value=self.run_install(ns,stage,op)
                self.assertEqual('blocked_no_replay',value['status']);self.assertEqual(0,value['runtime_writes'])

    def test_failure_after_either_replace_rolls_back_in_reverse_order(self):
        for fail_at in (5,6):
            with self.subTest(fail_at=fail_at),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                before=ns['pp_snapshot']()[1];calls=0
                def lint(path):
                    nonlocal calls
                    calls+=1
                    if calls==fail_at:raise RuntimeError('activation_syntax')
                ns['pi_lint']=lint;value=self.run_install(ns,stage,op)
                self.assertEqual('rolled_back_no_replay',value['status'])
                self.assertEqual(list(reversed(value['changed_files'])),value['rollback_files'])
                self.assertEqual(before,ns['pp_snapshot']()[1]);self.assertFalse(list(project.rglob('*.tmp')))
                self.assertEqual(2*(fail_at-4),value['runtime_writes'])

    def test_failure_immediately_after_atomic_replace_can_restore_our_inode(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            before=ns['pp_snapshot']()[1];fsync=os.fsync;failed=False
            def fail_once(fd):
                nonlocal failed
                path=runtime/'app/integrations/tourvisor-operator-fuel-passive-intake.php'
                if not failed and hashlib.sha256(path.read_bytes()).hexdigest()==ns['PP_TARGETS'][str(path.relative_to(project))][1]:
                    failed=True;raise OSError('isolated fsync failure')
                return fsync(fd)
            with patch.object(os,'fsync',side_effect=fail_once):value=self.run_install(ns,stage,op)
            self.assertTrue(failed);self.assertEqual('rolled_back_no_replay',value['status'])
            self.assertEqual(before,ns['pp_snapshot']()[1])

    def test_rollback_does_not_overwrite_a_subsequent_writer(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory);calls=0
            path=runtime/'app/integrations/tourvisor-operator-fuel-passive-intake.php'
            def lint(target):
                nonlocal calls
                calls+=1
                if calls==5:
                    replacement=path.with_name('foreign.php');replacement.write_bytes(b'foreign-writer')
                    os.replace(replacement,path);raise RuntimeError('activation_syntax')
            ns['pi_lint']=lint;value=self.run_install(ns,stage,op)
            self.assertEqual('rollback_failed_no_replay',value['status']);self.assertEqual(b'foreign-writer',path.read_bytes())
            self.assertEqual([],value['rollback_files'])

    def test_config_or_protected_drift_before_apply_prevents_install(self):
        for case in ('config','protected','store','target_parent'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory);calls=0
                def lint(path):
                    nonlocal calls
                    calls+=1
                    if calls==4:
                        if case=='config':(private/'search3-preview.php').write_bytes(b'changed')
                        elif case=='protected':(project/'index.php').write_bytes(b'changed')
                        elif case=='store':(private/'searches').rename(private/'old-searches');(private/'searches').mkdir()
                        else:
                            parent=runtime/'app/integrations';parent.rename(runtime/'app/old-integrations')
                            parent.symlink_to(runtime/'app/old-integrations',target_is_directory=True)
                ns['pi_lint']=lint;value=self.run_install(ns,stage,op)
                self.assertEqual('blocked_no_replay',value['status']);self.assertEqual(0,value['runtime_writes'])
                self.assertEqual([],value['changed_files'])

    def test_registration_and_no_replay_admission_keep_old_operation_pins(self):
        value=self.p.remote_program(m.REMOTE,self.command)
        self.assertEqual(2,value.count("if mode not in ('passive-fuel-activate','reconcile',"))
        self.assertIn("result['passive_fuel_activation']=passive_fuel_activate(stage,op,before)",value)
        self.assertEqual('199a570adbaa8de4957bdd84b30648bfaaee5fb5',self.p.PLAN_SOURCE)
        self.assertEqual(self.command,m.parse_command(m.PREFIX+' '.join(self.command.values())))
        with self.assertRaises(ValueError):self.p.parse([self.p.PLAN_SOURCE,self.p.INSTALL_MODE,self.p.INSTALL_OPERATION])
        fixture=PassiveFuelActivationPlanTest();fixture.setUp();fixture.command=self.command
        fixture.test_plan_admission_requires_own_unique_created_command_and_exact_candidate_ci()

    def test_ready_snapshot_cannot_adopt_an_unverified_dependency_after_preflight(self):
        for case in ('caller','private_db','config','protected'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                original=ns['passive_fuel_activation_plan'];calls=0
                def preflight(*args):
                    nonlocal calls
                    calls+=1;value=original(*args)
                    if calls==2:
                        path={'caller':runtime/'api-v2.php','private_db':runtime/'data/db-v1.php',
                            'config':private/'search3-preview.php','protected':project/'data/db-v1.php'}[case]
                        path.write_bytes(b'unverified-after-ready')
                    return value
                ns['passive_fuel_activation_plan']=preflight;value=self.run_install(ns,stage,op)
                self.assertEqual('blocked_no_replay',value['status']);self.assertEqual(0,value['runtime_writes'])
                self.assertEqual(0,value['staging_file_writes']);self.assertEqual([],value['changed_files'])

    def test_installed_completion_requires_exact_two_readback_records_and_unchanged_protected_files(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            production=ns['fingerprints']();value=self.run_install(ns,stage,op)
            # Fixture bytes are deliberately not deployable. Validate the public
            # receipt contract with its fixed canonical records separately.
            canonical=self._canonical_targets()
            value['preflight']['plan_files']={p:{'predecessor_sha256':v[0],'candidate_sha256':v[1]}
                for p,v in canonical.items()}
            for p,(old,new) in canonical.items():
                root=p=='api-v2.php'
                value['before_files'][p]={'sha256':old,'bytes':16366 if root else 13823,'mode':0o644}
                value['after_files'][p]={'sha256':new,'bytes':20233 if root else 13766,'mode':0o644}
            value['preflight']['protected_before']['api-v2.php']=value['before_files']['api-v2.php']
            value['preflight']['protected_after']=copy.deepcopy(value['preflight']['protected_before'])
            value['protected_after']={**value['preflight']['protected_before'],'api-v2.php':value['after_files']['api-v2.php']}
            if self.command['mode']==self.p.CURRENT_MODE:
                value['preflight']['stock_topology']['manifest_record']={
                    'sha256':self.p.CURRENT_MANIFEST_SHA256,'bytes':self.p.CURRENT_MANIFEST_BYTES,'mode':0o644}
            result={**self.command,'status':'complete','runtime_changed':True,'production_unchanged':True,
                'production_before':production,'production_after':production,
                'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                'passive_fuel_activation':value}
            self.assertTrue(m.completed_result(self.command,result))
            for key,bad in [('runtime_writes',3),('staging_file_writes',3),('publication_lock','not_acquired'),
                            ('before_files',{}),('after_files',{}),('changed_files',['api-v2.php'])]:
                changed=copy.deepcopy(result);changed['passive_fuel_activation'][key]=bad
                self.assertFalse(m.completed_result(self.command,changed))
            changed=copy.deepcopy(result);changed['passive_fuel_activation']['protected_after']['index.php']={}
            self.assertFalse(m.completed_result(self.command,changed))
            if 'stock_topology' in value['preflight']:
                for key,bad in [('required_rows_verified',False),('manifest_record',None),
                        ('status','unrecognized_manifest'),('publisher_source_sha','0'*40)]:
                    changed=copy.deepcopy(result)
                    changed['passive_fuel_activation']['preflight']['stock_topology'][key]=bad
                    self.assertFalse(m.completed_result(self.command,changed))

    def test_completion_rejects_rollback_unknown_and_any_forged_extra_write(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            (runtime/'api-v2.php').unlink();value=self.run_install(ns,stage,op)
            value['preflight']['plan_files']={p:{'predecessor_sha256':v[0],'candidate_sha256':v[1]}
                for p,v in self._canonical_targets().items()}
            production=ns['fingerprints']()
            result={**self.command,'status':'complete','runtime_changed':False,'production_unchanged':True,
                'production_before':production,'production_after':production,
                'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0,
                'passive_fuel_activation':value}
            self.assertTrue(m.completed_result(self.command,result))
            for key,bad in [('runtime_writes',1),('changed_files',['third.php']),('status','rolled_back_no_replay'),
                            ('status','unknown_no_replay'),('backup_retained',True),('supplier_calls',1)]:
                changed=copy.deepcopy(result);changed['passive_fuel_activation'][key]=bad
                self.assertFalse(m.completed_result(self.command,changed))
            changed=copy.deepcopy(result);changed['runtime_changed']=True
            self.assertFalse(m.completed_result(self.command,changed))

    def _canonical_targets(self):
        ns={};exec(self.p.PLAN_REMOTE_READER,ns);return ns['PP_TARGETS']


class PassiveFuelStockLayoutTest(unittest.TestCase):
    def setUp(self):
        self.p=m.passive_fuel_readiness
        self.command=self.p.parse([self.p.STOCK_SOURCE,self.p.STOCK_MODE,self.p.STOCK_OPERATION])

    def fixture(self,directory):
        base=PassiveFuelActivationTransactionTest();base.setUp()
        ns,home,project,runtime,private,stage,op=base.fixture(directory)
        constants={k:copy.deepcopy(v) for k,v in ns.items() if k.startswith(('PF_','PP_'))}
        exec(self.p.STOCK_REMOTE,ns);ns.update(constants);ns['runtime']=runtime
        ns['operation']=self.p.STOCK_OPERATION;ns['pi_lint']=lambda path:None
        (runtime/'api-v2.php').unlink()
        manifest={'schema_version':1,'source_sha':self.p.STOCK_PUBLISHER_SOURCE,
            'route':'/_preview/search3-anex-candidate/','production_entry_changes':False,
            'production_lead_delivery':False,'preview_metrika_counter':0,'mapping_scope':'preview',
            'files':[{'path':path,'bytes':row[0],'sha256':row[1]} for path,row in self.p.STOCK_REQUIRED.items()],
            'file_count':len(self.p.STOCK_REQUIRED)}
        (runtime/'anex-preview-manifest.json').write_text(json.dumps(manifest))
        return ns,home,project,runtime,private,stage,op

    def test_verified_stock_exclusion_installs_only_original_two_targets(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            value=ns['passive_fuel_activation_plan']()
            self.assertEqual('ready',value['status']);self.assertEqual('excluded_by_stock_publisher',value['private_caller_state'])
            result=ns['passive_fuel_activate'](stage,op,ns['fingerprints']())
            self.assertEqual('installed_verified',result['status']);self.assertEqual(2,result['runtime_writes'])
            self.assertEqual('verified_stock_exclusion',result['preflight']['stock_topology']['status'])
            self.assertFalse((runtime/'api-v2.php').exists());self.assertFalse((runtime/'v2/api-v2.php').exists())
            self.assertNotIn('SECRET_NEVER_EXPORT',json.dumps(result));self.assertNotIn(str(home),json.dumps(result))

    def test_manifest_requires_all_seven_exact_rows_flags_and_unambiguous_paths(self):
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=self.fixture(directory)
            path=runtime/'anex-preview-manifest.json';original=json.loads(path.read_text())
            malformed=[]
            for key,value in [('source_sha','0'*40),('production_entry_changes',True),('production_lead_delivery',True),
                    ('preview_metrika_counter',False),('mapping_scope','production'),('file_count',True)]:
                malformed.append({**copy.deepcopy(original),key:value})
            for case in ('empty','missing','wrong_hash','duplicate','root_api','nested_api','dotdot','absolute'):
                value=copy.deepcopy(original);rows=value['files']
                if case=='empty':rows.clear()
                elif case=='missing':rows.pop()
                elif case=='wrong_hash':rows[0]['sha256']='0'*64
                elif case=='duplicate':rows.append(dict(rows[0]))
                else:rows.append({'path':{'root_api':'api-v2.php','nested_api':'v2/api-v2.php',
                    'dotdot':'foo/../bar.php','absolute':'/foo.php'}[case],'sha256':'0'*64,'bytes':1})
                value['file_count']=len(rows);malformed.append(value)
            for value in malformed:
                with self.subTest(value=str(value)[:80]):
                    path.write_text(json.dumps(value));self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])
                    result=ns['passive_fuel_activate'](stage,op,ns['fingerprints']())
                    self.assertEqual(('hold',0,0),(result['status'],result['runtime_writes'],result['staging_file_writes']))
            self.assertFalse(ns['ps_manifest'](b'{"source_sha":"first","source_sha":"second"}'))
            path.write_text(json.dumps(original));path.chmod(0o600)
            self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])

    def test_either_existing_private_gateway_holds_even_if_known_compatible(self):
        for relative in ('api-v2.php','v2/api-v2.php'):
            with self.subTest(relative=relative),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                target=runtime/relative;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(b'<?php /* caller */')
                ns['PP_CALLER']=hashlib.sha256(target.read_bytes()).hexdigest()
                result=ns['passive_fuel_activate'](stage,op,ns['fingerprints']())
                self.assertEqual(('hold',0),(result['status'],result['runtime_writes']))
                self.assertEqual('gateway_present',result['preflight']['stock_topology']['status'])

    def test_manifest_or_nested_gateway_drift_after_ready_prevents_staging(self):
        for case in ('manifest','nested'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                original=ns['passive_fuel_activation_plan'];calls=0
                def plan(*args):
                    nonlocal calls
                    calls+=1;value=original(*args)
                    if calls==2:
                        if case=='manifest':(runtime/'anex-preview-manifest.json').write_text('{}')
                        else:(runtime/'v2').mkdir();(runtime/'v2/api-v2.php').write_bytes(b'<?php /* new caller */')
                    return value
                ns['passive_fuel_activation_plan']=plan
                result=ns['passive_fuel_activate'](stage,op,ns['fingerprints']())
                self.assertEqual('blocked_no_replay',result['status']);self.assertEqual(0,result['runtime_writes'])
                self.assertEqual(0,result['staging_file_writes'])

    def test_new_contract_keeps_consumed_missing_caller_hold_and_strict_completion(self):
        self.assertEqual(self.command,m.parse_command(m.PREFIX+' '.join(self.command.values())))
        remote=self.p.remote_program(m.REMOTE,self.command)
        self.assertEqual(2,remote.count("if mode not in ('passive-fuel-stock-activate','reconcile',"))
        base=PassiveFuelActivationTransactionTest();base.setUp()
        with tempfile.TemporaryDirectory() as directory:
            ns,home,project,runtime,private,stage,op=base.fixture(directory);(runtime/'api-v2.php').unlink()
            self.assertEqual('hold',ns['passive_fuel_activation_plan']()['status'])
        # Exercise the same canonical installed readback validator with this
        # positive topology proof; no change to the original mode's receipt.
        base.command=self.command;base.fixture=self.fixture
        base.test_installed_completion_requires_exact_two_readback_records_and_unchanged_protected_files()


class PassiveFuelCurrentLayoutTest(unittest.TestCase):
    def setUp(self):
        self.p=m.passive_fuel_readiness
        self.command=self.p.parse([self.p.CURRENT_SOURCE,self.p.CURRENT_MODE,self.p.CURRENT_OPERATION])

    def fixture(self,directory):
        base=PassiveFuelStockLayoutTest();base.setUp()
        ns,home,project,runtime,private,stage,op=base.fixture(directory)
        constants={k:copy.deepcopy(v) for k,v in ns.items() if k.startswith(('PF_','PP_'))}
        exec(self.p.CURRENT_REMOTE,ns);ns.update(constants);ns['runtime']=runtime
        ns['operation']=self.p.CURRENT_OPERATION;ns['pi_lint']=lambda path:None
        path=runtime/'anex-preview-manifest.json';manifest=json.loads(path.read_text())
        manifest['source_sha']=self.p.CURRENT_PUBLISHER_SOURCE
        rows=[{'path':p,'bytes':v[0],'sha256':v[1]} for p,v in self.p.CURRENT_REQUIRED.items()]
        rows.extend({'path':p,'bytes':0,'sha256':hashlib.sha256(b'').hexdigest()} for p in self.p.CURRENT_EMPTY_PATHS)
        rows.extend({'path':'fixture/asset-'+str(i)+'.js','bytes':1,'sha256':'0'*64} for i in range(818-len(rows)))
        manifest.update(files=rows,file_count=818);data=json.dumps(manifest).encode();path.write_bytes(data)
        # Isolated fixture bytes never qualify as the pinned real publication.
        # Override only the remote fixture globals, as with API/helper test hashes.
        ns['PS_MANIFEST_PIN']=hashlib.sha256(data).hexdigest();ns['PS_MANIFEST_BYTES']=len(data)
        return ns,home,project,runtime,private,stage,op

    def test_current_exact_manifest_binding_and_two_file_receipt(self):
        base=PassiveFuelStockLayoutTest();base.setUp();base.command=self.command;base.fixture=self.fixture
        base.test_verified_stock_exclusion_installs_only_original_two_targets()
        transaction=PassiveFuelActivationTransactionTest();transaction.setUp()
        transaction.command=self.command;transaction.fixture=self.fixture
        transaction.test_installed_completion_requires_exact_two_readback_records_and_unchanged_protected_files()

    def test_hash_size_count_anchor_and_unknown_empty_file_each_hold(self):
        for case in ('hash','bytes','count','quote','unknown_empty','wrong_empty_hash'):
            with self.subTest(case=case),tempfile.TemporaryDirectory() as directory:
                ns,home,project,runtime,private,stage,op=self.fixture(directory)
                path=runtime/'anex-preview-manifest.json';value=json.loads(path.read_text())
                if case=='hash':ns['PS_MANIFEST_PIN']='0'*64
                elif case=='bytes':ns['PS_MANIFEST_BYTES']+=1
                else:
                    if case=='count':value['files'].pop();value['file_count']-=1
                    elif case=='quote':value['files']=[r for r in value['files'] if r['path']!='api-andromeda-quote-preview.php'];value['file_count']-=1
                    elif case=='unknown_empty':value['files'][-1]['bytes']=0;value['files'][-1]['sha256']=hashlib.sha256(b'').hexdigest()
                    else:next(r for r in value['files'] if r['path']==self.p.CURRENT_EMPTY_PATHS[0])['sha256']='0'*64
                    data=json.dumps(value).encode();path.write_bytes(data)
                    ns['PS_MANIFEST_PIN']=hashlib.sha256(data).hexdigest();ns['PS_MANIFEST_BYTES']=len(data)
                result=ns['passive_fuel_activate'](stage,op,ns['fingerprints']())
                self.assertEqual(('hold',0,0),(result['status'],result['runtime_writes'],result['staging_file_writes']))

    def test_extended_drift_and_old_scopes_stay_sealed(self):
        base=PassiveFuelStockLayoutTest();base.setUp();base.command=self.command;base.fixture=self.fixture
        base.test_manifest_or_nested_gateway_drift_after_ready_prevents_staging()
        self.assertEqual(self.command,m.parse_command(m.PREFIX+' '.join(self.command.values())))
        value=self.p.remote_program(m.REMOTE,self.command)
        self.assertEqual(2,value.count("if mode not in ('passive-fuel-current-activate','reconcile',"))
        self.assertEqual('0415fe57046a8929d1b857e13cd0d0d8824e904d',self.p.STOCK_SOURCE)
        with self.assertRaises(ValueError):self.p.parse([self.p.STOCK_SOURCE,self.p.CURRENT_MODE,self.p.CURRENT_OPERATION])


if __name__=='__main__':unittest.main(verbosity=2)


def test_match_common4_continuation_bulk_limit():
    cmd=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-bulk-20260923-v1 135 1214')
    assert cmd['offset']==135 and cmd['limit']==1214
    try:
        m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-acquire int-andromeda-match-common4-continuation-overflow-20260923-v1 135 1215')
        raise AssertionError('overflow accepted')
    except ValueError as exc:
        assert str(exc) in ('match_scope','match_limit')


class MatchContinuationResumeParseTest(unittest.TestCase):
    def test_match_common4_continuation_resume_day(self):
        cmd=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-resume-day int-andromeda-match-common4-continuation-resume-day-20260924-v1')
        self.assertEqual(cmd['mode'],'match-common4-continuation-resume-day')
        self.assertEqual(cmd['operation_id'],'int-andromeda-match-common4-continuation-resume-day-20260924-v1')

    def test_match_common4_resume_readback(self):
        cmd=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-resume-readback int-andromeda-match-common4-resume-readback-20260924-v1')
        self.assertEqual(cmd['mode'],'match-common4-resume-readback')

    def test_match_common4_continuation_remainder(self):
        cmd=m.parse_command(f'/run-int-server-v1 {SHA} match-common4-continuation-remainder int-andromeda-match-common4-continuation-remainder-20260924-v1 300')
        self.assertEqual(cmd['mode'],'match-common4-continuation-remainder')
        self.assertEqual(cmd['limit'],300)
