"""Exercise source drift, rebuilding and fail-closed behavior on an isolated tree."""
import importlib.util
import json
from pathlib import Path
import shutil
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('search3_assets', ROOT / 'scripts/build/search3_assets.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class Search3SourceBuildTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        shutil.copytree(ROOT / 'src/search3', self.root / 'src/search3')
        (self.root / 'docs/project').mkdir(parents=True)
        shutil.copy(ROOT / 'docs/project/search3-production-import.json', self.root / 'docs/project')
        (self.root / 'v2').mkdir()
        self.outputs, _, self.reviewed = builder.assemble(self.root)
        for name in self.outputs:
            shutil.copy(ROOT / 'v2' / name, self.root / 'v2')

    def test_current_outputs_match_and_build_is_idempotent(self):
        self.assertEqual(builder.build(self.root), 8)
        before = (self.root / 'docs/project/search3-production-import.json').read_bytes()
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        self.assertEqual(before, (self.root / 'docs/project/search3-production-import.json').read_bytes())

    def test_changed_source_requires_rebuild_and_preserves_other_assets(self):
        source = self.root / 'src/search3/behavior/entry-v1.js'
        source.write_bytes(source.read_bytes() + b'\n/* controlled test edit */\n')
        with self.assertRaisesRegex(ValueError, 'Generated assets differ'):
            builder.build(self.root)
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        for name, original in self.outputs.items():
            if name != 'search3-entry-v1.js':
                self.assertEqual((self.root / 'v2' / name).read_bytes(), original)
        reviewed = json.loads((self.root / 'docs/project/search3-production-import.json').read_text())
        self.assertEqual(reviewed['protectedSha256'], self.reviewed['protectedSha256'])

    def test_missing_source_does_not_partially_write_outputs(self):
        (self.root / 'src/search3/behavior/entry-v1.js').unlink()
        with self.assertRaises(OSError):
            builder.build(self.root, write=True)
        for name, original in self.outputs.items():
            self.assertEqual((self.root / 'v2' / name).read_bytes(), original)

    def test_unlisted_source_is_rejected(self):
        (self.root / 'src/search3/behavior/forgotten.js').write_text('void 0;')
        with self.assertRaisesRegex(ValueError, 'Unlisted'):
            builder.build(self.root)

    def test_source_outside_module_root_is_rejected(self):
        manifest = self.root / 'src/search3/manifest.json'
        data = json.loads(manifest.read_text())
        data['assets']['search3-entry-v1.js'] = ['../../v2/search3-entry-v1.js']
        manifest.write_text(json.dumps(data))
        with self.assertRaisesRegex(ValueError, 'Invalid or repeated'):
            builder.build(self.root, write=True)


if __name__ == '__main__':
    unittest.main(verbosity=2)
