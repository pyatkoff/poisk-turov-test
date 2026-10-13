"""Retain then independently rederive a bounded update of NEW design preview.

No source runs with SSH credentials. All non-visual donor bytes must still match
the original accepted artifact, including supplier, price, lead, logo and fonts.
"""
from __future__ import annotations
import copy
import io
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import shutil
import struct
import sys
import tarfile
import zipfile
import gzip
import base64
import subprocess
from search3_design_v2_preview import (REPO, RELEASE, OLD_ROUTE, ROUTE_TRANSFORMS,
    LEDGER_PATH, LEDGER_JS, gzip_lengths, render_checks, live_checks)
from search3_design_v2_update_remote import (ROUTE, INVARIANTS, ORIGINAL_SOURCE, PREDECESSOR_SOURCE,
    PREDECESSOR_RUN, PREDECESSOR_DIGEST, COMPLETED_SOURCES, NATIVE_SUCCESSOR_CHANGES,
    ORIGINAL_BUILD, ORIGINAL_ARTIFACT, ORIGINAL_ZIP, SOURCE_CHANGES, ROUTE_CHANGES,
    digest, inventory, json_bytes, need, verify, predecessor, native_acceptance, accepted_native,
    native_graph, NATIVE_BUILD_WORKFLOW, NATIVE_REQUIRED_CHECKS, NATIVE_ADDED, NATIVE_REPLACED,
    NATIVE_MANIFEST, NATIVE_ROUTE_CHANGES)
from search3_design_v2_preview_remote import SOURCE_TREE as ORIGINAL_TREE

PREFIX = '/update-search3-design-v2-preview '
REQUIRED_CHECKS = NATIVE_REQUIRED_CHECKS
# Exact reviewed source ancestry, newest parent first. These static pins are never
# supplied by commands or artifacts; no skipped, substituted or extra parent is valid.
NATIVE_SOURCE_ANCESTORS = (
    ('99efd35919f00311093da657b560385b4a6bf572', '88303b7369c5472af9f4e0b0c60befe3b56fa67e'),
    ('b34c329974278933b31f44789ca773aa8f8af33d', 'd3194507fab76f8cdca2c2d39a776971c2c2459c'),
    ('0ba4d1195e3423cae2537d09d40a55d725589d6b', '19c141c0faad33cad9b97c338d3395b6bd484b6c'),)
NATIVE_SOURCE_PARENT, NATIVE_SOURCE_PARENT_TREE = NATIVE_SOURCE_ANCESTORS[0]
NATIVE_SOURCE_BASE_PARENT = '160d957dd0aecef1ac6e3eb8f7df881d527b0e7f'
RESULT_FACT_KINDS = ('result-title', 'result-location', 'result-date', 'result-room',
                     'result-meal', 'result-operator', 'result-party')
RESULT_NOTICE_TEXT = ('Цены из поиска — за весь тур и всех туристов. Перед заявкой проверим '
                      'цену, наличие и состав пакета, включая обязательные доплаты.')
RESULT_FIXTURE_FACTS = (
    ('FIXTURE Laguna Beach Resort', 'Стандартный номер', 'Ультра всё включено'),
    ('FIXTURE Marina Bay Hotel', 'Стандартный номер', 'Завтраки и ужины'),
    ('FIXTURE Seaside Garden Hotel & Residence', 'Стандартный номер', 'Завтраки'),
    ('FIXTURE Laguna Collection Premium Resort & Private Villas',
     'Вилла с двумя спальнями · свой бассейн', 'Ультра всё включено'))


def checked_request(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == '226193297', 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('repository', {}).get('full_name') == REPO and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == 226193297 and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'new_command_only')
    issue = event.get('issue', {}); c = event.get('comment', {})
    need(issue.get('number') == 4217 and 'pull_request' not in issue, 'coordination_only')
    need(c.get('user', {}).get('id') == 226193297 and c.get('author_association') == 'OWNER', 'comment_owner')
    body = c.get('body', '')
    need(isinstance(body, str) and body.startswith(PREFIX) and '\n' not in body and '\r' not in body, 'command_syntax')
    parts = body[len(PREFIX):].split(' ')
    need(len(parts) == 4 and all(re.fullmatch('[0-9a-f]{40}', x) for x in parts[:2])
         and all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:]), 'exact_pins')
    need(parts[0] not in COMPLETED_SOURCES, 'new_source_required')
    q = {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
        'artifact_id': int(parts[3]), 'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1}
    accepted_native(q, ('source_sha', 'release_sha', 'build_run', 'artifact_id'))
    return q


def strict_checks(api, source):
    body = api.get('/commits/' + source + '/check-runs?per_page=100')
    need(body.get('total_count', 0) <= 100, 'check_pagination_required')
    latest = {}
    for c in body['check_runs']:
        if c['name'] not in latest or (c.get('started_at') or '') > (latest[c['name']].get('started_at') or ''):
            latest[c['name']] = c
    need(REQUIRED_CHECKS <= latest.keys(), 'required_visual_checks_missing')
    for name in REQUIRED_CHECKS:
        c = latest[name]
        need(c.get('head_sha') == source and c.get('status') == 'completed'
             and c.get('conclusion') == 'success' and c.get('app', {}).get('slug') == 'github-actions', 'required_visual_check_not_success:' + name)


def authorized():
    from search3_preview_publish import Github
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_request(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == event['comment']['body'] and fresh.get('user', {}).get('id') == 226193297, 'command_revoked')
    tree, zip_hash = native_provenance(api, q, env['GITHUB_SHA'])
    strict_checks(api, q['source_sha'])
    return q, api, tree, zip_hash


def native_provenance(api, q, control_sha):
    """Native-only verifier; shared publisher provenance is not relaxed."""
    pin = accepted_native(q, ('source_sha', 'release_sha', 'build_run', 'artifact_id'))
    need(api.get('/git/ref/heads/main')['object']['sha'] == control_sha, 'control_main_changed')
    need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed')
    source = api.get('/git/commits/' + q['source_sha']); release = api.get('/git/commits/' + q['release_sha'])
    need(source['tree']['sha'] == pin['source_tree'], 'native_source_tree_pin')
    need([row.get('sha') for row in source.get('parents', [])] == [NATIVE_SOURCE_PARENT]
         and NATIVE_SOURCE_ANCESTORS[-1][0] == pin['release_parent'],
         'native_source_parent_pin')
    for index, (parent_sha, parent_tree) in enumerate(NATIVE_SOURCE_ANCESTORS):
        parent = api.get('/git/commits/' + parent_sha)
        next_sha = (NATIVE_SOURCE_ANCESTORS[index + 1][0]
                    if index + 1 < len(NATIVE_SOURCE_ANCESTORS) else NATIVE_SOURCE_BASE_PARENT)
        need(parent['tree']['sha'] == parent_tree
             and [row.get('sha') for row in parent.get('parents', [])] == [next_sha],
             'native_source_parent_lineage_pin')
    need(release['tree']['sha'] == pin['release_tree'] == pin['source_tree'], 'native_release_tree_pin')
    need([row.get('sha') for row in release.get('parents', [])] == [pin['release_parent']],
         'native_release_parent_pin')
    relation = api.get('/compare/' + q['source_sha'] + '...' + q['release_sha'])
    need(relation.get('status') == 'diverged' and relation.get('behind_by') == len(NATIVE_SOURCE_ANCESTORS)
         and relation.get('ahead_by') == 1
         and relation.get('merge_base_commit', {}).get('sha') == pin['release_parent'],
         'native_exact_squash_relation')
    run = api.get('/actions/runs/' + str(q['build_run']))
    need(run.get('head_sha') == q['source_sha'] and run.get('path') == NATIVE_BUILD_WORKFLOW
         and run.get('event') == 'pull_request' and run.get('status') == 'completed'
         and run.get('conclusion') == 'success' and run.get('run_attempt') == 1
         and run.get('repository', {}).get('full_name') == REPO
         and run.get('head_repository', {}).get('full_name') == REPO, 'native_build_exact_source_required')
    strict_checks(api, q['source_sha'])
    checks = api.get('/commits/' + q['source_sha'] + '/check-runs?per_page=100')
    need(checks.get('total_count', 0) <= 100 and all(row.get('head_sha') == q['source_sha']
         and row.get('status') == 'completed' and row.get('conclusion') in ('success', 'skipped', 'neutral')
         for row in checks['check_runs']), 'native_source_check_not_green')
    meta = api.get('/actions/artifacts/' + str(q['artifact_id']))
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['build_run']
         and meta['workflow_run'].get('head_sha') == q['source_sha']
         and meta.get('name') == f"search3-design-v2-native-{q['source_sha']}-{q['build_run']}"
         and meta.get('digest') == 'sha256:' + pin['source_ZIP_sha256'], 'native_artifact_pin')
    return pin['source_tree'], pin['source_ZIP_sha256']


