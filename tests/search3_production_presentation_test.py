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
            '.selected-picture img{max-width:100%}\n',
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
            # Nights have mutually exclusive native/legacy presentation branches.
            self.assertEqual(index.count(f'name="{name}"'), 2 if name in ('daysFrom', 'daysTill') else 1, name)
        self.assertIn('& .search-group{', native)
        self.assertIn('& :is(.main-fields,.search-preferences){grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;display:grid}', native)
        self.assertNotIn('@media(min-width:1200px){& .main-fields{', native)
        self.assertIn('@media(min-width:1200px){& .search-preferences{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}', native)
        self.assertIn('@media(max-width:700px){grid-template-columns:minmax(0,1fr);& .main-fields{grid-template-columns:1fr}& .search-submit{grid-column:1;width:100%;margin-left:0}', native)
        self.assertIn('@media(max-width:430px){& .search-group--route{grid-template-columns:1fr}', native)
        self.assertIn('@media(max-width:350px){& .search-group{grid-template-columns:1fr}', native)
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
        self.assertIn("list.every(value=>value>0)", source)
        self.assertIn("fieldNode.hidden=options.length<2", source)
        self.assertIn("facets.categories[index]===facets.category", source)
        self.assertIn("window.Search3LocalHotelFilter={apply,clear,project,reset,version:9}", source)
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
                # Reviewed offer handoff and in-memory editor draft only.
                self.assertEqual(source.count("window.addEventListener('v2:search-reset',()=>{leadDraft=null;tourGeneration++;".encode()), 1)
                source = source.replace("window.addEventListener('v2:search-reset',()=>{leadDraft=null;tourGeneration++;".encode(), "window.addEventListener('v2:search-reset',()=>{tourGeneration++;".encode(), 1)
                self.assertEqual(source.count("if(e.target.closest&&e.target.closest('.other-hotel-offers')){e.preventDefault();e.stopPropagation();const renderer=window.V2Results,target=renderer&&renderer.revealOfferAlternatives&&renderer.revealOfferAlternatives(currentTour&&currentTour.id);rememberSource(target);returnToResults(root);return;}const retry=e.target.closest&&e.target.closest('.retry-tour');".encode()), 1)
                source = source.replace("if(e.target.closest&&e.target.closest('.other-hotel-offers')){e.preventDefault();e.stopPropagation();const renderer=window.V2Results,target=renderer&&renderer.revealOfferAlternatives&&renderer.revealOfferAlternatives(currentTour&&currentTour.id);rememberSource(target);returnToResults(root);return;}const retry=e.target.closest&&e.target.closest('.retry-tour');".encode(), "const retry=e.target.closest&&e.target.closest('.retry-tour');".encode(), 1)
                self.assertEqual(source.count("root.innerHTML=tourHtml(t,tid);restoreLeadDraft(root);emit('tour-selected'".encode()), 1)
                source = source.replace("root.innerHTML=tourHtml(t,tid);restoreLeadDraft(root);emit('tour-selected'".encode(), "root.innerHTML=tourHtml(t,tid);emit('tour-selected'".encode(), 1)
                self.assertEqual(source.count('busyTourId=String(tid);keepLeadDraft(root);currentTour=null;'.encode()), 1)
                source = source.replace('busyTourId=String(tid);keepLeadDraft(root);currentTour=null;'.encode(), 'busyTourId=String(tid);currentTour=null;'.encode(), 1)
                self.assertEqual(source.count('+alternativesHtml(tid)+descriptionHtml(desc)+\'<div class="tour-flights">'.encode()), 1)
                source = source.replace('+alternativesHtml(tid)+descriptionHtml(desc)+\'<div class="tour-flights">'.encode(), '+descriptionHtml(desc)+\'<div class="tour-flights">'.encode(), 1)
                self.assertEqual(source.count('let leadDraft=null;\nfunction keepLeadDraft(root){if(!currentTour||!document.body.classList.contains(\'search3-candidate\'))return;const form=root.querySelector(\'.lead-form\');if(!form)return;if(form.dataset.sent===\'1\'){leadDraft=null;return;}leadDraft={};for(const name of[\'name\',\'phone\',\'comment\']){const field=form.querySelector(\'[name="\'+name+\'"]\');if(field)leadDraft[name]=field.value;}}\nfunction restoreLeadDraft(root){if(!leadDraft||!document.body.classList.contains(\'search3-candidate\'))return;for(const name of[\'name\',\'phone\',\'comment\']){const field=root.querySelector(\'.lead-form [name="\'+name+\'"]\');if(field&&typeof leadDraft[name]===\'string\')field.value=leadDraft[name];}}\nfunction alternativesHtml(tid){if(!document.body.classList.contains(\'search3-candidate\'))return\'\';const renderer=window.V2Results,info=renderer&&renderer.offerAlternatives&&renderer.offerAlternatives(tid);return info&&info.count>1?\'<button type="button" class="secondary other-hotel-offers">Другие варианты этого отеля (\'+(info.count-1)+\')</button>\':\'\';}\nfunction tourHtml(t,tid){'.encode()), 1)
                source = source.replace('let leadDraft=null;\nfunction keepLeadDraft(root){if(!currentTour||!document.body.classList.contains(\'search3-candidate\'))return;const form=root.querySelector(\'.lead-form\');if(!form)return;if(form.dataset.sent===\'1\'){leadDraft=null;return;}leadDraft={};for(const name of[\'name\',\'phone\',\'comment\']){const field=form.querySelector(\'[name="\'+name+\'"]\');if(field)leadDraft[name]=field.value;}}\nfunction restoreLeadDraft(root){if(!leadDraft||!document.body.classList.contains(\'search3-candidate\'))return;for(const name of[\'name\',\'phone\',\'comment\']){const field=root.querySelector(\'.lead-form [name="\'+name+\'"]\');if(field&&typeof leadDraft[name]===\'string\')field.value=leadDraft[name];}}\nfunction alternativesHtml(tid){if(!document.body.classList.contains(\'search3-candidate\'))return\'\';const renderer=window.V2Results,info=renderer&&renderer.offerAlternatives&&renderer.offerAlternatives(tid);return info&&info.count>1?\'<button type="button" class="secondary other-hotel-offers">Другие варианты этого отеля (\'+(info.count-1)+\')</button>\':\'\';}\nfunction tourHtml(t,tid){'.encode(), 'function tourHtml(t,tid){'.encode(), 1)
                # Search3-only native disclosure changes presentation, with the
                # original legacy markup and all protected bytes recovered below.
                description_helper = 'function descriptionHtml(desc){if(!desc)return\'\';const content=\'<div class="hotel-desc">\'+esc(desc)+\'</div>\';return document.body.classList.contains(\'search3-candidate\')&&desc.length>280?\'<details class="selected-description"><summary>Об отеле</summary>\'+content+\'</details>\':content;}\n'.encode()
                self.assertEqual(source.count(description_helper), 1)
                self.assertEqual(source.count(b"+descriptionHtml(desc)+"), 1)
                source = source.replace(description_helper, b'', 1).replace(
                    b"+descriptionHtml(desc)+", '+(desc?\'<div class="hotel-desc">\'+esc(desc)+\'</div>\':\'\')+'.encode(), 1)
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
                # Reviewed selected-tour date display only. The supplier ISO value
                # remains unchanged in state and the lead payload; reversing these
                # two exact fragments recovers the protected controller.
                date_display = b",date=window.V2Results&&typeof window.V2Results.formatTourDate==='function'?window.V2Results.formatTourDate(t.date):t.date;return"
                date_original = b";return"
                date_value = b"esc(date||'\xe2\x80\x94')"
                raw_date_value = b"esc(t.date||'\xe2\x80\x94')"
                self.assertEqual(source.count(date_display), 1, 'one canonical selected date formatter call')
                self.assertEqual(source.count(date_value), 1, 'one formatted selected date value')
                source = source.replace(date_display, date_original, 1).replace(date_value, raw_date_value, 1)
                # Reviewed flight-fee display correction only. It distinguishes
                # missing, explicit zero and positive values without changing
                # the raw fee, selected total, arithmetic or lead payload.
                fuel_helper = b"function flightFuelText(v){if(!v||!Object.prototype.hasOwnProperty.call(v,'fuelCharge')||v.fuelCharge===null||v.fuelCharge==='')return'\xd1\x83\xd1\x82\xd0\xbe\xd1\x87\xd0\xbd\xd1\x8f\xd0\xb5\xd1\x82\xd1\x81\xd1\x8f';const fuel=v.fuelCharge,raw=fuel&&typeof fuel==='object'&&fuel.value!==undefined?fuel.value:fuel,n=Number(raw);if(!Number.isFinite(n)||n<0)return'\xd1\x83\xd1\x82\xd0\xbe\xd1\x87\xd0\xbd\xd1\x8f\xd0\xb5\xd1\x82\xd1\x81\xd1\x8f';return n?money(n)+' \xe2\x82\xbd':'\xd0\xb1\xd0\xb5\xd0\xb7 \xd0\xb4\xd0\xbe\xd0\xbf\xd0\xbb\xd0\xb0\xd1\x82\xd1\x8b';}\n"
                current_variant = b"function variantHtml(v,i){const p=v&&v.price||{},price=p&&p.value!==undefined?p.value:p||0,fuelText=flightFuelText(v);return '<div class=\"flight-variant'+(i===selectedFlightIndex?' is-selected':'')+'\" data-flight-index=\"'+i+'\"><label class=\"flight-choice\"><input type=\"radio\" name=\"v2flight\" value=\"'+i+'\"'+(i===selectedFlightIndex?' checked':'')+'><span>\xd0\x92\xd0\xb0\xd1\x80\xd0\xb8\xd0\xb0\xd0\xbd\xd1\x82 '+(i+1)+(v&&v.isDefault?' \xc2\xb7 \xd1\x80\xd0\xb5\xd0\xba\xd0\xbe\xd0\xbc\xd0\xb5\xd0\xbd\xd0\xb4\xd1\x83\xd0\xb5\xd0\xbc\xd1\x8b\xd0\xb9':'')+'</span><b>'+money(price)+' \xe2\x82\xbd</b></label>'+((v&&Array.isArray(v.forward)?v.forward:[]).map((f,n)=>segmentHtml(f,n?'\xd0\x9f\xd0\xb5\xd1\x80\xd0\xb5\xd1\x81\xd0\xb0\xd0\xb4\xd0\xba\xd0\xb0 \xd1\x82\xd1\x83\xd0\xb4\xd0\xb0':'\xd0\xa2\xd1\x83\xd0\xb4\xd0\xb0')).join(''))+((v&&Array.isArray(v.backward)?v.backward:[]).map((f,n)=>segmentHtml(f,n?'\xd0\x9f\xd0\xb5\xd1\x80\xd0\xb5\xd1\x81\xd0\xb0\xd0\xb4\xd0\xba\xd0\xb0 \xd0\xbe\xd0\xb1\xd1\x80\xd0\xb0\xd1\x82\xd0\xbd\xd0\xbe':'\xd0\x9e\xd0\xb1\xd1\x80\xd0\xb0\xd1\x82\xd0\xbd\xd0\xbe')).join(''))+'<div class=\"flight-fuel\">\xd0\xa2\xd0\xbe\xd0\xbf\xd0\xbb\xd0\xb8\xd0\xb2\xd0\xbd\xd1\x8b\xd0\xb9 \xd1\x81\xd0\xb1\xd0\xbe\xd1\x80: '+esc(fuelText)+'</div></div>'; }".replace(b"; }", b";}")
                original_variant = b"function variantHtml(v,i){const p=v&&v.price||{},fuel=v&&v.fuelCharge||{},price=p&&p.value!==undefined?p.value:p||0,fuelValue=fuel&&fuel.value!==undefined?fuel.value:fuel||0;return '<div class=\"flight-variant'+(i===selectedFlightIndex?' is-selected':'')+'\" data-flight-index=\"'+i+'\"><label class=\"flight-choice\"><input type=\"radio\" name=\"v2flight\" value=\"'+i+'\"'+(i===selectedFlightIndex?' checked':'')+'><span>\xd0\x92\xd0\xb0\xd1\x80\xd0\xb8\xd0\xb0\xd0\xbd\xd1\x82 '+(i+1)+(v&&v.isDefault?' \xc2\xb7 \xd1\x80\xd0\xb5\xd0\xba\xd0\xbe\xd0\xbc\xd0\xb5\xd0\xbd\xd0\xb4\xd1\x83\xd0\xb5\xd0\xbc\xd1\x8b\xd0\xb9':'')+'</span><b>'+money(price)+' \xe2\x82\xbd</b></label>'+((v&&Array.isArray(v.forward)?v.forward:[]).map((f,n)=>segmentHtml(f,n?'\xd0\x9f\xd0\xb5\xd1\x80\xd0\xb5\xd1\x81\xd0\xb0\xd0\xb4\xd0\xba\xd0\xb0 \xd1\x82\xd1\x83\xd0\xb4\xd0\xb0':'\xd0\xa2\xd1\x83\xd0\xb4\xd0\xb0')).join(''))+((v&&Array.isArray(v.backward)?v.backward:[]).map((f,n)=>segmentHtml(f,n?'\xd0\x9f\xd0\xb5\xd1\x80\xd0\xb5\xd1\x81\xd0\xb0\xd0\xb4\xd0\xba\xd0\xb0 \xd0\xbe\xd0\xb1\xd1\x80\xd0\xb0\xd1\x82\xd0\xbd\xd0\xbe':'\xd0\x9e\xd0\xb1\xd1\x80\xd0\xb0\xd1\x82\xd0\xbd\xd0\xbe')).join(''))+(fuelValue?'<div class=\"flight-fuel\">\xd0\xa2\xd0\xbe\xd0\xbf\xd0\xbb\xd0\xb8\xd0\xb2\xd0\xbd\xd1\x8b\xd0\xb9 \xd1\x81\xd0\xb1\xd0\xbe\xd1\x80: '+money(fuelValue)+' \xe2\x82\xbd</div>':'')+'</div>'; }".replace(b"; }", b";}")
                self.assertEqual(source.count(fuel_helper), 1, 'one reviewed flight fuel display helper')
                self.assertEqual(source.count(current_variant), 1, 'one reviewed flight fuel variant renderer')
                source = source.replace(fuel_helper, b'', 1).replace(current_variant, original_variant, 1)
                self.assertTrue(source.endswith(b'})();\n'), 'reviewed controller keeps one canonical final line break')
                source = source[:-1]
                # Reviewed presentation-state fix: preserve the renderer's exact
                # action label while the existing selection request is pending.
                # Reversing both fragments recovers the protected controller.
                label_capture = b"const buttonLabel=button?button.textContent:'';"
                label_restore = b"button.textContent=buttonLabel;"
                self.assertEqual(source.count(label_capture), 1, 'one selection action label capture')
                self.assertEqual(source.count(label_restore), 1, 'one selection action label restore')
                source = source.replace(label_capture, b'', 1).replace(
                    label_restore, "button.textContent='Выбрать';".encode(), 1)
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



if __name__ == '__main__':
    unittest.main(verbosity=2)
