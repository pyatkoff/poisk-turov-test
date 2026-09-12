#!/usr/bin/env python3
"""Offline MATCH audit interpretation; never a mapping writer or acceptance gate.

Missing historical digests are missing provenance, not proof of database drift.
Alternate/EX names cannot erase meaningful qualifiers from the current primary
name. Passing this necessary check never authorizes identity acceptance.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import unicodedata
from pathlib import Path
from typing import Any

QUALIFIERS = frozenset({'annex', 'beach', 'garden', 'north', 'south', 'posh', 'city', 'adults', 'adult'})
FORMER = re.compile(r'\(\s*(?:ex\b|formerly\b|быв\b)[^)]*\)', re.IGNORECASE)


def primary_name_guard(source: str, target: str) -> dict[str, Any]:
    """Conservative veto only. Call with current primary names, never aliases."""
    if not isinstance(source, str) or not isinstance(target, str) or not source.strip() or not target.strip():
        return {'status': 'blocked', 'reason': 'missing_primary_name', 'auto_accept': False}
    def protected(name: str) -> set[str]:
        current = FORMER.sub(' ', unicodedata.normalize('NFKC', name).casefold())
        tokens = set(re.findall(r'[^\W_]+', current, re.UNICODE))
        return tokens & QUALIFIERS
    source_terms, target_terms = protected(source), protected(target)
    differences = sorted(source_terms ^ target_terms)
    return {'status': 'blocked' if differences else 'requires_other_identity_guards',
            'reason': 'current_primary_qualifier_mismatch' if differences else None,
            'differing_qualifiers': differences, 'auto_accept': False}


def receipt_row_state(expected: dict[str, Any], current: list[dict[str, Any]]) -> str:
    if len(current) != 1:
        return 'target_or_cardinality_changed'
    row = current[0]
    if int(row.get('catalog_hotel_id', -1)) != int(expected['local_id']) or int(row.get('enabled', 0)) != 1:
        return 'target_or_cardinality_changed'
    for field in ('mapping_digest', 'source_row_digest'):
        if expected.get(field) is not None and expected[field] != row.get(field):
            return 'digest_changed'
    if not expected.get('mapping_digest') or not expected.get('source_row_digest'):
        return 'matching_available_fields_incomplete_legacy_provenance'
    return 'full_match'


def review(audit: dict[str, Any], raw_details: dict[str, Any], computed: dict[str, Any]) -> dict[str, Any]:
    if audit.get('status') != 'read_only_audit_complete':
        raise ValueError('completed_read_only_audit_required')
    if any(audit.get(key) != 0 for key in ('database_writes', 'mapping_writes', 'supplier_calls', 'tourvisor_calls')):
        raise ValueError('read_only_provenance_required')
    counts = {'full_match': audit['current_verified'],
              'matching_available_fields_incomplete_legacy_provenance': 0,
              'target_or_cardinality_changed': 0, 'digest_changed': 0}
    for row in audit['changed_rows']:
        if row['provider'] != 'anex':
            raise ValueError('unsupported_changed_provider_do_not_infer')
        counts[receipt_row_state(row, row['current'])] += 1
    if sum(counts.values()) != audit['historical_row_count']:
        raise ValueError('receipt_total_mismatch')
    hotels = {int(row['id']): row for row in audit['local_hotels']}
    anchors = {str(row['external_hotel_id']): row for row in audit['andromeda_anchors']}
    details = {int(row['anex_hotel_id']): row['detail'] for row in raw_details['details']['rows'] if row.get('status') == 'ok'}
    proposals = {int(row['anex_hotel_id']): row for row in computed['prepared']}
    cases = []
    for hotspot in audit['hotspots']:
        aid = int(hotspot['anex_hotel_id'])
        source = details[aid]
        proposal = proposals[aid]
        alternative_checks = []
        for lid in hotspot['disputed_local_ids']:
            alternative_checks.append({'local_id': lid, 'local_name': hotels[lid]['name'],
                                       'primary_name_guard': primary_name_guard(source['name'], hotels[lid]['name'])})
        cases.append({'anex_hotel_id': aid, 'current_mappings': hotspot['mappings'],
                      'raw_supplier_detail': source,
                      'supplier_reported_local_id': None,
                      'legacy_computed_proposal': {'local_id': proposal['target_local_hotel_id'],
                          'name_score': proposal['name']['score'], 'rule': proposal['rule'],
                          'provenance_kind': 'computed_candidate_not_supplier_identity'},
                      'alternative_checks': alternative_checks})
    posh = anchors['2000042757']
    if len(posh['source_rows']) != 1:
        raise ValueError('ambiguous_posh_primary_source')
    if posh['evidence_sha256'] != posh['actual_evidence_sha256']:
        raise ValueError('posh_evidence_digest_mismatch')
    source = posh['source_rows'][0]
    proposal = {'status': 'requires_explicit_owner_authorization_not_executable',
                'namespace': 'andromeda_catalog', 'external_hotel_id': '2000042757',
                'observed_current_status': posh['decision_status'],
                'observed_current_local_id': posh['local_hotel_id'],
                'expected_current_evidence_sha256': posh['evidence_sha256'],
                'proposed_local_id': 132075, 'primary_name': source['name'],
                'alternate_name': source.get('lName'),
                'existing_target_guard': primary_name_guard(source['name'], hotels[int(posh['local_hotel_id'])]['name']),
                'proposed_target_guard': primary_name_guard(source['name'], hotels[132075]['name']),
                'mandatory_before_any_change': ['explicit_owner_authorization', 'new_current_full_evidence_revalidation',
                    'preserve_original_evidence', 'compare_and_swap_expected_old_target_status_digest',
                    'transaction_and_post_commit_readback'], 'auto_accept': False}
    return {'schema_version': 1, 'status': 'offline_interpretation_of_completed_current_audit',
            'audit_operation_id': audit['operation_id'], 'audit_generated_at_utc': audit['generated_at_utc'],
            'receipt_counts': counts, 'historical_row_count': audit['historical_row_count'],
            'cases': cases, 'posh_anchor_proposal': proposal,
            'nova_note': '44411 was written. Raw ANEX does not identify local123389; that ID was a fuzzy suggestion. Do not reassign the existing131384 mapping on that false provenance.',
            'guard_scope': 'necessary_primary_name_veto_only_not_installed_in_runtime_or_existing_writers',
            'mapping_writes': 0, 'database_writes': 0, 'supplier_calls': 0, 'tourvisor_calls': 0,
            'apply_manifest': False, 'no_replay': True}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--audit', required=True, type=Path)
    parser.add_argument('--raw-details', required=True, type=Path)
    parser.add_argument('--computed-review', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    paths = [args.audit, args.raw_details, args.computed_review]
    raw = [path.read_bytes() for path in paths]
    result = review(*(json.loads(value) for value in raw))
    result['input_sha256'] = dict(zip(['current_audit', 'raw_details', 'computed_review'], [hashlib.sha256(value).hexdigest() for value in raw]))
    with args.output.open('x', encoding='utf-8') as out:
        out.write(json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    print(json.dumps(result['receipt_counts'], sort_keys=True))


if __name__ == '__main__':
    main()
