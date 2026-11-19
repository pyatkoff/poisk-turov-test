#!/usr/bin/env python3
"""Fail closed when the frozen Search3 reference boundary drifts."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
DEFAULT_MANIFEST = ROOT / "docs/project/search3-reference-dossier.json"
DEFAULT_DOCUMENT = ROOT / "docs/project/SEARCH3_REFERENCE_DOSSIER.md"
FULL_SHA = re.compile(r"^[0-9a-f]{40}$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")

EXPECTED_LAYOUTS = {
    1: "Интерфейс поиска туров AnyTour",
    2: "Макет фильтров поиска туров AnyTour",
    3: "Выбор рейсов AnyTour",
    4: "Итог тура AnyTour",
    5: "Страница бронирования тура AnyTour",
    6: "UI-кит заявки на тур AnyTour",
    7: "Спецификация футера AnyTour",
    8: "Цельный mobile flow AnyTour",
}
EXPECTED_SOURCE_FILES = {
    ".github/workflows/deploy-search3-preview.yml": (
        "8aa17d782542c51c26ce1cabd961a5d8973204b0",
        "28590e2d840b1a90ca6c0977cb6a92483a24ca367e1fa88395ac9a793db14237",
    ),
    "search3-visual-desktop.cjs": (
        "c6a59bfc2cdbceefe571eaf11867725c217f79c9",
        "62adc1b1b3fd10d705d1b48e08052b34cec526523b0e78b9dd6219c5722765cd",
    ),
    "search3-visual-tablet.cjs": (
        "73c60cdbd028b08294a8233914633ef867254154",
        "7a497df868509bcc2dd21e00911c0cf191bbcbdf6731112c1b48f8dbe0685cad",
    ),
    "search3-visual-mobile.cjs": (
        "1f50930fd94a501fbac90a747852b75fb297323c",
        "f8b4b7c9721662234491fcfde01c43be890865f844127a7e5ac04c39251a21d2",
    ),
    "v2/SEARCH3_PREVIEW_TARGET.md": (
        "6bf245cdc9c37395e9bb1e849758c476ad33c786",
        "959e44784ca7541ff353aacd193c0bef68e2f72ed8b22cf4fbc476920f49a325",
    ),
    "v2/SEARCH3_WORK_MAP.md": (
        "6e43a1c8f2ab35e5a6a1e290b68de4fa1232659f",
        "1619806950982c3e03066356bb34d613e4d31e54ff3b3926a970de53742ab907",
    ),
}
EXPECTED_VIEWPORTS = {
    "desktop": (
        1440,
        1000,
        ["d00-footer", "01-search", "02-results", "03-expanded-hotel", "04-tour-details", "05-flights", "06-final-review", "07-lead-entry", "08-lead-sending", "09-lead-success", "10-lead-error"],
    ),
    "tablet": (
        834,
        1112,
        ["t00-footer", "t01-search", "t02-results", "t02a-filters-open", "t02b-filter-subpanel", "t03-tour-details", "t04-flights", "t05-final-review", "t06-lead-entry", "t07-lead-sending", "t08-lead-success", "t09-lead-error"],
    ),
    "mobile": (
        390,
        844,
        ["m00-footer", "m01-search", "m02-results", "m02a-filters-open", "m02b-filter-subpanel", "m03-tour-details", "m04-flights", "m05-final-review", "m06-lead-entry", "m07-lead-sending", "m08-lead-success", "m09-lead-error"],
    ),
}
EXPECTED_ARTIFACTS = {
    "desktop": {
        "artifact_id": 9915925993,
        "name": "search3-visual-desktop-467-e5baf32f455cdb0aa1a704964f28e5efbebf57ff",
        "size_in_bytes": 2738645,
        "archive_sha256": "ae398a14323d99bb23ad5b2bd26be06b6923845da6e3218d1613770c064718fd",
        "expires_at": "2026-10-03T22:41:35Z",
        "report_file": "report-desktop.json",
        "report_sha256": "dbfc87b0dd42feea64d10609ad93fb794313f118e5e99412aad89dede8df85ef",
        "capture_index_sha256": "cfad486e3fd99362f4537a9350f769d2b5637674d2506eb616e2b22514f48fd0",
    },
    "tablet": {
        "artifact_id": 9915896367,
        "name": "search3-visual-tablet-467-e5baf32f455cdb0aa1a704964f28e5efbebf57ff",
        "size_in_bytes": 2645305,
        "archive_sha256": "cd001dd85c81a051a5a02446e087656eeb45bd5f62cf14db6bf7cf3252be02d3",
        "expires_at": "2026-10-03T22:40:28Z",
        "report_file": "report-mobile.json",
        "report_sha256": "5c17295c2caff613f4788b1e9601275e524676b1919182fb46f1303f054e8b21",
        "capture_index_sha256": "733faa32d2998af94dfd5a84dc9c3603eb81f58e6939f81c018218b9e710908d",
    },
    "mobile": {
        "artifact_id": 9915896517,
        "name": "search3-visual-mobile-467-e5baf32f455cdb0aa1a704964f28e5efbebf57ff",
        "size_in_bytes": 854848,
        "archive_sha256": "0b00d1827004b3c516c3dcc1c4305c68b3e250210ca5605f8e910c147062c8ff",
        "expires_at": "2026-10-03T22:40:28Z",
        "report_file": "report-mobile.json",
        "report_sha256": "d1ed9fe18e3c30dc755c22a64ce4f381ebe74e9c8544df2a32610a6a044c6f21",
        "capture_index_sha256": "cb270770fb8ff1ed9d2d360bcd088d249543877b0b87a2604b580b7dc8f45f8f",
    },
}
EXPECTED_RUN_TIMESTAMPS = {
    "created_at": "2026-09-03T22:36:03Z",
    "completed_at": "2026-09-03T22:41:38Z",
    "retrieved_at": "2026-09-04T09:05:36Z",
}
EXPECTED_PROTECTED = {
    "public_urls_and_routes",
    "tourvisor_external_contract",
    "lead_payload_field_mapping_delivery",
    "yandex_metrika_configuration_goals_events",
    "pricing_semantics",
    "progressive_loading_and_stale_state_guards",
}
EXPECTED_OWNERSHIP_AREAS = {
    "public_routes_and_canonical_urls",
    "initial_search_and_shared_ds2_shell",
    "tourvisor_search_and_progressive_loading",
    "results_filters_cards_selected_tour_flights_review",
    "lead_ui_states",
    "analytics_metrika",
    "shared_footer",
    "preview_deploy_and_state_simulator",
}


def validate(manifest_path: Path, document_path: Path) -> list[str]:
    errors: list[str] = []
    try:
        data = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        return [f"manifest unreadable: {exc}"]

    try:
        document = document_path.read_text(encoding="utf-8")
    except OSError as exc:
        return [f"document unreadable: {exc}"]

    if data.get("schema_version") != 1:
        errors.append("schema_version must be 1")
    if data.get("project") != "pyatkoff/poisk-turov-test":
        errors.append("project identity drift")
    if data.get("status") != "REFERENCE_ONLY_REVIEW_LOCKED":
        errors.append("Search3 must stay reference-only and review-locked")

    routes = data.get("routes") or {}
    if routes.get("production") != "https://anytoour.ru/poisk-turov/":
        errors.append("production route drift")
    if routes.get("preview") != "https://anytoour.ru/_preview/search3/poisk-turov/":
        errors.append("preview route drift")

    identity = data.get("source_identity") or {}
    for key in ("branch_head", "branch_tree", "implementation_commit", "implementation_tree"):
        if not FULL_SHA.fullmatch(str(identity.get(key, ""))):
            errors.append(f"{key} must be a full Git SHA")
    if identity.get("implementation_commit") != "e5baf32f455cdb0aa1a704964f28e5efbebf57ff":
        errors.append("verified implementation commit drift")
    if identity.get("branch_head") != "6ce565620becaba8e91d50aff13529b5a52aba37":
        errors.append("reference branch head drift")
    if identity.get("branch_tree") != "232320be541dc08271fb6a32fb997c60970b103e":
        errors.append("reference branch tree drift")
    if identity.get("implementation_tree") != "2c88a4e3786cdadc6a0eec2b88fafb1f388ba541":
        errors.append("verified implementation tree drift")

    source_files = identity.get("source_files") or []
    source_paths = {item.get("path") for item in source_files if isinstance(item, dict)}
    if source_paths != set(EXPECTED_SOURCE_FILES):
        errors.append("frozen evidence source file set drift")
    for item in source_files:
        if not isinstance(item, dict):
            errors.append("source file entry must be an object")
            continue
        if not FULL_SHA.fullmatch(str(item.get("git_blob", ""))):
            errors.append(f"invalid Git blob for {item.get('path')}")
        if not SHA256.fullmatch(str(item.get("sha256", ""))):
            errors.append(f"invalid SHA-256 for {item.get('path')}")
        expected_identity = EXPECTED_SOURCE_FILES.get(item.get("path"))
        if expected_identity and (item.get("git_blob"), item.get("sha256")) != expected_identity:
            errors.append(f"frozen source identity drift for {item.get('path')}")

    design = data.get("design_reference") or {}
    if design.get("canonical_set_label") != "/AnyTour/Search3 Design Final/00_CURRENT_FULL_CYCLE":
        errors.append("canonical eight-layout source label drift")
    if design.get("archive_label") != "99_ARCHIVE_ITERATIONS":
        errors.append("archive exclusion label drift")
    if design.get("pixels_vendored_in_repository") is not False:
        errors.append("do not claim reference pixels are vendored without adding checksum evidence")

    layouts = data.get("layouts") or []
    actual_layouts = {item.get("id"): item.get("name") for item in layouts if isinstance(item, dict)}
    if actual_layouts != EXPECTED_LAYOUTS:
        errors.append("the admissible design set must contain exactly the eight frozen layouts")
    for item in layouts:
        if item.get("status") != "REVIEW":
            errors.append(f"layout {item.get('id')} is not review-locked")
        if not item.get("states"):
            errors.append(f"layout {item.get('id')} has no required states")

    run = data.get("run_467") or {}
    if run.get("run_number") != 467 or run.get("conclusion") != "SUCCESS":
        errors.append("run 467 attestation drift")
    if run.get("run_id") != 33813829683 or run.get("run_attempt") != 1:
        errors.append("run 467 immutable run identity drift")
    if run.get("run_url") != "https://github.com/pyatkoff/poisk-turov-test/actions/runs/33813829683":
        errors.append("run 467 URL drift")
    for key, expected_value in EXPECTED_RUN_TIMESTAMPS.items():
        if run.get(key) != expected_value:
            errors.append(f"run 467 {key} drift")
    if run.get("implementation_commit") != identity.get("implementation_commit"):
        errors.append("run 467 is not bound to the frozen implementation commit")
    if run.get("artifact_retention_days") != 30:
        errors.append("run 467 artifact retention evidence drift")
    if run.get("artifact_ids_recorded") is not True or run.get("artifact_bytes_vendored") is not False:
        errors.append("artifact durability must not be overstated")
    if run.get("artifact_bytes_private_copy") is not True:
        errors.append("recovered artifact private-copy evidence drift")
    if run.get("private_copy_storage") != "owner_private_library":
        errors.append("recovered artifacts must remain in owner-private storage")
    if run.get("public_redistribution_authorized") is not False:
        errors.append("candidate pixels must not become publicly redistributable")
    if run.get("candidate_capture_count") != 35:
        errors.append("run 467 candidate capture count drift")
    if run.get("evidence_level") != "SOURCE_RUN_IDS_ARCHIVE_AND_PIXEL_DIGESTS_PRIVATE_BYTES_NO_APPROVED_DESIGN_PIXELS":
        errors.append("run 467 evidence level drift")

    artifacts = run.get("artifacts") or []
    artifact_modes = {item.get("mode") for item in artifacts if isinstance(item, dict)}
    if artifact_modes != set(EXPECTED_ARTIFACTS) or len(artifacts) != len(EXPECTED_ARTIFACTS):
        errors.append("run 467 artifact set drift")
    capture_count = 0
    for artifact in artifacts:
        if not isinstance(artifact, dict):
            errors.append("run 467 artifact entry must be an object")
            continue
        mode = artifact.get("mode")
        expected_artifact = EXPECTED_ARTIFACTS.get(mode)
        if expected_artifact is None:
            continue
        for key, expected_value in expected_artifact.items():
            if artifact.get(key) != expected_value:
                errors.append(f"{mode} artifact {key} drift")
        if artifact.get("expired_when_retrieved") is not False:
            errors.append(f"{mode} artifact was not live when recovered")
        expected_private_name = expected_artifact["name"] + ".zip"
        if artifact.get("private_copy_filename") != expected_private_name:
            errors.append(f"{mode} private-copy filename drift")
        state_hashes = artifact.get("state_sha256") or {}
        if not isinstance(state_hashes, dict):
            errors.append(f"{mode} state_sha256 must be an object")
            state_hashes = {}
        expected_states = EXPECTED_VIEWPORTS[mode][2]
        if list(state_hashes) != expected_states:
            errors.append(f"{mode} captured state index drift")
        for state, digest in state_hashes.items():
            if not SHA256.fullmatch(str(digest)):
                errors.append(f"invalid {mode}/{state} pixel SHA-256")
        capture_count += len(state_hashes)
        capture_index = {
            "report_file": artifact.get("report_file"),
            "report_sha256": artifact.get("report_sha256"),
            "state_sha256": state_hashes,
        }
        canonical = json.dumps(capture_index, sort_keys=True, separators=(",", ":"), ensure_ascii=False).encode()
        calculated = hashlib.sha256(canonical).hexdigest()
        if calculated != expected_artifact["capture_index_sha256"]:
            errors.append(f"{mode} capture index content drift")
    if capture_count != 35:
        errors.append(f"run 467 must retain exactly 35 pixel digests, got {capture_count}")
    jobs = set(run.get("jobs") or [])
    if jobs != {"deploy-preview", "visual-qa desktop", "visual-qa tablet", "visual-qa mobile"}:
        errors.append("run 467 job attestation drift")
    viewports = run.get("viewports") or []
    actual_modes = set()
    for viewport in viewports:
        mode = viewport.get("mode")
        actual_modes.add(mode)
        expected = EXPECTED_VIEWPORTS.get(mode)
        actual = (viewport.get("width"), viewport.get("height"), viewport.get("states") or [])
        if expected != actual:
            errors.append(f"{mode} viewport/state contract drift: expected {expected}, got {actual}")
    if actual_modes != set(EXPECTED_VIEWPORTS):
        errors.append("run 467 must describe desktop, tablet and mobile exactly")

    observation = data.get("live_observation") or {}
    preview_observation = observation.get("preview") or {}
    production_observation = observation.get("production") or {}
    if preview_observation.get("search3_asset_version_prefix") != "e5baf32f455c":
        errors.append("live preview asset-to-implementation binding drift")
    if preview_observation.get("h1_count") != 2 or preview_observation.get("zero_area_h1_count") != 1:
        errors.append("recorded Search3 duplicate-H1 finding drift")
    if production_observation.get("search3_asset_count") != 0:
        errors.append("production Search3 asset observation drift")

    ownership = data.get("ownership") or []
    ownership_areas = {item.get("area") for item in ownership if isinstance(item, dict)}
    if ownership_areas != EXPECTED_OWNERSHIP_AREAS:
        errors.append("migration ownership map drift")
    for item in ownership:
        if item.get("area") != "preview_deploy_and_state_simulator" and not str(item.get("canonical_owner", "")).startswith("main"):
            errors.append(f"non-preview area lost main ownership: {item.get('area')}")

    protected = data.get("protected_contracts") or []
    protected_names = {item.get("name") for item in protected if isinstance(item, dict)}
    if protected_names != EXPECTED_PROTECTED:
        errors.append("protected contract set drift")
    for item in protected:
        if item.get("change_allowed") is not False:
            errors.append(f"protected contract became mutable: {item.get('name')}")

    backlog = data.get("visual_diff_backlog") or []
    backlog_ids = {item.get("id") for item in backlog if isinstance(item, dict)}
    if backlog_ids != {f"S3-REF-{index:03d}" for index in range(1, 8)}:
        errors.append("reference blocker backlog drift")
    for item in backlog:
        if item.get("status") != "OPEN" or not str(item.get("severity", "")).startswith("P1_"):
            errors.append(f"reference blocker must remain open P1 until evidence closes it: {item.get('id')}")

    release_lock = data.get("release_lock") or {}
    if release_lock.get("visual_owner_approval") != "LOCKED":
        errors.append("visual owner approval lock removed")
    if release_lock.get("production_deploy") != "LOCKED":
        errors.append("production deploy lock removed")
    if data.get("next_stage") != "search3/contract-boundaries":
        errors.append("next program gate drift")

    required_document_fragments = [
        "Search3 is a **donor of validated visual and interaction patterns**",
        "e5baf32f455cdb0aa1a704964f28e5efbebf57ff",
        "6ce565620becaba8e91d50aff13529b5a52aba37",
        "https://anytoour.ru/_preview/search3/poisk-turov/",
        "33813829683",
        "9915925993",
        "9915896367",
        "9915896517",
        "search3/contract-boundaries",
        "This dossier does not authorize merge",
    ]
    for fragment in required_document_fragments:
        if fragment not in document:
            errors.append(f"document missing required boundary: {fragment}")

    return errors


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", type=Path, default=DEFAULT_MANIFEST)
    parser.add_argument("--document", type=Path, default=DEFAULT_DOCUMENT)
    args = parser.parse_args()

    errors = validate(args.manifest, args.document)
    if errors:
        print("SEARCH3_REFERENCE_DOSSIER_FAIL")
        for error in errors:
            print(f"- {error}")
        return 1
    print("SEARCH3_REFERENCE_DOSSIER_OK layouts=8 viewports=3 artifacts=3 captures=35 blockers=7 status=review_locked")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