def prepare_native_zip(content, request, source_tree, expected_digest, directory):
    """Read retained review bytes only; never run artifact code in a secret job."""
    pin = accepted_native(request, ('source_sha', 'build_run', 'artifact_id'))
    need(source_tree == pin['source_tree'] and digest(content) == expected_digest == pin['source_ZIP_sha256'], 'native_ZIP_pin')
    need(not directory.exists(), 'native_extraction_exists')
    with zipfile.ZipFile(io.BytesIO(content)) as archive:
        rows = archive.infolist(); names = set(); total = 0
        need(len(rows) <= 12000, 'native_ZIP_members')
        for row in rows:
            from search3_design_v2_preview_remote import safe_name
            name = str(safe_name(row.filename.rstrip('/')))
            need(name not in names, 'native_ZIP_duplicate'); names.add(name)
            need((row.external_attr >> 16) & 0o170000 in (0, 0o100000, 0o040000)
                 and not row.flag_bits & 1, 'native_ZIP_type')
            need(row.file_size <= 8 * 1024 * 1024, 'native_ZIP_file_size'); total += row.file_size
            need(total <= 64 * 1024 * 1024, 'native_ZIP_total_size')
            if not row.is_dir():
                need(name.startswith('payload/') or name.startswith('evidence/')
                     or name in ('control/inventory.json', 'control/native-build.json'), 'native_ZIP_scope')
        need({'control/inventory.json', 'control/native-build.json', 'evidence/acceptance.json', 'evidence/fixture.json'} <= names, 'native_ZIP_control')
        meta = json.loads(archive.read('control/inventory.json'))
        need(meta.get('schema') == 1 and meta.get('source_sha') == request['source_sha']
             and meta.get('source_tree_sha') == source_tree and str(meta.get('run_id')) == str(request['build_run'])
             and str(meta.get('run_attempt')) == '1'
             and meta.get('donor_source_sha') == '00fe6734c08acf234756bb59e5b61dbfecf41941'
             and meta.get('intended_route') == ROUTE + 'visual-search/'
             and meta.get('status') == 'review-artifact-not-published'
             and meta.get('publication_authority') is False
             and meta.get('canonical_route_rewrites_applied') is False, 'native_review_inventory_identity')
        files = {}
        for row in meta['files']:
            name = str(safe_name(row['path']))
            need(name not in files and isinstance(row.get('sha256'), str) and re.fullmatch('[0-9a-f]{64}', row['sha256'])
                 and type(row.get('bytes')) is int and 0 <= row['bytes'] <= 8 * 1024 * 1024, 'native_review_inventory_row')
            files[name] = row['sha256']
        need(meta.get('file_count') == len(files) == 845 + len(NATIVE_ADDED), 'native_review_inventory_count')
        actual_names = {row.filename[len('payload/'):] for row in rows if row.filename.startswith('payload/') and not row.is_dir()}
        need(actual_names == files.keys(), 'native_review_inventory_files')
        directory.mkdir(mode=0o700); payload = directory / 'release/payload'; payload.mkdir(parents=True)
        for row in meta['files']:
            value = archive.read('payload/' + row['path'])
            need(len(value) == row['bytes'] and digest(value) == row['sha256'], 'native_review_file_hash:' + row['path'])
            target = payload / row['path']; target.parent.mkdir(parents=True, exist_ok=True); target.write_bytes(value)
        native = native_graph(payload)
        need(json.loads(archive.read('control/native-build.json')) == native, 'native_build_manifest_readback')
        native_evidence(archive, request, native)
    control = directory / 'release/control'; control.mkdir()
    manifest = {'schema_version': 1, 'source_sha': request['source_sha'], 'source_tree_sha': source_tree,
        'files': [{'path': row['path'], 'sha256': row['sha256'], 'size': row['bytes']} for row in meta['files']],
        'file_count': len(files)}
    (control / 'manifest.json').write_bytes(json_bytes(manifest))
    return files


