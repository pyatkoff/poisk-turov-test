"""Apply locally reviewed CSS deletions to one pinned isolated source snapshot."""
import hashlib
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = '91a7ff5df1fb117af972f517bc188e7597a6ee33'
PATCHES = {"src/search3/styles/filters.css":{"before":"57372cc66e2befd94d62aaeab6fe3a5809ef084e81fb789fffee078e6e3e1c69","after":"e46e7cb99c6eea55c5c96eadc9383696afe2a6f50d2f53c0c75dd0517fec5d79","edits":[[2537,2561,[]],[2778,2804,[]],[2815,2839,[]],[7261,7287,[]],[7326,7351,[]]]},"src/search3/styles/footer-compatibility.css":{"before":"3594839c594479c44349bd3de7b57b94a5f6cadc791b4525a07c7dd06213ee62","after":"fa58b2536c603c25f03a259248c92d2bcc1427eee514bf3b5c828e872a04d422","edits":[[3454,3483,[]],[3985,4011,[]],[4412,4455,[]],[4503,4527,[]],[4622,4701,[]],[4767,4791,[]],[5109,5132,[]],[5735,5806,[]],[5807,5885,[]],[5986,6009,[]],[6108,6201,[]],[6202,6276,[]],[6592,6617,[]],[7073,7096,[]],[7598,7675,[]],[7789,7886,[]],[8015,8039,[]]]},"src/search3/styles/hotel-results.css":{"before":"4658f3a126b40fb659af530fe63a3b5e385b11b184bf358b3f35c33eb9909af0","after":"4daa58748a3e07b254c563e2344c6323722cf5eeffaa3ee2959c69ffb677dddb","edits":[[965,991,[]],[1038,1088,[]],[1432,1458,[]],[1593,1618,[]],[1832,1855,[]],[2759,2784,[]],[3282,3333,[]],[3556,3580,[]],[6602,6628,[]],[6855,6881,[]],[7094,7119,[]],[9515,9543,[]],[9696,9778,[]],[9934,10029,[]],[10303,10372,[]],[10420,10529,[]],[12440,12490,[]],[12739,12764,[]],[13010,13035,[]],[13161,13187,[]],[13600,13626,[]],[14851,14877,[]],[18998,19021,[]],[20537,20561,[]],[20735,20760,[]],[20808,20877,[]]]},"src/search3/styles/results-context.css":{"before":"b5d2803769e058bb88054fe424c06e7f36a8f53bfe9933deecb0cfdd923e0ca9","after":"73b87a4b298002fa850714163a8da2017b1cf040a2f38684d6d69d926db237ce","edits":[[6382,6449,[]]]},"src/search3/styles/review.css":{"before":"aca8ac77cedc76468fd4315f9f8ca2e6e7e111a49edeb9d64deefa1f789c0acb","after":"a24c7f1e29c6b45d5966f7b92f398e29909166d857d18cca5b6abd2d6b9022dd","edits":[[4398,4421,[]],[4554,4581,[]],[4648,4749,[]],[4750,4849,[]]]},"src/search3/styles/selected-tour.css":{"before":"c9398e9d29cc80be55eaeaf50a4cfa9ac0ca45eb5015c53c61e691b50395df05","after":"57e0dd3f87b806953086d31739a85d72a34da6693c3d0c798b57af21f388b570","edits":[[1524,1549,[]],[4205,4233,[]],[5458,5481,[]],[5706,5733,[]],[5826,5906,[]],[5988,6014,[]],[7308,7334,[]],[7442,7465,[]],[7528,7551,[]],[7930,7958,[]],[8044,8069,[]],[8161,8185,[]],[8533,8559,[]],[9737,9823,[]]]}}

def digest(raw):
    return hashlib.sha256(raw).hexdigest()

def metrics(raw):
    return {'bytes': len(raw), 'lines': len(raw.splitlines()), 'sha256': digest(raw)}

