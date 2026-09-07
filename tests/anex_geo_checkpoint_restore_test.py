import importlib.util
from pathlib import Path
import unittest

path = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/anex_geo_checkpoint_restore.py'
spec = importlib.util.spec_from_file_location('restore', path)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def artifact(identifier, run, name='anex-hotel-catalog-match', expired=False):
    return dict(id=identifier,workflow_run={'id':run},name=name,expired=expired)


class RestoreTest(unittest.TestCase):
    def test_newest_attempt_of_running_run_is_kept(self):
        rows=[artifact(1,10),artifact(3,11,'anex-hotel-catalog-match-11-2'),artifact(2,11)]
        self.assertEqual(module.choose_checkpoint({10,11},rows)['id'],3)
    def test_other_workflow_artifacts_are_excluded(self):
        rows=[artifact(1,10),artifact(2,99),artifact(3,10,'unrelated')]
        self.assertEqual(module.choose_checkpoint({10},rows)['id'],1)
    def test_expired_latest_does_not_roll_back(self):
        with self.assertRaises(ValueError):
            module.choose_checkpoint({10,11},[artifact(1,10),artifact(2,11,expired=True)])
    def test_missing_checkpoint_stops(self):
        with self.assertRaises(ValueError):
            module.choose_checkpoint({10},[])

if __name__ == '__main__':
    unittest.main()