def native_frame_inventory(width, percent):
    frames = {f'{side}-{screen}-{width}-text{percent}.png'
              for side in ('native', 'donor') for screen in ('search', 'results', 'hotel', 'tour', 'lead')}
    frames.update(f'native-{screen}-{width}-text{percent}.png'
                  for screen in ('error', 'budget-results', 'hotel-return', 'startup-pending', 'startup-error',
                                 'destination-choice', 'destination-confirm', 'popup-calendar', 'popup-nights'))
    frames.add(f'native-calendar-later-{width}-text{percent}.png')
    frames.add(f'native-results-bounded-{width}-text{percent}.png')
    frames.add(f'native-tour-alternatives-{width}-text{percent}.png')
    frames.update(f'native-{screen}-{width}-text{percent}.png' for screen in (
        'filters-active', 'filter-empty', 'results-loading', 'results-error'))
    frames.update(f'native-{screen}-{width}-text{percent}.png' for screen in (
        'results-partial-empty', 'results-partial', 'filter-empty-partial'))
    if width in (360, 390, 430):
        frames.update(f'native-budget-{screen}-{width}-text{percent}.png' for screen in ('invalid', 'draft'))
    if (width, percent) == (390, 100): frames.add('native-budget-progress-invalid-390-text100.png')
    frames.update(f'native-family-{state}-{width}-text{percent}.png'
                  for state in ('0', '1', '2', '3', 'applied', 'review'))
    if width == 1280:
        frames.update(f'native-short-desktop-{screen}-{width}-text{percent}.png'
                      for screen in ('search', 'results', 'hotel', 'tour', 'lead'))
    return frames


