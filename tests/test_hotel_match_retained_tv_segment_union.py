"""Local-only regression tests; MATCH_RETAINED_DIR supplies immutable ZIP fixtures."""
import ast
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / 'scripts/diagnostics/hotel_match_retained_tv_segment_union.py'
sys.path.insert(0, str(PATH.parent))
SPEC = importlib.util.spec_from_file_location('segments', PATH)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


class BoundaryTests(unittest.TestCase):
    def test_wrong_archive(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d) / 'bad.zip'; p.write_bytes(b'wrong')
            with self.assertRaisesRegex(ValueError, 'segment_zip_hash'):
                M.load_segment(p, 'first450')

    def test_symlink(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d) / 'bad.zip'; p.write_bytes(b'wrong')
            link = Path(d) / 'link.zip'; link.symlink_to(p)
            with self.assertRaisesRegex(ValueError, 'segment_file'):
                M.load_segment(link, 'c0c30')

    def test_no_remote_or_database_code(self):
        tree = ast.parse(PATH.read_text())
        imports = {n.module.split('.')[0] for n in ast.walk(tree) if isinstance(n, ast.ImportFrom) and n.module}
        imports |= {a.name.split('.')[0] for n in ast.walk(tree) if isinstance(n, ast.Import) for a in n.names}
        self.assertFalse(imports & {'subprocess', 'socket', 'urllib', 'requests', 'sqlite3', 'pymysql', 'http'})
        self.assertFalse({n.func.id for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Name)} & {'eval', 'exec'})


