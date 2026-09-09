"""Verify Search3 activation without changing runtime, lead or analytics contracts."""
import hashlib
import importlib.util
import json
import re
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = json.loads((ROOT / 'docs/project/search3-production-import.json').read_text())
RESET_AUDIT = ROOT / 'docs/project/search3-half-size-reset.json'
RESET_ACTIVE = RESET_AUDIT.exists()


@unittest.skipIf(RESET_ACTIVE, 'superseded by the owner-authorized half-size reset contract')
class Search3ProductionPresentationTest(unittest.TestCase):
    @unittest.skipUnless(shutil.which('node'), 'Node required for summary event regression')
    def test_booking_summary_event_bursts(self):
        for name in ('search3-presentation-utils.cjs', 'search3-booking-summary.cjs', 'search3-booking-services.cjs', 'search3-lead-note-owner.cjs', 'search3-booking-navigation.cjs', 'search3-results-scheduler.cjs', 'search3-selected-flow-scheduler.cjs', 'search3-selected-handoff-ownership.cjs', 'search3-selected-return-owner.cjs', 'search3-entry-summary.cjs', 'search3-meal-owner.cjs', 'search3-mobile-toolbar-scheduler.cjs', 'search3-injected-styles.cjs', 'search3-progress-owner.cjs'):
            subprocess.run(['node', str(ROOT / 'tests' / name)], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for filter ownership regression')
    def test_filter_rail_ownership(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-filter-rail-ownership.cjs')], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for price input regression')
    def test_filter_rail_price_input(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-filter-rail-price-input.cjs')], check=True)

    @unittest.skipUnless(shutil.which('node'), 'Node required for mobile toolbar ownership regression')
    def test_mobile_toolbar_ownership(self):
        subprocess.run(['node', str(ROOT / 'tests' / 'search3-mobile-toolbar-ownership.cjs')], check=True)

    def test_cascade_compatibility_section_contract(self):
        subprocess.run([
            'python3',
            str(ROOT / 'scripts/build/search3_cascade_sections.py'),
            '--check',
        ], check=True)

    def test_cascade_has_no_empty_media_rules(self):
        contract = json.loads((ROOT / 'docs/project/search3-cascade-sections.json').read_text())
        for name in contract['sections']:
            with self.subTest(name=name):
                source = (ROOT / contract['source_root'] / name).read_text()
                # Strip complete CSS comments without crossing their closing delimiter.
                source = re.sub(r'/\*[^*]*\*+(?:[^/*][^*]*\*+)*/', '', source)
                self.assertNotRegex(source, r'(?m)^[ \t]*@media[^{};]+\{\s*\}')

    def test_cascade_module_eof_contract(self):
        contract = json.loads((ROOT / 'docs/project/search3-cascade-sections.json').read_text())
        for name in contract['sections']:
            with self.subTest(name=name):
                raw = (ROOT / contract['source_root'] / name).read_bytes()
                self.assertTrue(raw.endswith(b'\n'), name)
                self.assertFalse(raw.endswith(b'\n\n'), name)

    def test_card_readability_has_one_current_owner(self):
        retired = (ROOT / 'src/search3/styles/acceptance-guards.css').read_text()
        source = (ROOT / 'src/search3/styles/results-cards-v2.css').read_text()
        prefix = 'html body.search3-candidate.search3-results-active #results '
        shared = ':is(.search3-hotel-facts,.tour-fact)'
        self.assertNotIn('.search3-hotel-heading{', retired)
        self.assertNotIn('.hotel-best-offer>small.hotel-price-context{', retired)
        self.assertNotIn('#selectedTour:not(.search3-final-review)', retired)
        self.assertEqual(source.count('& ' + shared + ' small{'), 1)
        self.assertEqual(source.count('& ' + shared + ' b{'), 1)
        for selector in ('.search3-hotel-facts', '.tour-fact'):
            self.assertNotIn(prefix + selector + ' small{', source)
        # :is() contributes its most-specific argument: one class, exactly as
        # either replaced selector did. The trailing element is unchanged.
        self.assertEqual(shared.count('.'), 2)

    def test_shared_lead_guard_has_one_current_lifecycle_owner(self):
        retired = (ROOT / 'src/search3/behavior/lead-flow.js').read_text()
        source = (ROOT / 'src/search3/styles/lead-state.css').read_text()
        guard = (ROOT / 'v2/lead-form-guard-v1.js').read_text()
        race = (ROOT / 'v2/lead-ui-race-guard-v1.js').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')
        self.assertNotIn('search3-lead-status', source)
        for selector in ('.lead-success-panel', '.lead-success-handoff', '.lead-success-handoff__actions'):
            self.assertIn(selector, source)
        self.assertIn(':is(.lead-success-back,.lead-success-messenger){min-height:44px}', source)
        for event in ('v2:lead-started', 'v2:lead-success', 'v2:lead-error'):
            self.assertIn("window.addEventListener('" + event + "'", guard)
            self.assertIn("window.addEventListener('" + event + "'", race)
        for owner in ('syncLeadSubmitState', 'decorateLeadSuccess', 'decorateMessengerHandoff'):
            self.assertIn(owner, guard)

    def test_mobile_search_entry_uses_linked_presentation_owner(self):
        retired = (ROOT / 'src/search3/behavior/mobile-search-entry.js').read_text()
        behavior = (ROOT / 'src/search3/behavior/search-form/secondary-controls.js').read_text()
        compiled = (ROOT / 'v2/search3-results-filters-v1.js').read_text()
        retired_styles = (ROOT / 'src/search3/styles/result-cards.css').read_text()
        styles = (ROOT / 'src/search3/styles/entry-v1.css').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')
        self.assertIn("className='search3-mobile-search-entry'", behavior)
        self.assertIn("window.addEventListener('v2:results-rendered'", behavior)
        self.assertIn("mobileFilter.setAttribute('aria-expanded'", behavior)
        self.assertNotIn('search3-mobile-search-entry-style', behavior)
        self.assertNotIn('search3-mobile-search-entry-style', compiled)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired_styles, flags=re.S).strip(), '')
        self.assertIn('.search3-mobile-search-entry{display:none}', styles)
        self.assertIn('.search3-mobile-search-filter-button{', styles)
        self.assertIn('#tourSearch.search3-mobile-advanced-open', styles)

    def test_entry_presentation_has_one_current_runtime_owner(self):
        retired = (ROOT / 'src/search3/behavior/entry-v1.js').read_text()
        form = (ROOT / 'src/search3/behavior/search-form.js').read_text()
        owner = (ROOT / 'src/search3/behavior/search-form/entry-presentation.js').read_text()
        entry_asset = (ROOT / 'v2/search3-entry-v1.js').read_text()
        compiled = (ROOT / 'v2/search3-results-filters-v1.js').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')
        self.assertEqual(entry_asset, '')
        self.assertIn('@include behavior/search-form/entry-presentation.js', form)
        self.assertIn('installEntryPresentation(form,main,grid,region)', form)
        self.assertIn('window.Search3CandidateEntryV1', owner)
        self.assertEqual(compiled.count('Search3CandidateEntryV1'), 2)
        self.assertIn("legacyGrid.insertBefore(starsField", form)
        self.assertNotIn('search-params-filter-split', compiled)

    def test_booking_summary_has_no_geometry_only_resize_owner(self):
        source = (ROOT / 'src/search3/behavior/booking-summary.js').read_text()
        self.assertNotIn("addEventListener('resize',layoutSoon)", source)
        self.assertFalse((ROOT / 'src/search3/behavior/booking/layout.js').exists())
        self.assertNotIn('search3FinalLayout', source)

    def test_retired_hotel_donor_and_tour_header_have_current_owners(self):
        retired = (ROOT / 'src/search3/styles/hotel-results.css').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')
        packages = (ROOT / 'src/search3/styles/hotel-packages.css').read_text()
        context = (ROOT / 'src/search3/styles/results-context.css').read_text()
        base = (ROOT / 'src/search3/styles/base.css').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        card_styles = (ROOT / 'src/search3/styles/results-cards-v2.css').read_text()
        mobile = (ROOT / 'src/search3/styles/results-mobile-layout.css').read_text()
        cards = (ROOT / 'src/search3/behavior/results/cards.js').read_text()
        all_source = ''.join(
            re.sub(r'/\*.*?\*/', '', path.read_text(), flags=re.S)
            for path in (ROOT / 'src/search3').rglob('*')
            if path.is_file() and path.suffix in ('.css', '.js')
        )
        self.assertEqual(re.sub(r'/\*.*?\*/', '', context, flags=re.S).strip(), '')
        self.assertIn('#anytour-consultant-host,& .mobile-search-sticky{display:none!important}', base)
        self.assertIn('&:not(.search3-has-results) .results-layout{display:none!important}', results)
        self.assertIn('&.search3-has-results.search3-editing-search #tourSearch{display:block!important', results)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', packages, flags=re.S).strip(), '')
        self.assertIn('& .hotel-tours::before{display:none!important;content:none!important}', card_styles)
        self.assertIn(':is(.hotel-tours[hidden],.tour-row[hidden]){display:none!important}', card_styles)
        self.assertIn('.direct-tour{width:100%!important;min-height:36px!important', results)
        self.assertIn('grid-template-columns:repeat(3,minmax(0,1fr))!important', results)
        self.assertIn('& .tour-fact-badge b {display:inline-flex!important', results)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', mobile, flags=re.S).strip(), '')
        self.assertIn('& .search3-hotel-facts>span:nth-child(n+4){display:none!important}', card_styles)
        self.assertNotIn('search3-hotel-tours-open .search3-tour-filters', all_source)
        self.assertNotIn('.search3-hotel-highlights::-webkit-scrollbar', all_source)
        self.assertNotIn('search3-tour-list-head', all_source)
        self.assertNotIn('ensureTourListHead', cards)
        self.assertIn("var showLabel = 'Показать ' + count + ' ' + tourWord(count);", cards)
        self.assertIn('data-search3-show-label', cards)
        self.assertNotIn('search3-hotel-action__copy', cards)

    def test_retired_filter_and_maket7_owners_have_static_replacements(self):
        filters = (ROOT / 'src/search3/styles/filters.css').read_text()
        maket7 = (ROOT / 'src/search3/behavior/maket7-lock.js').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', filters, flags=re.S).strip(), '')
        self.assertEqual(re.sub(r'/\*.*?\*/', '', maket7, flags=re.S).strip(), '')

        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        mobile = (ROOT / 'src/search3/styles/mobile-results-toolbar.css').read_text()
        selected = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()
        results_top = (ROOT / 'src/search3/behavior/results-top.js').read_text()
        results_presentation = (ROOT / 'src/search3/behavior/results-presentation.js').read_text()
        desktop_filters = (ROOT / 'v2/ds2-results-filters.js').read_text()
        all_source = ''.join(
            path.read_text() for path in (ROOT / 'src/search3').rglob('*')
            if path.is_file()
        )

        for marker in ('search3-filter-section', 'input[type=radio]', 'search3-filter-edit-row'):
            self.assertNotIn(marker, results)
        for marker in ('data-ds2-price', 'data-ds2-meal-fieldset',
                       'data-ds2-stars-fieldset', 'data-ds2-rating-fieldset',
                       'data-ds2-sea-fieldset'):
            self.assertIn(marker, desktop_filters)
        for marker in (
            'position:static!important',
            'background:transparent!important',
            'backdrop-filter:none!important',
        ):
            self.assertIn(marker, mobile)
        self.assertIn('.selected-confidence{grid-column:1/3!important', selected)
        self.assertIn('.selected-confidence-steps{min-width:0!important}', selected)

        self.assertNotIn('search3-desktop-two-row', all_source)
        self.assertNotIn('search3-selected-confidence-grid-lock', all_source)
        self.assertNotIn('getBoundingClientRect', results_top)
        self.assertNotIn('style.setProperty', results_top)
        self.assertNotIn("addEventListener('resize'", results_top)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', results_top, flags=re.S).strip(), '')
        self.assertIn('new MutationObserver(scheduleResultsSync)', results_presentation)
        self.assertIn('function scheduleResultsSync()', results_presentation)

    def test_results_layout_guard_is_legacy_only(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        self.assertIn("'results-layout-guard-v1.css'", manifest)
        scoped = manifest.split('$excluded =', 1)[1]
        self.assertIn("'results-layout-guard-v1.css'", scoped)
        current = ''.join((ROOT / 'src/search3/styles' / name).read_text() for name in [
            'results-layout.css', 'results-cards-v2.css', 'hotel-card-convergence.css'
        ])
        for contract in ['.results-layout', '.hotel-card', '.hotel-photo', '.hotel-tours', '.tour-row']:
            self.assertIn(contract, current)

    def test_design_v1_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/design-v1.css').read_text()
        current = ''.join(path.read_text() for path in (ROOT / 'src/search3/styles').rglob('*.css'))
        self.assertIn("'design-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'design-v1.css'", scoped)
        for legacy_contract, current_contract in [
            ('.search-card', '#tourSearch'),
            ('.hotel-card', '.hotel-card'),
            ('.selected-tour', '#selectedTour'),
            ('.flight-variant', '.flight-variant'),
            ('.lead-form', '.lead-form'),
        ]:
            self.assertIn(legacy_contract, legacy)
            self.assertIn(current_contract, current)

    def test_app_css_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/app.css').read_text()
        current = ''.join(path.read_text() for path in (ROOT / 'src/search3/styles').rglob('*.css'))
        self.assertIn("'app.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'app.css'", scoped)
        for legacy_contract, current_contract in [
            ('.search-card', '#tourSearch'),
            ('.hotel-card', '.hotel-card'),
            ('.skeleton-grid', '.skeleton-grid'),
            ('.selected-tour', '#selectedTour'),
            ('.lead-form', '.lead-form'),
        ]:
            self.assertIn(legacy_contract, legacy)
            self.assertIn(current_contract, current)
        self.assertIn('&,& *{box-sizing:border-box!important}', current)
        for marker in (
            '.selected-head .eyebrow{min-height:0!important;padding:0!important;display:inline-flex;align-items:center;border-radius:999px',
            '.selected-picture img{display:block}',
            '.flight-choice>span {display:flex;align-items:baseline;justify-content:space-between;gap:16px',
            '.flight-choice small {color:#243f9e;font-size:14px;font-weight:900',
            '.lead-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}',
            '.lead-form .primary{width:100%;margin-top:3px;border:0;color:#fff}',
        ):
            self.assertIn(marker, current)

    def test_enhancements_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/enhancements.css').read_text()
        current = ''.join(path.read_text() for path in (ROOT / 'src/search3/styles').rglob('*.css'))
        self.assertIn("'enhancements.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'enhancements.css'", scoped)
        for legacy_contract, current_contract in [
            ('.hotel-card', '.hotel-card'),
            ('.hotel-inline-detail', '.hotel-inline-detail'),
            ('.flight-variant', '.flight-variant'),
            ('.lead-form', '.lead-form'),
        ]:
            self.assertIn(legacy_contract, legacy)
            self.assertIn(current_contract, current)

    def test_tour_design_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/tour-design-v1.css').read_text()
        current = ''.join(path.read_text() for path in (ROOT / 'src/search3/styles').rglob('*.css'))
        self.assertIn("'tour-design-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'tour-design-v1.css'", scoped)
        for legacy_contract, current_contract in [
            ('.selected-tour', '#selectedTour'),
            ('.flight-variant', '.flight-variant'),
            ('.room-detail-card', '.room-details-host'),
            ('.lead-form', '.lead-form'),
        ]:
            self.assertIn(legacy_contract, legacy)
            self.assertIn(current_contract, current)

    def test_room_details_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/room-details.css').read_text()
        current = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        self.assertIn("'room-details.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'room-details.css'", scoped)
        self.assertIn("@import url('/poisk-turov-test/v2/selected-tour-ux.css?v=1')", legacy)
        for contract in ['.room-detail-card', '.room-gallery-main', '.room-gallery-thumb', '.room-facts', '.room-comment']:
            self.assertIn(contract, legacy)
            self.assertIn(contract, current)

    def test_hotel_details_design_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/hotel-details-design.css').read_text()
        results = (ROOT / 'src/search3/styles/results-cards-v2.css').read_text()
        self.assertIn("'hotel-details-design.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'hotel-details-design.css'", scoped)
        for contract in [
            '.hotel-actions',
            '.hotel-info-toggle',
            '.hotel-inline-detail',
            '.hotel-gallery-thumb',
        ]:
            self.assertIn(contract, legacy)
        self.assertIn('.hotel-actions,.hotel-inline-detail', results)
        self.assertIn('display:none!important', results)

    def test_search_states_design_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/search-states-design.css').read_text()
        current = (ROOT / 'src/search3/styles/search-progress.css').read_text()
        self.assertIn("'search-states-design.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'search-states-design.css'", scoped)
        for contract in ['.skeleton-card', '.empty', '.tour-loading']:
            self.assertIn(contract, legacy)
            self.assertIn(contract, current)

    def test_mobile_results_filter_css_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/mobile-results-filters-v1.css').read_text()
        current = (ROOT / 'src/search3/styles/mobile-results-toolbar.css').read_text()
        self.assertIn("'mobile-results-filters-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'mobile-results-filters-v1.css'", scoped)
        for contract in ['.mrf-bar', '.mrf-sheet', '.mrf-panel', '.mrf-actions']:
            self.assertIn(contract, legacy)
            self.assertIn(contract, current)

    def test_current_price_calendar_is_the_shared_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/current-price-calendar-v1.css').read_text()
        self.assertIn("'current-price-calendar-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertNotIn("'current-price-calendar-v1.css'", scoped)
        self.assertNotIn("'current-price-calendar-v1.js'", scoped)
        for contract in ['.current-price-calendar__days', '.current-price-calendar__day', '.current-price-calendar__note']:
            self.assertIn(contract, legacy)

    def test_results_experience_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/results-experience-v1.css').read_text()
        current = ''.join((ROOT / 'src/search3/styles' / name).read_text() for name in [
            'results-layout.css', 'results-cards-v2.css', 'hotel-card-convergence.css'
        ])
        self.assertIn("'results-experience-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'results-experience-v1.css'", scoped)
        for contract in ['.hotel-card', '.hotel-photo', '.hotel-tours', '.tour-row', '.results-tools']:
            self.assertIn(contract, legacy)
            self.assertIn(contract, current)

    def test_anytour_brand_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/anytour-brand.css').read_text()
        current = ''.join((ROOT / 'src/search3/styles' / name).read_text() for name in [
            'base.css', 'entry-v1.css', 'results-layout.css', 'results-cards-v2.css',
            'tour-detail.css', 'lead-state.css'
        ])
        self.assertIn("'anytour-brand.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'anytour-brand.css'", scoped)
        self.assertNotIn('var(--anytour-', current)
        for legacy_contract, current_contract in [
            ('.search-card', '#tourSearch'),
            ('.results-tools', '.results-tools'),
            ('.hotel-card', '.hotel-card'),
            ('.selected-tour', '#selectedTour'),
            ('.lead-form', '.lead-form'),
        ]:
            self.assertIn(legacy_contract, legacy)
            self.assertIn(current_contract, current)

    def test_product_shell_is_a_legacy_only_presentation_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/product-shell-v1.css').read_text()
        base = (ROOT / 'src/search3/styles/base.css').read_text()
        current_header = (ROOT / 'v2/site-header-v2.php').read_text()
        legacy_route = (ROOT / 'v2/poisk-turov-old/index.php').read_text()
        self.assertIn("'product-shell-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'product-shell-v1.css'", scoped)
        self.assertIn('.at-site-header', legacy)
        self.assertIn('.v2-product-hero', legacy)
        self.assertIn('.primary-search-flow', legacy)
        self.assertIn('<header class="at-global-header">', current_header)
        self.assertIn('& .v2-product-hero{display:none!important}', base)
        self.assertIn('& .v2-shell{padding-bottom:52px!important}', base)
        self.assertIn('--at-ink:#151B24!important', base)
        self.assertIn('--at-soft:#F4F7FF!important', base)
        self.assertIn('font-family:Aeroport,Inter,-apple-system', base)
        self.assertIn("define('V2_SEARCH3_PRESENTATION', false)", legacy_route)

    def test_search_header_shared_shell_is_a_legacy_only_layer(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/search-header-shared-shell-v1.css').read_text()
        current_header = (ROOT / 'v2/site-header-v2.php').read_text()
        current_header_css = (ROOT / 'v2/site-header-v2.css').read_text()
        self.assertIn("'search-header-shared-shell-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'search-header-shared-shell-v1.css'", scoped)
        self.assertIn('.at-site-header', legacy)
        self.assertNotIn('.at-global-header', legacy)
        self.assertIn('<header class="at-global-header">', current_header)
        self.assertIn('.at-global-header', current_header_css)
        for current_contract in ['.v2-shell', '#tourSearch', '#results', '#selectedTour', '.search3-candidate']:
            self.assertNotIn(current_contract, legacy)

    def test_ds2_selected_convergence_is_retired_from_search3_only(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/ds2-selected-tour-convergence-v1.css').read_text()
        current = ''.join((ROOT / 'src/search3/styles' / name).read_text() for name in [
            'tour-detail.css', 'flights.css', 'review-layout.css',
            'selected-flow-v2.css', 'tour-detail-convergence.css'
        ])
        self.assertIn("'ds2-selected-tour-convergence-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'ds2-selected-tour-convergence-v1.css'", scoped)
        for contract in ['.selected-tour', '.selected-head', '.selected-picture', '.checkout-facts', '.checkout-flights', '.checkout-lead']:
            self.assertIn(contract, legacy)
        for contract in ['#selectedTour', '.selected-head', '.selected-picture', '.facts', '.tour-flights', '.lead-form']:
            self.assertIn(contract, current)

    def test_tablet_legacy_extras_are_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        self.assertIn("'ds2-search-tablet-filters-v1.css'", scoped)
        entry = (ROOT / 'src/search3/styles/entry-v1.css').read_text()
        self.assertIn('#tourSearch>details.extras[hidden]{display:none!important}', entry)

    def test_legacy_search_filter_skin_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/search-filters-ux-v1.css').read_text()
        primary = (ROOT / 'src/search3/behavior/search-form/primary-controls.js').read_text()
        entry = (ROOT / 'src/search3/styles/entry-v1.css').read_text()
        self.assertIn("'search-filters-ux-v1.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'search-filters-ux-v1.css'", scoped)
        for marker in ('.primary-search-flow', '.dates-picker', '.guests-picker', '.extras-secondary'):
            self.assertIn(marker, legacy)
        self.assertIn("select.classList.remove('ux-native-hidden')", primary)
        self.assertIn("childAges.classList.remove('guests-ages')", primary)
        self.assertIn('body.search3-candidate .ux-native-field-hidden{display:none!important}', entry)
        self.assertIn('.search3-tourists__ages{grid-column:1/-1!important;display:grid!important;gap:8px!important;margin-top:9px!important', entry)
        self.assertNotIn('.ux-native-hidden{', entry)

    def test_legacy_selected_tour_skin_has_compact_current_owners(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/selected-tour-ux.css').read_text()
        detail = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        flights = (ROOT / 'src/search3/styles/flights.css').read_text()
        flow = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()
        lead = (ROOT / 'src/search3/styles/lead-state.css').read_text()
        self.assertIn("'selected-tour-ux.css'", manifest.split('$excluded =', 1)[0])
        self.assertIn("'selected-tour-ux.css'", scoped)
        for marker in ('.selected-choice-summary', '.lead-success-panel', '.selected-lead-cta-note'):
            self.assertIn(marker, legacy)
        for marker in ('position:relative!important;max-width:var(--at-shell)', '.selected-head .eyebrow{min-height:0!important;padding:0!important;display:inline-flex;align-items:center', '.hotel-desc.is-collapsed', '.room-details-host.is-expanded'):
            self.assertIn(marker, detail)
        for marker in ('.tour-flights {position:relative;scroll-margin-top:18px}', '.flight-variant {position:relative!important', '.flight-route {position:relative;display:grid!important', '.flight-route>div:not(.flight-arrow) span {margin-top:2px}', '.flight-baggage {padding-top:8px}', '.flight-segment+.flight-segment', '.flight-arrow{transform:rotate(90deg)'):
            self.assertIn(marker, flights)
        self.assertIn('.selected-loading[data-v2-friendly-error="1"]', flow)
        self.assertIn('.tour-load-retry{min-height:44px', flow)
        self.assertIn('#selectedTour .lead-form{position:relative!important}', lead)
        self.assertIn('#selectedTour .lead-selection-summary{display:flex;flex-wrap:wrap', lead)
        self.assertIn('& .lead-form{display:grid;grid-template-columns:1fr}', lead)
        self.assertIn('& .lead-form input{min-height:46px;font-size:16px}', lead)
        self.assertIn('#selectedTour .lead-phone-hint{display:block', lead)

    def test_legacy_header_css_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        current_markup = (ROOT / 'v2/site-header-v2.php').read_text()
        current_styles = (ROOT / 'v2/site-header-v2.css').read_text()
        legacy_styles = (ROOT / 'v2/header-current-site.css').read_text()
        self.assertIn("'header-current-site.css'", scoped)
        self.assertNotIn("'site-header-v2.css'", scoped)
        self.assertNotIn("'design-system-v2.css'", scoped)
        self.assertIn('<header class="at-global-header">', current_markup)
        self.assertNotIn('<header class="at-site-header">', current_markup)
        self.assertIn('.at-global-header{', current_styles)
        self.assertNotIn('.at-global-header', legacy_styles)

    def test_legacy_header_runtime_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        current_markup = (ROOT / 'v2/site-header-v2.php').read_text()
        legacy = (ROOT / 'v2/header-current-site.js').read_text()
        search_form = (ROOT / 'src/search3/behavior/search-form.js').read_text()
        self.assertIn("'header-current-site.js'", scoped)
        self.assertIn("require_once __DIR__ . '/phone-value.php'", current_markup)
        self.assertIn('<details class="at-global-header__mobile">', current_markup)
        self.assertIn("['/poisk-turov/', 'Поиск туров']", current_markup)
        self.assertIn("hero.hidden=true", search_form)
        self.assertIn("document.querySelector('.at-mobile-menu')", legacy)
        self.assertIn("document.querySelector('.v2-product-hero')", legacy)
        self.assertNotIn('.at-mobile-menu', current_markup)

    def test_legacy_selected_return_runtime_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        controller = (ROOT / 'v2/tour-controller-v4.js').read_text()
        legacy = (ROOT / 'v2/selected-tour-return-v1.js').read_text()
        self.assertIn("'selected-tour-return-v1.js'", scoped)
        self.assertIn("closest('.back-results,.lead-success-back')", controller)
        self.assertIn("emit('tour-returned'", controller)
        self.assertIn("root.setAttribute('aria-hidden','true')", controller)
        self.assertIn("sourceButton", controller)
        self.assertIn("window.V2SelectedTourReturnV1", legacy)

    def test_legacy_flight_empty_runtime_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        current = (ROOT / 'src/search3/behavior/selected-flow-v2.js').read_text()
        fallback = (ROOT / 'src/search3/behavior/selected/flight-fallback.js').read_text()
        legacy = (ROOT / 'v2/flight-empty-recovery-v1.js').read_text()
        self.assertIn("'flight-empty-recovery-v1.js'", scoped)
        self.assertIn('ensureEmptyFlightRecovery(flights)', current)
        self.assertIn('function ensureEmptyFlightRecovery(flights)', fallback)
        self.assertIn('менеджер уточнит перелёт по заявке', fallback)
        self.assertIn("setAttribute(retry, 'data-tid', tourId)", fallback)
        self.assertIn('window.V2FlightEmptyRecoveryV1', legacy)

    def test_legacy_price_confidence_runtime_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        selected = (ROOT / 'src/search3/behavior/selected-flow-v2.js').read_text()
        booking = (ROOT / 'src/search3/behavior/booking-summary.js').read_text()
        review = (ROOT / 'src/search3/styles/review-layout.css').read_text()
        legacy = (ROOT / 'v2/price-confidence-v1.js').read_text()
        self.assertIn("'price-confidence-v1.js'", scoped)
        self.assertIn('if (value.pricePending) return number(value.basePrice)', selected)
        self.assertIn('if(d.pricePending)return number(d.basePrice)', booking)
        self.assertIn('Перед оплатой менеджер подтвердит итоговую стоимость и детали перелёта.', booking)
        self.assertNotIn('.selected-price-confidence', review)
        self.assertIn('window.V2PriceConfidenceV1', legacy)

    def test_legacy_filter_autorefresh_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        lifecycle = (ROOT / 'v2/search-lifecycle-v6.js').read_text()
        results = (ROOT / 'src/search3/behavior/results-presentation.js').read_text()
        legacy = (ROOT / 'v2/results-filter-autorefresh-v1.js').read_text()
        self.assertIn("'results-filter-autorefresh-v1.js'", scoped)
        self.assertIn("form.addEventListener('submit'", lifecycle)
        self.assertIn("window.addEventListener('v2:search-dirty', markStale)", results)
        self.assertIn("staleBanner.querySelector('.search-stale-update')", results)
        self.assertIn('window.V2ResultsFilterAutorefreshV1', legacy)
        self.assertNotIn('setTimeout(()=>{timer=0', results)

    def test_legacy_results_depth_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        lifecycle = (ROOT / 'v2/search-lifecycle-v6.js').read_text()
        legacy = (ROOT / 'v2/results-depth-v1.js').read_text()
        self.assertIn("'results-depth-v1.js'", scoped)
        self.assertEqual(lifecycle.count('loadResults(id,run,100)'), 1)
        self.assertIn("const items=await loadResults(id,run,100)", lifecycle)
        self.assertIn("emit('complete',{progress:100,items},id)", lifecycle)
        self.assertIn('await loadResults(id,run,25)', lifecycle)
        self.assertIn("window.addEventListener('v2:search-complete',expand)", legacy)
        self.assertIn("runtime.api('search_results',{searchId,limit:EXPANDED_LIMIT})", legacy)
        self.assertIn('window.V2ResultsDepthV1', legacy)

    def test_form_local_filters_are_consolidated_into_ds2_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        current = (ROOT / 'v2/ds2-results-filters.js').read_text()
        legacy = (ROOT / 'v2/results-local-filters-v1.js').read_text()
        self.assertIn("'results-local-filters-v1.js'", scoped)
        self.assertIn("formLocalNames=new Set(['stars','rating','price_from','price_till','region','subregion'])", current)
        self.assertIn('canUseFormLocal(filters)', current)
        self.assertIn("form.addEventListener('change',handleFormLocal,true)", current)
        self.assertIn("window.__DS2ResultsRailApplying=true", current)
        self.assertIn("CustomEvent('v2:results-local-filtered'", current)
        self.assertIn('window.V2ResultsLocalFiltersV1', legacy)
        self.assertIn('version:15', current)

    def test_legacy_selected_description_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        legacy = (ROOT / 'v2/selected-tour-description-v1.js').read_text()
        current = (ROOT / 'src/search3/behavior/selected-flow-v2.js').read_text()
        self.assertIn("'selected-tour-description-v1.js'", scoped)
        self.assertIn('function ensureDescriptionDisclosure()', current)
        self.assertIn('function ensureFactsDisclosure()', current)
        self.assertIn("setText(selected.querySelector('.selected-head .eyebrow'), 'ВАШ ТУР')", current)
        self.assertIn("addClass(selected, 'v2-approved-selected-tour')", current)
        detail = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        self.assertIn('& .selected-head .eyebrow{min-height:0!important', detail)
        self.assertIn('window.V2SelectedTourDescription=', legacy)
        self.assertNotIn('ensureApprovedStyles', current)
        self.assertNotIn('selected-tour-progress', current)
        self.assertNotIn('selected-choice-summary-item', current)

    def test_small_legacy_layout_guards_are_not_search3_owners(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        for name in ('search-shell-grid-v1.css', 'search-footer-rhythm-v1.css'):
            self.assertIn(repr(name), manifest)
            self.assertIn(repr(name), scoped)
        self.assertIn("'selected-tour-layout-guard-v1.css'", manifest)
        self.assertIn("'selected-tour-layout-guard-v1.css'", scoped)
        self.assertIn("'br3-control-consistency-v1.css'", manifest)
        self.assertIn("'br3-control-consistency-v1.css'", scoped)

        shell = (ROOT / 'src/search3/styles/base.css').read_text()
        entry = (ROOT / 'src/search3/styles/entry-native-controls.css').read_text()
        self.assertIn('.v2-shell{display:block!important;width:min(var(--at-shell)', shell)
        self.assertIn('padding-inline:0!important', shell)
        self.assertIn(':not(.search3-has-results) .ds2-site-footer{margin-top:24px!important}', entry)

        progress = (ROOT / 'src/search3/styles/search-progress.css').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        detail = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        self.assertIn('background:linear-gradient(135deg,#3458dd,#2544bf)!important', progress)
        self.assertIn('.search-progress-filters{border:1px solid #c8d4f1!important', progress)
        self.assertIn('.empty-edit-search{color:#3154cf!important', progress)
        self.assertIn('.direct-tour:hover{background:linear-gradient(135deg,#3c61e5,#294ac7)!important', results)
        self.assertIn('.secondary{border:1px solid #c8d4f1!important', shell)
        self.assertIn(':is(.search-submit,.direct-tour,.search-progress-retry', shell)
        self.assertIn('@media(max-width:820px){#selectedTour.selected-tour .selected-head.checkout-head{', detail)
        self.assertIn('width:min(100%,240px)!important', detail)

    def test_legacy_checkout_presentation_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        self.assertIn("'checkout-experience-v1.css'", manifest)
        self.assertIn("'checkout-experience-v1.css'", scoped)
        current = ''.join((ROOT / 'src/search3/styles' / name).read_text() for name in (
            'tour-detail.css', 'selected-flow-v2.css', 'review-layout.css',
            'booking-summary.css', 'final-sections.css', 'lead-state.css'
        ))
        for marker in ('.selected-head', '.selected-picture', '.facts', '.flight-variant',
                       '.search3-booking-summary', '.lead-form'):
            self.assertIn(marker, current)
        flights = (ROOT / 'src/search3/styles/flights.css').read_text()
        self.assertIn('position:relative!important', flights)
        self.assertIn('.flight-variant{margin:0!important}', flights)
        self.assertIn('min-height:380px!important', current)
        self.assertIn('.lead-message{color:#2743cb!important;font-weight:600!important}', current)

    def test_retired_tablet_drawer_and_redundant_phone_rules_have_current_owners(self):
        tablet = (ROOT / 'src/search3/styles/results-tablet-layout.css').read_text()
        toolbar = (ROOT / 'src/search3/styles/mobile-results-toolbar.css').read_text()
        mobile = (ROOT / 'src/search3/styles/results-mobile-layout.css').read_text()
        cards = (ROOT / 'src/search3/styles/results-cards-v2.css').read_text()
        guards = (ROOT / 'src/search3/styles/acceptance-guards.css').read_text()
        results = (ROOT / 'src/search3/styles/results-layout.css').read_text()
        detail = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        selected = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()

        self.assertEqual(re.sub(r'/\*.*?\*/', '', tablet, flags=re.S).strip(), '')
        for marker in (
            '@media(max-width:760px),(min-width:761px){',
            '& .mrf-sheet,& .mrf-backdrop{inset:0!important}',
            '& .mrf-panel,& .mrf-head,& .mrf-choice,& .mrf-actions,& .mrf-reset{background:#fff!important}',
            '& .mrf-section{border-top:1px solid #edf0f5!important}',
        ):
            self.assertIn(marker, toolbar)
        for marker in (
            '& .results-search-summary {margin:8px var(--search3-results-inline-gutter)!important',
            '& .hotel-title {font-size:16px!important}',
            '& .search3-show-tours{min-height:44px!important}',
            ':is(.results-search-summary,.results-tools--ds2,.search3-mobile-toolbar)',
        ):
            self.assertNotIn(marker, mobile)
        self.assertIn('.hotel-title{font-size:18px!important', cards)
        self.assertIn('.hotel-tours:not([hidden]) .direct-tour{width:118px!important;min-width:118px!important;min-height:44px!important', cards)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', guards, flags=re.S).strip(), '')
        self.assertIn('.mrf-sheet{display:block!important}', toolbar)
        self.assertNotIn('search3-filter-edit-row', results)
        self.assertNotIn('input[type=radio]', results)
        self.assertIn('font-size:14px!important', cards)
        self.assertNotIn('.search3-hotel-action__copy', cards)
        self.assertIn('.search3-show-tours{width:150px!important}', cards)
        self.assertIn('.search3-results-active.search3-selected-open .v2-shell>', detail)
        self.assertIn('.search3-selected-open .ds2-site-footer{display:none!important}', detail)
        self.assertIn('#selectedTour[hidden]{display:none!important}', selected)

    def test_lead_review_layer_is_retired_without_losing_lifecycle_truth(self):
        retired = (ROOT / 'src/search3/styles/lead-review.css').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')

        lead_state = (ROOT / 'src/search3/styles/lead-state.css').read_text()
        all_styles = ''.join(path.read_text() for path in (ROOT / 'src/search3/styles').rglob('*.css'))
        self.assertNotIn('search3-lead-status', all_styles)
        self.assertNotIn('data-search3-lead-state', all_styles)
        self.assertNotIn('search3LeadSpin', all_styles)
        self.assertRegex(lead_state, r"\.lead-consent input\{[^}]*flex:0 0 15px[^}]*width:15px!important[^}]*height:15px!important")
        self.assertRegex(lead_state, r"\.search3-lead-protection\{[^}]*display:flex[^}]*gap:7px")
        self.assertRegex(lead_state, r"\.search3-lead-protection span\{[^}]*display:grid[^}]*width:18px[^}]*height:18px")
        self.assertIn('&.search3-final-review .search3-lead-comment{display:none!important}', lead_state)
        self.assertIn('.search3-booking-summary__price-note{display:none!important}', lead_state)
        self.assertIn('.search3-final-review.search3-lead-entry .lead-selection-summary{display:none!important}', lead_state)
        self.assertNotIn('.search3-lead-shell.search3-lead-shell{grid-row:6!important}', lead_state)
        self.assertNotIn('.search3-final-sections.search3-final-sections{grid-row:7!important}', lead_state)
        self.assertIn(':is(.selected-head>:not(:first-child),.tour-flights .flight-baggage,.tour-flights .section-heading span){display:none!important}', lead_state)
        self.assertNotIn('min-height:280px!important;display:flex!important;align-items:center!important', all_styles)
        self.assertNotIn('& .lead-fields {gap:14px!important;grid-template-columns:1fr 1fr!important}', all_styles)
        self.assertIn('.lead-success-panel{display:grid', lead_state)
        self.assertIn('.lead-success-handoff__actions{display:flex', lead_state)

        selected = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()
        self.assertFalse((ROOT / 'src/search3/styles/injected/selected-tour-mobile.css').exists())
        self.assertNotIn('#selectedTour.search3-final-review.search3-lead-entry', selected.split('Narrow selected-tour layout', 1)[1])
        self.assertIn('#selectedTour:not(.search3-final-review)', selected)

    def test_retired_review_donor_and_selected_desktop_family_have_current_owners(self):
        retired = (ROOT / 'src/search3/styles/review.css').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', retired, flags=re.S).strip(), '')

        review = (ROOT / 'src/search3/styles/review-layout.css').read_text()
        for marker in (
            '.search3-summary-actions[hidden]{display:none!important}',
            '.search3-summary-submit:disabled{cursor:default;opacity:.58;filter:none}',
            '#selectedTour .lead-form button[type=submit]{background:#ff5a0a!important',
            '#selectedTour.search3-final-review .search3-booking-summary{border-color:#dfe5ef!important}',
        ):
            self.assertIn(marker, review)
        self.assertNotIn('search3-review-heading', review)
        self.assertNotIn('.selected-picture {height:154px!important', review)
        self.assertNotIn('#selectedTour.search3-lead-entry{display:flex!important', review)
        self.assertIn('.search3-lead-shell{display:contents!important}', review)
        self.assertIn('@media(max-width:999px)', review)
        self.assertIn('.search3-lead-shell>.search3-booking-summary{grid-column:1!important;grid-row:2!important;position:static!important}', review)

        selected = (ROOT / 'src/search3/styles/selected-tour.css').read_text()
        detail = (ROOT / 'src/search3/styles/tour-detail.css').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', selected, flags=re.S).strip(), '')
        self.assertIn('#selectedTour{position:relative!important;max-width:var(--at-shell)!important', detail)
        self.assertIn('& .selected-picture img{width:100%!important;height:100%!important;object-fit:cover!important}', detail)
        self.assertIn('& .facts{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important', detail)
        self.assertIn('#selectedTour:not(.search3-final-review){display:grid!important', detail)
        self.assertIn('grid-row:1!important;justify-self:start!important', detail)
        self.assertIn('height:176px!important;border:1px solid var(--at-line)!important;border-right:0!important', detail)
        self.assertIn('min-height:176px!important;border-left:0!important', detail)

        convergence = (ROOT / 'src/search3/styles/tour-detail-convergence.css').read_text()
        self.assertNotIn('grid-template-columns:repeat(5,minmax(0,1fr))!important', convergence)
        self.assertNotIn('min-width:188px!important;min-height:42px!important', convergence)
        self.assertIn('.flight-variant {display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important', convergence)
        self.assertIn('.search3-flight-continue {grid-column:1/3!important;width:100%!important;max-width:none!important}', convergence)

        selected_flow = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()
        self.assertIn('.search3-selected-mobile-bar:not([hidden]){position:fixed', selected_flow)
        self.assertIn('.search3-selected-mobile-bar button{min-height:48px', selected_flow)
        self.assertIn('& .selected-price{display:none!important}', selected_flow)

    def test_redundant_booking_chrome_is_retired_without_losing_flow_owners(self):
        stepper_js = (ROOT / 'src/search3/behavior/booking-stepper.js').read_text()
        stepper_css = (ROOT / 'src/search3/styles/booking-stepper.css').read_text()
        heading_js = (ROOT / 'src/search3/behavior/review-heading.js').read_text()
        for source in (stepper_js, stepper_css, heading_js):
            self.assertEqual(re.sub(r'/\*.*?\*/', '', source, flags=re.S).strip(), '')

        selected_flow = (ROOT / 'src/search3/styles/selected-flow-v2.css').read_text()
        all_source = ''.join(
            re.sub(r'/\*.*?\*/', '', path.read_text(), flags=re.S)
            for path in (ROOT / 'src/search3').rglob('*')
            if path.is_file() and path.suffix in ('.css', '.js')
        )
        self.assertIn(':is(.checkout-journey,.checkout-facts-heading,.selected-tour-progress)', selected_flow)
        self.assertNotIn('search3-booking-stepper', all_source)
        self.assertNotIn('search3-booking-step', all_source)
        self.assertNotIn('search3-review-heading', all_source)
        self.assertNotIn('Search3BookingStepper', all_source)

        continue_owner = (ROOT / 'src/search3/behavior/flight-continue.js').read_text()
        summary_owner = (ROOT / 'src/search3/behavior/summary-cta.js').read_text()
        lead_owner = (ROOT / 'src/search3/behavior/lead-flow.js').read_text()
        lead_guard = (ROOT / 'v2/lead-form-guard-v1.js').read_text()
        handoff = (ROOT / 'src/search3/behavior/selected-tour-handoff.js').read_text()
        results_owner = (ROOT / 'src/search3/behavior/results-presentation.js').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', continue_owner, flags=re.S).strip(), '')
        self.assertIn("dispatchEvent(new CustomEvent('v2:booking-review'", summary_owner)
        self.assertIn("closest('#selectedTour .search3-flight-continue button')", summary_owner)
        self.assertIn("dispatchEvent(new CustomEvent('search3:lead-entry'", summary_owner)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', lead_owner, flags=re.S).strip(), '')
        self.assertIn("window.addEventListener('v2:lead-started'", lead_guard)
        self.assertIn("window.addEventListener('v2:lead-success'", lead_guard)
        self.assertIn("window.addEventListener('v2:lead-error'", lead_guard)
        self.assertEqual(re.sub(r'/\*.*?\*/', '', handoff, flags=re.S).strip(), '')
        self.assertIn("selected.querySelector('.selected-head h2')", results_owner)

    def test_cascade_split_rejects_byte_drift(self):
        spec = importlib.util.spec_from_file_location(
            'search3_cascade_sections', ROOT / 'scripts/build/search3_cascade_sections.py')
        inspector = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(inspector)
        contract = json.loads(inspector.CONTRACT.read_text())
        # Mutate copies only; regression checks must never rewrite checked-in CSS.
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / contract['source_root']
            shutil.copytree(ROOT / contract['source_root'], source)
            contract_path = root / 'contract.json'
            contract_path.write_bytes(inspector.CONTRACT.read_bytes())
            with patch.object(inspector, 'ROOT', root), patch.object(inspector, 'CONTRACT', contract_path):
                _, raw, rows = inspector.inspect_sections()
                self.assertEqual(len(raw), contract['combined_bytes'])
                self.assertEqual(inspector.git_blob_sha(raw), contract['combined_git_blob_sha'])
                self.assertEqual([row['name'] for row in rows], contract['sections'])
                self.assertEqual(rows[-1]['endByte'], len(raw))
                path = source / contract['sections'][1]
                original = path.read_bytes()
                self.assertTrue(original.endswith(b'\n'))
                mutations = (
                    ('lost seam newline', original[:-1], 'byte count changed'),
                    ('CRLF conversion', original.replace(b'\n', b'\r\n'), 'byte count changed'),
                    # Retired sections may contain provenance comments only.
                    # Replacing the final newline changes bytes without changing length.
                    ('same-length source corruption', original[:-1] + b' ', 'blob changed'),
                )
                for name, changed, message in mutations:
                    with self.subTest(name=name):
                        self.assertNotEqual(changed, original)
                        path.write_bytes(changed)
                        try:
                            with self.assertRaisesRegex(ValueError, message):
                                inspector.inspect_sections()
                        finally:
                            path.write_bytes(original)
                # Restoration is exact; inspection remains read-only.
                self.assertEqual(inspector.inspect_sections()[1], raw)

    def test_reviewed_assets_and_protected_runtime(self):
        self.assertEqual(len(MANIFEST['assets']), 8)
        for name, values in MANIFEST['assets'].items():
            self.assertEqual(hashlib.sha256((ROOT / 'v2' / name).read_bytes()).hexdigest(), values['productionSha256'], name)
        for name, digest in MANIFEST['protectedSha256'].items():
            self.assertEqual(hashlib.sha256((ROOT / 'v2' / name).read_bytes()).hexdigest(), digest, name)

    def test_preview_controls_are_absent_from_production_assets(self):
        for name in MANIFEST['assets']:
            source = (ROOT / 'v2' / name).read_text()
            for marker in ('search3:preview-lead-state', '__search3CandidateNativeMatchMedia', '?lead=disabled', 'PREVIEW_LEAD_DISABLED'):
                self.assertNotIn(marker, source, name)

    def test_duplicate_supplier_party_card_is_retired(self):
        source = (ROOT / 'v2' / 'search3-results-filters-v1.js').read_text()
        controller = (ROOT / 'v2' / 'tour-controller-v4.js').read_text()
        self.assertNotIn('Состав размещения у туроператора', source)
        self.assertNotIn('search3-final-sections', source)
        self.assertNotIn('Состав поездки из поиска', source)
        for marker in ('placement', 'fuelCharge', 'baggage'):
            self.assertIn(marker, controller)

    def test_shared_footer_has_no_search3_replacement(self):
        # Search3 renders the same server footer as the rest of the site.
        # Its retired client replacement and private CSS must not be shipped.
        for name in MANIFEST['assets']:
            self.assertFalse('search3-footer-' in (ROOT / 'v2' / name).read_text(), name)

    @unittest.skipUnless(shutil.which('php'), 'PHP rendering requires the existing CI runtime')
    def test_canonical_and_compatibility_rendering(self):
        def render(host, entry, enabled=None):
            code = "define('METRIKA_COUNTER_ID',123456);"
            code += "$_SERVER['HTTP_HOST']=" + json.dumps(host) + ";"
            code += "$_SERVER['SCRIPT_NAME']='/poisk-turov/index.php';"
            code += "$_SERVER['REQUEST_URI']='/poisk-turov/';"
            if enabled is not None:
                code += "define('V2_SEARCH3_PRESENTATION'," + ('true' if enabled else 'false') + ");"
            code += "require " + json.dumps(str(ROOT / 'v2' / entry)) + ";"
            return subprocess.check_output(['php', '-r', code], text=True)

        canonical = render('anytoour.ru', 'poisk-turov/index.php')
        self.assertIn('<body class="search3-candidate">', canonical)
        self.assertNotIn('class="results-view-switch"', canonical)
        self.assertNotIn('data-results-view=', canonical)
        self.assertEqual(canonical.count('data-site-footer="shared"'), 1)
        self.assertIn('class="ds2-site-footer__logo"', canonical)
        for kind, suffix in [('css', 'style'), ('js', 'script')]:
            positions = []
            for name in ['search3-results-filters-v1', 'search3-entry-v1', 'search3-results-cards-v2', 'search3-selected-flow-v2']:
                marker = 'id="' + name + '-' + suffix + '"'
                self.assertEqual(canonical.count(marker), 1)
                self.assertIn('/' + name + '.' + kind + '?v=', canonical)
                positions.append(canonical.index(marker))
            self.assertEqual(positions, sorted(positions))
        for marker in ('leadApi:"/lead-adapter-v2.php"', 'api:"/api-v2.php"', 'metrikaCounter:123456', 'href="https://anytoour.ru/poisk-turov/"'):
            self.assertIn(marker, canonical)
        self.assertNotIn('?lead=disabled', canonical)
        legacy = render('anytoour.ru', 'poisk-turov-old/index.php')
        self.assertNotIn('id="search3-entry-v1-style"', legacy)
        self.assertIn('class="results-view-switch"', legacy)
        self.assertEqual(legacy.count('data-results-view='), 2)
        self.assertIn('content="noindex,follow', legacy)
        self.assertIn('href="https://anytoour.ru/poisk-turov/"', legacy)
        self.assertIn('leadApi:"/lead-adapter-v2.php"', legacy)
        for html in [render('anytour.online', 'poisk-turov/index.php'), render('anytoour.ru', 'index.php'), render('anytoour.ru', 'poisk-turov/index.php', False)]:
            self.assertNotIn('<body class="search3-candidate">', html)
            self.assertNotIn('id="search3-entry-v1-style"', html)
            self.assertIn('metrikaCounter:123456', html)


@unittest.skipUnless(RESET_ACTIVE, 'half-size reset is not active')
class Search3HalfSizeResetTest(unittest.TestCase):
    def setUp(self):
        self.source = json.loads((ROOT / 'src/search3/manifest.json').read_text())
        self.bundle = (ROOT / 'v2/bundle-manifest-v1.php').read_text()

    def test_eight_public_paths_and_half_size_budget(self):
        expected = {
            'search3-results-filters-v1.js', 'search3-results-filters-v1.css',
            'search3-entry-v1.css', 'search3-entry-v1.js',
            'search3-results-cards-v2.css', 'search3-results-cards-v2.js',
            'search3-selected-flow-v2.css', 'search3-selected-flow-v2.js',
        }
        self.assertEqual(set(self.source['assets']), expected)
        total = sum((ROOT / 'v2' / name).stat().st_size for name in expected)
        self.assertLessEqual(total, 89174, 'eight assets must remain at least two times smaller')

    def test_reset_css_allowlist_and_native_selected_bound(self):
        assets = self.source['assets']
        self.assertEqual(assets['search3-results-filters-v1.css'], ['styles/results-layout.css'])
        self.assertEqual(assets['search3-entry-v1.css'], ['styles/entry-native-controls.css'])
        self.assertEqual(assets['search3-results-cards-v2.css'], ['styles/result-cards.css'])
        self.assertEqual(assets['search3-selected-flow-v2.css'], ['styles/selected-tour.css'])
        self.assertEqual(assets['search3-selected-flow-v2.js'], [])
        self.assertFalse((ROOT / 'src/search3/styles/base.css').exists())
        self.assertFalse((ROOT / 'src/search3/behavior/selected-flow-v2.js').exists())
        self.assertFalse((ROOT / 'src/search3/behavior/selected/flight-fallback.js').exists())
        self.assertLessEqual(
            (ROOT / 'v2/search3-results-filters-v1.css').stat().st_size,
            17000,
            'owner-approved DS2 results and selected-tour restoration use one CSS owner (16408 B measured)',
        )
        self.assertLessEqual((ROOT / 'v2/search3-results-cards-v2.css').stat().st_size, 1)
        self.assertEqual(
            (ROOT / 'v2/search3-selected-flow-v2.css').read_text(),
            '.selected-picture img{max-width:100%;height:auto}\n',
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
        self.assertIn("classList.add('search3-editing-search')", form)
        self.assertIn('&.search3-selected-open :is(.results-tools,.results-layout){display:none!important}', results)
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
        for marker in ('.results-layout', '.direct-tour', '[hidden]', '.v2-product-hero'):
            self.assertIn(marker, results)
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
            self.assertEqual(index.count(f'name="{name}"'), 1, name)
        self.assertIn('& .search-group{', native)
        self.assertIn('@media(max-width:430px){& .search-group{grid-template-columns:1fr}', native)
        self.assertNotIn('.ds2-site-footer', results)

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
        self.assertIn("values.every(value=>value>0)", source)
        self.assertIn("categoryField.hidden=!available", source)
        self.assertIn("categories[index]===category", source)
        self.assertIn("window.Search3LocalHotelFilter={apply,clear,project,version:4}", source)
        self.assertIn("mealField.hidden=!available", source)
        self.assertNotIn('V2SearchLifecycle', source)
        self.assertNotIn('fetch(', source)
        self.assertGreaterEqual(self.bundle.count("'mobile-results-filters-v1.js'"), 2)
        self.assertGreaterEqual(self.bundle.count("'ds2-results-filters.js'"), 2)

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
                # Reviewed keyboard-entry fix only; reversing the exact insertion
                # must recover all existing business and transport bytes below.
                focus_entry = b"if(root.focus)root.focus({preventScroll:true});root.scrollIntoView({behavior:'smooth',block:'start'});"
                self.assertEqual(source.count(focus_entry), 1, 'one selected-tour entry focus owner')
                source = source.replace(focus_entry, b"root.scrollIntoView({behavior:'smooth',block:'start'});", 1)
                # Owner-authorized display-only meal label and hotel-description
                # entity decoding: reversing these exact fragments must recover
                # the entire protected controller. Decoded text is still escaped.
                # Lead mapping, arithmetic, selection and transport stay hash-locked.
                entity_decoder = ("const descriptionEntities={amp:'&',lt:'<',gt:'>',quot:'\"',apos:\"'\",nbsp:' ',sup2:'²'};\n"
                                  "function decodeEntities(v){return String(v||'').replace(/&(#(?:x[0-9a-f]+|[0-9]+)|amp|lt|gt|quot|apos|nbsp|sup2);/gi,(match,entity)=>{if(entity[0]!=='#')return descriptionEntities[entity.toLowerCase()];const hex=entity[1].toLowerCase()==='x',point=Number.parseInt(entity.slice(hex?2:1),hex?16:10);return Number.isInteger(point)&&point>0&&point<=1114111&&!(point>=55296&&point<=57343)?String.fromCodePoint(point):match;});}\n").encode()
                current_clean = b"function clean(v){return decodeEntities(String(v||'').replace(/<[^>]*>/g,' ')).replace(/\\s+/g,' ').trim();}"
                original_clean = b"function clean(v){return String(v||'').replace(/<[^>]*>/g,' ').replace(/\\s+/g,' ').trim();}"
                self.assertEqual(source.count(entity_decoder), 1, 'one reviewed description entity decoder')
                self.assertEqual(source.count(current_clean), 1, 'one reviewed description clean path')
                source = source.replace(entity_decoder, b'', 1).replace(current_clean, original_clean, 1)
                display = b"esc((window.V2Results&&typeof window.V2Results.mealLabel==='function'?window.V2Results.mealLabel(t):mealName(t))||'\xe2\x80\x94')"
                original = b"esc(mealName(t)||'\xe2\x80\x94')"
                self.assertEqual(source.count(display), 1, 'one reviewed meal display expression')
                source = source.replace(display, original, 1)
            digest = hashlib.sha256(source).hexdigest()
            self.assertEqual(digest, protected[name], name)
            for closure in closures:
                self.assertEqual(closure.count(name), 1, name)

    @unittest.skipUnless(shutil.which('node'), 'Node required for retained behavior contracts')
    def test_retained_business_and_runtime_behavior(self):
        # The reset retires CSS-owner assertions, not booking, lifecycle, price,
        # filter, handoff or lead behavior. Keep those contracts executable.
        for name in (
            'search3-presentation-utils.cjs', 'search3-booking-summary.cjs',
            'search3-booking-services.cjs', 'search3-lead-note-owner.cjs',
            'search3-booking-navigation.cjs',
            'search3-selected-flow-scheduler.cjs',
            'search3-selected-return-owner.cjs', 'search3-entry-summary.cjs',
            'search3-meal-owner.cjs',
        ):
            subprocess.run(['node', str(ROOT / 'tests' / name)], check=True)

    def test_search_progress_presentation_is_retired(self):
        self.assertFalse((ROOT / 'src/search3/behavior/search-progress.js').exists())
        self.assertNotIn('behavior/search-progress.js', json.dumps(self.source))


if __name__ == '__main__':
    unittest.main(verbosity=2)
