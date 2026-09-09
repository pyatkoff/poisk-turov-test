import sys
import unittest
from pathlib import Path

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


if __name__ == '__main__':
    unittest.main()