@unittest.skipUnless(os.environ.get('MATCH_RETAINED_DIR'), 'pinned archives not supplied')
class RealSegmentTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.inputs = Path(os.environ['MATCH_RETAINED_DIR'])
        cls.history_path = ROOT / 'reports/hotel-match-retained175-history-mass-20260926.json'
        cls.documents = {k: M.load_segment(cls.inputs / v['file'], k) for k, v in M.SEGMENTS.items()}
        cls.report = M.run(cls.inputs, cls.history_path)

    def test_counts_and_partition(self):
        r = self.report
        self.assertEqual((r['input_count'], r['candidate_pairs_reviewed']), (175, 161))
        self.assertEqual((r['tv_input_rows'], r['tv_unique_edges'], r['tv_unique_hotels_with_evidence']), (1467, 1431, 1041))
        self.assertEqual(r['independent_operator_distribution'], {'0': 146, '1': 23, '2': 6})
        sets = [set(v) for v in r['dossier_ids_by_exact_independent_tv_lane_count'].values()]
        self.assertEqual(sum(map(len, sets)), len(set.union(*sets)))
        self.assertEqual(len(set.union(*sets)), 175)

    def test_new_candidates_are_not_safe_additions(self):
        r = self.report
        self.assertEqual(r['new_candidate_ids'], [466, 474, 1181])
        self.assertEqual(r['lost_candidate_ids'], [])
        self.assertEqual(r['review_disposition_counts'], {'historical_review': 8, 'one_operator_current_review': 16, 'two_operator_current_review': 5})
        for row in r['candidates']:
            if row['local_hotel_id'] in r['new_candidate_ids']:
                self.assertEqual(row['disposition'], 'historical_review')
                self.assertIn('historical_source_accepted_other_target', row['historical_review_reasons'])

    def test_priority_keeps_sural_out(self):
        ids = {r['local_hotel_id'] for r in self.report['candidates'] if r['disposition'] == 'two_operator_current_review'}
        self.assertEqual(ids, {11742, 11748, 11773, 113617, 121109})
        self.assertNotIn(1540, ids)

    def test_no_new_acceptance_or_current_claim(self):
        r = self.report
        for k in ('accepted_mapping_count', 'provider_http_calls', 'database_reads', 'database_writes', 'mapping_writes'):
            self.assertEqual(r[k], 0)
        self.assertFalse(r['current_validation_performed']); self.assertFalse(r['safe_to_write_now'])
        for row in r['candidates']:
            self.assertFalse(row['safe_to_write_now'])
            self.assertEqual(len(row['proven_lanes']), len({p['namespace'] for p in row['proven_lanes']}))
            self.assertLessEqual({p['namespace'] for p in row['proven_lanes']}, {'operator_315', 'operator_342'})

    def test_source_identity_and_children(self):
        for kind, doc in self.documents.items():
            for field in ('operation', 'state', 'source_sha'):
                wrong = copy.deepcopy(doc); wrong[field] = 'wrong'
                with self.subTest(kind=kind, field=field), self.assertRaisesRegex(ValueError, 'segment_' + field):
                    M.segment_edges(wrong, kind)
            wrong = copy.deepcopy(doc); wrong['children'][0]['result_sha256'] = '0' * 64
            with self.assertRaisesRegex(ValueError, 'segment_children'): M.segment_edges(wrong, kind)
            wrong = copy.deepcopy(doc); wrong['children'][1] = wrong['children'][0]
            with self.assertRaisesRegex(ValueError, 'segment_children'): M.segment_edges(wrong, kind)

    def test_rejects_side_effect_and_boolean_zero(self):
        for kind, doc in self.documents.items():
            for key in ('database_writes', 'mapping_writes', 'provider_http_calls', 'supplier_calls'):
                for value in (1, False):
                    wrong = copy.deepcopy(doc); wrong[key] = value
                    with self.subTest(kind=kind, key=key, value=value), self.assertRaisesRegex(ValueError, 'segment_zero_'):
                        M.segment_edges(wrong, kind)

    def test_rejects_row_tampering(self):
        changes = [('operator_id', True, 'namespace'), ('external_hotel_id', '01', 'id_shape'),
                   ('source_result_sha256', '0' * 64, 'child_hash'), ('safe_to_write_now', True, 'row_safety'),
                   ('operator_link_sha256', '', 'proof_hash')]
        for field, value, error in changes:
            wrong = copy.deepcopy(self.documents['first450']); wrong['rows'][0][field] = value
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, error): M.segment_edges(wrong, 'first450')
        wrong = copy.deepcopy(self.documents['first450']); wrong['rows'][0]['catalog_hotel']['id'] = 1
        with self.assertRaisesRegex(ValueError, 'target_binding'): M.segment_edges(wrong, 'first450')

    def test_every_proof_dereferences(self):
        docs = {M.SEGMENTS[k]['result_sha256']: v for k, v in self.documents.items()}
        for kind, name in (('tv','match_v63_secondary.zip'),('tv_tail','match_tv_tail_r1_r2.zip'),('tv_early','match_tv_c35_c135.zip')):
            value = M.core.load_archive(self.inputs / name, kind)
            pin = M.core.PINS['tv'] if kind == 'tv' else (M.core.TV_TAIL_PIN if kind == 'tv_tail' else M.core.TV_EARLY_PIN)
            docs[pin[1]] = value
        count = 0
        for row in self.report['candidates']:
            for lane in row['proven_lanes']:
                for p in lane['evidence']:
                    node = docs[p['archive_result_sha256']]
                    for part in p['row_path'].strip('/').split('/'):
                        node = node[int(part)] if isinstance(node, list) else node[part]
                    self.assertEqual(M.core.digest(node), p['row_canonical_sha256'])
                    self.assertEqual(node['tv_hotel_id'], row['local_hotel_id'])
                    count += 1
        self.assertGreater(count, 29)

    def test_deterministic(self):
        self.assertEqual(self.report, M.run(self.inputs, self.history_path))

    def test_cli_refuses_existing_output(self):
        with tempfile.TemporaryDirectory() as d:
            out = Path(d) / 'report.json'; out.write_bytes(b'keep')
            r = subprocess.run([sys.executable, str(PATH), '--input-dir', str(self.inputs),
                                '--history-summary', str(self.history_path), '--output', str(out)], capture_output=True)
            self.assertNotEqual(r.returncode, 0); self.assertEqual(out.read_bytes(), b'keep')


if __name__ == '__main__':
    unittest.main()
