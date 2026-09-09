import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_catalog_content as content


class ContentSources(unittest.TestCase):
    def test_only_confirmed_same_identity_is_eligible(self):
        good = {'external_id': 17, 'api_xml_relation': 'same_record', 'api': {'id': 17, 'name': 'Hotel'}}
        rows = [good, good, {'external_id': 19, 'api_xml_relation': 'same_record', 'api': {'id': 18, 'name': 'Wrong'}},
                {'external_id': 20, 'api_xml_relation': 'unknown', 'api': {'id': 20, 'name': 'Unknown'}},
                {'external_id': 21, 'status': 'source_error'}]
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for name in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json', 'anex-observed-hotel-checkpoint.json'):
                (directory / name).write_text(json.dumps({'rows': rows}))
            self.assertEqual(content.verified_ids(directory), [17])

    def test_no_verified_source_does_not_start_a_new_queue(self):
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            for name in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json', 'anex-observed-hotel-checkpoint.json'):
                (directory / name).write_text(json.dumps({'rows': []}))
            with self.assertRaises(ValueError):
                content.verified_ids(directory)


if __name__ == '__main__':
    unittest.main()
