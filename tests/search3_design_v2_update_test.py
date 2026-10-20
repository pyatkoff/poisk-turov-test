"""Bounded source proof, exact published predecessor and interrupted updates."""
import copy
import io
import json
import os
from pathlib import Path
import shutil
import struct
import sys
import tempfile
import unittest
import zipfile
from unittest.mock import patch, Mock
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_design_v2_preview as creator
import search3_design_v2_preview_remote as create_remote
import search3_design_v2_update as pub
import search3_design_v2_update_remote as remote
from search3_design_v2_preview_test import fixture, request as original_request


class UpdateContracts(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(); self.root = Path(self.tmp.name)
        self.baseline = self.root / 'baseline'; fixture(self.baseline)
        paths = [p for p in remote.SOURCE_CHANGES if p != creator.LEDGER_PATH]
        for i, name in enumerate(paths):
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(self.baseline / 'payload' / name)
        for i, name in enumerate(('visual-search/preview-lead.js', 'prototype-search/lead.js',
                'tour-controller-v4.js', 'visual-search/assets/anytour-logo.svg',
                'visual-search/fonts/Aeroport.woff2'), start=len(paths)):
            target = self.baseline / 'payload' / name; target.parent.mkdir(parents=True, exist_ok=True)
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(target)
        import posixpath
        for i, src in enumerate(remote.NATIVE_SCRIPTS, start=50):
            name = posixpath.normpath(posixpath.join('visual-search', src)); target = self.baseline / 'payload' / name
            if target.exists() or name in remote.NATIVE_ASSETS: continue
            target.parent.mkdir(parents=True, exist_ok=True)
            (self.baseline / 'payload' / f'visual-search/fixture-{i}.js').rename(target)
        self.original_pin = patch.object(creator, 'SOURCE_LEDGER_SHA256', remote.digest((self.baseline / 'payload' / creator.LEDGER_PATH).read_bytes()))
        self.original_pin.start(); self.addCleanup(self.original_pin.stop)
        self.source = self.root / 'source'; shutil.copytree(self.baseline, self.source)
        (self.source / 'payload/visual-search/app.js').write_text('previous published polish')
        for name in remote.NATIVE_ASSETS:
            path = self.source / 'payload' / name; path.parent.mkdir(parents=True, exist_ok=True); path.write_text('native fixture:' + name)
        html = '<!doctype html><meta name="robots" content="noindex,nofollow,noarchive"><main id="design-v2-native-root"></main>\n'
        html += '\n'.join('<link rel="stylesheet" href="' + src + '">' for src in remote.NATIVE_STYLES) + '\n'
        html += '\n'.join('<script src="' + src + '" defer></script>' for src in remote.NATIVE_SCRIPTS) + '\n'
        (self.source / 'payload/visual-search/index.html').write_text(html)
        (self.source / 'payload/visual-search/index.php').write_text("<?php $entry = '/_preview/search3-design-v2-candidate/visual-search/';\nif ($script !== $entry . 'index.php') { exit; }")
        def row(src):
            name = posixpath.normpath(posixpath.join('visual-search', src)); content = (self.source / 'payload' / name).read_bytes()
            return {'src': src, 'path': name, 'sha256': remote.digest(content), 'bytes': len(content)}
        native = {'schema': 2, 'donorSource': '00fe6734c08acf234756bb59e5b61dbfecf41941', 'donor': remote.NATIVE_DONOR,
            'status': 'native-source-candidate-not-published', 'legacyPresentationLoaded': False, 'mockSimulatorLoaded': False,
            'isolatedOverlay': True, 'canonicalDependenciesRewritten': False,
            'entry': {'html': remote.digest(html.encode()), 'php': remote.digest((self.source / 'payload/visual-search/index.php').read_bytes())},
            'graph': {'scripts': [row(src) for src in remote.NATIVE_SCRIPTS], 'styles': [row(src) for src in remote.NATIVE_STYLES]},
            'files': {Path(name).name: remote.digest((self.source / 'payload' / name).read_bytes()) for name in remote.NATIVE_ASSETS}}
        (self.source / 'payload' / remote.NATIVE_MANIFEST).write_text(json.dumps(native, ensure_ascii=False, indent=2) + '\n')
        self.native = native
        self.native_pin = {'source_sha': 'a' * 40, 'source_tree': 'd' * 40,
            'release_sha': 'c' * 40, 'release_tree': 'd' * 40,
            'build_run': 123, 'artifact_id': 456,
            'source_ZIP_sha256': 'e' * 64, 'entry_html_sha256': native['entry']['html'], 'entry_php_sha256': native['entry']['php'],
            'source_manifest_sha256': remote.digest((self.source / 'payload' / remote.NATIVE_MANIFEST).read_bytes())}
        for change in (patch.object(remote, 'NATIVE_ACCEPTANCE', self.native_pin),
                       patch.object(remote, 'NATIVE_BRAND', {name: native['files'][name] for name in remote.NATIVE_BRAND})):
            change.start(); self.addCleanup(change.stop)
        m = json.loads((self.source / 'control/manifest.json').read_bytes())
        m.update(source_sha='a' * 40, source_tree_sha='d' * 40)
        (self.source / 'control/manifest.json').write_bytes(remote.json_bytes(m))
        q = {**original_request(), 'source_sha': 'a' * 40, 'release_sha': 'c' * 40,
            'source_tree': 'd' * 40, 'build_run': 123, 'artifact_id': 456, 'deploy_run': 789}
        # The predecessor is already a native 855-file publication, not a legacy
        # 845-file fixture with a patched owner. Its canonical and brand bytes
        # stay identical while only reviewed native owners and generated manifest
        # change for this package.
        pub.derive(self.source, self.baseline, self.root / 'installed', q, 'e' * 64)
        for name in remote.NATIVE_SUCCESSOR_CHANGES:
            if name == remote.NATIVE_MANIFEST: continue
            path = self.source / 'payload' / name
            if name == 'visual-search/index.html':
                path.write_text(path.read_text().replace('<main id="design-v2-native-root"></main>',
                    '<main id="design-v2-native-root"><div role="status">Native startup fixture</div></main>'))
            else: path.write_text('reviewed native successor:' + name)
        native['entry']['html'] = remote.digest((self.source / 'payload/visual-search/index.html').read_bytes())
        self.native_pin['entry_html_sha256'] = native['entry']['html']
        native['files'] = {Path(name).name: remote.digest((self.source / 'payload' / name).read_bytes())
                           for name in remote.NATIVE_ASSETS}
        native['graph'] = {'scripts': [row(src) for src in remote.NATIVE_SCRIPTS],
                           'styles': [row(src) for src in remote.NATIVE_STYLES]}
        (self.source / 'payload' / remote.NATIVE_MANIFEST).write_text(json.dumps(native, ensure_ascii=False, indent=2) + '\n')
        self.native_pin['source_manifest_sha256'] = remote.digest((self.source / 'payload' / remote.NATIVE_MANIFEST).read_bytes())
        self.q, self.archive = pub.derive(self.source, self.baseline, self.root / 'derived', q, 'e' * 64)
        site_root = self.root / 'anytoour.ru'; parent = site_root / '_preview'; parent.mkdir(parents=True)
        for name in create_remote.REQUIRED:
            p = site_root / name; p.parent.mkdir(parents=True, exist_ok=True); p.write_text('protected:' + name)
        for name in ('search3-site-candidate', 'search3-local-candidate', 'search3-v17-candidate',
                     'search3-v18-candidate', 'search3-next-candidate', 'search3-anex-candidate'):
            (parent / name).mkdir(); (parent / name / 'keep.txt').write_text('old unchanged:' + name)
        shutil.copytree(self.root / 'installed/payload', parent / 'search3-design-v2-candidate')
        self.digest = remote.digest(remote.json_bytes(remote.inventory(parent / 'search3-design-v2-candidate')))
        self.owner = {**remote.PREDECESSOR_OWNER, 'digest': self.digest}
        self.pins = [patch.object(remote, 'PREDECESSOR_DIGEST', self.digest), patch.object(remote, 'PREDECESSOR_OWNER', self.owner)]
        for pin in self.pins: pin.start(); self.addCleanup(pin.stop)
        self.site = remote.Site(site_root); self.site.owner.write_bytes(remote.json_bytes(self.owner))
        self.uploads = []
        self.env = {'GITHUB_REPOSITORY': pub.REPO, 'GITHUB_REF': 'refs/heads/main',
            'GITHUB_ACTOR': 'pyatkoff', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff', 'GITHUB_ACTOR_ID': '226193297',
            'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_EVENT_NAME': 'issue_comment', 'GITHUB_RUN_ID': '789'}
        self.event = {'repository': {'full_name': pub.REPO, 'id': 1345518271},
            'sender': {'login': 'pyatkoff', 'id': 226193297}, 'action': 'created', 'issue': {'number': 4709},
            'comment': {'user': {'id': 226193297}, 'author_association': 'OWNER',
                'body': pub.PREFIX + 'a' * 40 + ' ' + 'c' * 40 + ' 123 456'}}

    def tearDown(self):
        for p in self.uploads: p.unlink(missing_ok=True)
        self.tmp.cleanup()

    def staged(self, run=789):
        fd, name = tempfile.mkstemp(prefix='search3-design-v2-update.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
        p = Path(name); p.write_bytes(self.archive); self.uploads.append(p)
        return {**self.q, 'deploy_run': run, 'archive': name, 'before': self.site.snapshot()}

    def review_archive(self, mutate=None):
        contents = {('payload/' + name): (self.source / 'payload' / name).read_bytes()
                    for name in remote.inventory(self.source / 'payload')}
        meta = {'schema': 1, 'source_sha': 'a' * 40, 'source_tree_sha': 'd' * 40, 'base_sha': 'b' * 40,
            'run_id': '123', 'run_attempt': '1', 'donor_source_sha': self.native['donorSource'],
            'intended_route': remote.ROUTE + 'visual-search/', 'status': 'review-artifact-not-published',
            'publication_authority': False, 'canonical_route_rewrites_applied': False,
            'file_count': 855, 'files': [{'path': name, 'sha256': remote.digest(value), 'bytes': len(value)}
                                      for name, value in sorted((name[len('payload/'):], value) for name, value in contents.items())]}
        contents['control/inventory.json'] = remote.json_bytes(meta)
        contents['control/native-build.json'] = remote.json_bytes(self.native)
        proof = {'passiveHydrationSearches': 0, 'formSubmitSearches': 1, 'localFilterExtraSearches': 0,
            'exactLoadedOfferRetained': True, 'flightChoiceIndex': 0, 'selectedFullTotal': 112000,
            'contactPayloadFullTotal': 112000, 'contactPayloadFlightChoiceIndex': 0,
            'contactPreviewCount': 1, 'realLeadSubmissions': 0, 'backForwardRetainedSelection': True,
            'backForwardExtraSearches': 0, 'errorRetainedRows': 4, 'explicitSearchRetryCount': 1,
            'retryRows': 4, 'favoriteStoragePreserved': True, 'budgetLoadedCount': 2, 'budgetExtraSearches': 0,
            'calendarPages': 2, 'calendarLaterLoadedTotal': 166400,
            'calendarPagingExtraSearches': 0, 'calendarHistoryPageRetained': True,
            'calendarSingleWeekNavigation': True,
            'boundedInitialCards': 24, 'boundedLoadedHotels': 55,
            'boundedBackCards': 48, 'boundedExtraSearches': 0,
            'startupPendingFormHidden': True, 'startupErrorSearches': 0, 'startupRetrySearches': 0,
            'activeConstraintsVisible': True, 'recoveryLoadedHotels': 2,
            'recoveryPreservedMeal': True, 'recoveryExtraSearches': 0,
            'loadingInitialCountNeutral': True, 'loadingEmptyActionHidden': True, 'loadingExtraSearches': 0,
            'failedInitialCountNeutral': True, 'failedInitialFriendlyCopy': True, 'failedInitialExtraSearches': 0,
            'filteredCalendarTruthful': True,
            'partialEmptyNeutralCount': True, 'partialSavedSearchRetained': True,
            'partialLoadedHotels': 4, 'partialExtraSearches': 0,
            'partialFilteredRecoveryLoadedHotels': 2,
            'flightAlternativesExtraQuotes': 0, 'flightAlternativeOriginalIndices': [0, 1, 0],
            'flightAlternativesClosedHeight': 500, 'flightAlternativesExpandedHeight': 800,
            'flightAlternativesSavedHeight': 300,
            'familyAges': [0, 7, 17], 'familyExtraSearches': 0, 'familyAgeReviewRetained': True,
            'nightKeyboardFocusRetained': True, 'formCalendarPagingFocusRetained': True,
            'familyInputFocusRetained': True, 'destinationKeyboardFocusRetained': True,
            'formPopupSingleSubmitRetained': True, 'presearchNavigationTruthful': True,
            'latePresentationTerminalStatesRetained': True, 'latePresentationExtraSearches': 0,
            'contactExactItineraryRetained': True, 'contactDraftAndConsentRetained': True,
            'groupMinimumOpenedHotelWithoutQuote': True, 'groupPassiveExtraSearches': 0,
            'groupPassiveExpansions': 0, 'groupExplicitExpandCount': 1,
            'groupExpansionExactReferences': True, 'groupPendingRemountRetained': True,
            'groupPendingStatusFocusRetained': True, 'groupConcreteQuoteCount': 1,
            'groupConcreteFocusRetained': True, 'groupBackRemountExtraOperations': 0,
            'groupVisibleConcreteMinimum': 161400, 'groupVisibleConcreteDelta': 9000,
            'groupDirectConcreteReopen': True,
            'retainedContent': {
                'hotelId': 3678, 'hotelName': 'RIA SUITES HOTEL',
                'snapshotSHA256': '00d95b2e50b683686c95a79aa459ad36a5dd947a7e5c70fe3ce9049df0a8c7e3',
                'images': [
                    {'file': 'ria-suites-1.jpg',
                     'sourceURL': 'https://static.tourvisor.ru/hotel_pics/main400/103994.jpg',
                     'sha256': 'f259ca07c0edf996bf23e456b548990830e010538a465e86f952a399abb1c4d5'},
                    {'file': 'ria-suites-2.jpg',
                     'sourceURL': 'https://static.tourvisor.ru/hotel_pics/verybig/4/103994-90635200.jpg',
                     'sha256': '7a74632ddf255de435e4b7fec6c6c982d33681552fe3d94b8aa4b80bde853dab'}],
                'cardErrorAndRetry': True, 'galleryFullImage': True,
                'lateFailureIgnored': True, 'galleryErrorAndRetry': True,
                'compactImageNoPlaceholder': True, 'compactErrorWithoutControls': True,
                'passiveSupplierOperations': 0},
            'contactValidation': {'emptyPhone': True, 'invalidPhone': True,
                'restoredInvalidDraft': True, 'whitespacePhone': True,
                'missingConsent': True, 'correctedPreviewDelta': 1,
                'invalidPreviewDelta': 0, 'exactContextRetained': True,
                'backReopenRetained': True, 'extraSupplierOperations': 0,
                'realSubmissions': 0},
            'staticHeadingFocus': {
                'results': {'focused': True, 'tabIndex': -1, 'outline': 'none'},
                'control': {'focused': True, 'visible': True, 'outline': 'auto', 'width': 1},
                'loading': True, 'hotel': True},
            'screenNavigation': {'deepScroll': 1200, 'hotelHeadingVisible': True,
                'resultFocusRestored': True, 'resultAnchorDelta': 0, 'extraSearches': 0,
                'responsiveHiddenFocusFallback': True, 'responsiveRemountExtraOperations': 0},
            'managerContact': {'total': 110000, 'flightChoiceId': None,
                'oldPairRemoved': True, 'consentRevoked': True},
            'nonMinimumJourney': {'exactOfferIndex': 1, 'total': 170400,
                'summaryAndContinueRetained': True, 'contactItineraryRetained': True,
                'backReopenExtraOperations': 0},
            'contactPriceBlockFit': {'captionCount': 1, 'amountCount': 1,
                'readableCaptionWeight': True, 'readableCaptionSpacing': True,
                'captionInsidePrice': True, 'amountInsidePrice': True,
                'captionAboveAmount': True, 'overflow': False},
            'manyFlightChoices': {'total': 40, 'initiallyVisible': 3, 'collapsed': 37,
                'selectedCanonicalIndex': 39, 'selectedTotal': 151000,
                'passiveExtraOperations': 0,
                'managerBeforeList': {'before': True, 'visible': True, 'overflow': False}}}
        report = {'schema': 1, 'sourceSHA': 'a' * 40, 'donorSource': self.native['donorSource'],
            'compiledNativeManifest': self.native, 'status': 'passed', 'requiredWidths': [430,1280],
            'requiredTextPercents': [100], 'expectedPairedScreens': ['search','results','hotel','tour','lead'],
            'cases': [], 'failures': []}
        for width in (430,1280):
            for percent in (100,):
                case = {'width': width, 'textPercent': percent, 'proof': dict(proof), 'errors': [], 'unexpectedRequests': [], 'frames': []}
                case['proof']['manyFlightChoices'] = {**proof['manyFlightChoices'],
                    'pairLayout': {'columns': 2 if width == 1280 else 1,
                        'paired': width == 1280, 'stacked': width != 1280}}
                case['proof']['aiPriceContact'] = {'searchPrice': 138989, 'checkedFullTotal': 163056,
                    'readableMeal': True, 'originalIndex': 0, 'explicitAcceptance': True,
                    'comparableSearchPrices': True, 'selectedPackagePriceRetained': True,
                    'conditionsAgreement': True, 'backForwardExtraOperations': 0,
                    'contactFit': {'width': 490 if width == 1280 else width - 60,
                        'titleWidth': 370 if width == 1280 else width - 140,
                        'titleLines': 2, 'titleFits': True, 'summaryFits': True, 'columns': width >= 768}}
                case['proof']['calendarVisibleNavigation'] = {
                    'lastPriceReachableByArrows': True,
                    'backwardDatesNotSkipped': True if width == 430 else None,
                    'unselectedBackPositionVisible': True,
                    'mobileRemountPositionRetained': True if width == 430 else None,
                    'selectedDateVisible': True, 'clearedDateVisible': True,
                    'backPositionVisible': True, 'implicitDayLocatorScroll': False}
                case['proof']['contactDisclosure'] = {'initiallyCollapsed': width <= 760,
                    'expandedFactsVisible': True, 'passiveExtraOperations': 0}
                if (width, percent) == (430, 100):
                    case['proof'].update(progressiveDrawerRetainedDraftAndFocus=True, budgetBreakpointDraftRetained=True)
                for name in sorted(pub.native_frame_inventory(width, percent), key=lambda name: (name.startswith('donor-'), name)):
                    side = name.split('-')[0]
                    short_desktop = name.startswith('native-short-desktop-')
                    logical_name = name.replace('native-short-desktop-', 'native-', 1) if short_desktop else name
                    screen = ('startup' if logical_name.startswith('native-startup-')
                              else 'search' if logical_name.startswith(('native-family-', 'native-destination-', 'native-popup-', 'native-initial-search-return-'))
                              else 'filters' if logical_name.startswith(('native-budget-invalid-', 'native-budget-draft-', 'native-budget-progress-invalid-'))
                              else 'results' if logical_name.startswith(('native-content-results-', 'native-budget-results-', 'native-error-',
                                                                 'native-calendar-later-', 'native-calendar-single-range-', 'native-results-bounded-', 'native-initial-favorites-',
                                                                 'native-filters-active-', 'native-filter-empty-',
                                                                 'native-results-loading-', 'native-results-error-',
                                                                 'native-results-partial-', 'native-filter-empty-partial-', 'native-terminal-'))
                              else 'tour' if logical_name.startswith('native-ai-price-changed-')
                              else 'lead' if logical_name.startswith(('native-ai-contact-', 'native-content-contact-'))
                              else 'gallery' if logical_name.startswith('native-content-gallery-')
                              else 'hotel' if logical_name.startswith(('native-hotel-return-', 'native-ai-hotel-checked-')) else logical_name.split('-')[1])
                    png = b'\x89PNG\r\n\x1a\nfictional offline verifier fixture'
                    if short_desktop:
                        png = b'\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR' + struct.pack('>II', 1280, 720) + b'fictional offline verifier fixture'
                    if name.startswith(('native-calendar-later-', 'native-calendar-single-range-')):
                        png = b'\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR' + struct.pack('>II', width, 900) + b'fictional offline verifier fixture'
                    contents['evidence/' + name] = png
                    has_budget = screen == 'filters' or screen == 'results' and width >= 768
                    size = (16 if width <= 430 else 14) * percent / 100
                    calendar_fit = None
                    if name.startswith(('native-calendar-later-', 'native-calendar-single-range-')):
                        calendar_fit = {'left': 17, 'right': width - 17,
                            'strip': {'left': 17, 'right': width - 17,
                                'scrollLeft': 230 if width == 430 else 0,
                                'clientWidth': width - 34,
                                'scrollWidth': width - 34 + (230 if width == 430 else 0),
                                'dateLeft': width - 102, 'dateRight': width - 18},
                            'footClientWidth': width - 34, 'footScrollWidth': width - 34,
                            'pager': {'left': width - 109, 'right': width - 17, 'top': 300, 'bottom': 344},
                            'buttons': [
                                {'disabled': False, 'left': width - 109, 'right': width - 65,
                                 'top': 300, 'bottom': 344, 'width': 44, 'height': 44},
                                {'disabled': True, 'left': width - 61, 'right': width - 17,
                                 'top': 300, 'bottom': 344, 'width': 44, 'height': 44}],
                            'clipped': False}
                    summary_fit = []
                    if side == 'native' and screen in ('tour', 'lead'):
                        summary_width = (300 if width <= 430 else 434 if screen == 'lead' else 670)
                        summary_font = 14 * percent / 100
                        narrow = summary_width <= summary_font * 18 + 1
                        summary_fit = [{'className': 'st-quick-context' if width <= 430 and screen == 'tour' else 'st-tour-hotel',
                            'width': summary_width, 'fontSize': summary_font,
                            'imageWidth': summary_width if narrow else 82,
                            'copyWidth': summary_width if narrow else summary_width * .7,
                            'copyWidthRatio': 1 if narrow else .7,
                            'stacked': narrow, 'queryNarrow': narrow}]
                    offer_action_fit = []
                    if side == 'native' and screen == 'hotel':
                        offer_width = width - 36 if width <= 430 else 650
                        offer_font = 14 * percent / 100
                        offer_narrow = offer_width <= offer_font * 22 + 1
                        offer_action_fit = [{'text': text, 'width': offer_width,
                            'availableWidth': offer_width, 'containerWidth': offer_width,
                            'fontSize': offer_font,
                            'actionWidth': offer_width if offer_narrow else 110,
                            'actionWidthRatio': 1 if offer_narrow else 110 / offer_width,
                            'queryNarrow': offer_narrow, 'actionOnOwnRow': offer_narrow,
                            'outside': False} for text in ('Выбрать тур', 'Выбран тур')]
                    readable_text = [{'text': 'Fixture condition', 'clipped': False,
                        'brokenWords': False, 'overlappingLines': False, 'kind': 'other', 'cardId': None}]
                    if name.startswith('native-popup-nights-'):
                        readable_text.extend({**readable_text[0], 'text': text, 'kind': 'night-preset'}
                                             for text in ('7 ночей', '10 ночей', '14 ночей', '21 ночь'))
                    result_card_ids = []
                    result_notice = {'visibleCount': 0, 'afterBatch': False, 'cardNoticeCount': 0, 'text': ''}
                    if side == 'native' and screen == 'results' and not name.startswith((
                            'native-filter-empty-', 'native-results-loading-', 'native-results-error-',
                            'native-results-partial-empty-', 'native-initial-favorites-')):
                        result_card_ids = ([3678] if logical_name.startswith('native-content-results-')
                            else list(range(1000, 1024)) if logical_name.startswith('native-results-bounded-')
                            else [2, 3] if logical_name.startswith('native-budget-results-')
                            else [1, 4] if logical_name.startswith('native-filters-active-') else [1, 2, 3, 4])
                        fixture_hotels = [
                            ('FIXTURE Laguna Beach Resort', 'Стандартный номер', 'Ультра всё включено'),
                            ('FIXTURE Marina Bay Hotel', 'Стандартный номер', 'Завтраки и ужины'),
                            ('FIXTURE Seaside Garden Hotel & Residence', 'Стандартный номер', 'Завтраки'),
                            ('FIXTURE Laguna Collection Premium Resort & Private Villas',
                             'Вилла с двумя спальнями · свой бассейн', 'Ультра всё включено')]
                        for card_id in result_card_ids:
                            if logical_name.startswith('native-content-results-'):
                                facts = {'result-title': 'RIA SUITES HOTEL', 'result-location': 'Стамбул, Турция',
                                    'result-date': '12 окт · 7 ночей', 'result-room': 'Стандартный номер',
                                    'result-meal': 'Ультра всё включено', 'result-operator': 'FIXTURE TV',
                                    'result-party': 'за 2 взрослых'}
                            else:
                                bounded = card_id >= 1000
                                hotel = fixture_hotels[(card_id - 1000) % 4 if bounded else card_id - 1]
                                title = f'FICTIONAL loaded hotel {card_id - 999:02d}' if bounded else hotel[0]
                                terminal = logical_name.startswith(('native-terminal-', 'native-calendar-single-range-'))
                                operator = 'FIXTURE ANEX' if terminal else 'FIXTURE TV'
                                facts = {'result-title': title, 'result-location': 'Турция · Кемер',
                                    'result-date': '19 окт · 7 ночей' if terminal else '12 окт · 7 ночей', 'result-room': hotel[1],
                                    'result-meal': hotel[2], 'result-operator': operator, 'result-party': 'за 2 взрослых'}
                            readable_text.extend({**readable_text[0], 'text': value, 'kind': kind, 'cardId': card_id}
                                                 for kind, value in facts.items())
                        result_notice = {'visibleCount': 1, 'afterBatch': True, 'cardNoticeCount': 0,
                            'text': 'Цены из поиска — за весь тур и всех туристов. Перед заявкой проверим цену, наличие и состав пакета, включая обязательные доплаты.'}
                        readable_text.append({**readable_text[0], 'text': result_notice['text'], 'kind': 'result-notice'})
                    if side == 'native' and screen == 'tour':
                        readable_text.extend({**readable_text[0], 'text': text} for text in (
                            'Туристы', 'Туроператор', 'Обязательные доплаты'))
                    if name.startswith('native-family-'):
                        state = name[len('native-family-'):].split('-', 1)[0]
                        party = {'0': '1 взрослый', '1': '1 взрослый + 1 ребёнок',
                            '2': '1 взрослый + 2 ребёнка', '3': '1 взрослый + 3 ребёнка',
                            'applied': '1 взрослый + 3 ребёнка', 'review': '1 взрослый + 3 ребёнка'}[state]
                        readable_text.append({**readable_text[0], 'text': party})
                        if state == 'review':
                            readable_text.extend({**readable_text[0], 'text': text} for text in (
                                'Проверьте возраста детей на дату возвращения',
                                'Подтвердите возраста детей на дату возвращения.'))
                    if name.startswith('native-initial-favorites-'):
                        readable_text = [{**readable_text[0], 'text': text} for text in (
                            'Поиск ещё не запускали', 'Начните с поиска',
                            'Выберите условия поездки и нажмите «Найти туры».')]
                    count_fit = {'text': 'Поиск ещё не запускали' if name.startswith('native-initial-favorites-') else '4 отеля',
                        'toolbar': {'left': 10, 'right': width - 10},
                        'textRects': [{'left': 10, 'right': 100, 'top': 10, 'bottom': 40}],
                        'controls': [{'text': 'Фильтры', 'left': width - 100, 'right': width - 10,
                                      'top': 10, 'bottom': 54}],
                        'outside': False, 'overlap': False} if side == 'native' and screen == 'results' else None
                    destination_fit = None
                    if name.startswith(('native-destination-choice-', 'native-destination-confirm-')):
                        readable_text.append({**readable_text[0], 'text': 'Отмена'})
                    if name.startswith('native-destination-confirm-'):
                        narrow = percent == 200 and width in (360, 390, 430)
                        font = 13 * percent / 100
                        available = 270 if width < 760 else 800
                        destination_fit = {'available': available, 'fontSize': font, 'queryNarrow': narrow,
                            'stacked': narrow, 'outside': False, 'overlap': False,
                            'actions': [{'text': text, 'left': 0 if narrow else index * 135,
                                'right': 270 if narrow else index * 135 + 125,
                                'top': index * 52 if narrow else 0, 'bottom': index * 52 + 44 if narrow else 44,
                                'width': 270 if narrow else 125, 'height': 44, 'disabled': False}
                                for index, text in enumerate(('Оставить', 'Заменить'))]}
                        readable_text.extend({**readable_text[0], 'text': text} for text in ('Оставить', 'Заменить'))
                    popup_editor = ('dates' if logical_name.startswith('native-popup-calendar-')
                        else 'nights' if logical_name.startswith('native-popup-nights-')
                        else 'travelers' if logical_name.startswith('native-family-') and not logical_name.startswith('native-family-applied-')
                        else 'destination' if logical_name.startswith('native-destination-') else None)
                    popup_fit = {'editor': popup_editor, 'modal': True, 'outside': False,
                        'panelOverflow': False, 'findVisible': True, 'findEnabled': True,
                        'findHeight': 50, 'submitCount': 1} if popup_editor else None
                    terminal_state = None
                    if name.startswith('native-terminal-'):
                        status = name.split('-')[2]
                        message = ('' if status == 'complete' else 'API HTTP 403'
                                   if status == 'partial' else
                                   'Не удалось завершить поиск. Полученные предложения сохранены.')
                        alert = ('Поиск завершён не полностью'
                                 'Показаны предложения: ANEX. Не все источники завершили поиск: '
                                 'Tourvisor и Andromeda.' if status == 'partial' else
                                 'Не удалось завершить поиск'
                                 'Полученные предложения сохранены. Измените условия или повторите поиск.'
                                 'Изменить и повторить' if status == 'error' else '')
                        terminal_state = {'status': status, 'message': message, 'searchGeneration': 1,
                            'searchStarts': 1, 'otherCalls': 0, 'alertVisible': status != 'complete',
                            'alertText': alert,
                            'ownerSHA256': self.native['files']['bridge.js']}
                    consent_fit = None
                    if screen == 'lead':
                        consent_fit = {'label': {'left': 18, 'right': width - 18, 'top': 440, 'bottom': 496,
                                                   'width': width - 36, 'height': 56},
                            'checkbox': {'left': 29, 'right': 49, 'top': 450, 'bottom': 470,
                                         'width': 20, 'height': 20},
                            'ownsCheckbox': True, 'required': True, 'contained': True,
                            'focusOutline': side == 'native'}
                    case['frames'].append({'terminalState': terminal_state, 'file': name, 'sha256': remote.digest(png), 'side': side, 'screen': screen,
                        'viewportWidth': width, 'viewportHeight': 720 if short_desktop else 900,
                        'documentWidth': width, 'textPercent': percent, 'overflow': False, 'stageHidden': False,
                        'popupFit': popup_fit, 'calendarFit': calendar_fit, 'destinationFit': destination_fit,
                        'summaryFit': summary_fit, 'consentFit': consent_fit,
                        'offerActionFit': offer_action_fit,
                        'resultCardIds': result_card_ids, 'resultNotice': result_notice,
                        'buttons': [{'text': 'К параметрам поиска', 'clipped': False}] if name.startswith('native-initial-favorites-') else [] if name.startswith(('native-startup-pending-', 'native-results-loading-')) else [{'text': 'Fixture action', 'clipped': False}],
                        'readableText': readable_text,
                        'headerFit': {'outside': False, 'overlap': False}, 'countFit': count_fit,
                        'sortFit': {'text': 'Fixture sort', 'available': 200, 'textWidth': 80} if screen == 'results' else None,
                        'budgetInputs': [{'text': text, 'available': 200, 'textWidth': 80, 'clipped': False,
                            'appearance': 'textfield', 'font': f'500 {size:g}px Aeroport, system-ui, sans-serif'}
                                         for text in ('0', '150000')] if has_budget else [],
                        'budgetColumns': (2 if percent == 100 else 1) if screen == 'results' and width >= 768 else None,
                        'prices': [] if screen == 'startup' or name.startswith(('native-family-', 'native-filter-empty-', 'native-results-loading-', 'native-results-error-', 'native-results-partial-empty-', 'native-initial-favorites-', 'native-initial-search-return-')) else [{'overflow': False, 'fragmented': False, 'textScaleVerified': True,
                                    'fontSize': 29 * percent / 100, 'baselineFontSize': 29}]})
                report['cases'].append(case)
        contents['evidence/acceptance.json'] = remote.json_bytes(report)
        contents['evidence/fixture.json'] = b'{"scope":"fictional offline verifier fixture"}\n'
        if mutate: mutate(contents)
        output = io.BytesIO()
        with zipfile.ZipFile(output, 'w') as archive:
            for name, value in contents.items(): archive.writestr(name, value)
        return output.getvalue()

    def read_review(self, content, target='review'):
        pin = {**self.native_pin, 'source_ZIP_sha256': remote.digest(content)}
        with patch.object(remote, 'NATIVE_ACCEPTANCE', pin):
            return pub.prepare_native_zip(content, self.q, 'd' * 40, pin['source_ZIP_sha256'], self.root / target)

    def assert_evidence_rejected(self, changes):
        # Exercise rendered proof tampering without repeatedly extracting 855
        # already-verified payload files; complete ZIP tests retain that boundary.
        with zipfile.ZipFile(io.BytesIO(self.review_archive())) as archive:
            original = json.loads(archive.read('evidence/acceptance.json'))
            class ChangedEvidence:
                def __init__(self, report): self.report = remote.json_bytes(report)
                def read(self, name):
                    return self.report if name == 'evidence/acceptance.json' else archive.read(name)
                def infolist(self): return archive.infolist()
            for index, change in enumerate(changes):
                report = copy.deepcopy(original); change(report)
                with self.subTest(index=index), self.assertRaises(ValueError):
                    pub.native_evidence(ChangedEvidence(report), self.q, self.native)

    def test_owner_command_has_no_target_or_predecessor_argument(self):
        q = pub.checked_request(self.event, self.env)
        self.assertEqual(q['source_sha'], 'a' * 40)
        self.assertEqual(pub.RELEASE, 'release/search3-design-v2')
        self.assertEqual(remote.ROUTE, '/_preview/search3-design-v2-candidate/')
        self.assertEqual(remote.PREDECESSOR_SOURCE, '2dc409517e88493d9d24e46cafe95e462aa45819')
        self.assertEqual(remote.PREDECESSOR_RUN, 39354674302)
        self.assertEqual(remote.PREDECESSOR_DIGEST, self.digest)

    def test_preparation_has_no_publication_authority_without_real_pins(self):
        with patch.object(remote, 'NATIVE_ACCEPTANCE', None):
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'): pub.checked_request(self.event, self.env)
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'): remote.validate(self.q)
            with self.assertRaisesRegex(ValueError, 'native_acceptance_not_pinned'):
                pub.derive(self.source, self.baseline, self.root / 'unpinned', self.q, 'e' * 64)
        self.assertFalse((self.root / 'unpinned').exists())

    def test_complete_review_zip_preserves_exact_native_payload(self):
        files = self.read_review(self.review_archive())
        self.assertEqual(files, remote.inventory(self.source / 'payload'))
        self.assertEqual(remote.inventory(self.root / 'review/release/payload'), files)

    def test_command_request_propagates_verified_tree_through_prepare_inputs(self):
        import search3_preview_publish as shared
        content = self.review_archive()
        pin = {**self.native_pin, 'source_ZIP_sha256': remote.digest(content)}
        q = pub.checked_request(self.event, self.env)
        self.assertNotIn('source_tree', q)  # This is the actual authorized command shape.
        api = Mock(); api.download.side_effect = lambda artifact: content if artifact == 456 else b'baseline fixture'
        def baseline(content, request, tree, zip_hash, directory):
            self.assertEqual(request['artifact_id'], remote.ORIGINAL_ARTIFACT)
            shutil.copytree(self.baseline, directory / 'release')
        work = self.root / 'prepare-inputs'; work.mkdir()
        with patch.object(remote, 'NATIVE_ACCEPTANCE', pin), patch.object(shared, 'prepare_zip', baseline):
            ready, archive = pub.prepare_inputs(api, q, 'd' * 40, pin['source_ZIP_sha256'], work)
            self.assertEqual(remote.verify(work / 'derived', ready), remote.inventory(work / 'derived/payload'))
        self.assertEqual(ready['source_tree'], 'd' * 40)
        self.assertNotIn('source_tree', q)
        self.assertEqual(ready['archive_sha256'], remote.digest(archive))
        self.assertEqual(api.download.call_args_list[0].args, (456,))

    def test_review_zip_rejects_identity_scope_and_byte_drift(self):
        def meta_change(contents, key, value):
            meta = json.loads(contents['control/inventory.json']); meta[key] = value
            contents['control/inventory.json'] = remote.json_bytes(meta)
        mutations = [lambda c: meta_change(c, 'source_sha', 'f' * 40),
            lambda c: meta_change(c, 'publication_authority', True),
            lambda c: meta_change(c, 'run_attempt', '2'),
            lambda c: c.update({'payload/prototype-search/data.js': b'foreign provider byte'}),
            lambda c: c.update({'payload/visual-search/native-v2/unknown.js': b'unapproved'}),
            lambda c: c.update({'control/unknown.py': b'unapproved'}),
            lambda c: c.update({'../escape': b'unapproved'})]
        for i, mutate in enumerate(mutations):
            with self.subTest(index=i), self.assertRaises(ValueError): self.read_review(self.review_archive(mutate), 'bad-zip-' + str(i))

    def test_rendered_evidence_requires_full_matrix_factual_journey_and_hashes(self):
        def report_change(contents, modify):
            report = json.loads(contents['evidence/acceptance.json']); modify(report)
            contents['evidence/acceptance.json'] = remote.json_bytes(report)
        modifications = [lambda r: r.update({'sourceSHA': 'f' * 40}), lambda r: r['cases'].pop(),
            lambda r: r['cases'][0]['proof'].update({'selectedFullTotal': 1000}),
            lambda r: r['cases'][0]['proof']['retainedContent'].update({'hotelId': 1}),
            lambda r: r['cases'][0]['proof']['retainedContent'].update({'passiveSupplierOperations': 1}),
            lambda r: r['cases'][0]['proof']['contactValidation'].update({'invalidPreviewDelta': 1}),
            lambda r: r['cases'][0]['proof']['contactValidation'].update({'realSubmissions': 1}),
            lambda r: r['cases'][0]['proof'].update({'realLeadSubmissions': 1}),
            lambda r: r['cases'][0]['proof'].update({'localFilterExtraSearches': 1}),
            lambda r: r['cases'][0]['frames'][0].update({'overflow': True}),
            lambda r: r['cases'][0]['frames'][0]['buttons'][0].update({'clipped': True}),
            lambda r: r['cases'][0]['frames'][0]['prices'][0].update({'fragmented': True}),
            lambda r: r['cases'][0]['frames'][0]['prices'][0].update({'textScaleVerified': False}),
            lambda r: r['cases'][0]['frames'][0]['prices'][0].update({'fontSize': 30}),
            lambda r: r['cases'][0]['frames'][0].update({'sha256': 'f' * 64}),
            lambda r: next(frame for frame in r['cases'][0]['frames']
                           if frame['file'].startswith('native-content-contact-'))
                           ['consentFit'].update({'contained': False}),
            lambda r: next(frame for frame in r['cases'][0]['frames']
                           if frame['file'].startswith('donor-lead-'))
                           ['consentFit'].update({'focusOutline': True}),
            lambda r: next(frame for frame in r['cases'][0]['frames']
                           if frame['file'].startswith('donor-results-')).update({'consentFit': copy.deepcopy(
                               next(frame for frame in r['cases'][0]['frames']
                                    if frame['file'].startswith('donor-lead-'))['consentFit'])}),
            lambda r: r['cases'][0]['unexpectedRequests'].append('https://external.invalid/api'),
            lambda r: r.update({'failures': ['confirmed failure']})]
        for i, modify in enumerate(modifications):
            with self.subTest(index=i), self.assertRaises(ValueError):
                self.read_review(self.review_archive(lambda c: report_change(c, modify)), 'bad-evidence-' + str(i))

    def test_two_width_journey_requires_exact_113_frames_and_all_applicable_predecessor_frames(self):
        content = self.review_archive()
        with zipfile.ZipFile(io.BytesIO(content)) as archive:
            report = json.loads(archive.read('evidence/acceptance.json'))
            self.assertEqual(sum(len(case['frames']) for case in report['cases']), 113)
            self.assertEqual(sum(name.endswith('.png') for name in archive.namelist()), 113)
            ai_frames = ('native-ai-price-changed-', 'native-ai-hotel-checked-', 'native-ai-contact-')
            self.assertEqual(sum(frame['file'].startswith(ai_frames)
                for case in report['cases'] for frame in case['frames']), 6)
            for case in report['cases']:
                case['frames'] = [frame for frame in case['frames'] if not frame['file'].startswith(ai_frames)]
            self.assertEqual(sum(len(case['frames']) for case in report['cases']), 107)
            additions = ('native-tour-many-flights-', 'native-lead-manager-',
                         'native-hotel-selected-dearer-', 'native-lead-selected-dearer-')
            self.assertEqual(sum(frame['file'].startswith(additions)
                for case in report['cases'] for frame in case['frames']), 8)
            for case in report['cases']:
                case['frames'] = [frame for frame in case['frames'] if not frame['file'].startswith(additions)]
            self.assertEqual(sum(len(case['frames']) for case in report['cases']), 99)
            self.assertEqual(sum(not frame['file'].startswith(('native-family-', 'native-short-desktop-', 'native-destination-', 'native-popup-', 'native-initial-', 'native-terminal-'))
                for case in report['cases'] for frame in case['frames']), 64)
            self.assertEqual(sum(not frame['file'].startswith(('native-destination-', 'native-popup-', 'native-initial-', 'native-terminal-')) for case in report['cases'] for frame in case['frames']), 81)
            self.assertEqual(sum(not frame['file'].startswith(('native-popup-', 'native-initial-', 'native-terminal-')) for case in report['cases'] for frame in case['frames']), 85)
            self.assertEqual(sum(not frame['file'].startswith(('native-initial-', 'native-terminal-')) for case in report['cases'] for frame in case['frames']), 89)
            self.assertEqual(sum(not frame['file'].startswith('native-terminal-') for case in report['cases'] for frame in case['frames']), 93)
            self.assertEqual(sum(frame['file'].startswith('native-family-')
                for case in report['cases'] for frame in case['frames']), 12)
            self.assertEqual(sum(frame['file'].startswith('native-short-desktop-')
                for case in report['cases'] for frame in case['frames']), 5)
        def frame(report, prefix, case=0):
            return next(row for row in report['cases'][case]['frames'] if row['file'].startswith(prefix))
        changes = [lambda r: r['cases'][0]['proof'].pop('contactDisclosure'),
            lambda r: r['cases'][0]['proof']['contactDisclosure'].update(initiallyCollapsed=False),
            lambda r: r['cases'][0]['proof']['contactDisclosure'].update(passiveExtraOperations=1),
            lambda r: r['cases'][0]['proof'].pop('aiPriceContact'),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(checkedFullTotal=138989),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(originalIndex=1),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(explicitAcceptance=False),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(comparableSearchPrices=False),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(selectedPackagePriceRetained=False),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(conditionsAgreement=False),
            lambda r: r['cases'][0]['proof']['aiPriceContact'].update(backForwardExtraOperations=1),
            lambda r: r['cases'][0]['proof']['aiPriceContact']['contactFit'].update(titleFits=False),
            lambda r: r['cases'][-1]['proof']['aiPriceContact']['contactFit'].update(width=300),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-ai-contact-')),
            lambda r: frame(r, 'native-ai-price-changed-').update(screen='hotel'),
            lambda r: r['cases'][0]['proof'].update(flightAlternativesExtraQuotes=1),
            lambda r: r['cases'][0]['proof'].update(flightAlternativeOriginalIndices=[0, 0, 1]),
            lambda r: r['cases'][0]['proof'].pop('flightAlternativeOriginalIndices'),
            lambda r: r['cases'][0]['proof'].update(flightAlternativesSavedHeight=40),
            lambda r: r['cases'][0]['proof'].update(flightAlternativesClosedHeight=float('nan')),
            lambda r: r['cases'][0]['proof'].update(flightAlternativesExpandedHeight=True),
            lambda r: r['cases'][0]['proof'].update(flightAlternativesSavedHeight=500),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-tour-alternatives-')),
            lambda r: frame(r, 'native-tour-alternatives-').update(screen='hotel'),
            lambda r: r['cases'][0]['proof'].update(budgetLoadedCount=3),
            lambda r: r['cases'][0]['proof'].update(budgetExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(calendarPages=1),
            lambda r: r['cases'][0]['proof'].update(calendarLaterLoadedTotal=0),
            lambda r: r['cases'][0]['proof'].update(calendarPagingExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(calendarHistoryPageRetained=False),
            lambda r: r['cases'][0]['proof'].update(boundedInitialCards=55),
            lambda r: r['cases'][0]['proof'].update(boundedLoadedHotels=24),
            lambda r: r['cases'][0]['proof'].update(boundedBackCards=24),
            lambda r: r['cases'][0]['proof'].update(boundedExtraSearches=1),
            lambda r: r['cases'][0]['proof'].pop('boundedBackCards'),
            lambda r: r['cases'][0]['proof'].update(recoveryExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(recoveryPreservedMeal=False),
            lambda r: r['cases'][0]['proof'].update(recoveryLoadedHotels=0),
            lambda r: r['cases'][0]['proof'].pop('activeConstraintsVisible'),
            lambda r: r['cases'][0]['proof'].update(loadingInitialCountNeutral=False),
            lambda r: r['cases'][0]['proof'].update(loadingEmptyActionHidden=False),
            lambda r: r['cases'][0]['proof'].update(loadingExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(failedInitialCountNeutral=False),
            lambda r: r['cases'][0]['proof'].update(failedInitialFriendlyCopy=False),
            lambda r: r['cases'][0]['proof'].update(failedInitialExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(filteredCalendarTruthful=False),
            lambda r: r['cases'][0]['proof'].update(partialEmptyNeutralCount=False),
            lambda r: r['cases'][0]['proof'].update(partialSavedSearchRetained=False),
            lambda r: r['cases'][0]['proof'].update(partialLoadedHotels=0),
            lambda r: r['cases'][0]['proof'].update(partialExtraSearches=1),
            lambda r: r['cases'][0]['proof'].update(partialFilteredRecoveryLoadedHotels=0),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-filters-active-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-filter-empty-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-results-loading-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-results-error-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-results-partial-empty-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-results-partial-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-filter-empty-partial-')),
            lambda r: frame(r, 'native-results-loading-')['buttons'].append({'text':'Change search','clipped':False}),
            lambda r: frame(r, 'native-results-error-')['buttons'].clear(),
            lambda r: frame(r, 'native-results-partial-empty-')['buttons'].clear(),
            lambda r: frame(r, 'native-results-partial-empty-')['prices'].append({'text':'invented'}),
            lambda r: frame(r, 'native-filter-empty-')['prices'].append({'text':'invented'}),
            lambda r: frame(r, 'native-filters-active-')['prices'].clear(),
            lambda r: r['cases'][0]['proof'].update(progressiveDrawerRetainedDraftAndFocus=False),
            lambda r: r['cases'][0]['proof'].pop('budgetBreakpointDraftRetained'),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-hotel-return-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-calendar-later-')),
            lambda r: frame(r, 'native-calendar-later-').update(calendarFit=None),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit'].update(clipped=True),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit'].update(footScrollWidth=999),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit']['pager'].update(right=999),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit']['buttons'].pop(),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit']['buttons'][0].update(width=43),
            lambda r: frame(r, 'native-calendar-later-')['calendarFit']['buttons'][1].update(right=999),
            lambda r: frame(r, 'native-tour-').update(summaryFit=None),
            lambda r: frame(r, 'native-lead-')['summaryFit'].clear(),
            lambda r: frame(r, 'native-tour-')['summaryFit'][0].update(stacked=True),
            lambda r: frame(r, 'native-tour-')['summaryFit'][0].update(queryNarrow=True),
            lambda r: frame(r, 'native-results-').update(summaryFit=[{'className':'st-tour-hotel'}]),
            lambda r: frame(r, 'native-hotel-').update(offerActionFit=None),
            lambda r: frame(r, 'native-hotel-')['offerActionFit'].pop(),
            lambda r: frame(r, 'native-hotel-', 0)['offerActionFit'][0].update(outside=True),
            lambda r: frame(r, 'native-hotel-')['offerActionFit'][0].update(actionOnOwnRow=True),
            lambda r: frame(r, 'native-hotel-')['offerActionFit'][0].update(queryNarrow=True),
            lambda r: frame(r, 'native-results-').update(offerActionFit=[{'text':'Выбрать тур'}]),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-results-bounded-')),
            lambda r: frame(r, 'native-results-bounded-').update(screen='hotel'),
            lambda r: frame(r, 'native-budget-results-').update(file='native-unapproved-360-text100.png'),
            lambda r: frame(r, 'native-budget-invalid-')['budgetInputs'][0].update(clipped=True),
            lambda r: frame(r, 'native-budget-draft-')['budgetInputs'][0].update(textWidth=201.1),
            lambda r: frame(r, 'native-budget-draft-', 0)['budgetInputs'][0].update(font='500 15px Aeroport, system-ui, sans-serif'),
            lambda r: frame(r, 'native-budget-results-', 1)['budgetInputs'][0].update(font='500 13px Aeroport, system-ui, sans-serif'),
            lambda r: frame(r, 'native-budget-results-', 1)['budgetInputs'][0].update(font='500 26px Aeroport, system-ui, sans-serif'),
            lambda r: frame(r, 'native-budget-draft-')['budgetInputs'].pop(),
            lambda r: frame(r, 'native-results-')['readableText'][0].update(clipped=True),
            lambda r: frame(r, 'native-search-')['readableText'][0].update(overlappingLines=True),
            lambda r: frame(r, 'native-search-')['readableText'][0].pop('overlappingLines'),
            lambda r: frame(r, 'native-hotel-return-')['readableText'][0].update(brokenWords=True),
            lambda r: frame(r, 'native-tour-')['headerFit'].update(outside=True),
            lambda r: frame(r, 'native-lead-')['headerFit'].update(overlap=True),
            lambda r: frame(r, 'native-results-')['sortFit'].update(textWidth=202),
            lambda r: frame(r, 'native-results-', 1).update(budgetColumns=1),
            lambda r: frame(r, 'native-budget-results-')['prices'].clear()]
        for i, change in enumerate(changes):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json']); change(report)
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(index=i), self.assertRaises(ValueError):
                self.read_review(self.review_archive(mutate), 'bad-visual-evidence-' + str(i))
        with self.assertRaisesRegex(ValueError, 'native_rendered_evidence_inventory'):
            self.read_review(self.review_archive(lambda c: c.update({'evidence/unknown.png': b'unknown'})), 'extra-visual-frame')

    def test_calendar_navigation_requires_visible_dates_and_single_week_evidence(self):
        changes = [lambda r: r.update(requiredWidths=[360, 390, 430, 768, 1280]),
                   lambda r: r.update(requiredTextPercents=[100, 200])]
        for case in range(2):
            changes.extend((
                lambda r, c=case: r['cases'][c]['proof'].pop('calendarVisibleNavigation'),
                lambda r, c=case: r['cases'][c]['proof'].update(calendarSingleWeekNavigation=1),
                lambda r, c=case: r['cases'][c]['proof'].pop('calendarSingleWeekNavigation')))
            for key in ('lastPriceReachableByArrows', 'backwardDatesNotSkipped',
                        'unselectedBackPositionVisible', 'mobileRemountPositionRetained',
                        'selectedDateVisible', 'clearedDateVisible', 'backPositionVisible',
                        'implicitDayLocatorScroll'):
                changes.extend((
                    lambda r, c=case, k=key: r['cases'][c]['proof']['calendarVisibleNavigation'].pop(k),
                    lambda r, c=case, k=key: r['cases'][c]['proof']['calendarVisibleNavigation'].update({k: 1})))
        def frame(report):
            return next(row for row in report['cases'][0]['frames']
                        if row['file'].startswith('native-calendar-single-range-'))
        changes.extend((
            lambda r: r['cases'][0]['frames'].remove(frame(r)),
            lambda r: frame(r).update(screen='calendar'),
            lambda r: frame(r).pop('viewportHeight'),
            lambda r: frame(r).update(viewportHeight=720),
            lambda r: frame(r).update(viewportHeight=900.0),
            lambda r: frame(r).update(calendarFit=None),
            lambda r: frame(r)['calendarFit'].update(clipped=True),
            lambda r: frame(r)['calendarFit'].pop('strip'),
            lambda r: frame(r)['calendarFit']['strip'].update(dateRight=999),
            lambda r: frame(r)['calendarFit']['strip'].update(scrollLeft=148),
            lambda r: frame(r)['calendarFit']['strip'].update(clientWidth=True),
            lambda r: frame(r)['calendarFit']['buttons'][1].update(disabled=False),
            lambda r: next(row for row in frame(r)['readableText']
                           if row['kind'] == 'result-operator').update(text='FIXTURE TV')))
        self.assert_evidence_rejected(changes)

    def test_calendar_capture_requires_actual_viewport_png_dimensions(self):
        for case, prefix in ((0, 'native-calendar-later-'), (1, 'native-calendar-later-'),
                             (0, 'native-calendar-single-range-')):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json'])
                frame = next(row for row in report['cases'][case]['frames'] if row['file'].startswith(prefix))
                name = 'evidence/' + frame['file']; png = contents[name]
                contents[name] = png[:20] + struct.pack('>I', 720) + png[24:]
                frame['sha256'] = remote.digest(contents[name])
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(case=case, prefix=prefix), self.assertRaisesRegex(ValueError, 'native_calendar_viewport_capture_required'):
                self.read_review(self.review_archive(mutate), f'bad-calendar-png-{case}-{prefix}')

    def test_complete_selected_journey_cannot_be_replaced_by_frame_count_only(self):
        mutations = [
            lambda p: p.pop('screenNavigation'),
            lambda p: p['screenNavigation'].update(deepScroll=0),
            lambda p: p['screenNavigation'].update(resultAnchorDelta=200),
            lambda p: p['screenNavigation'].update(resultFocusRestored=False),
            lambda p: p['screenNavigation'].update(extraSearches=True),
            lambda p: p['screenNavigation'].pop('responsiveHiddenFocusFallback'),
            lambda p: p['screenNavigation'].update(responsiveHiddenFocusFallback=False),
            lambda p: p['screenNavigation'].update(responsiveRemountExtraOperations=True),
            lambda p: p['screenNavigation'].update(responsiveRemountExtraOperations=1),
            lambda p: p['managerContact'].update(flightChoiceId=0),
            lambda p: p['managerContact'].update(oldPairRemoved=False),
            lambda p: p['managerContact'].update(consentRevoked=False),
            lambda p: p['managerContact'].update(total=170400),
            lambda p: p['nonMinimumJourney'].update(exactOfferIndex=0),
            lambda p: p['nonMinimumJourney'].update(summaryAndContinueRetained=False),
            lambda p: p['nonMinimumJourney'].update(backReopenExtraOperations=1),
            lambda p: p['contactPriceBlockFit'].update(overflow=True),
            lambda p: p['contactPriceBlockFit'].update(captionCount=True),
            lambda p: p['manyFlightChoices'].update(initiallyVisible=40),
            lambda p: p['manyFlightChoices'].update(selectedCanonicalIndex=3),
            lambda p: p['manyFlightChoices'].update(selectedTotal=112000),
            lambda p: p['manyFlightChoices'].update(passiveExtraOperations=1),
            lambda p: p['manyFlightChoices']['managerBeforeList'].update(before=False),
            lambda p: p['manyFlightChoices']['pairLayout'].update(columns=7),
            lambda p: p.pop('groupMinimumOpenedHotelWithoutQuote'),
            lambda p: p.update(groupExplicitExpandCount=2),
            lambda p: p.update(contactExactItineraryRetained=False),
            lambda p: p.update(contactDraftAndConsentRetained=False),
        ]
        for index, change in enumerate(mutations):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json'])
                change(report['cases'][index % 2]['proof'])
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(index=index), self.assertRaises(ValueError):
                self.read_review(self.review_archive(mutate), 'bad-whole-journey-' + str(index))

    def test_tour_cost_label_evidence_is_required_for_each_tour_capture(self):
        captures = [('native-tour-', 0), ('native-tour-alternatives-', 0),
                    ('native-short-desktop-tour-', 1)]
        for capture_index, (prefix, case_index) in enumerate(captures):
            for label_index, label in enumerate(('Туристы', 'Туроператор', 'Обязательные доплаты')):
                for defect in ('missing', 'mislabelled', 'clipped', 'brokenWords', 'overlappingLines'):
                    def mutate(contents):
                        report = json.loads(contents['evidence/acceptance.json'])
                        frame = next(row for row in report['cases'][case_index]['frames']
                                     if row['file'].startswith(prefix))
                        text = next(row for row in frame['readableText'] if row['text'] == label)
                        if defect == 'missing': frame['readableText'].remove(text)
                        elif defect == 'mislabelled': text['text'] = 'unmeasured label'
                        else: text[defect] = True
                        contents['evidence/acceptance.json'] = remote.json_bytes(report)
                    expected = 'native_tour_cost_labels_required' if defect in ('missing', 'mislabelled') else 'native_text_readability_required'
                    with self.subTest(capture=prefix, label=label, defect=defect), self.assertRaisesRegex(ValueError, expected):
                        self.read_review(self.review_archive(mutate), f'bad-cost-label-{capture_index}-{label_index}-{defect}')

    def test_static_heading_focus_keeps_navigation_and_interactive_outline(self):
        for case_index in (0, 1):
            def focus(report): return report['cases'][case_index]['proof']['staticHeadingFocus']
            changes = [
                lambda r: r['cases'][case_index]['proof'].pop('staticHeadingFocus'),
                lambda r: r['cases'][case_index]['proof'].update(staticHeadingFocus=None),
                lambda r: focus(r).update(extra=True),
                lambda r: focus(r).update(loading=False),
                lambda r: focus(r).update(hotel=False),
                lambda r: focus(r).update(loading=1),
                lambda r: focus(r).update(hotel=1),
                lambda r: focus(r).pop('results'),
                lambda r: focus(r).update(results=None),
                lambda r: focus(r)['results'].update(focused=False),
                lambda r: focus(r)['results'].update(focused=1),
                lambda r: focus(r)['results'].update(tabIndex=0),
                lambda r: focus(r)['results'].update(tabIndex=-1.0),
                lambda r: focus(r)['results'].update(outline='auto'),
                lambda r: focus(r)['results'].update(extra=True),
                lambda r: focus(r).pop('control'),
                lambda r: focus(r).update(control=None),
                lambda r: focus(r)['control'].update(focused=False),
                lambda r: focus(r)['control'].update(visible=False),
                lambda r: focus(r)['control'].update(focused=1),
                lambda r: focus(r)['control'].update(visible=1),
                lambda r: focus(r)['control'].update(outline='none'),
                lambda r: focus(r)['control'].update(outline=''),
                lambda r: focus(r)['control'].update(outline='hidden'),
                lambda r: focus(r)['control'].update(width=0),
                lambda r: focus(r)['control'].update(width=-1),
                lambda r: focus(r)['control'].update(width=True),
                lambda r: focus(r)['control'].update(width='1px'),
                lambda r: focus(r)['control'].update(width=float('nan')),
                lambda r: focus(r)['control'].update(width=float('inf')),
                lambda r: focus(r)['control'].update(extra=True)]
            self.assert_evidence_rejected(changes)

    def test_result_facts_require_complete_identity_copy_and_readability(self):
        def frame(report, prefix='native-results-', case=0):
            return next(row for row in report['cases'][case]['frames'] if row['file'].startswith(prefix))
        def fact(report, kind, prefix='native-results-', case=0):
            return next(row for row in frame(report, prefix, case)['readableText'] if row['kind'] == kind)
        changes = []
        for prefix, case in (('native-results-', 0), ('native-short-desktop-results-', 1),
                             ('native-results-bounded-', 0)):
            for kind in ('result-title', 'result-location', 'result-date', 'result-room',
                         'result-meal', 'result-operator', 'result-party'):
                changes.extend((
                    lambda r, p=prefix, c=case, k=kind: frame(r, p, c)['readableText'].remove(fact(r, k, p, c)),
                    lambda r, p=prefix, c=case, k=kind: fact(r, k, p, c).update(kind='other'),
                    lambda r, p=prefix, c=case, k=kind: fact(r, k, p, c).update(text='Lost factual copy')))
            for field in ('clipped', 'brokenWords', 'overlappingLines'):
                changes.append(lambda r, p=prefix, c=case, f=field: fact(r, 'result-title', p, c).update({f: True}))
        changes.extend((
            lambda r: frame(r).pop('resultCardIds'),
            lambda r: frame(r).update(resultCardIds=None),
            lambda r: frame(r).update(resultCardIds=[True, 2, 3, 4]),
            lambda r: frame(r).update(resultCardIds=[1.0, 2, 3, 4]),
            lambda r: frame(r).update(resultCardIds=[1, 2, 3, 3]),
            lambda r: frame(r).update(resultCardIds=[2, 1, 3, 4]),
            lambda r: frame(r).update(resultCardIds=[1, 2, 3, 5]),
            lambda r: frame(r).update(resultCardIds=[]),
            lambda r: fact(r, 'result-title').pop('kind'),
            lambda r: fact(r, 'result-title').pop('cardId'),
            lambda r: fact(r, 'result-title').update(kind='unreviewed-role'),
            lambda r: fact(r, 'result-title').update(cardId=True),
            lambda r: fact(r, 'result-title').update(cardId=1.0),
            lambda r: fact(r, 'result-title').update(cardId=None),
            lambda r: fact(r, 'result-title').update(cardId=2),
            lambda r: frame(r)['readableText'].append(copy.deepcopy(fact(r, 'result-title'))),
            lambda r: frame(r, 'native-results-bounded-', 0)['resultCardIds'].pop(),
            lambda r: frame(r, 'native-results-bounded-', 0)['resultCardIds'].append(1024)))
        self.assert_evidence_rejected(changes)

    def test_shared_notice_requires_one_truthful_owner_and_correct_scope(self):
        def frame(report, prefix='native-results-', case=0):
            return next(row for row in report['cases'][case]['frames'] if row['file'].startswith(prefix))
        def note(report, prefix='native-results-', case=0):
            return next(row for row in frame(report, prefix, case)['readableText'] if row['kind'] == 'result-notice')
        changes = []
        for prefix, case in (('native-results-', 0), ('native-short-desktop-results-', 1)):
            changes.extend((
                lambda r, p=prefix, c=case: frame(r, p, c).pop('resultNotice'),
                lambda r, p=prefix, c=case: frame(r, p, c).update(resultNotice=None),
                lambda r, p=prefix, c=case: frame(r, p, c)['resultNotice'].update(extra=True),
                lambda r, p=prefix, c=case: frame(r, p, c)['readableText'].remove(note(r, p, c)),
                lambda r, p=prefix, c=case: note(r, p, c).update(kind='other'),
                lambda r, p=prefix, c=case: note(r, p, c).update(cardId=1),
                lambda r, p=prefix, c=case: note(r, p, c).update(text='Цена уже подтверждена')))
            for field, value in (('visibleCount', 0), ('visibleCount', 2), ('visibleCount', True),
                                 ('visibleCount', 1.0), ('afterBatch', False), ('afterBatch', 1),
                                 ('cardNoticeCount', 1), ('cardNoticeCount', False),
                                 ('text', 'Цена подтверждена'), ('text', 'За одного туриста')):
                changes.append(lambda r, p=prefix, c=case, f=field, v=value: frame(r, p, c)['resultNotice'].update({f: v}))
            for field in ('clipped', 'brokenWords', 'overlappingLines'):
                changes.append(lambda r, p=prefix, c=case, f=field: note(r, p, c).update({f: True}))
        for prefix in ('native-results-loading-', 'native-results-partial-empty-', 'native-filter-empty-', 'native-lead-'):
            changes.extend((
                lambda r, p=prefix: frame(r, p).update(resultCardIds=[1]),
                lambda r, p=prefix: frame(r, p)['resultNotice'].update(visibleCount=1, afterBatch=True, text='Unexpected price notice'),
                lambda r, p=prefix: frame(r, p)['readableText'].append(copy.deepcopy(note(r)))))
        self.assert_evidence_rejected(changes)

    def test_form_focus_proofs_are_required_in_every_case(self):
        changes = []
        for case in range(2):
            for key in ('nightKeyboardFocusRetained', 'formCalendarPagingFocusRetained', 'familyInputFocusRetained', 'destinationKeyboardFocusRetained', 'formPopupSingleSubmitRetained'):
                changes.extend((
                    lambda r, c=case, k=key: r['cases'][c]['proof'].pop(k),
                    lambda r, c=case, k=key: r['cases'][c]['proof'].update({k: False}),
                    lambda r, c=case, k=key: r['cases'][c]['proof'].update({k: 1})))
        self.assert_evidence_rejected(changes)

    def test_popup_frames_and_one_visible_native_submit_are_required(self):
        def frame(report):
            return next(row for row in report['cases'][0]['frames'] if row['file'].startswith('native-popup-calendar-'))
        changes = [lambda r: frame(r).pop('popupFit'), lambda r: frame(r)['popupFit'].update(editor='nights'),
            lambda r: frame(r)['popupFit'].update(modal=1), lambda r: frame(r)['popupFit'].update(outside=True),
            lambda r: frame(r)['popupFit'].update(panelOverflow=True), lambda r: frame(r)['popupFit'].update(findVisible=False),
            lambda r: frame(r)['popupFit'].update(findEnabled=False), lambda r: frame(r)['popupFit'].update(findHeight=43),
            lambda r: frame(r)['popupFit'].update(findHeight=True), lambda r: frame(r)['popupFit'].update(findHeight=float('nan')),
            lambda r: frame(r)['popupFit'].update(submitCount=True), lambda r: frame(r)['popupFit'].update(submitCount=2)]
        def nights(report):
            return next(row for row in report['cases'][0]['frames'] if row['file'].startswith('native-popup-nights-'))
        changes.extend((lambda r: nights(r)['readableText'].pop(),
            lambda r: nights(r)['readableText'][-1].update(kind='other'),
            lambda r: nights(r)['readableText'][-1].update(text='21 ночей'),
            lambda r: nights(r)['readableText'][-1].update(clipped=True)))
        self.assert_evidence_rejected(changes)

    def test_destination_frames_decisions_and_readable_actions_are_required(self):
        def frame(report, case=0):
            return next(row for row in report['cases'][case]['frames'] if row['file'].startswith('native-destination-confirm-'))
        changes = [lambda r: frame(r).pop('destinationFit'),
            lambda r: r['cases'][0]['frames'].remove(frame(r)),
            lambda r: frame(r)['destinationFit'].update(outside=True),
            lambda r: frame(r)['destinationFit'].update(overlap=True),
            lambda r: frame(r)['destinationFit'].update(fontSize=True),
            lambda r: frame(r)['destinationFit'].update(queryNarrow=True),
            lambda r: frame(r)['destinationFit']['actions'].pop(),
            lambda r: frame(r)['destinationFit']['actions'][0].update(height=43),
            lambda r: frame(r)['destinationFit']['actions'][0].update(width=True),
            lambda r: frame(r)['destinationFit']['actions'][0].update(disabled=True),
            lambda r: frame(r)['destinationFit']['actions'][0].update(text='Не проверено'),
            lambda r: frame(r)['destinationFit']['actions'][1].update(left=0),
            lambda r: frame(r)['readableText'].pop(),
            lambda r: frame(r)['readableText'][-1].update(brokenWords=True),
            lambda r: frame(r)['readableText'][-3].update(text='Отмен')]
        self.assert_evidence_rejected(changes)

    def test_unstarted_proof_copy_and_recomputed_toolbar_geometry_are_required(self):
        def frame(report):
            return next(row for row in report['cases'][0]['frames']
                        if row['file'].startswith('native-initial-favorites-'))
        changes = [lambda r: r['cases'][0]['proof'].pop('presearchNavigationTruthful'),
            lambda r: r['cases'][0]['proof'].update(presearchNavigationTruthful=1),
            lambda r: r['cases'][0]['frames'].remove(frame(r)),
            lambda r: frame(r).update(screen='search'),
            lambda r: frame(r)['prices'].append({'text': 'invented availability'}),
            lambda r: frame(r)['readableText'][1].update(text='Нет подходящих туров'),
            lambda r: frame(r)['buttons'][0].update(text='Изменить даты'),
            lambda r: frame(r).pop('countFit'),
            lambda r: frame(r)['countFit'].update(text='0 отелей'),
            lambda r: frame(r)['countFit'].update(textRects=[]),
            lambda r: frame(r)['countFit'].update(controls=[]),
            lambda r: frame(r)['countFit']['toolbar'].update(right=True),
            lambda r: frame(r)['countFit']['textRects'][0].update(right=500),
            lambda r: frame(r)['countFit']['textRects'][0].update(left=True),
            # False summary flags cannot hide actual overlapping rectangles.
            lambda r: frame(r)['countFit']['controls'][0].update(left=20, right=90),
            lambda r: frame(r)['countFit'].update(overlap=True)]
        self.assert_evidence_rejected(changes)

    def test_terminal_status_owner_and_zero_extra_searches_are_required_in_every_case(self):
        def frame(report, case, status):
            return next(row for row in report['cases'][case]['frames']
                        if row['file'].startswith('native-terminal-' + status + '-'))
        changes = []
        for case in range(2):
            for key, wrong in (('latePresentationTerminalStatesRetained', 1),
                               ('latePresentationExtraSearches', False)):
                changes.extend((lambda r, c=case, k=key: r['cases'][c]['proof'].pop(k),
                    lambda r, c=case, k=key, v=wrong: r['cases'][c]['proof'].update({k: v})))
            for status in ('complete', 'partial', 'error'):
                changes.extend((
                    lambda r, c=case, s=status: r['cases'][c]['frames'].remove(frame(r, c, s)),
                    lambda r, c=case, s=status: frame(r, c, s).update(screen='search'),
                    lambda r, c=case, s=status: frame(r, c, s).pop('terminalState')))
                for key, wrong in (('status', 'loading'), ('message', 'changed'),
                    ('searchGeneration', True), ('searchStarts', 2), ('otherCalls', 1),
                    ('alertVisible', status == 'complete'),
                    ('alertText', 'unexpected' if status == 'complete' else ''),
                    ('ownerSHA256', 'f' * 64)):
                    changes.append(lambda r, c=case, s=status, k=key, v=wrong:
                                   frame(r, c, s)['terminalState'].update({k: v}))
                changes.extend((
                    lambda r, c=case, s=status: frame(r, c, s)['terminalState'].update(otherCalls=False),
                    lambda r, c=case, s=status: frame(r, c, s)['terminalState'].update(extra=True),
                    lambda r, c=case, s=status: frame(r, c, s)['headerFit'].update(outside=True),
                    lambda r, c=case, s=status: frame(r, c, s)['resultCardIds'].pop(),
                    lambda r, c=case, s=status: frame(r, c, s)['prices'].clear()))
        self.assert_evidence_rejected(changes)

    def test_family_and_short_desktop_proof_cannot_be_missing_or_mislabelled(self):
        def frame(report, prefix, case=0):
            return next(row for row in report['cases'][case]['frames'] if row['file'].startswith(prefix))
        changes = [lambda r: r['cases'][0]['proof'].pop('familyAges'),
            lambda r: r['cases'][0]['proof'].update(familyAges=[0, 7, 16]),
            lambda r: r['cases'][0]['proof'].update(familyAges=[False, 7, 17]),
            lambda r: r['cases'][0]['proof'].update(familyAges=[0.0, 7, 17]),
            lambda r: r['cases'][0]['proof'].update(familyExtraSearches=1),
            lambda r: r['cases'][0]['proof'].pop('familyAgeReviewRetained'),
            lambda r: r['cases'][0]['proof'].update(familyAgeReviewRetained=False),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-family-0-')),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-family-review-')),
            lambda r: frame(r, 'native-family-3-').update(screen='results'),
            lambda r: frame(r, 'native-family-1-')['readableText'][1].update(text='1 взрослых + 1 детей'),
            lambda r: frame(r, 'native-family-applied-')['readableText'].pop(),
            lambda r: frame(r, 'native-family-review-')['readableText'].pop(),
            lambda r: frame(r, 'native-family-review-')['readableText'][2].update(text='Сохранённые возраста'),
            lambda r: frame(r, 'native-family-2-')['readableText'][1].update(clipped=True),
            lambda r: frame(r, 'native-family-0-')['prices'].append({'text': 'invented family price'}),
            lambda r: frame(r, 'native-family-2-').update(sha256='f' * 64),
            lambda r: r['cases'][1]['frames'].remove(frame(r, 'native-short-desktop-tour-', 1)),
            lambda r: frame(r, 'native-short-desktop-results-', 1).update(screen='short'),
            lambda r: frame(r, 'native-short-desktop-search-', 1).pop('viewportHeight'),
            lambda r: frame(r, 'native-short-desktop-search-', 1).update(viewportHeight=900),
            lambda r: frame(r, 'native-short-desktop-search-', 1).update(viewportHeight=720.0),
            lambda r: frame(r, 'native-short-desktop-hotel-', 1).update(sha256='f' * 64),
            lambda r: frame(r, 'native-short-desktop-tour-', 1)['prices'][0].update(fragmented=True),
            lambda r: frame(r, 'native-short-desktop-lead-', 1)['summaryFit'].clear()]
        for i, change in enumerate(changes):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json']); change(report)
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(index=i), self.assertRaises(ValueError):
                self.read_review(self.review_archive(mutate), 'bad-family-short-evidence-' + str(i))

    def test_short_desktop_png_dimensions_are_checked_even_with_matching_hash(self):
        for i, dimensions in enumerate(((1280, 900), (1279, 720))):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json'])
                frame = next(row for row in report['cases'][1]['frames']
                             if row['file'].startswith('native-short-desktop-search-'))
                name = 'evidence/' + frame['file']; value = contents[name]
                contents[name] = value[:16] + struct.pack('>II', *dimensions) + value[24:]
                frame['sha256'] = remote.digest(contents[name])
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(dimensions=dimensions), self.assertRaisesRegex(ValueError, 'native_short_desktop_png_dimensions_required'):
                self.read_review(self.review_archive(mutate), 'bad-short-png-' + str(i))

    def test_startup_proof_requires_exact_ten_ordinary_frames_no_prices_and_readable_retry(self):
        def frame(report, name):
            return next(row for row in report['cases'][0]['frames'] if row['file'].startswith(name))
        changes = [lambda r: r['cases'][0]['proof'].update(startupPendingFormHidden=False),
            lambda r: r['cases'][0]['proof'].update(startupErrorSearches=1),
            lambda r: r['cases'][0]['proof'].pop('startupRetrySearches'),
            lambda r: r['cases'][0]['frames'].remove(frame(r, 'native-startup-pending-')),
            lambda r: frame(r, 'native-startup-error-').update(file='native-startup-unapproved-360-text100.png'),
            lambda r: frame(r, 'native-startup-pending-').update(stageHidden=True),
            lambda r: frame(r, 'native-startup-pending-').update(screen='results'),
            lambda r: frame(r, 'native-startup-pending-')['prices'].append({'fontSize': 29}),
            lambda r: frame(r, 'native-startup-error-')['buttons'].clear(),
            lambda r: frame(r, 'native-startup-error-')['buttons'][0].update(clipped=True),
            lambda r: frame(r, 'native-startup-pending-')['readableText'][0].update(clipped=True),
            lambda r: frame(r, 'native-startup-pending-')['readableText'][0].update(overlappingLines=True),
            lambda r: frame(r, 'native-startup-error-')['readableText'][0].pop('overlappingLines'),
            lambda r: frame(r, 'native-startup-error-')['headerFit'].update(overlap=True)]
        for i, change in enumerate(changes):
            def mutate(contents):
                report = json.loads(contents['evidence/acceptance.json']); change(report)
                contents['evidence/acceptance.json'] = remote.json_bytes(report)
            with self.subTest(index=i), self.assertRaises(ValueError):
                self.read_review(self.review_archive(mutate), 'bad-startup-evidence-' + str(i))

    def test_native_inventory_cannot_accept_extra_or_missing_asset(self):
        path = self.source / 'payload/visual-search/native-v2/not-reviewed.js'; path.write_text('foreign')
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'):
            pub.derive(self.source, self.baseline, self.root / 'extra-native', self.q, 'e' * 64)
        path.unlink(); (self.source / 'payload' / remote.NATIVE_ASSETS[0]).unlink()
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'):
            pub.derive(self.source, self.baseline, self.root / 'missing-native', self.q, 'e' * 64)

    def test_native_entry_rejects_legacy_renderer_even_after_manifest_rehash(self):
        payload = self.source / 'payload'; path = payload / 'visual-search/index.html'
        path.write_text(path.read_text() + '<script src="./app.js" defer></script>')
        manifest_path = payload / remote.NATIVE_MANIFEST; native = json.loads(manifest_path.read_bytes())
        native['entry']['html'] = remote.digest(path.read_bytes()); manifest_path.write_text(json.dumps(native, indent=2) + '\n')
        pin = {**self.native_pin, 'entry_html_sha256': native['entry']['html'], 'source_manifest_sha256': remote.digest(manifest_path.read_bytes())}
        with patch.object(remote, 'NATIVE_ACCEPTANCE', pin), self.assertRaisesRegex(ValueError, 'native_exact_entry_graph'):
            remote.native_graph(payload)

    def test_native_checksum_and_route_reversal_protect_original_owner(self):
        manifest = self.root / 'derived/payload' / remote.NATIVE_MANIFEST; native = json.loads(manifest.read_bytes())
        native['graph']['scripts'][0]['sha256'] = 'f' * 64; manifest.write_text(json.dumps(native, indent=2) + '\n')
        with self.assertRaisesRegex(ValueError, 'native_graph_checksum'): remote.native_graph(self.root / 'derived/payload', rewritten=True)

    def test_owner_main_event_and_attempt_guards(self):
        for key, value in [('GITHUB_REF', 'refs/heads/work'), ('GITHUB_ACTOR', 'other'),
            ('GITHUB_TRIGGERING_ACTOR', 'other'), ('GITHUB_ACTOR_ID', '0'), ('GITHUB_RUN_ATTEMPT', '2'),
            ('GITHUB_EVENT_NAME', 'workflow_dispatch'), ('GITHUB_REPOSITORY', 'other/repo')]:
            with self.subTest(key=key), self.assertRaises(ValueError): pub.checked_request(self.event, {**self.env, key: value})
        for key, field, value in [('issue', 'number', 4217), ('issue', 'number', 3419),
            ('issue', 'number', 2530), ('issue', 'number', 996), ('issue', 'number', 4708),
            ('issue', 'pull_request', {}), ('sender', 'id', 1),
            ('comment', 'author_association', 'MEMBER'), ('comment', 'user', {'id': 1}), ('repository', 'id', 1)]:
            event = copy.deepcopy(self.event); event[key][field] = value
            with self.subTest(key=key, field=field), self.assertRaises(ValueError): pub.checked_request(event, self.env)

    def test_bad_syntax_and_old_source_rejected(self):
        good = self.event['comment']['body']
        for body in [good + '\n', good + ';id', good + ' /_preview/search3-next-candidate/',
            good.replace(pub.PREFIX, creator.PREFIX), good.replace(' 123 ', '  123 '),
            good.replace('a' * 40, remote.PREDECESSOR_SOURCE),
            good.replace('a' * 40, remote.ORIGINAL_SOURCE), good.replace(' 123 ', ' 0 ')]:
            event = copy.deepcopy(self.event); event['comment']['body'] = body
            with self.subTest(body=body), self.assertRaises(ValueError): pub.checked_request(event, self.env)

    def test_all_completed_sources_rejected_by_both_owners(self):
        self.assertEqual(remote.COMPLETED_SOURCES, (remote.ORIGINAL_SOURCE,
            '950c9b5c0a00430c5b5a711d1b95bc04449d069d',
            '634d1c97fd7bf499107d82036588f4bd77176d93',
            '2eb237639c6c53c38f27d71684de43384842daf7',
            'a0e5f1e7c0083b45678df5837c7faf1cad8751f6',
            '16196410a8cb90b10e8d06b7ac8b38d4ce7d235d',
            'b53d5ca0bf541e66b684018fb7db332de8fefaf3',
            '4955ee078aa0827f745d737681b4ed9187a274bd',
            '0674fa6cc7744ee21cb073b284f089c20171f38e',
            '8c2d0308eb29e26d2819342d10501db1ed82e3db',
            '9fec34cfeb5a7c97659b16188404ae02c166c770',
            '20c6488c01d764ce9bdc19c7eb71f01108efbda5',
            '9de4cdb3d044b060b039abc096f9351466147d86',
            '5a84a0d66394116f9845de3565563ce1d025b6bc',
            '75e52f2597172469fbcb6f742dc8c7b5934cd173',
            '660a760d9d0a0c0121d05d53bdc0895689600bc8',
            '08a99f2dfe2f420c9c03a6eb451d00e9a04e5026',
            'a3b0fa5125adb1a90b32a49d5f27a6b8852f5510',
            'd341f7648749f2f8a3272a1e51e2d923c1db80a6',
            '002dc0d636c5ad6e5597d37259baba574fe9ed88',
            'f4b343ecce2411899ce2f2138c8fc5f64ea27eb5',
            '67468aa3ba24526ee7c32d3e57bf2d7a28bd9190',
            'e42e2a19c134a79127cb22a6dd26fc1abcd19e41',
            '2da88e41fb436acefd293066bcc98db013e07ee0',
            'fbbd23e39c4fa71f8ba9500048ef4f2a3c2f1735',
            '740ed0b20596f901a1d6c15d238cec527633ce80',
            'b8b14e89931fbaab363895065d53ed7063339eb3',
            'af7ec8fd2a8af89efdf0253619ae9bd63ac756ba',
            '41dd5a9c013b9431f68e38b85de5b7a72ae6848d',
            'b7cbb1e372cd083d8402a970ec7d16bf720c2a34',
            'ecdb5a7768366a80732f5788ea2446ebd3bcc51e',
            '54b66f34d9dc2fdb1d84e4b7a6e62fc644974241',
            '8d3d66eeb95ac69534ec7557c7c0926cf201543c',
            '04667912bb1f3fb81c89edaf43b1a40b7d5f6e1b',
            '0fc16325635d113fafa96081f295a1a5696359c3',
            'f99306ffe3725f2badc70096d801608fc5393009',
            '1959dcabac17335fd09763389231e67db9afde6b',
            'dc69049f126cac2ae516f5ee41e886a7cbfc8c2d',
            '246670818bba433adc9b002e06b2d560f46eb6ac',
            '756f8b4b0eec662e8b78c22e4b2a36dfe8e8669d',
            '185739d46757bd34a05709e3656eab76d1003fa5',
            '84897fd5778debe040b74945212777aaf878d83e',
            '2dc409517e88493d9d24e46cafe95e462aa45819'))
        with patch.object(remote, 'PREDECESSOR_SOURCE', 'f' * 40):
            self.assertIn('16196410a8cb90b10e8d06b7ac8b38d4ce7d235d', remote.COMPLETED_SOURCES)
            self.assertIn('b53d5ca0bf541e66b684018fb7db332de8fefaf3', remote.COMPLETED_SOURCES)
            self.assertIn('4955ee078aa0827f745d737681b4ed9187a274bd', remote.COMPLETED_SOURCES)
            self.assertIn('0674fa6cc7744ee21cb073b284f089c20171f38e', remote.COMPLETED_SOURCES)
            self.assertIn('8c2d0308eb29e26d2819342d10501db1ed82e3db', remote.COMPLETED_SOURCES)
        for source in remote.COMPLETED_SOURCES:
            event = copy.deepcopy(self.event); event['comment']['body'] = event['comment']['body'].replace('a' * 40, source)
            with self.subTest(source=source), self.assertRaisesRegex(ValueError, 'new_source_required'):
                pub.checked_request(event, self.env)
            with self.subTest(source=source), self.assertRaisesRegex(ValueError, 'new_source_required'):
                remote.validate({**self.q, 'source_sha': source})

    def test_exact_source_native_ci_checks_required(self):
        checks = [{'name': n, 'head_sha': self.q['source_sha'], 'status': 'completed', 'conclusion': 'success',
            'app': {'slug': 'github-actions'}, 'started_at': '2026-10-11T21:30:00Z'} for n in pub.REQUIRED_CHECKS]
        class API:
            def get(self, path): return {'total_count': len(checks), 'check_runs': checks}
        pub.strict_checks(API(), self.q['source_sha'])
        for key, value in [('conclusion', 'skipped'), ('head_sha', 'f' * 40), ('status', 'in_progress'), ('app', {'slug': 'other'})]:
            previous = checks[0][key]; checks[0][key] = value
            with self.subTest(key=key), self.assertRaises(ValueError): pub.strict_checks(API(), self.q['source_sha'])
            checks[0][key] = previous
        checks.pop()
        with self.assertRaisesRegex(ValueError, 'required_visual_checks_missing'): pub.strict_checks(API(), self.q['source_sha'])

    def test_native_provenance_requires_exact_reviewed_merge_commits_and_relation(self):
        source_parents = (('1' * 40, '3' * 40), ('2' * 40, '4' * 40))
        release_parents = (('2' * 40, '4' * 40), ('a' * 40, 'd' * 40))
        source_pin = patch.object(pub, 'NATIVE_SOURCE_PARENTS', source_parents)
        release_pin = patch.object(pub, 'NATIVE_RELEASE_PARENTS', release_parents)
        source_pin.start(); release_pin.start()
        self.addCleanup(source_pin.stop); self.addCleanup(release_pin.stop)
        checks = [{'name': n, 'head_sha': 'a' * 40, 'status': 'completed', 'conclusion': 'success',
            'app': {'slug': 'github-actions'}, 'started_at': '2026-10-12T15:00:00Z'}
            for n in pub.REQUIRED_CHECKS]
        values = {
            '/git/ref/heads/main': {'object': {'sha': 'f' * 40}},
            '/git/ref/heads/' + pub.RELEASE: {'object': {'sha': 'c' * 40}},
            '/git/commits/' + 'a' * 40: {'tree': {'sha': 'd' * 40},
                'parents': [{'sha': sha} for sha, _ in source_parents]},
            '/git/commits/' + 'c' * 40: {'tree': {'sha': 'd' * 40},
                'parents': [{'sha': sha} for sha, _ in release_parents]},
            '/git/commits/' + '1' * 40: {'tree': {'sha': '3' * 40}},
            '/git/commits/' + '2' * 40: {'tree': {'sha': '4' * 40}},
            '/compare/' + 'a' * 40 + '...' + 'c' * 40: {'status': 'ahead', 'behind_by': 0, 'ahead_by': 1,
                'merge_base_commit': {'sha': 'a' * 40}},
            '/actions/runs/123': {'head_sha': 'a' * 40, 'path': remote.NATIVE_BUILD_WORKFLOW,
                'event': 'pull_request', 'status': 'completed', 'conclusion': 'success', 'run_attempt': 1,
                'repository': {'full_name': pub.REPO}, 'head_repository': {'full_name': pub.REPO}},
            '/commits/' + 'a' * 40 + '/check-runs?per_page=100': {'total_count': len(checks), 'check_runs': checks},
            '/actions/artifacts/456': {'expired': False, 'workflow_run': {'id': 123, 'head_sha': 'a' * 40},
                'name': 'search3-design-v2-native-' + 'a' * 40 + '-123', 'digest': 'sha256:' + 'e' * 64},
        }
        class API:
            def __init__(self, rows): self.rows = rows
            def get(self, path): return self.rows[path]
        q = {'source_sha': 'a' * 40, 'release_sha': 'c' * 40, 'build_run': 123, 'artifact_id': 456}
        self.assertEqual(pub.native_provenance(API(values), q, 'f' * 40), ('d' * 40, 'e' * 64))
        mutations = (
            ('native_source_parent_pin', '/git/commits/' + 'a' * 40, lambda row: row.update(parents=[])),
            ('native_source_parent_pin', '/git/commits/' + 'a' * 40, lambda row: row.update(parents=[{'sha': '0' * 40}])),
            ('native_source_parent_pin', '/git/commits/' + 'a' * 40,
             lambda row: row['parents'].append({'sha': '0' * 40})),
            ('native_source_parent_pin', '/git/commits/' + 'a' * 40,
             lambda row: row.update(parents=list(reversed(row['parents'])))),
            ('native_source_parent_tree_pin', '/git/commits/' + '1' * 40,
             lambda row: row['tree'].update(sha='0' * 40)),
            ('native_release_tree_pin', '/git/commits/' + 'c' * 40, lambda row: row['tree'].update(sha='0' * 40)),
            ('native_release_parent_pin', '/git/commits/' + 'c' * 40, lambda row: row.update(parents=[{'sha': '0' * 40}])),
            ('native_release_parent_pin', '/git/commits/' + 'c' * 40,
             lambda row: row.update(parents=list(reversed(row['parents'])))),
            ('native_source_parent_tree_pin', '/git/commits/' + '2' * 40,
             lambda row: row['tree'].update(sha='0' * 40)),
            ('native_exact_merge_relation', '/compare/' + 'a' * 40 + '...' + 'c' * 40,
             lambda row: row.update(status='diverged')),
            ('native_exact_merge_relation', '/compare/' + 'a' * 40 + '...' + 'c' * 40,
             lambda row: row.update(behind_by=1)),
            ('native_exact_merge_relation', '/compare/' + 'a' * 40 + '...' + 'c' * 40,
             lambda row: row.update(ahead_by=2)),
            ('native_exact_merge_relation', '/compare/' + 'a' * 40 + '...' + 'c' * 40,
             lambda row: row['merge_base_commit'].update(sha='0' * 40)),
        )
        for i, (message, path, mutate) in enumerate(mutations):
            rows = copy.deepcopy(values); mutate(rows[path])
            with self.subTest(index=i), self.assertRaisesRegex(ValueError, message):
                pub.native_provenance(API(rows), q, 'f' * 40)
        rows = copy.deepcopy(values)
        rows['/git/commits/' + 'a' * 40]['parents'] = [{'sha': '0' * 40}]
        with self.assertRaisesRegex(ValueError, 'native_source_parent_pin'):
            pub.native_provenance(API(rows), {**q, 'source_parent': '0' * 40, 'source_commit_count': 1}, 'f' * 40)

    def test_only_eight_reviewed_presentation_paths_can_differ(self):
        self.assertEqual(set(remote.SOURCE_CHANGES), {
            'visual-search/app.js', 'visual-search/asset-size.json',
            'visual-search/hotel-details-v1.js', 'visual-search/offer-list-v1.js',
            'visual-search/index.html', 'visual-search/migration-manifest.json',
            'visual-search/mobile-controls-v1.css', 'visual-search/styles.css'})
        before = remote.inventory(self.source / 'payload'); after = remote.inventory(self.root / 'derived/payload')
        self.assertEqual(set(before), set(after)); self.assertEqual(len(after), 855)
        self.assertEqual({n for n in before if before[n] != after[n]}, set(remote.NATIVE_ROUTE_CHANGES))
        m = json.loads((self.root / 'derived/control/manifest.json').read_bytes())
        self.assertEqual(m['derived_from']['changed_source_payload_files'], sorted(['visual-search/app.js', *remote.NATIVE_REPLACED, *remote.NATIVE_ADDED]))
        q2, a2 = pub.derive(self.source, self.baseline, self.root / 'again', {k: self.q[k] for k in original_request()}, 'e' * 64)
        self.assertEqual(q2, self.q); self.assertEqual(a2, self.archive)

    def test_hotel_and_offer_presentation_files_retain_every_other_payload_byte(self):
        for i, name in enumerate(('visual-search/hotel-details-v1.js', 'visual-search/offer-list-v1.js')):
            source_path = self.source / 'payload' / name; original = source_path.read_bytes()
            source_path.write_bytes(b'reviewed final Sites presentation')
            output = self.root / ('hotel-presentation-' + str(i))
            try:
                q, _ = pub.derive(self.source, self.baseline, output, self.q, 'e' * 64)
                remote.verify(output, q)
                manifest = json.loads((output / 'control/manifest.json').read_bytes())
                self.assertEqual(manifest['derived_from']['changed_source_payload_files'],
                    sorted(['visual-search/app.js', name, *remote.NATIVE_REPLACED, *remote.NATIVE_ADDED]))
                before = remote.inventory(self.source / 'payload'); after = remote.inventory(output / 'payload')
                self.assertEqual({n for n in before if before[n] != after[n]}, set(remote.NATIVE_ROUTE_CHANGES))
                self.assertEqual((output / 'payload' / name).read_bytes(), source_path.read_bytes())
            finally:
                source_path.write_bytes(original)

    def test_original_baseline_remains_independent_of_installed_predecessor(self):
        manifest = json.loads((self.root / 'derived/control/manifest.json').read_bytes())
        self.assertEqual(manifest['derived_from']['baseline'], {
            'source_sha': creator.SOURCE_SHA, 'build_run': creator.BUILD_RUN,
            'artifact_id': creator.ARTIFACT_ID, 'ZIP_sha256': creator.SOURCE_ZIP_SHA256})
        self.assertNotEqual(remote.ORIGINAL_SOURCE, remote.PREDECESSOR_SOURCE)
        with patch('search3_preview_publish.prepare_zip') as prepare_zip, patch.object(pub, 'prepare_native_zip'):
            class API:
                def download(self, artifact_id): return b'synthetic ZIP'
            with patch.object(pub, 'derive', return_value=(self.q, self.archive)):
                pub.prepare_inputs(API(), self.q, 'd' * 40, 'e' * 64, self.root / 'inputs')
        baseline_request = prepare_zip.call_args_list[0].args[1]
        self.assertEqual(baseline_request['source_sha'], remote.ORIGINAL_SOURCE)
        self.assertEqual(baseline_request['release_sha'], remote.ORIGINAL_SOURCE)

    def test_all_completed_publish_runs_cannot_replay(self):
        self.assertEqual(remote.COMPLETED_PUBLISH_RUNS,
            (remote.ORIGINAL_PUBLISH_RUN, 38180461319, 38191104207,
                38197437835, 38203171197, 38207863194, 38237246171, 38238503101,
             38255222254, 38269206819, 38276638172, 38290989467, 38312997899,
             38320934646, 38331033682, 38336349915, 38354248961, 38363323689, 38388948150, 38404222823,
             38413259110, 38420411530, 38429562174, 38437139126, 38444580624, 38451804327,
             38464277088, 38477454593, 38484937090, 38493989503, 38499121754, 38505456663,
             38514276332, 38522678669, 38534763188, 38558081728, 38941719240, 39242956656, 39254022688,
             39278795885, 39291592436, 39333812766, 39346251452, 39354674302))
        with patch.object(remote, 'PREDECESSOR_RUN', 999):
            self.assertIn(38207863194, remote.COMPLETED_PUBLISH_RUNS)
            self.assertIn(38237246171, remote.COMPLETED_PUBLISH_RUNS)
            self.assertIn(38238503101, remote.COMPLETED_PUBLISH_RUNS)
            self.assertIn(38255222254, remote.COMPLETED_PUBLISH_RUNS)
            self.assertIn(38269206819, remote.COMPLETED_PUBLISH_RUNS)
            self.assertIn(38276638172, remote.COMPLETED_PUBLISH_RUNS)
        for deploy_run in remote.COMPLETED_PUBLISH_RUNS:
            with self.subTest(deploy_run=deploy_run), self.assertRaisesRegex(ValueError, 'no_replay'):
                remote.validate({**self.q, 'deploy_run': deploy_run})

    def test_historical_creation_cannot_replace_current_predecessor(self):
        before = {'target': 'f8e4742f6964dd1237dc86ba27c0d3379a53b57ca4f45d6c16e713f392b43aef',
            'owner': {'run': remote.ORIGINAL_PUBLISH_RUN, 'source': remote.ORIGINAL_SOURCE,
                'digest': 'f8e4742f6964dd1237dc86ba27c0d3379a53b57ca4f45d6c16e713f392b43aef',
                'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_polish_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38180461319,
            'source': '950c9b5c0a00430c5b5a711d1b95bc04449d069d',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_restoration_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38191104207,
            'source': '634d1c97fd7bf499107d82036588f4bd77176d93',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_meal_search_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38197437835,
            'source': '2eb237639c6c53c38f27d71684de43384842daf7',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_meal_reconciliation_owner_cannot_claim_native_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38207863194,
            'source': '16196410a8cb90b10e8d06b7ac8b38d4ce7d235d',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_first_native_owner_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38238503101,
            'source': 'b53d5ca0bf541e66b684018fb7db332de8fefaf3',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_cost_label_predecessor_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 38363323689,
            'source': '08a99f2dfe2f420c9c03a6eb451d00e9a04e5026',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_consumed_calendar_predecessor_cannot_claim_current_inventory(self):
        before = {'target': self.digest, 'owner': {'run': 39346251452,
            'source': '84897fd5778debe040b74945212777aaf878d83e',
            'digest': self.digest, 'status': 'published'}}
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'):
            remote.predecessor(before)

    def test_successor_requires_exact_two_file_delta_and_same_855_paths(self):
        before = remote.inventory(self.site.target)
        after = remote.inventory(self.root / 'derived/payload')
        self.assertEqual(len(before), 855)
        self.assertEqual(remote.NATIVE_SUCCESSOR_CHANGES, (
            'visual-search/native-v2/source-manifest.json',
            'visual-search/native-v2/viewport.css',
        ))
        self.assertEqual(remote.predecessor_delta(before, after), remote.NATIVE_SUCCESSOR_CHANGES)
        for name in ('visual-search/app.js', 'visual-search/native-v2/bridge.js',
                     'visual-search/native-v2/frontend.js', 'visual-search/native-v2/tour.js',
                     'visual-search/native-v2/bootstrap.js',
                     'visual-search/native-v2/donor.css',
                     'prototype-search/data.js', 'visual-search/native-v2/lucide.min.js'):
            bad = {**after, name: 'f' * 64}
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'unreviewed_predecessor_delta'):
                remote.predecessor_delta(before, bad)
        missing = dict(after); missing.pop(remote.NATIVE_SUCCESSOR_CHANGES[0])
        substituted = {**missing, 'visual-search/native-v2/unknown.js': 'f' * 64}
        for bad in (missing, substituted, {**after, 'unknown.js': 'f' * 64}):
            with self.assertRaisesRegex(ValueError, 'successor_inventory_drift'):
                remote.predecessor_delta(before, bad)
        for name in remote.NATIVE_SUCCESSOR_CHANGES:
            incomplete = {**after, name: before[name]}
            with self.subTest(missing_change=name), self.assertRaisesRegex(ValueError, 'unreviewed_predecessor_delta'):
                remote.predecessor_delta(before, incomplete)

    def test_historical_allowances_cannot_activate_extra_legacy_or_unreviewed_native_change(self):
        manifest_path = self.source / 'payload' / remote.NATIVE_MANIFEST
        manifest_original = manifest_path.read_bytes()
        for i, name in enumerate(('visual-search/app.js', 'visual-search/native-v2/bridge.js',
                                  'visual-search/native-v2/frontend.js', 'visual-search/native-v2/tour.js')):
            path = self.source / 'payload' / name; original = path.read_bytes()
            try:
                path.write_bytes(original + b' extra change outside successor scope')
                native = copy.deepcopy(self.native)
                if name in remote.NATIVE_ASSETS:
                    native['files'][Path(name).name] = remote.digest(path.read_bytes())
                    for rows in native['graph'].values():
                        for row in rows:
                            if row['path'] == name:
                                row.update(sha256=remote.digest(path.read_bytes()), bytes=path.stat().st_size)
                    manifest_path.write_text(json.dumps(native, ensure_ascii=False, indent=2) + '\n')
                with patch.dict(self.native_pin, {'source_manifest_sha256': remote.digest(manifest_path.read_bytes())}):
                    q, archive = pub.derive(self.source, self.baseline, self.root / ('extra-delta-' + str(i)), self.q, 'e' * 64)
                    fd, upload_name = tempfile.mkstemp(prefix='search3-design-v2-update.', suffix='.tar.gz', dir='/tmp'); os.close(fd)
                    upload = Path(upload_name); upload.write_bytes(archive); self.uploads.append(upload)
                    q.update(deploy_run=791 + i, archive=upload_name, before=self.site.snapshot())
                    with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'unreviewed_predecessor_delta'):
                        self.site.activate(q)
                    self.assertEqual(self.site.snapshot(), q['before'])
                    self.assertFalse((self.site.receipt(q) / 'predecessor').exists())
                    self.assertEqual(self.site.finish(q, True)['status'], 'not_activated')
            finally:
                path.write_bytes(original); manifest_path.write_bytes(manifest_original)

    def test_supplier_price_lead_logo_font_and_unknown_payload_changes_denied(self):
        for name in ['prototype-search/data.js', 'search-page-v2.php', 'site-path-v1.php',
                'visual-search/preview-lead.js', 'prototype-search/lead.js', 'tour-controller-v4.js',
                'visual-search/assets/anytour-logo.svg', 'visual-search/fonts/Aeroport.woff2']:
            p = self.source / 'payload' / name; original = p.read_bytes(); p.write_bytes(original + b' unreviewed')
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'unreviewed_source_delta'):
                pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)
            p.write_bytes(original)
        (self.source / 'payload/new-asset.js').write_text('unknown')
        with self.assertRaisesRegex(ValueError, 'source_inventory_drift'): pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)

    def test_corrupt_source_ledger_denied(self):
        p = self.source / 'payload' / creator.LEDGER_PATH; ledger = json.loads(p.read_bytes())
        ledger['files'][0]['sha256'] = 'f' * 64; p.write_text(json.dumps(ledger))
        with self.assertRaisesRegex(ValueError, 'source_ledger_accounting'): pub.derive(self.source, self.baseline, self.root / 'rejected', self.q, 'e' * 64)

    def test_exact_predecessor_owner_and_inventory_required(self):
        q = self.staged()
        for field, value in [('source', 'f' * 40), ('run', 123), ('status', 'activated'), ('digest', 'f' * 64)]:
            bad = copy.deepcopy(q); bad['before']['owner'][field] = value
            with self.subTest(field=field), self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): self.site.activate(bad)
        (self.site.target / 'visual-search/app.js').write_text('different installed preview')
        with self.assertRaisesRegex(ValueError, 'predecessor_changed'): self.site.activate(q)

    def test_absent_unknown_or_symlinked_target_denied(self):
        for before in [{'target': None, 'owner': None}, {'target': self.digest, 'owner': None}]:
            with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): remote.predecessor(before)
        self.site.target.rename(self.site.parent / 'untrusted'); self.site.target.symlink_to(self.site.parent / 'untrusted')
        with self.assertRaisesRegex(ValueError, 'target_or_owner_link'): remote.Site(self.site.root)

    def test_activate_and_publish_preserve_production_all_old_previews(self):
        q = self.staged(); old = self.site.protected()
        before_files = remote.inventory(self.site.target)
        activation = self.site.activate(q)
        self.assertEqual(activation['status'], 'activated')
        self.assertEqual(activation['changed_payload_files'], list(remote.NATIVE_SUCCESSOR_CHANGES))
        self.assertEqual(activation['files'], 855)
        self.assertEqual(self.site.finish(q)['status'], 'published')
        self.assertEqual(self.site.protected(), old)
        self.assertEqual(remote.digest(remote.json_bytes(remote.inventory(self.site.target))), self.q['target_digest'])
        self.assertEqual(remote.digest(remote.json_bytes(remote.inventory(self.site.receipt(q) / 'predecessor'))), self.digest)
        self.assertEqual(remote.inventory(self.site.receipt(q) / 'predecessor'), before_files)
        self.assertEqual(json.loads(self.site.owner.read_bytes())['status'], 'published')
        with self.assertRaisesRegex(ValueError, 'exact_published_predecessor_required'): self.site.activate(self.staged(run=790))

    def test_rollback_restores_exact_target_owner_and_old_previews(self):
        before_files = remote.inventory(self.site.target)
        q = self.staged(); self.site.activate(q)
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])
        self.assertEqual((self.site.target / 'visual-search/app.js').read_text(), 'previous published polish')
        self.assertEqual(remote.inventory(self.site.target), before_files)
        self.assertEqual(len(before_files), 855)
        self.assertEqual((self.site.target / 'visual-search/native-v2/bootstrap.js').read_text(),
                         'native fixture:visual-search/native-v2/bootstrap.js')

    def test_completed_publish_or_rollback_cannot_replay(self):
        q = self.staged(); self.site.activate(q); self.site.finish(q)
        for rollback in (False, True):
            with self.subTest(rollback=rollback), self.assertRaisesRegex(ValueError, 'transaction_already_finished'): self.site.finish(q, rollback)
        self.assertEqual(json.loads(self.site.owner.read_bytes())['status'], 'published')

    def test_interruption_after_old_move_restores_without_guessing(self):
        q = self.staged(); real = Path.rename
        def fail_new_move(path, target):
            if path.name == 'payload' and Path(target) == self.site.target: raise RuntimeError('interrupted install')
            return real(path, target)
        with patch.object(Path, 'rename', fail_new_move), self.assertRaisesRegex(RuntimeError, 'interrupted install'): self.site.activate(q)
        self.assertFalse(self.site.target.exists())
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])

    def test_interruption_before_owner_commit_restores_checked_new_payload(self):
        q = self.staged()
        with (patch.object(self.site, 'write_owner', side_effect=RuntimeError('owner interruption')),
             self.assertRaisesRegex(RuntimeError, 'owner interruption')):
            self.site.activate(q)
        self.assertEqual(json.loads(self.site.owner.read_bytes()), self.owner)
        self.assertEqual(self.site.finish(q, True)['status'], 'rolled_back_to_exact_predecessor')
        self.assertEqual(self.site.snapshot(), q['before'])

    def test_validation_failure_before_activation_has_terminal_receipt(self):
        q = self.staged()
        with patch.object(remote, 'verify', side_effect=ValueError('invalid artifact')), self.assertRaisesRegex(ValueError, 'invalid artifact'): self.site.activate(q)
        self.assertEqual(self.site.finish(q, True)['status'], 'not_activated')
        with self.assertRaisesRegex(ValueError, 'transaction_already_finished'): self.site.finish(q, True)
        with self.assertRaisesRegex(ValueError, 'previous_outcome_unknown'): self.site.activate(self.staged())

    def test_modified_new_target_backup_or_owner_stops_rollback(self):
        q = self.staged(); self.site.activate(q)
        (self.site.target / 'visual-search/app.js').write_text('another writer')
        with self.assertRaisesRegex(ValueError, 'modified_target'): self.site.finish(q, True)
        self.assertTrue((self.site.receipt(q) / 'predecessor').is_dir())
        self.assertFalse((self.site.receipt(q) / 'result.json').exists())

    def test_request_or_backup_drift_stops_transaction(self):
        q = self.staged(); self.site.activate(q)
        with self.assertRaisesRegex(ValueError, 'accepted_native_source_only'): self.site.finish({**q, 'artifact_id': 999}, True)
        (self.site.receipt(q) / 'predecessor/visual-search/app.js').write_text('modified backup')
        for rollback in (False, True):
            with self.subTest(rollback=rollback), self.assertRaisesRegex(ValueError, 'backup_changed'): self.site.finish(q, rollback)

    def test_existing_preview_locks_refuse_update(self):
        q = self.staged()
        for name in ('.search3-site-lock', '.search3-local-lock', '.search3-v17-lock', '.search3-v18-lock', '.search3-next-lock'):
            p = self.site.parent / name; p.mkdir()
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'existing_preview_busy'): self.site.activate(q)
            p.rmdir()

    def test_request_target_digest_cannot_be_changed(self):
        with self.assertRaisesRegex(ValueError, 'payload_hashes'): remote.verify(self.root / 'derived', {**self.q, 'target_digest': 'f' * 64})
        with self.assertRaisesRegex(ValueError, 'control_hash'): remote.verify(self.root / 'derived', {**self.q, 'manifest_sha256': 'f' * 64})

    def test_trusted_remote_module_bootstrap_is_portable(self):
        code = pub.remote_source()
        self.assertNotIn(b'prototype-search', code.split(b'exec(compile', 1)[0])
        # Compile only, then import both modules without their CLI entry point.
        compile(code, '<trusted-remote>', 'exec')
        namespace = {'__name__': 'portable_test'}; exec(code, namespace)
        self.assertIn('Site', namespace); self.assertEqual(namespace['ROUTE'], remote.ROUTE)


if __name__ == '__main__': unittest.main()