def native_evidence(archive, request, native):
    """Rendered fixture proof, never a statement of live supplier acceptance."""
    report = json.loads(archive.read('evidence/acceptance.json'))
    need(report.get('schema') == 1 and report.get('sourceSHA') == request['source_sha']
         and report.get('donorSource') == native['donorSource']
         and report.get('compiledNativeManifest') == native and report.get('failures') == []
         and report.get('status') == 'passed'
         and report.get('requiredWidths') == [360, 390, 430, 768, 1280]
         and report.get('requiredTextPercents') == [100, 200]
         and report.get('expectedPairedScreens') == ['search', 'results', 'hotel', 'tour', 'lead'], 'native_rendered_identity')
    matrix = {(width, percent) for width in (360, 390, 430, 768, 1280) for percent in (100, 200)}
    cases = report.get('cases'); need(isinstance(cases, list) and len(cases) == len(matrix), 'native_rendered_matrix')
    seen = set(); frames = set()
    proof = {'passiveHydrationSearches': 0, 'formSubmitSearches': 1, 'localFilterExtraSearches': 0,
        'exactLoadedOfferRetained': True, 'flightChoiceIndex': 0, 'selectedFullTotal': 112000,
        'contactPayloadFullTotal': 112000, 'contactPayloadFlightChoiceIndex': 0,
        'contactPreviewCount': 1, 'realLeadSubmissions': 0, 'backForwardRetainedSelection': True,
        'backForwardExtraSearches': 0, 'errorRetainedRows': 4, 'explicitSearchRetryCount': 1,
        'retryRows': 4, 'favoriteStoragePreserved': True,
        'budgetLoadedCount': 2, 'budgetExtraSearches': 0,
        'calendarPages': 2, 'calendarLaterLoadedTotal': 166400,
        'calendarPagingExtraSearches': 0, 'calendarHistoryPageRetained': True,
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
        'familyExtraSearches': 0, 'familyAgeReviewRetained': True,
        'nightKeyboardFocusRetained': True, 'formCalendarPagingFocusRetained': True,
        'familyInputFocusRetained': True, 'destinationKeyboardFocusRetained': True,
        'formPopupSingleSubmitRetained': True}
    for case in cases:
        key = (case.get('width'), case.get('textPercent'))
        need(key in matrix and key not in seen and not case.get('error')
             and case.get('errors') == [] and case.get('unexpectedRequests') == [], 'native_rendered_case')
        seen.add(key); actual = case.get('proof', {})
        need(all(type(actual.get(k)) is type(value) and actual.get(k) == value for k, value in proof.items()), 'native_rendered_journey')
        ages = actual.get('familyAges')
        need(isinstance(ages, list) and ages == [0, 7, 17]
             and all(type(age) is int for age in ages), 'native_family_age_identity_required')
        closed, expanded, saved = (actual.get(k) for k in (
            'flightAlternativesClosedHeight', 'flightAlternativesExpandedHeight', 'flightAlternativesSavedHeight'))
        need(all(type(value) in (int, float) and 0 < value < 10000 for value in (closed, expanded, saved))
             and saved > 44 and abs(expanded - closed - saved) < 0.5,
             'native_flight_disclosure_compactness_required')
        if key == (390, 100):
            need(actual.get('progressiveDrawerRetainedDraftAndFocus') is True
                 and actual.get('budgetBreakpointDraftRetained') is True, 'native_budget_progressive_draft_required')
        if key in ((390, 200), (430, 200)):
            need(actual.get('enlargedCaptionPaintOverlapRegressionChecked') is True,
                 'native_caption_overlap_calibration_required')
        required = native_frame_inventory(*key)
        case_frames = case.get('frames'); need(isinstance(case_frames, list) and len(case_frames) == len(required), 'native_rendered_frames')
        case_seen = set()
        for frame in case_frames:
            name = frame.get('file')
            need(name in required and name not in case_seen and name not in frames, 'native_rendered_frame_inventory')
            case_seen.add(name); frames.add(name)
            value = archive.read('evidence/' + name)
            need(value.startswith(b'\x89PNG\r\n\x1a\n') and digest(value) == frame.get('sha256'), 'native_rendered_frame_hash')
            need(frame.get('viewportWidth') == key[0] and frame.get('textPercent') == key[1]
                 and frame.get('side') == name.split('-', 1)[0], 'native_rendered_frame_scope')
            short_desktop = name.startswith('native-short-desktop-')
            logical_name = name.replace('native-short-desktop-', 'native-', 1) if short_desktop else name
            if short_desktop:
                need(key[0] == 1280 and type(frame.get('viewportHeight')) is int
                     and frame['viewportHeight'] == 720, 'native_short_desktop_viewport_required')
                need(len(value) >= 24 and value[8:16] == b'\x00\x00\x00\rIHDR'
                     and struct.unpack('>II', value[16:24]) == (1280, 720),
                     'native_short_desktop_png_dimensions_required')
            if frame['side'] == 'native':
                popup_editor = ('dates' if logical_name.startswith('native-popup-calendar-')
                    else 'nights' if logical_name.startswith('native-popup-nights-')
                    else 'travelers' if logical_name.startswith('native-family-') and not logical_name.startswith('native-family-applied-')
                    else 'destination' if logical_name.startswith('native-destination-') else None)
                popup = frame.get('popupFit')
                if popup_editor:
                    need(isinstance(popup, dict) and set(popup) == {'editor', 'modal', 'outside',
                         'panelOverflow', 'findVisible', 'findEnabled', 'findHeight', 'submitCount'}
                         and popup.get('editor') == popup_editor and popup.get('modal') is True
                         and popup.get('outside') is False and popup.get('panelOverflow') is False
                         and popup.get('findVisible') is True and popup.get('findEnabled') is True
                         and type(popup.get('findHeight')) in (int, float) and 44 <= popup['findHeight'] < 1000
                         and type(popup.get('submitCount')) is int and popup['submitCount'] == 1,
                         'native_popup_choice_and_single_submit_required')
                else: need(popup is None, 'native_popup_measurement_scope')
                screen = ('startup' if logical_name.startswith('native-startup-')
                          else 'search' if logical_name.startswith(('native-family-', 'native-destination-', 'native-popup-'))
                          else 'filters' if logical_name.startswith(('native-budget-invalid-', 'native-budget-draft-',
                                                       'native-budget-progress-invalid-'))
                          else 'results' if logical_name.startswith(('native-budget-results-', 'native-error-',
                                                            'native-calendar-later-', 'native-results-bounded-',
                                                            'native-filters-active-', 'native-filter-empty-',
                                                            'native-results-loading-', 'native-results-error-',
                                                            'native-results-partial-', 'native-filter-empty-partial-'))
                          else 'hotel' if logical_name.startswith('native-hotel-return-') else logical_name.split('-')[1])
                need(frame.get('screen') == screen, 'native_rendered_screen_identity')
                need(frame.get('overflow') is False and type(frame.get('documentWidth')) in (int, float)
                     and frame['documentWidth'] <= key[0] + 1 and frame.get('stageHidden') is False, 'native_rendered_frame_geometry')
                if name.startswith(('native-destination-choice-', 'native-destination-confirm-')):
                    readable = frame.get('readableText')
                    need(isinstance(readable, list) and sum(row.get('text', '').strip() == 'Отмена'
                         for row in readable if isinstance(row, dict)) == 1,
                         'native_destination_cancel_label_required')
                if name.startswith('native-destination-confirm-'):
                    fit = frame.get('destinationFit')
                    need(isinstance(fit, dict) and fit.get('outside') is False and fit.get('overlap') is False,
                         'native_destination_fit_required')
                    available, font = fit.get('available'), fit.get('fontSize')
                    need(all(type(value) in (int, float) and 0 < value < 10000 for value in (available, font)),
                         'native_destination_geometry_required')
                    narrow = available <= font * 18 + 1
                    need(type(fit.get('queryNarrow')) is bool and fit['queryNarrow'] == narrow
                         and type(fit.get('stacked')) is bool and (not narrow or fit['stacked'])
                         and (key[1] != 100 or not fit['stacked']), 'native_destination_reflow_required')
                    actions = fit.get('actions')
                    need(isinstance(actions, list) and len(actions) == 2,
                         'native_destination_decisions_required')
                    for index, action in enumerate(actions):
                        need(isinstance(action, dict) and action.get('text') == ('Оставить', 'Заменить')[index]
                             and action.get('disabled') is False, 'native_destination_action_identity_required')
                        need(all(type(action.get(field)) in (int, float) for field in
                                 ('left', 'right', 'top', 'bottom', 'width', 'height'))
                             and action['width'] > 0 and action['height'] >= 44
                             and abs(action['right'] - action['left'] - action['width']) < 1
                             and abs(action['bottom'] - action['top'] - action['height']) < 1,
                             'native_destination_action_geometry_required')
                        need(sum(row.get('text', '').strip() == action['text']
                             for row in readable if isinstance(row, dict)) == 1,
                             'native_destination_action_label_required')
                    first, second = actions
                    need(fit['stacked'] == (second['top'] >= first['bottom'] - 1)
                         and not (min(first['right'], second['right']) > max(first['left'], second['left']) + 1
                                  and min(first['bottom'], second['bottom']) > max(first['top'], second['top']) + 1),
                         'native_destination_action_overlap_required')
                if name.startswith('native-calendar-later-'):
                    fit = frame.get('calendarFit')
                    need(isinstance(fit, dict) and fit.get('clipped') is False,
                         'native_calendar_fit_required')
                    left, right = fit.get('left'), fit.get('right')
                    client, scroll = fit.get('footClientWidth'), fit.get('footScrollWidth')
                    pager, page_buttons = fit.get('pager'), fit.get('buttons')
                    need(all(type(value) in (int, float) for value in (left, right, client, scroll))
                         and left < right and client > 0 and scroll <= client + 1,
                         'native_calendar_footer_geometry_required')
                    need(isinstance(pager, dict)
                         and all(type(pager.get(field)) in (int, float)
                                 for field in ('left', 'right', 'top', 'bottom'))
                         and left - 1 <= pager['left'] < pager['right'] <= right + 1,
                         'native_calendar_pager_geometry_required')
                    need(isinstance(page_buttons, list) and len(page_buttons) == 2,
                         'native_calendar_page_controls_required')
                    for button in page_buttons:
                        need(isinstance(button, dict)
                             and all(type(button.get(field)) in (int, float)
                                     for field in ('left', 'right', 'top', 'bottom', 'width', 'height'))
                             and type(button.get('disabled')) is bool
                             and left - 1 <= button['left'] < button['right'] <= right + 1
                             and button['width'] >= 44 and button['height'] >= 44,
                             'native_calendar_page_control_geometry_required')
                summaries = frame.get('summaryFit')
                if screen in ('tour', 'lead'):
                    need(isinstance(summaries, list) and len(summaries) == 1,
                         'native_selected_hotel_summary_required')
                    summary = summaries[0]
                    numeric = ('width', 'fontSize', 'imageWidth', 'copyWidth', 'copyWidthRatio')
                    need(isinstance(summary, dict)
                         and summary.get('className') in ('st-tour-hotel', 'st-quick-context')
                         and all(type(summary.get(field)) in (int, float) and summary[field] > 0
                                 for field in numeric)
                         and type(summary.get('stacked')) is bool
                         and type(summary.get('queryNarrow')) is bool,
                         'native_selected_hotel_summary_geometry_required')
                    width, font = summary['width'], summary['fontSize']
                    narrow = width <= font * 18 + 1
                    need(summary['queryNarrow'] is narrow,
                         'native_selected_hotel_summary_query_required')
                    if narrow:
                        need(summary['stacked'] is True and summary['copyWidthRatio'] >= .94
                             and abs(summary['imageWidth'] - width) <= 1
                             and abs(summary['copyWidth'] - width) <= 1,
                             'native_selected_hotel_summary_reflow_required')
                    else:
                        need(summary['stacked'] is False and summary['imageWidth'] < width
                             and .25 <= summary['copyWidthRatio'] < .9,
                             'native_selected_hotel_summary_donor_composition_required')
                else:
                    need(summaries == [], 'native_selected_hotel_summary_scope')
                offers = frame.get('offerActionFit')
                if screen == 'hotel':
                    need(isinstance(offers, list) and len(offers) == 2,
                         'native_hotel_offer_actions_required')
                    for offer in offers:
                        numeric = ('width', 'availableWidth', 'containerWidth', 'fontSize',
                                   'actionWidth', 'actionWidthRatio')
                        need(isinstance(offer, dict)
                             and isinstance(offer.get('text'), str) and bool(offer['text'].strip())
                             and all(type(offer.get(field)) in (int, float) and offer[field] > 0
                                     for field in numeric)
                             and type(offer.get('queryNarrow')) is bool
                             and type(offer.get('actionOnOwnRow')) is bool
                             and offer.get('outside') is False,
                             'native_hotel_offer_action_geometry_required')
                        narrow = offer['containerWidth'] <= offer['fontSize'] * 22 + 1
                        need(offer['queryNarrow'] is narrow,
                             'native_hotel_offer_action_query_required')
                        if narrow:
                            need(offer['actionOnOwnRow'] is True
                                 and offer['actionWidthRatio'] >= .94
                                 and abs(offer['actionWidth'] - offer['availableWidth']) <= 1,
                                 'native_hotel_offer_action_reflow_required')
                        else:
                            need(offer['actionOnOwnRow'] is False
                                 and offer['actionWidthRatio'] <= .6
                                 and offer['actionWidth'] < offer['availableWidth'],
                                 'native_hotel_offer_action_donor_composition_required')
                else:
                    need(offers == [], 'native_hotel_offer_action_scope')
                prices = frame.get('prices')
                need(isinstance(prices, list), 'native_price_measurements_required')
                if name.startswith('native-family-'):
                    need(prices == [], 'native_family_no_factual_prices')
                if screen == 'startup': need(prices == [], 'native_startup_no_factual_prices')
                no_result_prices = name.startswith(('native-filter-empty-', 'native-results-loading-',
                                                     'native-results-error-', 'native-results-partial-empty-'))
                if no_result_prices: need(prices == [], 'native_empty_no_factual_prices')
                elif screen in ('results', 'hotel', 'tour'):
                    need(bool(prices), 'native_visible_price_measurements_required')
                buttons = frame.get('buttons')
                need(isinstance(buttons, list) and all(isinstance(button, dict) and button.get('clipped') is False
                     for button in buttons), 'native_action_readability_required')
                if screen == 'startup':
                    need(len(buttons) == (1 if name.startswith('native-startup-error-') else 0)
                         and all(isinstance(button.get('text'), str) and bool(button['text'].strip()) for button in buttons),
                         'native_startup_retry_readability_required')
                if name.startswith('native-results-loading-'):
                    need(buttons == [], 'native_loading_no_empty_action')
                if name.startswith(('native-filter-empty-', 'native-results-error-', 'native-results-partial-empty-')):
                    need(len(buttons) == 1 and isinstance(buttons[0].get('text'), str)
                         and bool(buttons[0]['text'].strip()), 'native_empty_action_readability_required')
                for price in prices:
                    need(isinstance(price, dict) and price.get('overflow') is False
                         and price.get('fragmented') is False and price.get('textScaleVerified') is True,
                         'native_price_readability_required')
                    font, baseline = price.get('fontSize'), price.get('baselineFontSize')
                    need(type(font) in (int, float) and type(baseline) in (int, float)
                         and font > 0 and baseline > 0
                         and abs(font - baseline * (2 if key[1] == 200 else 1)) < 0.5,
                         'native_actual_text_scale_required')
                text = frame.get('readableText')
                need(isinstance(text, list) and bool(text)
                     and all(isinstance(row, dict) and isinstance(row.get('text'), str)
                             and row.get('clipped') is False and row.get('brokenWords') is False
                             and row.get('overlappingLines') is False for row in text),
                     'native_text_readability_required')
                card_ids = frame.get('resultCardIds')
                need(isinstance(card_ids, list)
                     and all(type(card_id) is int and card_id > 0 for card_id in card_ids)
                     and len(set(card_ids)) == len(card_ids), 'native_result_card_identity_required')
                notice = frame.get('resultNotice')
                need(isinstance(notice, dict)
                     and set(notice) == {'visibleCount', 'beforeList', 'cardNoticeCount', 'text'}
                     and type(notice['visibleCount']) is int and notice['visibleCount'] >= 0
                     and type(notice['cardNoticeCount']) is int and notice['cardNoticeCount'] == 0
                     and type(notice['beforeList']) is bool and isinstance(notice['text'], str),
                     'native_result_notice_measurement_required')
                presets = [row['text'] for row in text if row.get('kind') == 'night-preset']
                need(presets == (['7 ночей', '10 ночей', '14 ночей', '21 ночь']
                     if logical_name.startswith('native-popup-nights-') else []),
                     'native_popup_night_presets_readability_required')
                need(all(row.get('kind') in (*RESULT_FACT_KINDS, 'result-notice', 'night-preset', 'other')
                         and (row.get('cardId') is None
                              or type(row['cardId']) is int and row['cardId'] in card_ids)
                         and ('cardId' in row)
                         and (row['kind'] not in RESULT_FACT_KINDS or row['cardId'] is not None)
                         and (row['kind'] != 'result-notice' or row['cardId'] is None)
                         for row in text), 'native_result_fact_identity_required')
                result_rows = [row for row in text if row['kind'] in RESULT_FACT_KINDS]
                note_rows = [row for row in text if row['kind'] == 'result-notice']
                populated = screen == 'results' and not no_result_prices
                if populated:
                    bounded = logical_name.startswith('native-results-bounded-')
                    expected_count = (24 if bounded else 2 if logical_name.startswith(
                        ('native-budget-results-', 'native-filters-active-')) else 4)
                    need(len(card_ids) == expected_count
                         and all(1000 <= card_id < 1024 if bounded else 1 <= card_id <= 4
                                 for card_id in card_ids), 'native_result_card_scope_required')
                    if logical_name == f'native-results-{key[0]}-text{key[1]}.png':
                        need(card_ids == [1, 2, 3, 4], 'native_result_standard_identity_required')
                    need(notice == {'visibleCount': 1, 'beforeList': True,
                                    'cardNoticeCount': 0, 'text': RESULT_NOTICE_TEXT}
                         and len(note_rows) == 1 and note_rows[0]['text'].strip() == RESULT_NOTICE_TEXT,
                         'native_result_shared_notice_required')
                    need(len(result_rows) == len(card_ids) * len(RESULT_FACT_KINDS),
                         'native_result_fact_measurements_required')
                    for card_id in card_ids:
                        template = RESULT_FIXTURE_FACTS[(card_id - 1000) % 4 if bounded else card_id - 1]
                        title = f'FICTIONAL loaded hotel {card_id - 999:02d}' if bounded else template[0]
                        expected = dict(zip(RESULT_FACT_KINDS, (title, 'Турция · Кемер',
                            '12 окт · 7 ночей', template[1], template[2], 'FIXTURE TV', 'за 2 взрослых')))
                        for kind, value in expected.items():
                            rows = [row for row in result_rows if row['cardId'] == card_id and row['kind'] == kind]
                            need(len(rows) == 1 and rows[0]['text'].strip() == value,
                                 'native_result_factual_copy_required:' + kind)
                else:
                    need(card_ids == [] and result_rows == [] and note_rows == []
                         and notice == {'visibleCount': 0, 'beforeList': False,
                                        'cardNoticeCount': 0, 'text': ''},
                         'native_result_empty_scope_required')
                if screen == 'tour':
                    need({'Туристы', 'Туроператор', 'Обязательные доплаты'}
                         <= {row['text'].strip() for row in text},
                         'native_tour_cost_labels_required')
                if name.startswith('native-family-'):
                    state = name[len('native-family-'):].split('-', 1)[0]
                    expected = {'0': '1 взрослый', '1': '1 взрослый + 1 ребёнок',
                        '2': '1 взрослый + 2 ребёнка', '3': '1 взрослый + 3 ребёнка',
                        'applied': '1 взрослый + 3 ребёнка', 'review': '1 взрослый + 3 ребёнка'}[state]
                    need(any(row['text'] == expected for row in text), 'native_family_copy_required')
                    if state == 'review':
                        need(any('Проверьте возраста детей на дату возвращения' in row['text'] for row in text)
                             and any(row['text'] == 'Подтвердите возраста детей на дату возвращения.' for row in text),
                             'native_family_age_review_copy_required')
                need(frame.get('headerFit') == {'outside': False, 'overlap': False}, 'native_header_fit_required')
                sort = frame.get('sortFit')
                if screen == 'results':
                    need(isinstance(sort, dict) and isinstance(sort.get('text'), str) and bool(sort['text'])
                         and type(sort.get('available')) in (int, float) and sort['available'] > 0
                         and type(sort.get('textWidth')) in (int, float) and 0 <= sort['textWidth'] <= sort['available'] + 1,
                         'native_sort_readability_required')
                else: need(sort is None, 'native_sort_measurement_scope')
                inputs = frame.get('budgetInputs')
                need(isinstance(inputs, list)
                     and len(inputs) == (2 if screen == 'filters' or screen == 'results' and key[0] >= 768 else 0),
                     'native_budget_measurements_required')
                for row in inputs:
                    need(isinstance(row, dict) and isinstance(row.get('text'), str) and bool(row['text'])
                         and row.get('clipped') is False and row.get('appearance') == 'textfield'
                         and type(row.get('available')) in (int, float) and row['available'] > 0
                         and type(row.get('textWidth')) in (int, float) and 0 <= row['textWidth'] <= row['available'] + 1,
                         'native_budget_readability_required')
                    font = row.get('font')
                    measured = re.fullmatch(r'500 ([0-9]+(?:\.[0-9]+)?)px Aeroport, system-ui, sans-serif', font) if isinstance(font, str) else None
                    expected = (16 if key[0] <= 430 else 14) * (2 if key[1] == 200 else 1)
                    need(measured is not None and abs(float(measured[1]) - expected) < 0.5,
                         'native_budget_actual_text_scale_required')
                if screen == 'results' and key[0] >= 768:
                    need(type(frame.get('budgetColumns')) is int
                         and frame['budgetColumns'] == (2 if key[1] == 100 else 1), 'native_budget_donor_columns_required')
        need(case_seen == required, 'native_rendered_required_frames')
    need(seen == matrix, 'native_rendered_matrix')
    need(len(frames) == 373, 'native_rendered_frame_count')
    evidence_files = {row.filename[len('evidence/'):] for row in archive.infolist()
                      if row.filename.startswith('evidence/') and not row.is_dir()}
    need(evidence_files == frames | {'acceptance.json', 'fixture.json'}, 'native_rendered_evidence_inventory')


