"""Check current Search3 presentation owners and protected runtime contracts.

The pre-reset owner assertions remain in Git history. The current suite executes
without using a historical audit file as a switch; protected/business assertions
and preview isolation remain active.
"""
import hashlib
import json
import re
from pathlib import Path
import shutil
import subprocess
import unittest

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = json.loads((ROOT / 'docs/project/search3-production-import.json').read_text())


class Search3HalfSizeResetTest(unittest.TestCase):
    def setUp(self):
        self.source = json.loads((ROOT / 'src/search3/manifest.json').read_text())
        self.bundle = (ROOT / 'v2/bundle-manifest-v1.php').read_text()

    def test_eight_public_paths_and_presentation_budget(self):
        expected = {
            'search3-results-filters-v1.js', 'search3-results-filters-v1.css',
            'search3-entry-v1.css', 'search3-entry-v1.js',
            'search3-results-cards-v2.css', 'search3-results-cards-v2.js',
            'search3-selected-flow-v2.css', 'search3-selected-flow-v2.js',
        }
        self.assertEqual(set(self.source['assets']), expected)
        total = sum((ROOT / 'v2' / name).stat().st_size for name in expected)
        # Current product plan permits useful UX growth after the completed half-size reset.
        # Keep a finite measured envelope for the same eight assets, not the historical 2x ratio.
        # Budget range + native mobile return: 89925B -> 92323B (+2398B), no added asset/owner.
        # Approved mobile reference + unique open description: 94481B -> 95218B (+737B CSS).
        # In-form mobile return action: 96000B -> 96455B (+455B CSS/behavior).
        # Sticky mobile form return: 96498B -> 96582B (+84B entry CSS).
        self.assertLessEqual(total, 96750, 'eight presentation assets stay within the 96.75KB envelope including mobile card hierarchy and persistent form return')

    def test_reset_css_owners_and_native_selected_bound(self):
        assets = self.source['assets']
        self.assertEqual(assets['search3-results-filters-v1.css'], ['styles/results-layout.css'])
        self.assertEqual(assets['search3-entry-v1.css'], ['styles/entry-native-controls.css'])
        self.assertEqual(assets['search3-results-cards-v2.css'], ['styles/result-cards.css'])
        self.assertEqual(assets['search3-selected-flow-v2.css'], ['styles/selected-tour.css'])
        self.assertEqual(assets['search3-selected-flow-v2.js'], [])
        self.assertFalse((ROOT / 'src/search3/styles/base.css').exists())
        self.assertFalse((ROOT / 'src/search3/behavior/selected-flow-v2.js').exists())
        self.assertFalse((ROOT / 'src/search3/behavior/selected/flight-fallback.js').exists())
        self.assertLessEqual((ROOT / 'v2/search3-results-cards-v2.css').stat().st_size, 1)
        self.assertEqual(
            (ROOT / 'v2/search3-selected-flow-v2.css').read_text(),
            '.selected-picture img{max-width:100%}'
            '#selectedTour:not(.search3-lead-entry) .lead-form{display:none}\n',
        )
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        self.assertIn(
            '& .selected-picture img {position:absolute;inset:0;display:block;width:100%;height:100%;object-fit:cover}',
            results,
        )
        self.assertEqual((ROOT / 'v2/search3-selected-flow-v2.js').stat().st_size, 0)

    def test_selected_runtime_is_eager_and_uses_canonical_owners_once(self):
        phases = json.loads(subprocess.check_output([
            'php', '-r',
            'require "v2/bundle-manifest-v1.php"; echo json_encode(['
            'v2_bundle_phase_files("js", "search3", "initial"),'
            'v2_bundle_phase_files("js", "search3", "selected")]);'
        ], cwd=ROOT))
        initial, selected = phases
        self.assertEqual(selected.count('flight-empty-recovery-v1.js'), 1)
        self.assertEqual(selected.count('flight-price-sync-v1.js'), 1)
        self.assertEqual(selected.count('tour-controller-v4.js'), 1)
        recovery = (ROOT / 'v2/flight-empty-recovery-v1.js').read_text()
        summary = (ROOT / 'src/search3/behavior/summary-cta.js').read_text()
        self.assertIn('Проверить рейсы ещё раз', recovery)
        self.assertIn("window.addEventListener('v2:tour-selected'", recovery)
        self.assertIn("selectedState(true)", summary)
        self.assertIn('correctTradeoffs', summary)
        self.assertFalse((ROOT / 'src/search3/behavior/selected-runtime.js').exists())
        index = (ROOT / 'v2/index.php').read_text()
        self.assertNotIn('data-search3-selected-runtime', index)
        self.assertNotIn("v2_search3_enabled() ? 'initial' : 'all'", index)
        self.assertIn("v2_bundle_asset('js', null, 'all')", index)

    def test_results_top_bridge_is_retired_with_compact_native_state(self):
        self.assertFalse((ROOT / 'src/search3/behavior/results-top.js').exists())
        self.assertNotIn('behavior/results-top.js', json.dumps(self.source))
        form = (ROOT / 'src/search3/behavior/search-form.js').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        compiled = (ROOT / 'v2/search3-results-filters-v1.js').read_text()
        for event in (
            'v2:search-started', 'v2:search-error', 'v2:results-rendered',
            'v2:search-reset',
        ):
            self.assertIn(event, form)
        self.assertIn("setAttribute('aria-busy'", form)
        self.assertIn("data-search3-view=summary", (ROOT / 'src/search3/styles/entry-native-controls.css').read_text())
        self.assertIn("setAttribute('aria-expanded'", form)
        self.assertNotIn('search3-editing-search', form + results + compiled)
        self.assertNotIn('search3-editing-search', (ROOT / 'v2/results-renderer-v5.js').read_text())
        self.assertIn('&.search3-selected-open :is(#tourSearch,.results-tools,.results-layout){display:none!important}', results)
        self.assertIn(':has(#results>*)', results)
        self.assertNotIn('search3-results-active', compiled)
        self.assertNotIn('search3-has-results', compiled)

    def test_current_renderer_owns_clear_cards_and_truthful_states(self):
        renderer = (ROOT / 'v2/results-renderer-v5.js').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        for marker in (
            '<h3 class="hotel-title">',
            '<small>Итого за тур</small>',
            'Выбрать тур',
            "showSearchStatus('loading'",
            "showSearchStatus('error'",
            'aria-valuenow',
            'Уже найденные отели сохранены',
        ):
            self.assertIn(marker, renderer)
        self.assertNotIn('hotel-best-offer', renderer)
        self.assertIn('& .results-state{', results)
        self.assertIn('& .tour-row{display:grid', results)
        lifecycle = (ROOT / 'v2/search-lifecycle-v6.js').read_text()
        self.assertIn("renderer.render(list,{empty:!!terminal})", lifecycle)
        self.assertIn("loadResults(id,run,25,false)", lifecycle)
        self.assertIn("loadResults(id,run,100,true)", lifecycle)
        self.assertEqual(lifecycle.count("window.addEventListener('popstate'"), 1)
        self.assertIn("new CustomEvent('v2:search-history-pop'", lifecycle)
        search3_js = json.loads(subprocess.check_output([
            'php', '-r',
            'require "v2/bundle-manifest-v1.php"; echo json_encode(v2_bundle_files("js", "search3"));'
        ], cwd=ROOT, text=True))
        self.assertNotIn('search-progress-ux-v1.js', search3_js)

    def test_native_controls_and_isolation_remain(self):
        native = (ROOT / 'src/search3/styles/entry-native-controls.css').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        for marker in ('input:not([type=checkbox])', 'font-size:16px!important', 'min-height:44px!important'):
            self.assertIn(marker, native)
        self.assertNotIn('search3-direct-control', native)
        for marker in ('.results-layout', '.direct-tour', '[hidden]'):
            self.assertIn(marker, results)
        self.assertIn('&.search3-selected-open .v2-product-hero{display:none!important}', results)
        self.assertIn('& .v2-shell a{', results)
        self.assertIn('& .v2-shell :focus-visible{', results)
        self.assertNotIn('.at-global-header', results)

    def test_native_form_groups_and_shared_footer_keep_single_owners(self):
        index = (ROOT / 'v2/index.php').read_text()
        native = (ROOT / 'src/search3/styles/entry-native-controls.css').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        self.assertEqual(index.count('<fieldset class="search-group '), 4)
        for label in ('Направление', 'Даты вылета', 'Продолжительность', 'Туристы'):
            self.assertIn(label, index)
        for name in (
            'from', 'country', 'dateFrom', 'dateTo', 'daysFrom', 'daysTill',
            'count_people', 'child_count',
        ):
            # Nights have mutually exclusive native/legacy presentation branches.
            self.assertEqual(index.count(f'name="{name}"'), 2 if name in ('daysFrom', 'daysTill') else 1, name)
        self.assertIn('& .search-group{', native)
        self.assertIn('& :is(.main-fields,.search-preferences){grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;display:grid}', native)
        self.assertNotIn('@media(min-width:1200px){& .main-fields{', native)
        self.assertIn('& .search-preferences{grid-template-columns:minmax(150px,1.15fr) minmax(230px,1.7fr)', native)
        self.assertNotIn('Legacy source-contract marker', native)
        self.assertIn('@media(max-width:700px){grid-template-columns:minmax(0,1fr);', native)
        self.assertIn('&>.search-section-title:first-child{position:sticky;top:0;z-index:2;background:#fff}', native)
        self.assertIn('& .main-fields{grid-template-columns:1fr}', native)
        self.assertIn('& .child-ages{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:12px}', native)
        self.assertIn('& .child-age:last-child:nth-child(odd){grid-column:1/-1}', native)
        self.assertIn('& .search-submit{grid-column:1;width:100%;margin-left:0}', native)
        self.assertIn('@media(max-width:430px){& .search-group--route{grid-template-columns:1fr}', native)
        self.assertIn('@media(max-width:350px){& .search-group,& .search-preferences,& .child-ages{grid-template-columns:1fr}', native)
        self.assertIn('& .child-age{grid-column:auto!important}', native)
        self.assertNotIn('.ds2-site-footer', results)

    def test_native_nights_have_one_rendered_owner_and_legacy_stays_unchanged(self):
        for candidate in (True, False):
            rendered = subprocess.check_output([
                'php', '-r',
                'define("V2_SEARCH3_PRESENTATION", ' + ('true' if candidate else 'false') + ');'
                '$_SERVER["DOCUMENT_ROOT"]=""; require "v2/index.php";'
            ], cwd=ROOT, text=True)
            for name in ('daysFrom', 'daysTill'):
                self.assertEqual(rendered.count(f'name="{name}"'), 1)
                if candidate:
                    control = re.search(r'<select name="' + name + r'">(.*?)</select>', rendered).group(1)
                    self.assertEqual(re.findall(r'<option value="(\d+)"', control), [str(i) for i in range(1, 29)])
                else:
                    self.assertIn(f'<input type="number" min="1" max="28" name="{name}"', rendered)

    def test_optional_duplicate_layers_are_search3_only_exclusions(self):
        self.assertEqual(self.bundle.count("'site-footer-v1.css'"), 1)
        self.assertEqual(self.bundle.count("'design-system-v2.css'"), 1)
        self.assertEqual(self.bundle.count("'site-header-v2.css'"), 1)
        for name in (
            'ds2-search-intro-v1.css', 'ds2-search.css',
            'hotel-actions-v3.js', 'room-details-v3.js', 'hotel-autocomplete-v1.js',
            'search-filters-ux-v1.js',
            'mobile-results-filters-v1.js', 'ds2-results-filters.js',
        ):
            self.assertGreaterEqual(self.bundle.count("'" + name + "'"), 2, name)

    def test_one_local_hotel_filter_owns_loaded_card_filtering(self):
        parts = self.source['assets']['search3-results-filters-v1.js']
        owner = 'behavior/results/local-hotel-filter.js'
        self.assertEqual(parts.count(owner), 1)
        source = (ROOT / 'src/search3' / owner).read_text()
        self.assertIn("querySelectorAll('.hotel-card')", source)
        self.assertIn("querySelector('.hotel-title')", source)
        self.assertIn("window.addEventListener('v2:results-rendered',rendered)", source)
        self.assertIn("window.addEventListener('v2:search-reset',clear)", source)
        self.assertIn("Array.isArray(event.detail.items)", source)
        self.assertIn("numericCoverage(seas,.8)", source)
        self.assertIn("fieldNode.hidden=options.length<2", source)
        self.assertIn("facets.categories[index]===facets.category", source)
        self.assertIn("window.Search3LocalHotelFilter={apply,clear,project,reset,version:12}", source)
        self.assertIn("mealField.hidden=!available", source)
        self.assertIn("window.matchMedia('(min-width:1025px)')", source)
        self.assertIn("Number(t&&t.price||0)<=budget", source)
        self.assertIn("cardValues('rating')", source)
        self.assertIn("cardValues('seaDistance')", source)
        self.assertNotIn('V2SearchLifecycle', source)
        self.assertNotIn('fetch(', source)
        self.assertGreaterEqual(self.bundle.count("'mobile-results-filters-v1.js'"), 2)
        self.assertGreaterEqual(self.bundle.count("'ds2-results-filters.js'"), 2)

    def test_one_shortlist_owner_keeps_exact_local_offer_snapshots(self):
        parts = self.source['assets']['search3-results-filters-v1.js']
        owner = 'behavior/results/shortlist.js'
        self.assertEqual(parts.count(owner), 1)
        source = (ROOT / 'src/search3' / owner).read_text()
        for marker in (
            "limit=3", "source='tourvisor'", 'observedPrice',
            "window.localStorage.setItem", "window.localStorage.getItem",
            "window.addEventListener('search3:local-results-filtered'",
            "window.addEventListener('v2:tour-returned'",
            "window.Search3Shortlist={storageKey",
        ):
            self.assertIn(marker, source)
        for forbidden in ('fetch(', 'XMLHttpRequest', 'leadApi', 'phone', 'comment', 'consent'):
            self.assertNotIn(forbidden, source)

    def test_protected_core_files_and_hashes_remain_exact(self):
        protected = MANIFEST['protectedSha256']
        # Count executable closures, not filename mentions in the phase allowlist.
        # Every protected source must still be delivered exactly once on each route.
        closures = json.loads(subprocess.check_output([
            'php', '-r',
            'require "v2/bundle-manifest-v1.php"; echo json_encode(['
            'v2_bundle_files("js", "full"), v2_bundle_files("js", "search3")]);'
        ], cwd=ROOT))
        for name in (
            'analytics-v4.js', 'tour-controller-v4.js', 'flight-price-sync-v1.js',
            'lead-search-context.js', 'lead-form-guard-v1.js', 'runtime-v3.js',
        ):
            source = (ROOT / 'v2' / name).read_bytes()
            if name == 'tour-controller-v4.js':
                # Reviewed canonical hotel presentation only. Reverse every exact
