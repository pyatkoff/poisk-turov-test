#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import tempfile
import unittest

SCRIPT=Path(__file__).resolve().parents[1]/'scripts/deploy/int_server_executor.py'
spec=importlib.util.spec_from_file_location('int_server_executor',SCRIPT)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
SHA='cde183f7d33cef6bd1df4d0ff16dad0904d53570'

class ParseTest(unittest.TestCase):
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
        ]
        for value in bad:
            with self.subTest(value=value),self.assertRaises(ValueError):m.parse_command(value)

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

class ContractTest(unittest.TestCase):
    def test_control_boundaries(self):
        text=SCRIPT.read_text()
        for x in ["ISSUE = 2530","OWNER_ID = 226193297","FEATURE = 'feature/anex-search-adapter-20260907'",
                  "operation_exists_no_replay","StrictHostKeyChecking=yes","production_unchanged",
                  "anex_local_offer_demand_fill.php","andromeda_local_offer_collect.php",
                  "search3-local-results-read-v1.php","--max-captures=","--capture-mode=non_external_only",
                  "reconcile_target","collector_stderr_sha256","skipped_after_collector_nonzero",
                  "php=\"$c=require $argv[1];","allowed_keys={'status'","name_sha256","top_level_keys",
                  "local-readback","local_readback_exit","stderr_sha256",
                  "LOCAL_READER_MISSING","LOCAL_DB_CONNECTION","LOCAL_DB_NOT_CONFIGURED",
                  "require_once $config","errorSha256","attempt_state","package_record",
                  "diagnostic_code","actualization","failure_class","actions_used",
                  "supplier_error_facts","reason_category","code_field","error_sha256"]:
            self.assertIn(x,text)
        for x in ['shell=True',"booking(","bron_ticket","workflow_dispatch("]:
            self.assertNotIn(x,text)

if __name__=='__main__':unittest.main(verbosity=2)
