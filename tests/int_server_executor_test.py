#!/usr/bin/env python3
import importlib.util
import hashlib
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
            event={'issue':{'number':3419},'comment':comment}
            self.assertEqual(m.checked_event('fixture',event,'a'*40)['source_sha'],SHA)
            for number in (2530,996,1646):
                api.reset_mock()
                event={'issue':{'number':number},'comment':comment}
                with self.subTest(issue=number),self.assertRaisesRegex(ValueError,'issue'):
                    m.checked_event('fixture',event,'a'*40)
                api.assert_not_called()

    def test_workflow_uses_the_same_current_journal(self):
        text=(SCRIPT.parents[2]/'.github/workflows/int-server-executor.yml').read_text()
        self.assertIn('github.event.issue.number == 3419',text)
        self.assertNotIn('github.event.issue.number == 2530',text)


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
        bundle,manifest=m.bundle_source(source)
        archive=root/'source.tar.gz';archive.write_bytes(bundle)
        home=root/'home';project=home/'www/anytoour.ru'
        runtime=project/'_preview/search3-anex-candidate'
        (runtime/'app/integrations').mkdir(parents=True)
        (runtime/'app/integrations/x0.php').write_text('<?php /* old */\n')
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
        for x in ["ISSUE = 3419","OWNER_ID = 226193297","FEATURE = 'feature/anex-search-adapter-20260907'",
                  "operation_exists_no_replay","StrictHostKeyChecking=yes","production_unchanged","remote_command_size","zlib.compress",
                  "install-runtime","install-andromeda-preview","install-andromeda-quote-preview","install-plan.json","preview-install-plan.json","quote-preview-install-plan.json","install-state.json","rollback_install",
                  "v2/api-andromeda-search3-preview.php","api-andromeda-search3-preview.php","v2/api-andromeda-quote-preview.php","api-andromeda-quote-preview.php",
                  "manifest_digest","public_ui_entrypoints_unchanged","three-provider-fuel-evidence.php",
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
                  "install-runtime','install-andromeda-preview','install-andromeda-quote-preview','match-coverage','match-coverage-v2','match-coverage-v2-readback','match-coverage-readback','match-tv234-readback','match-tv234-secondary','match-common4-acquire','match-common4-continuation-acquire','match-common4-continuation-resume-day','match-common4-resume-readback','match-common4-readback','match-common4-current-v2','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942",
                  "provider_attempted_without_terminal","pre_provider_reservation_only",
                  "match_readback_hash"]:
            self.assertIn(x,text)
        self.assertIn("if command['mode'] == 'match-common4-continuation-remainder':",text)
        for x in ['shell=True',"booking(","bron_ticket","workflow_dispatch("]:
            self.assertNotIn(x,text)

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