def expected_native_render(payload):
    html = (payload / 'visual-search/index.html').read_text()
    def version(match):
        from search3_design_v2_preview_remote import safe_name
        import posixpath
        name = posixpath.normpath(posixpath.join('visual-search', match[2])); safe_name(name)
        file = payload / name
        need(file.is_file() and not file.is_symlink(), 'native_render_asset_missing')
        return match[1] + '="' + match[2] + '?v=' + digest(file.read_bytes())[:12] + '"'
    return re.sub(r'\b(src|href)="(\.\.?/[^"?]+\.(?:js|css))"', version, html)


def native_render_checks(root):
    """The same isolated page/boundary gates, with exact native entry readback."""
    payload = root / 'payload'; native_graph(payload, rewritten=True)
    empty = root / 'empty-document-root'; empty.mkdir()
    php = "$_SERVER['DOCUMENT_ROOT']=$argv[1]; $_SERVER['HTTP_HOST']='anytoour.ru'; $_SERVER['SCRIPT_NAME']=$argv[2]; include $argv[3];"
    def render(script, file):
        result = subprocess.run(['php', '-d', 'display_errors=1', '-r', php, str(empty), script, str(file)],
                                capture_output=True, text=True, timeout=30)
        need(result.returncode == 0 and 'Fatal error' not in result.stdout, 'native_local_render')
        return result.stdout
    try:
        for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
            html = render(ROUTE + route + 'index.php', payload / route / 'index.php')
            need('<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'local_noindex:' + route)
            if route in ('poisk-turov/', 'poisk-turov-old/'):
                for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                    need(marker in html, 'local_search_guard')
                need('web-consultant/widget.js' not in html and OLD_ROUTE not in html, 'local_wrong_preview')
        entry = payload / 'visual-search/index.php'
        need(render(ROUTE + 'visual-search/index.php', entry) == expected_native_render(payload), 'native_exact_PHP_render')
        for wrong in ('/poisk-turov/index.php', '/_preview/search3-next-candidate/visual-search/index.php',
                      '/_preview/search3-local-candidate/visual-search/index.php',
                      '/_preview/search3-site-candidate/visual-search/index.php',
                      ROUTE + 'visual-search/other.php'):
            need(render(wrong, entry) == 'This entry is available only in the design-v2 preview.', 'native_negative_entry:' + wrong)
    finally:
        empty.rmdir()


def native_live_checks(files):
    from search3_preview_publish import http
    for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
        status, html = http(ROUTE + route)
        need(status == 200 and '<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'live_route:' + route)
        if route in ('poisk-turov/', 'poisk-turov-old/'):
            for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                need(marker in html, 'live_search_guard')
            need(OLD_ROUTE not in html and 'web-consultant/widget.js' not in html, 'live_wrong_preview')
    status, text = http(ROUTE + 'preview-lead-disabled.php', b'{"synthetic":true}')
    need(status == 403 and 'PREVIEW_LEAD_DISABLED' in text, 'lead_not_disabled')
    for name in ('assets.php', 'search-page-v2.php', 'seo-config.php', 'data/db-v1.php', 'data/hotel-presentation-read-v1.php'):
        need(http(ROUTE + name)[0] == 403, 'internal_PHP_exposed:' + name)
    need(http(ROUTE + 'config.php')[0] in (403, 404), 'config_exposed')
    for path, message in {
        'data/hotel-details-read-v1.php?catalog=anytour&anytourHotelId=1': 'Canonical catalogue is isolated to local preview',
        'data/search3-local-results-read-v1.php': 'Local DB results are isolated to local preview',
    }.items():
        status, body = http(ROUTE + path)
        need(status == 403 and json.loads(body) == {'ok': False, 'error': message}, 'LOCAL_only_data_boundary:' + path)
    status, body = http(ROUTE + 'data/local-tv-catalog-read-v1.php?oldLocalHotelIds%5B%5D=1'); catalog = json.loads(body)
    need(status == 200 and catalog.get('ok') is True and catalog.get('source') == 'anytour-local-tv-catalog-v1'
         and catalog.get('catalog') == 'local-tv' and catalog.get('requestedIds') == [1]
         and all(isinstance(catalog.get(k), list) for k in ('items', 'links', 'missingIds')), 'new_catalog_read_boundary')
    status, html = http(ROUTE + 'visual-search/')
    need(status == 200 and '<meta name="robots" content="noindex,nofollow,noarchive">' in html
         and 'id="design-v2-native-root"' in html, 'native_live_entry_identity')
    from search3_design_v2_update_remote import NativeEntry, NATIVE_SCRIPTS, NATIVE_STYLES
    import posixpath
    entry = NativeEntry(); entry.feed(html); entry.close()
    def served(src):
        name = posixpath.normpath(posixpath.join('visual-search', src))
        need(name in files, 'native_live_dependency_inventory')
        return src + '?v=' + files[name][:12]
    need(not entry.inline and tuple(entry.scripts) == tuple(served(src) for src in NATIVE_SCRIPTS)
         and tuple(entry.styles) == tuple(served(src) for src in NATIVE_STYLES), 'native_live_exact_graph')
    targets = {name for name in files if name.startswith('visual-search/') and not name.endswith('.php')}
    targets |= {posixpath.normpath(posixpath.join('visual-search', src)) for src in (*NATIVE_SCRIPTS, *NATIVE_STYLES)}
    targets |= {'search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css'}
    for name in sorted(targets):
        status, value = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(value) == files[name], 'native_live_asset_hash:' + name)
    need(http('/')[0] == 200 and http('/poisk-turov/')[0] == 200, 'production_pages_unhealthy')
    return {'routes_200': 9, 'noindex': True, 'counter': 0, 'lead_HTTP': 403,
        'LOCAL_only_data_HTTP': 403, 'LOCAL_TV_catalog_HTTP': 200, 'supplier_searches': 0, 'real_leads': 0,
        'visual_route_200': True, 'native_graph_verified': True, 'visual_assets_verified': len(targets)}


def update_ledger(source, output):
    ledger = json.loads((source / 'payload' / LEDGER_PATH).read_bytes()); original = copy.deepcopy(ledger)
    entries = {f['target']: f for f in ledger['files']}
    need(len(entries) == len(ledger['files']) and all(n in entries for n in LEDGER_JS), 'served_ledger_inventory')
    before = [(source / 'payload' / n).read_bytes() for n in LEDGER_JS]
    after = [(output / 'payload' / n).read_bytes() for n in LEDGER_JS]
    lengths = gzip_lengths(before + after)
    for i, name in enumerate(LEDGER_JS):
        f = entries[name]
        need(f['sha256'] == digest(before[i]) and f['served'] == len(before[i])
             and f['gzip_after'] == lengths[i], 'source_ledger_accounting:' + name)
        f.update(sha256=digest(after[i]), served=len(after[i]), gzip_after=lengths[i + len(LEDGER_JS)])
    graphs = {**ledger['graphs'], 'complete_live': ledger['graphs']['live'] + ledger['graphs']['ondemand'],
        'complete_offline': ledger['graphs']['offline'] + ledger['graphs']['ondemand']}
    for name, graph in graphs.items():
        for field in ('served', 'gzip_after'):
            ledger['totals'][name][field] = sum(f[field] for f in ledger['files'] if f['src'] in graph)
    check = copy.deepcopy(ledger)
    for i, f in enumerate(check['files']):
        if f['target'] in LEDGER_JS:
            for field in ('sha256', 'served', 'gzip_after'): f[field] = original['files'][i][field]
    for name in ('live', 'complete_live'):
        for field in ('served', 'gzip_after'): check['totals'][name][field] = original['totals'][name][field]
    need(check == original, 'unexpected_ledger_delta')
    (output / 'payload' / LEDGER_PATH).write_bytes((json.dumps(ledger, ensure_ascii=False, indent=2) + '\n').encode())


def derive(source, baseline, output, request, zip_digest):
    need(not output.exists(), 'derived_output_exists')
    original = inventory(baseline / 'payload'); current = inventory(source / 'payload')
    accepted_native(request, ('source_sha', 'build_run', 'artifact_id'))
    need(len(original) == 845 and set(current) == set(original) | set(NATIVE_ADDED)
         and not set(original) & set(NATIVE_ADDED), 'source_inventory_drift')
    changes = sorted(n for n in current if n not in original or current[n] != original[n])
    need(set(NATIVE_ADDED) <= set(changes)
         and set(changes) <= set((*SOURCE_CHANGES, *NATIVE_REPLACED, *NATIVE_ADDED)), 'unreviewed_source_delta')
    native_graph(source / 'payload')
    shutil.copytree(source, output)
    for name, old, new in ROUTE_TRANSFORMS:
        if name == 'visual-search/index.php': continue  # Exact native entry is already design-v2 fenced and hash-pinned.
        p = output / 'payload' / name; before = p.read_bytes()
        need(before.count(old) == 1 and new not in before, 'route_source_drift:' + name)
        p.write_bytes(before.replace(old, new))
    update_ledger(source, output)
    update_native_ledger(output)
    for p in (output / 'payload').rglob('*'):
        if p.is_file(): need(OLD_ROUTE.rstrip('/').encode() not in p.read_bytes(), 'residual_old_preview_route')
    files = inventory(output / 'payload')
    need(current.keys() == files.keys() and {n for n in files if files[n] != current[n]} == set(NATIVE_ROUTE_CHANGES), 'unexpected_payload_delta')
    m = json.loads((source / 'control/manifest.json').read_bytes())
    m.update(target='search3-design-v2-preview', route=ROUTE, invariants=INVARIANTS,
        derived_from={'artifact_id': request['artifact_id'], 'build_run': request['build_run'],
            'ZIP_sha256': zip_digest, 'transform': 'design-v2-native-route-and-ledger-v2',
            'changed_payload_files': list(NATIVE_ROUTE_CHANGES), 'changed_source_payload_files': changes,
            'baseline': {'source_sha': ORIGINAL_SOURCE, 'build_run': ORIGINAL_BUILD,
                'artifact_id': ORIGINAL_ARTIFACT, 'ZIP_sha256': ORIGINAL_ZIP}})
    m['files'] = [{'path': n, 'sha256': h, 'size': (output / 'payload' / n).stat().st_size} for n, h in files.items()]
    m['file_count'] = len(files)
    (output / 'control/manifest.json').write_bytes(json_bytes(m))
    (output / 'control/payload.sha256').write_bytes(''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode())
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode='w', format=tarfile.USTAR_FORMAT) as t:
        for name in ('control/manifest.json', 'control/payload.sha256', *('payload/' + n for n in files)):
            data = (output / name).read_bytes(); info = tarfile.TarInfo(name)
            info.size = len(data); info.mode = 0o644; info.mtime = 0
            t.addfile(info, io.BytesIO(data))
    archive = gzip.compress(raw.getvalue(), mtime=0)
    q = {**request, 'source_ZIP_sha256': zip_digest, 'archive_sha256': digest(archive),
        'manifest_sha256': digest((output / 'control/manifest.json').read_bytes()),
        'payload_sha256': digest((output / 'control/payload.sha256').read_bytes()),
        'target_digest': digest(json_bytes(files)), 'file_count': len(files)}
    verify(output, q)
    return q, archive


