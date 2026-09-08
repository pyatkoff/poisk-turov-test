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


class Search3ProductionPresentationTest(unittest.TestCase):
    @unittest.skipUnless(shutil.which('node'), 'Node required for summary event regression')
    def test_booking_summary_event_bursts(self):
        for name in ('search3-presentation-utils.cjs', 'search3-booking-summary.cjs', 'search3-booking-services.cjs', 'search3-lead-note-owner.cjs', 'search3-booking-navigation.cjs', 'search3-results-scheduler.cjs', 'search3-selected-flow-scheduler.cjs', 'search3-selected-handoff-ownership.cjs', 'search3-entry-summary.cjs', 'search3-meal-owner.cjs', 'search3-mobile-toolbar-scheduler.cjs', 'search3-injected-styles.cjs', 'search3-progress-owner.cjs'):
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

    def test_mobile_lead_lifecycle_has_one_current_owner(self):
        source = (ROOT / 'src/search3/styles/lead-state.css').read_text()
        self.assertEqual(source.count('.search3-lead-status {display:grid!important;grid-template-columns:1fr!important'), 1)
        self.assertEqual(source.count(':is(.search3-messenger-actions,.search3-error-actions) {display:grid!important'), 1)
        self.assertEqual(source.count('.search3-stay-site {width:100%!important'), 1)

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
        layout = (ROOT / 'src/search3/behavior/booking/layout.js').read_text()
        self.assertNotIn("addEventListener('resize',layoutSoon)", source)
        self.assertNotIn('getBoundingClientRect', layout)
        self.assertNotIn('offsetWidth', layout)

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

    def test_tablet_legacy_extras_are_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        self.assertIn("'ds2-search-tablet-filters-v1.css'", scoped)
        entry = (ROOT / 'src/search3/styles/entry-v1.css').read_text()
        self.assertIn('#tourSearch>details.extras[hidden]{display:none!important}', entry)

    def test_legacy_header_css_is_not_a_search3_owner(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        current_markup = (ROOT / 'v2/site-header-v2.php').read_text()
        current_styles = (ROOT / 'v2/site-header-v2.css').read_text()
        legacy_styles = (ROOT / 'v2/header-current-site.css').read_text()
        self.assertIn("'header-current-site.css'", scoped)
        self.assertIn('<header class="at-global-header">', current_markup)
        self.assertNotIn('<header class="at-site-header">', current_markup)
        self.assertIn('.at-global-header{', current_styles)
        self.assertNotIn('.at-global-header', legacy_styles)

    def test_small_legacy_layout_guards_are_not_search3_owners(self):
        manifest = (ROOT / 'v2/bundle-manifest-v1.php').read_text()
        scoped = manifest.split('$excluded =', 1)[1]
        for name in ('search-shell-grid-v1.css', 'search-footer-rhythm-v1.css'):
            self.assertIn(repr(name), manifest)
            self.assertIn(repr(name), scoped)
        self.assertIn("'selected-tour-layout-guard-v1.css'", manifest)
        self.assertNotIn("'selected-tour-layout-guard-v1.css'", scoped)

        shell = (ROOT / 'src/search3/styles/base.css').read_text()
        entry = (ROOT / 'src/search3/styles/entry-native-controls.css').read_text()
        self.assertIn('.v2-shell{display:block!important;width:min(var(--at-shell)', shell)
        self.assertIn('padding-inline:0!important', shell)
        self.assertIn(':not(.search3-has-results) .ds2-site-footer{margin-top:24px!important}', entry)

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
        self.assertNotRegex(lead_state, r"content\s*:\s*['\"]✓['\"]")
        self.assertRegex(
            lead_state,
            r"\.search3-lead-status ol li:before\s*\{[^}]*content\s*:\s*['\"]{2}[^}]*background\s*:\s*#fff[^}]*color\s*:\s*transparent",
        )
        self.assertRegex(
            lead_state,
            r"data-search3-lead-state\s*=\s*['\"]sending['\"][^}]*animation\s*:\s*search3LeadSpin",
        )
        self.assertEqual(all_styles.count('@keyframes search3LeadSpin'), 1)
        self.assertEqual(all_styles.count('animation:search3LeadSpin'), 1)
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
        self.assertEqual(lead_state.count('.lead-form[data-search3-lead-state]~.search3-booking-summary'), 1)

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
        self.assertIn('#selectedTour{max-width:var(--at-shell)!important', detail)
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
        handoff = (ROOT / 'src/search3/behavior/selected-tour-handoff.js').read_text()
        results_owner = (ROOT / 'src/search3/behavior/results-presentation.js').read_text()
        self.assertEqual(re.sub(r'/\*.*?\*/', '', continue_owner, flags=re.S).strip(), '')
        self.assertIn("dispatchEvent(new CustomEvent('v2:booking-review'", summary_owner)
        self.assertIn("closest('#selectedTour .search3-flight-continue button')", summary_owner)
        self.assertIn("dispatchEvent(new CustomEvent('search3:lead-entry'", summary_owner)
        self.assertIn("window.addEventListener('v2:lead-started'", lead_owner)
        self.assertIn("window.addEventListener('v2:lead-success'", lead_owner)
        self.assertIn("window.addEventListener('v2:lead-error'", lead_owner)
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

    def test_supplier_party_is_not_mislabeled_as_search_input(self):
        source = (ROOT / 'v2' / 'search3-results-filters-v1.js').read_text()
        self.assertIn('Состав размещения у туроператора', source)
        self.assertIn('Для выбранного варианта', source)
        self.assertNotIn('Состав поездки из поиска', source)

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


if __name__ == '__main__':
    unittest.main(verbosity=2)
