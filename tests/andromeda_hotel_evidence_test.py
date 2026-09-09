import copy
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('evidence', Path(__file__).resolve().parents[1] / 'scripts/diagnostics/andromeda_hotel_evidence.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class EvidenceTests(unittest.TestCase):
    def setUp(self):
        self.offer = {'provider': 'andromeda', 'selection_enabled': False, 'local_hotel_id': None,
                      'search_ref': 'fixture', 'generation': 1, 'supplier_namespace': 'andromeda_catalog',
                      'external_hotel_id': '42', 'operator_ref': '5', 'hotel': 'Fixture Hotel',
                      'offer_ref': 'offer_' + 'a' * 64}
        self.page = {'provider': 'andromeda', 'selection_enabled': False, 'search_ref': 'fixture',
                     'generation': 1, 'offers': [self.offer], 'rejected': [], 'page': 1,
                     'pages_count': 2, 'status': 'partial'}
        self.catalog = {'params': {'STATEINC': 3, 'TOWNFROMINC': 1},
                        'payload': {'HOTELS': [{'id': 42, 'name': 'Fixture Hotel', 'stateKey': 3,
                                                'townKey': 2, 'town': 'Fixture town', 'secret': 'never-copy'}]}}

    def test_group_without_auto_mapping(self):
        self.page['offers'].append(dict(self.offer, offer_ref='offer_' + 'b' * 64))
        result = module.build(self.page, self.catalog)
        self.assertEqual(result['counts']['unique_hotels'], 1)
        self.assertEqual(result['rows'][0]['offer_count'], 2)
        self.assertEqual(result['rows'][0]['status'], 'catalog_found')
        self.assertIsNone(result['rows'][0]['local_hotel_id'])
        self.assertEqual(result['accepted_mappings'], 0)
        self.assertNotIn('never-copy', str(result))
        self.assertEqual(result['source_status'], 'partial')

    def test_operator_key_does_not_join_same_numeric_catalog_id(self):
        self.page['offers'].append(dict(self.offer, supplier_namespace='operator_5', offer_ref='offer_' + 'b' * 64))
        result = module.build(self.page, self.catalog)
        excluded = next(r for r in result['rows'] if r['status'] == 'operator_key_excluded')
        self.assertIsNone(excluded['catalog'])
        self.assertEqual(result['counts']['unique_hotels'], 2)

    def test_missing_does_not_match_by_name(self):
        self.offer['external_hotel_id'] = '043'
        row = module.build(self.page, self.catalog)['rows'][0]
        self.assertEqual(row['status'], 'catalog_missing')
        self.assertIsNone(row['catalog'])

    def test_stale_mapped_or_ambiguous_context_rejected(self):
        for change in [{'search_ref': 'old'}, {'generation': 2}, {'generation': True},
                       {'local_hotel_id': 42}, {'selection_enabled': True},
                       {'supplier_namespace': 'operator_6'}]:
            with self.subTest(change=change):
                page = copy.deepcopy(self.page)
                page['offers'][0].update(change)
                with self.assertRaises(module.EvidenceError):
                    module.build(page, self.catalog)

    def test_duplicates_rejected(self):
        self.page['offers'].append(dict(self.offer))
        with self.assertRaisesRegex(module.EvidenceError, 'DUPLICATE_OFFER'):
            module.build(self.page, self.catalog)
        self.page['offers'].pop()
        self.catalog['payload']['HOTELS'] *= 2
        with self.assertRaisesRegex(module.EvidenceError, 'DUPLICATE_CATALOG'):
            module.build(self.page, self.catalog)

    def test_country_mismatch_rejected(self):
        self.catalog['payload']['HOTELS'][0]['stateKey'] = 4
        with self.assertRaisesRegex(module.EvidenceError, 'COUNTRY_MISMATCH'):
            module.build(self.page, self.catalog)

    def test_digest_and_duplicate_json_guards(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'fixture.json'
            path.write_bytes(b'{"x":1,"x":2}')
            with self.assertRaisesRegex(module.EvidenceError, 'SOURCE_DIGEST'):
                module.read_pinned(path, '0' * 64)
            with self.assertRaisesRegex(module.EvidenceError, 'DUPLICATE_JSON'):
                module.read_pinned(path, hashlib.sha256(path.read_bytes()).hexdigest())

    def test_empty_and_deterministic(self):
        before = copy.deepcopy(self.page)
        self.assertEqual(module.build(self.page, self.catalog), module.build(self.page, self.catalog))
        self.assertEqual(self.page, before)
        self.page.update(offers=[], pages_count=0, status='complete')
        self.assertEqual(module.build(self.page, self.catalog)['counts']['offers'], 0)


if __name__ == '__main__':
    unittest.main()