def update_native_ledger(output):
    payload = output / 'payload'; path = payload / NATIVE_MANIFEST; value = json.loads(path.read_bytes())
    value['canonicalDependenciesRewritten'] = True
    for kind in ('scripts', 'styles'):
        for row in value['graph'][kind]:
            content = (payload / row['path']).read_bytes(); row.update(sha256=digest(content), bytes=len(content))
    path.write_bytes((json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode())
    native_graph(payload, rewritten=True)


def prepare_inputs(api, q, tree, zip_hash, work):
    from search3_preview_publish import prepare_zip
    prepare_native_zip(api.download(q['artifact_id']), q, tree, zip_hash, work / 'original')
    baseline = {'source_sha': ORIGINAL_SOURCE, 'release_sha': ORIGINAL_SOURCE,
        'artifact_id': ORIGINAL_ARTIFACT, 'build_run': ORIGINAL_BUILD,
        'deploy_run': q['deploy_run'], 'attempt': 1}
    prepare_zip(api.download(ORIGINAL_ARTIFACT), baseline, ORIGINAL_TREE, ORIGINAL_ZIP, work / 'baseline')
    return derive(work / 'original/release', work / 'baseline/release', work / 'derived',
                  {**q, 'source_tree': tree}, zip_hash)


def prepare():
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-prepare'; work.mkdir(mode=0o700)
    ready, archive = prepare_inputs(api, q, tree, zip_hash, work)
    native_render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'design-v2-update.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_not_published', 'route': ROUTE, 'archive_sha256': ready['archive_sha256']}))