def main():
    manifest_path = ROOT / 'docs/project/search3-production-import.json'
    reviewed_before = json.loads(manifest_path.read_text())
    assets_before = {name: (ROOT / 'v2' / name).read_bytes() for name in reviewed_before['assets']}
    if digest(assets_before['search3-results-filters-v1.css']) != '0644c80ac4e088137cc0a3d2cb1d1891dc3267d9d2a5c8161a2940c67cb1bea8':
        raise ValueError('Unexpected public CSS baseline')
    outputs, source_metrics = {}, []
    # Validate every input and output before any write. Byte offsets refer to BASE only.
    for name, patch in PATCHES.items():
        before = (ROOT / name).read_bytes()
        if digest(before) != patch['before']:
            raise ValueError('Unexpected source baseline: ' + name)
        after = before
        cursor = len(before)
        for start, end, replacement in reversed(patch['edits']):
            if not 0 <= start <= end <= cursor or replacement:
                raise ValueError('Invalid deletion span: ' + name)
            after = after[:start] + after[end:]
            cursor = start
        if digest(after) != patch['after']:
            raise ValueError('Reviewed source output mismatch: ' + name)
        outputs[name] = after
        source_metrics.append({'path': name, 'before': metrics(before), 'after': metrics(after)})
    for name, raw in outputs.items():
        (ROOT / name).write_bytes(raw)
    subprocess.run([sys.executable, '-B', str(ROOT / 'scripts/build/search3_assets.py'), '--write'], check=True)
    after = (ROOT / 'v2/search3-results-filters-v1.css').read_bytes()
    if digest(after) != '1756a00700f2c2f7b6ee0aa83cf52babaa76f02ecf241cbebf9b278c6d009979':
        raise ValueError('Public output differs from locally tested CSS')
    for name, raw in assets_before.items():
        if name != 'search3-results-filters-v1.css' and (ROOT / 'v2' / name).read_bytes() != raw:
            raise ValueError('Unrelated public asset changed: ' + name)
    reviewed_after = json.loads(manifest_path.read_text())
    expected = json.loads(json.dumps(reviewed_before))
    expected['assets']['search3-results-filters-v1.css']['productionSha256'] = digest(after)
    if reviewed_after != expected:
        raise ValueError('Unrelated review metadata or protected fingerprints changed')
    totals = {}
    for suffix in ('css', 'js'):
        raws = [(ROOT / 'v2' / name).read_bytes() for name in reviewed_after['assets'] if name.endswith('.' + suffix)]
        totals[suffix] = {'files': len(raws), 'bytes': sum(map(len, raws)), 'lines': sum(len(raw.splitlines()) for raw in raws)}
    report = {
        'baseline': BASE,
        'method': 'Remove only earlier important nonnegative numeric-px physical longhands with a later valid winner under the same literal selector, media stack and property. No shorthand, variable, fallback, priority, selector, media or keyframe rewrites. Existing cascade modules remain unchanged.',
        'removed_declarations': 75,
        'removed_empty_rules': 17,
        'removed_by_source': {'hotel-results.css': 31, 'selected-tour.css': 14, 'footer-compatibility.css': 20, 'filters.css': 5, 'review.css': 4, 'results-context.css': 1},
        'public_asset': 'v2/search3-results-filters-v1.css',
        'before': metrics(assets_before['search3-results-filters-v1.css']),
        'after': metrics(after),
        'sources': source_metrics,
        'exact_reproduction': PATCHES,
        'effective_declaration_stream_sha256': 'faaaefde3248929846d1ffb8378a5f902815ab43f443be0ad6f2ca932f961857',
        'native_cssom': {'browser': 'Chromium 144.0.7559.96', 'before_declarations': 14747, 'after_declarations': 14672, 'effective_declarations': 14052, 'effective_sequence_equal': True, 'effective_sequence_sha256': '1477a7a87b883dc1f7d978d9ddeb3ecd1cfb540153137aa8e76b5e78fe920cbe'},
        'offline_visual': {'widths': [375,430,640,641,760,761,768,999,1000,1024,1348,1440,1920], 'states': ['initial','hotel','editor','selected','review','lead'], 'comparisons': 78, 'computed_properties': 32, 'all_element_rectangles_equal': True, 'byte_identical_screenshot_pairs': 6, 'scope': 'Frozen offline fixture DOM, not live acceptance. External catalogue, widget, original logo and physical Safari were unavailable. Existing baseline fixture imperfections are not evidence of a live defect.'},
        'public_search3_totals': totals,
        'publication': 'NOT_DEPLOYED',
        'ci_evidence': 'See exact-head evidence in PR #1334 and issue #996; do not treat local evidence as remote CI.'
    }
    (ROOT / 'docs/project/search3-compatibility-cleanup.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    state_path = ROOT / 'AUTOPILOT_STATE.json'
    state = json.loads(state_path.read_text())
    state['presentation_refactor_checkpoint'] = {'baseline': BASE, 'evidence': 'docs/project/search3-compatibility-cleanup.json', 'public_css_sha256': digest(after), 'scope': '75 shadowed numeric-px declarations and 17 emptied rules removed from six non-cascade sources', 'source_validation': 'LOCAL_BUILD_REGRESSION_CSSOM_AND_OFFLINE_EQUIVALENCE_VERIFIED', 'preview_updated': False, 'production_updated': False, 'next_action': 'Read latest PR #1334 CI and #996 before the next independent refactor. Do not repeat these 75 removals or the earlier cascade cleanup. Production visual-approval lock remains.'}
    state_path.write_text(json.dumps(state, ensure_ascii=False, indent=2) + '\n')
    print('SEARCH3_COMPATIBILITY_CLEANUP_OK ' + json.dumps({'before': report['before'], 'after': report['after'], 'totals': totals}))

if __name__ == '__main__':
    main()
