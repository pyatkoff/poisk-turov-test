"""Exercise source drift, rebuilding and fail-closed behavior on an isolated tree."""
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('search3_assets', ROOT / 'scripts/build/search3_assets.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


def text_content_literals(source):
    # Decode actual emitted JS syntax, independent of the printer's quote choice.
    script = r'''
const fs = require('node:fs');
const acorn = require(process.argv[1]);
const values = [];
function visit(node) {
  if (!node || typeof node !== 'object') return;
  if (node.type === 'AssignmentExpression' && node.operator === '='
      && node.left.type === 'MemberExpression'
      && (node.left.computed ? node.left.property.value === 'textContent'
        : node.left.property.name === 'textContent')
      && node.right.type === 'Literal' && typeof node.right.value === 'string') {
    values.push(node.right.value);
  }
  for (const child of Object.values(node)) {
    if (Array.isArray(child)) child.forEach(visit);
    else if (child && typeof child === 'object') visit(child);
  }
}
visit(acorn.parse(fs.readFileSync(0, 'utf8'), { ecmaVersion: 2022, sourceType: 'script' }));
process.stdout.write(JSON.stringify(values));
'''
    result = subprocess.run(
        ['node', '-e', script, str(ROOT / 'scripts/build/search3-js/node_modules/acorn')],
        input=source, capture_output=True, check=True, timeout=10)
    return json.loads(result.stdout)


class Search3SourceBuildTest(unittest.TestCase):
    def test_css_notes_preserve_strings_escapes_token_boundaries_and_licenses(self):
        css = br'''/* private note */
.x{content:"/* literal */";--tokens:red/* note */blue;--escape:\"/* note */x}
/*! license */ /* Copyright owner */ /* @license MIT */
/*# sourceMappingURL=source.css.map */'''
        expected = css.replace(b'/* private note */', b'/**/').replace(b'/* note */', b'/**/')
        self.assertEqual(builder.compact_css_comments(css), expected)
        self.assertEqual(builder.compact_css_comments(expected), expected)
        with self.assertRaisesRegex(ValueError, 'Unterminated'):
            builder.compact_css_comments(b'.x{} /* broken')

    def test_css_indentation_keeps_separators_literals_and_escape_terminators(self):
        css = b'.x {\n  color:red;\n\t--tokens: first\n  second;\n}\n'
        expected = b'.x {\ncolor:red;\n--tokens: first\nsecond;\n}\n'
        self.assertEqual(builder.compact_css_comments(css, trim_indentation=True), expected)
        self.assertEqual(builder.compact_css_comments(expected, trim_indentation=True), expected)
        # A hex escape consumes its newline terminator: following indentation is
        # the only remaining separator and must not be removed.
        retained = b'.\\31\n  x{} .\\000031\r\n\tx{} .\\\n  y{}\n.x{content:"a\\\n  b"}\n/*! license\n  retained */'
        self.assertEqual(builder.compact_css_comments(retained, trim_indentation=True), retained)

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        shutil.copytree(ROOT / 'src/search3', self.root / 'src/search3')
        (self.root / 'docs/project').mkdir(parents=True)
        shutil.copy(ROOT / 'docs/project/search3-production-import.json', self.root / 'docs/project')
        (self.root / 'v2').mkdir()
        # The baseline/idempotence case verifies these committed outputs once.
        # Other cases need an immutable baseline, not another identical build.
        self.reviewed = json.loads((self.root / 'docs/project/search3-production-import.json').read_text())
        self.outputs = {name: (ROOT / 'v2' / name).read_bytes() for name in self.reviewed['assets']}
        for name in self.outputs:
            shutil.copy(ROOT / 'v2' / name, self.root / 'v2')

    def install_private_css_fixture(self):
        """Keep css-string safety coverage independent of production injectors."""
        source = self.root / 'src/search3/behavior/summary-cta-styles.js'
        part = self.root / 'src/search3/styles/injected/summary-cta.css'
        part.parent.mkdir(parents=True, exist_ok=True)
        part.write_text('.fixture { color: red; }\n')
        source.write_text('''(function () {
  const style = document.createElement('style');
  style.textContent=/* @css-string styles/injected/summary-cta.css */ "";
  document.head.appendChild(style);
})();
''')
        return source, part

    def test_current_outputs_match_and_build_is_idempotent(self):
        self.assertEqual(builder.build(self.root), 8)
        before = (self.root / 'docs/project/search3-production-import.json').read_bytes()
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        self.assertEqual(before, (self.root / 'docs/project/search3-production-import.json').read_bytes())

    def test_changed_source_requires_rebuild_and_preserves_other_assets(self):
        source = self.root / 'src/search3/behavior/search-form/entry-presentation.js'
        source.write_bytes(source.read_bytes() + b'\nwindow.__search3SourceDriftFixture = "controlled test edit";\n')
        with self.assertRaisesRegex(ValueError, 'Generated assets differ'):
            builder.build(self.root)
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        for name, original in self.outputs.items():
            if name != 'search3-results-filters-v1.js':
                self.assertEqual((self.root / 'v2' / name).read_bytes(), original)
        reviewed = json.loads((self.root / 'docs/project/search3-production-import.json').read_text())
        self.assertEqual(reviewed['protectedSha256'], self.reviewed['protectedSha256'])

    def test_missing_source_does_not_partially_write_outputs(self):
        (self.root / 'src/search3/behavior/entry-v1.js').unlink()
        with self.assertRaises(OSError):
            builder.build(self.root, write=True)
        for name, original in self.outputs.items():
            self.assertEqual((self.root / 'v2' / name).read_bytes(), original)

    def test_invalid_javascript_does_not_partially_write_outputs(self):
        source = self.root / 'src/search3/behavior/search-form/entry-presentation.js'
        source.write_bytes(source.read_bytes() + b'\nconst =;\n')
        with self.assertRaises(ValueError):
            builder.build(self.root, write=True)
        for name, original in self.outputs.items():
            self.assertEqual((self.root / 'v2' / name).read_bytes(), original)

    def test_unlisted_source_is_rejected(self):
        (self.root / 'src/search3/behavior/forgotten.js').write_text('void 0;')
        with self.assertRaisesRegex(ValueError, 'Unlisted'):
            builder.build(self.root)

    def test_private_part_drift_rebuilds_only_its_enclosing_asset(self):
        part = self.root / 'src/search3/behavior/results/labels.js'
        part.write_bytes(part.read_bytes() + b'\nwindow.__search3PrivateDriftFixture = "controlled private-part edit";\n')
        with self.assertRaisesRegex(ValueError, 'Generated assets differ'):
            builder.build(self.root)
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        for name, original in self.outputs.items():
            content = (self.root / 'v2' / name).read_bytes()
            if name == 'search3-results-filters-v1.js':
                self.assertIn(b'controlled private-part edit', content)
                self.assertNotIn(b'/* @include ', content)
            else:
                self.assertEqual(content, original)

    def test_invalid_private_include_fails_before_writing_any_output(self):
        part = self.root / 'src/search3/behavior/results/labels.js'
        original = part.read_bytes()
        for target in ('behavior/results-presentation.js',
                       'behavior/results/cards.js', '../../v2/search3-entry-v1.js'):
            with self.subTest(target=target):
                part.write_bytes(original + f'/* @include {target} */\n'.encode())
                with self.assertRaisesRegex(ValueError, 'Invalid or repeated'):
                    builder.build(self.root, write=True)
                for name, content in self.outputs.items():
                    self.assertEqual((self.root / 'v2' / name).read_bytes(), content)
        part.write_bytes(original)

    def test_private_css_literal_preserves_strings_escapes_and_host_asset(self):
        baseline, _, _ = builder.assemble(self.root)
        _, part = self.install_private_css_fixture()
        css = b'.x{content:"quote \\\" and slash \\\\";--tokens:red/* note */blue}\n'
        part.write_bytes(css)
        with self.assertRaisesRegex(ValueError, 'Generated assets differ'):
            builder.build(self.root)
        outputs, _, _ = builder.assemble(self.root)
        expected = builder.compact_css_comments(css, trim_indentation=True).decode()
        self.assertIn(expected, text_content_literals(outputs['search3-results-filters-v1.js']))
        builder.build(self.root, write=True)
        self.assertEqual(builder.build(self.root), 8)
        for name, original in baseline.items():
            if name != 'search3-results-filters-v1.js':
                self.assertEqual(outputs[name], original)

    def test_invalid_private_css_reference_fails_before_writing_outputs(self):
        source, _ = self.install_private_css_fixture()
        original = source.read_text()
        for target in ('styles/injected/selected-tour-mobile.css',
                       '../../v2/search3-entry-v1.css', 'styles/injected/missing.css'):
            with self.subTest(target=target):
                source.write_text(original.replace('styles/injected/summary-cta.css', target))
                with self.assertRaises((ValueError, OSError)):
                    builder.build(self.root, write=True)
                for name, content in self.outputs.items():
                    self.assertEqual((self.root / 'v2' / name).read_bytes(), content)
        source.write_text(original)

    def test_private_css_is_optimized_and_invalid_css_cannot_write_outputs(self):
        _, part = self.install_private_css_fixture()
        part.write_text('.x { color: #AABBCC; margin: 0px 0px; }\n')
        outputs, _, _ = builder.assemble(self.root)
        self.assertIn('.x{color:#abc;margin:0}\n',
                      text_content_literals(outputs['search3-results-filters-v1.js']))
        part.write_text('.x { color }')
        with self.assertRaisesRegex(ValueError, 'Colon is expected'):
            builder.build(self.root, write=True)
        for name, original in self.outputs.items():
            self.assertEqual((self.root / 'v2' / name).read_bytes(), original)

    def test_source_outside_module_root_is_rejected(self):
        manifest = self.root / 'src/search3/manifest.json'
        data = json.loads(manifest.read_text())
        data['assets']['search3-entry-v1.js'] = ['../../v2/search3-entry-v1.js']
        manifest.write_text(json.dumps(data))
        with self.assertRaisesRegex(ValueError, 'Invalid or repeated'):
            builder.build(self.root, write=True)


if __name__ == '__main__':
    unittest.main(verbosity=2)