def remote_source():
    # Trusted-main modules only; payload Python is never imported or executed.
    root = Path(__file__).parent
    base = (root / 'search3_design_v2_preview_remote.py').read_text()
    updater = (root / 'search3_design_v2_update_remote.py').read_text()
    return ('import sys, types\n'
        'm=types.ModuleType("search3_design_v2_preview_remote")\n'
        'sys.modules[m.__name__]=m\n'
        'exec(compile(' + repr(base) + ',"trusted-creator-remote","exec"),m.__dict__)\n'
        'exec(compile(' + repr(updater) + ',"trusted-update-remote","exec"))\n').encode()


def publish():
    from search3_preview_publish import command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-publish'; work.mkdir(mode=0o700)
    expected_q, archive = prepare_inputs(api, q, tree, zip_hash, work)
    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-design-v2-update-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id)); need('sha256:' + digest(packed) == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(io.BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'design-v2-update.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('design-v2-update.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')
    q = expected_q; (work / 'design-v2-update.tar.gz').write_bytes(archive)
    files = inventory(work / 'derived/payload')
    evidence = {'route': ROUTE, 'source_sha': q['source_sha'], 'release_sha': q['release_sha'],
        'source_artifact': q['artifact_id'], 'derived_artifact': int(artifact_id), 'derived_ZIP_sha256': digest(packed),
        'predecessor_source': PREDECESSOR_SOURCE, 'predecessor_run': PREDECESSOR_RUN,
        'predecessor_digest': PREDECESSOR_DIGEST,
        'expected_predecessor_delta': list(NATIVE_SUCCESSOR_CHANGES), 'status': 'checked_not_published'}
    host = os.environ['PREVIEW_HOST']; user = os.environ['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'; raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw: raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    attempted = False
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
            '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        source = remote_source()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-design-v2-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
        remote('bind', binding)
        try:
            status, text = http('/_preview/' + binding['name'])
            need(status == 200 and text == binding['nonce'], 'SSH_HTTPS_binding')
        finally: remote('unbind', binding)
        q['before'] = remote('snapshot', {}); predecessor(q['before'])
        need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed_before_activation')
        need(api.get('/git/ref/heads/main')['object']['sha'] == os.environ['GITHUB_SHA'], 'main_changed_before_activation')
        q['archive'] = remote('upload', {})['archive']
        need(re.fullmatch(r'/tmp/search3-design-v2-update\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'design-v2-update.tar.gz'), user + '@' + host + ':' + q['archive']])
        attempted = True; evidence['activation'] = remote('activate', q)
        evidence.update(native_live_checks(files))
        evidence['completion'] = remote('complete', q)
        evidence.update(status='published', production_unchanged=True, existing_preview_unchanged=True)
    except Exception as exc:
        evidence.update(status='failed_not_accepted', reason=str(exc))
        if attempted:
            try: evidence['rollback'] = remote('rollback', q)
            except Exception as error: evidence['rollback'] = {'status': 'unknown_stop_no_replay', 'reason': str(error)}
        raise
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_bytes(json_bytes(evidence))
    print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    need(len(sys.argv) == 2 and sys.argv[1] in ('prepare', 'publish'), 'usage')
    {'prepare': prepare, 'publish': publish}[sys.argv[1]]()
