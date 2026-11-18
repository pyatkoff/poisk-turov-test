"""Pure result classification only. Never relaxes source/editor price admission.
Caller MUST retain pinned source, native status, full context inspection, journal,
feed, receipt, budget and concurrency checks. No result fields are removed/mutated.
"""
from collections import Counter

def require(value, reason):
    if not value:
        raise ValueError(reason)

def ids(value, label):
    require(isinstance(value, list), label + '_not_list')
    require(all(type(x) is int and x > 0 for x in value), label + '_invalid')
    require(len(set(value)) == len(value), label + '_duplicate')
    return set(value)

def classify_expired(result, *, mode, admitted_verified_ids):
    require(mode in ('inspect', 'default'), 'mode_invalid')
    admitted = ids(admitted_verified_ids, 'admitted')
    require(bool(admitted), 'admitted_empty')
    require(result.get('ok') is True, 'result_not_ok')
    require(not any(result.get(k) for k in ('error', 'child_failure', 'pending', 'preflight_block', 'skipped')), 'global_error')
    require(all(type(result.get(k)) is int and result[k] == 0 for k in ('new_posts', 'supplier_calls', 'site_writes', 'held_pairs')), 'scope_changed')
    require(result.get('held_ids') == [], 'held_present')
    verified = ids(result.get('verified_ids'), 'verified')
    eligible = ids(result.get('eligible_ids'), 'eligible')
    unchanged = ids(result.get('unchanged_ids'), 'unchanged')
    updates = result.get('updates')
    require(isinstance(updates, list) and all(isinstance(x, dict) for x in updates), 'updates_invalid')
    updated = ids([x.get('tourvisor_hotel_id') for x in updates], 'updates')
    deferred = result.get('deferred')
    require(isinstance(deferred, list) and all(isinstance(x, dict) for x in deferred), 'deferred_invalid')
    deferred_ids = ids([x.get('tourvisor_hotel_id') for x in deferred], 'deferred')
    allowed = {'monitor_price_expired', 'no_fresh_observation', 'abandoned_before_write_record'}
    require(all(set(x) == {'tourvisor_hotel_id', 'reason'} and x['reason'] in allowed for x in deferred), 'other_deferred_error')
    counts = dict(Counter(x['reason'] for x in deferred))
    def count_map(value, expected, label):
        # PHP encodes empty associative arrays as []. Nonempty lists forbidden.
        if value == []:
            value = {}
        require(isinstance(value, dict) and all(type(n) is int and n > 0 for n in value.values()) and value == expected, label)
    count_map(result.get('deferred_counts'), counts, 'deferred_counts_mismatch')
    expired = {x['tourvisor_hotel_id'] for x in deferred if x['reason'] == 'monitor_price_expired'}
    count_map(result.get('error_counts'), {'monitor_price_expired': len(expired)} if expired else {}, 'error_counts_mismatch')
    require((verified | eligible | unchanged | updated | deferred_ids) <= admitted, 'outside_admitted_roster')
    require(not deferred_ids & (eligible | unchanged | updated), 'deferred_also_applied')
    require(not eligible & unchanged, 'eligible_unchanged_overlap')
    require(updated <= eligible and (eligible | unchanged) <= verified, 'receipt_membership_invalid')
    for key, group in [('updated_pairs', updated), ('eligible_pairs', eligible), ('unchanged_pairs', unchanged)]:
        require(type(result.get(key)) is int and result[key] == len(group), key + '_mismatch')
    scan = eligible | unchanged | deferred_ids
    require(type(result.get('scanned_pairs')) is int and result['scanned_pairs'] == len(scan), 'scan_partition_mismatch')
    require(type(result.get('roster_pairs')) is int and result['roster_pairs'] == len(admitted), 'roster_mismatch')
    if mode == 'inspect':
        require(result.get('mode') == 'read_only_hook_inspection' and verified == admitted and scan == admitted, 'inspection_incomplete')
        require(not updated, 'inspection_updated')
        for key in ('global_journal_writes', 'post_writes', 'feed_writes'):
            require(key not in result or type(result[key]) is int and result[key] == 0, 'inspection_writes')
    else:
        require(result.get('mode') != 'read_only_hook_inspection', 'default_mode_invalid')
        require(result.get('budget_seconds') == 480 and len(updated) <= 30, 'default_budget_changed')
        require(eligible == updated, 'default_unfinished_eligible')
        require(verified == eligible | unchanged and not expired & verified, 'default_verified_partition_invalid')
    return {'classification': 'expired_deferred_only' if expired else 'no_classified_errors',
            'expired_deferred_ids': sorted(expired), 'expired_deferred_count': len(expired),
            'expiry_guard_relaxed': False, 'source_result_modified': False}
