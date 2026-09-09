import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import anex_search3_cached_detail_alias_review as review


class CachedDetailAliasReviewTests(unittest.TestCase):
    def test_pinned_batch_is_unique_and_excludes_finished_cached_alias_ids(self):
        finished = {551, 1271, 4364, 18787, 34079, 34080, 37878, 39233}
        self.assertEqual(len(review.EXPECTED_IDS), 17)
        self.assertEqual(len(set(review.EXPECTED_IDS)), 17)
        self.assertFalse(set(review.EXPECTED_IDS) & finished)

    def test_source_and_bootstrap_are_pinned(self):
        self.assertEqual(
            review.PINNED_AUDIT_SHA,
            'f82d2391b98f817ebface0331a8ef3691ffcca2fd55213288827f0f20aff9afc')
        self.assertEqual(review.BOOTSTRAP_ARTIFACT, 10082284529)

    def test_approved_delta_contains_only_reproduced_strong_results(self):
        strong = {
            'anex_hotel_id': 32752, 'candidate_set_complete': True,
            'alias_set_complete': True, 'proposal_status': 'strong_candidate',
            'proposal_reason': 'name_country_coordinates', 'best': {'id': 66081}}
        rejected = dict(strong, anex_hotel_id=32724, proposal_status='review',
                        proposal_reason='competing_candidates')
        checkpoint = {'sources': {'catalog_sha256': 'a' * 64, 'geo_sha256': 'b' * 64},
                      'batches': [{'state': 'completed', 'results': [strong, rejected]}]}
        with tempfile.TemporaryDirectory() as temp, \
                patch.object(review, 'file_sha', return_value=review.CHECKED_CHECKPOINT_SHA), \
                patch.object(review, 'load', return_value=(checkpoint, {})):
            path = Path(temp) / review.CHECKPOINT
            path.write_text('{}')
            delta = review.approved_delta(path)
        self.assertEqual(delta['counts']['total'], 1)
        self.assertEqual(delta['rows'][0]['anex_hotel_id'], 32752)
        self.assertEqual(delta['rows'][0]['catalog_hotel_id'], 66081)
        self.assertEqual(
            set(delta['sources']),
            {'catalog_sha256', 'geo_sha256', 'gap_sha256'})
        self.assertEqual(delta['sources']['gap_sha256'], review.CHECKED_CHECKPOINT_SHA)

    def test_only_the_known_rejected_protocol_checkpoint_can_migrate(self):
        self.assertEqual(
            review.BROKEN_PROTOCOL_DELTA_SHA,
            'cc9a6034e1afb844eb6c163bf1de25fd15855d6a731ea136048a8a33491e61ce')
        source = Path(review.__file__).read_text(encoding='utf-8')
        self.assertIn("previous.get('state') == 'prepared'", source)
        self.assertIn("previous.get('checked_checkpoint_sha256') == CHECKED_CHECKPOINT_SHA", source)


if __name__ == '__main__':
    unittest.main()
