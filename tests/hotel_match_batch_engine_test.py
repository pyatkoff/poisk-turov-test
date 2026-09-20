#!/usr/bin/env python3
"""Offline engine tests. FakeGateway never performs HTTP, DB or quota IO."""
from __future__ import annotations
from collections import Counter
from dataclasses import asdict, replace
import hashlib
import importlib.util
import json
from pathlib import Path
import sys
import unittest

PATH = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_batch_engine.py'
spec = importlib.util.spec_from_file_location('match_batch_engine', PATH)
m = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = m
spec.loader.exec_module(m)


def task(hid=1000, *, missing=('anex', 'samo'), country=4, party=None, family='anex', oid=13, **kw):
    context = {'country_id': country, 'observation_reference': f'fixture:{hid}',
               'request_scope': {'departure_id': 1, 'date_from': '2026-10-01',
                                 'date_to': '2026-10-01', 'nights': 7,
                                 'adults': 2, 'child_ages': party or []}}
    return m.Task(hid, oid, family, 'test-account', m.canonical(context), frozenset(missing), **kw)


def saved(state='ready'):
    return m.Evidence('fixture:saved-reply', 'a' * 64, state)


class FakeGateway:
    def __init__(self):
        self.events = []
        self.requests = []
        self.operations = set()
        self.blocked = set()
        self.authorization = True
        self.limit = 3000
        self.used = 0
        self.quota_unknown = False
        self.ready = True
        self.http = 200
        self.fail_send = False
        self.fail_persist = False
        self.bad_receipt = False
        self.fail_finish = False
        self.fail_record = False
        self.bad_reservation = False
        self.reuse_reservation = False
        self.reservation_sequence = 0
        self.search_rows = None
        self.change_detail = None
        self.detail_state = 'ready'
        self.normalized_override = None
        self.raw_replies = []
        self.summaries = []

    def begin(self, operation, tasks):
        if operation in self.operations:
            raise RuntimeError('durable_operation_exists')
        self.operations.add(operation)
        self.events.append(('begin', operation))

    def eligible(self, item, phase):
        return (item.hotel_id, phase) not in self.blocked

    def authorize(self, request):
        self.events.append(('authorize', request.action))
        return self.authorization and all(self.eligible(t, request.provider) for t in request.tasks)

    def reserve(self, request):
        if self.quota_unknown:
            raise RuntimeError('existing_account_baseline_unknown')
        if request.provider == 'tourvisor':
            if self.used >= self.limit:
                raise RuntimeError('account_day_cap')
            self.used += 1
        self.events.append(('reserve', request.action))
        self.reservation_sequence += 1
        ref = 'fixture:reservation:' + str(0 if self.reuse_reservation else self.reservation_sequence)
        return m.Reservation(ref, 'wrong' if self.bad_reservation else request.key, request.provider)

    def row(self, t, **overrides):
        return {'hotel_id': t.hotel_id, 'operator_id': t.operator_id,
                'family': t.family, 'country_id': json.loads(t.context_json)['country_id'],
                'tour_id': str(1000000 + t.hotel_id), **overrides}

    def send(self, request, reservation):
        self.events.append(('send', request.action))
        self.requests.append(request)
        if self.fail_send:
            raise TimeoutError('fixture_unknown_outcome')
        first = request.tasks[0]
        action = request.action
        if action == 'tv.search':
            data = {'search_id': request.key}
        elif action == 'tv.status':
            data = {'search_id': request.reference, 'complete': self.ready}
        elif action == 'tv.results':
            rows = [self.row(t) for t in request.tasks] if self.search_rows is None else self.search_rows
            data = {'search_id': request.reference, 'rows': rows}
        elif action == 'tv.detail':
            data = self.row(first, tour_id=request.reference, evidence_state=self.detail_state)
            if self.change_detail:
                data.update(self.change_detail)
        elif action == 'samo.identity':
            data = {'hotel_id': first.hotel_id, 'evidence_state': 'ready'}
        else:
            raise AssertionError('unexpected_action_or_continue')
        raw = m.canonical(data).encode()
        if self.normalized_override is not None:
            data = self.normalized_override
        return m.Reply(self.http, raw, m.canonical(data))

    def persist(self, request, reservation, reply):
        if self.fail_persist:
            raise OSError('fixture_storage_failure')
        self.events.append(('persist', request.action))
        self.raw_replies.append(reply.raw)
        sha = 'b' * 64 if self.bad_receipt else hashlib.sha256(reply.raw).hexdigest()
        return m.Evidence(f'fixture:raw:{len(self.raw_replies)}', sha)

    def record(self, event, data):
        if self.fail_record:
            raise OSError('fixture_checkpoint_failure')
        self.events.append(('record', event))

    def finish(self, summary):
        if self.fail_finish:
            raise OSError('fixture_terminal_failure')
        self.summaries.append(summary)
        self.events.append(('finish', summary['state']))

    def pause(self, seconds):
        self.events.append(('pause', seconds))


class Tests(unittest.TestCase):
    def setUp(self):
        self.g = FakeGateway()

    def run_tasks(self, tasks, **kw):
        return m.BatchEngine(self.g, **kw).run('fixture-operation', tasks)

    def actions(self):
        return Counter(r.action for r in self.g.requests)

    def test_full_2041_hotel_partition_end_to_end(self):
        tasks = ([task(1000+i, missing=('samo',), saved=saved()) for i in range(270)]
                 + [task(2000+i, missing=('anex',)) for i in range(949)]
                 + [task(4000+i) for i in range(822)])
        before = repr(tasks)
        r = self.run_tasks(tasks)
        self.assertEqual(r.unique_tasks, 2041)
        self.assertEqual(r.saved_reused, 270)
        self.assertEqual(self.actions(), {'tv.search': 60, 'tv.status': 60, 'tv.results': 60,
                                         'tv.detail': 1771, 'samo.identity': 1092})
        self.assertEqual(r.attempted_http, {'tourvisor': 1951, 'samo': 1092})
        self.assertEqual(r.mapping_writes, 0)
        self.assertEqual(r.state, 'evidence_complete_not_accepted')
        self.assertEqual(before, repr(tasks))
        actions = [x.action for x in self.g.requests]
        first_samo = actions.index('samo.identity')
        self.assertTrue(all(a.startswith('tv.') for a in actions[:first_samo]))
        self.assertTrue(all(a.startswith('samo.') for a in actions[first_samo:]))
        self.assertEqual(len(r.outcomes), 2041)
        print('SIMULATED_2041_OK TV=1951 SAMO=1092 MAPPINGS=0 LIVE_HTTP=0')

    def test_batches_30_and_remainder_without_padding(self):
        self.run_tasks([task(1000+i, missing=('anex',)) for i in range(61)])
        self.assertEqual([len(r.tasks) for r in self.g.requests if r.action == 'tv.search'], [30,30,1])

    def test_every_scope_field_and_operator_are_hard_batch_keys(self):
        items = [task(1000),task(1001,party=[7]),task(1002,country=2),task(1003,family='biblio',oid=18)]
        other = json.loads(task(1004).context_json)
        other['request_scope']['meal_id'] = 4
        items.append(replace(task(1004), context_json=m.canonical(other)))
        self.run_tasks(items)
        self.assertEqual(self.actions()['tv.search'], 5)
        self.assertTrue(all(len(r.tasks)==1 for r in self.g.requests if r.action=='tv.search'))

    def test_saved_link_or_accepted_anchor_skips_tourvisor(self):
        r = self.run_tasks([task(saved=saved())])
        self.assertEqual(self.actions(), {'samo.identity':1})
        self.assertEqual(r.saved_reused, 1)

    def test_saved_hold_is_not_reacquired_or_sent_to_samo(self):
        r = self.run_tasks([task(saved=saved('hold'))])
        self.assertFalse(self.g.requests)
        self.assertEqual(r.outcomes[task(saved=saved('hold')).key]['samo'], 'evidence_review_required')

    def test_retained_tour_goes_to_detail_without_search(self):
        self.run_tasks([task(retained_tour_id='12345')])
        self.assertEqual(self.actions(), {'tv.detail':1,'samo.identity':1})

    def test_identical_duplicate_is_not_another_request(self):
        t=task(retained_tour_id='12345')
        r=self.run_tasks([t,t])
        self.assertEqual(r.unique_tasks,1)
        self.assertEqual(self.actions()['tv.detail'],1)

    def test_conflicting_duplicate_stops_before_operation(self):
        t=task()
        with self.assertRaises(ValueError): self.run_tasks([t,replace(t,saved=saved())])
        self.assertFalse(self.g.events)

    def test_second_context_for_same_edge_is_not_an_automatic_retry(self):
        t=task();ctx=json.loads(t.context_json);ctx['request_scope']['date_to']='2026-10-02'
        with self.assertRaises(ValueError):self.run_tasks([t,replace(t,context_json=m.canonical(ctx))])
        self.assertFalse(self.g.events)

    def test_blocked_and_complete_tasks_make_no_http(self):
        self.g.blocked.add((1000,'tourvisor'))
        self.run_tasks([task(),task(1001,missing=())])
        self.assertFalse(self.g.requests)

    def test_samo_current_guard_rechecked_after_tv(self):
        self.g.blocked.add((1000,'samo'))
        self.run_tasks([task(retained_tour_id='123')])
        self.assertEqual(self.actions(),{'tv.detail':1})

    def test_unauthorized_stops_before_reservation(self):
        self.g.authorization=False
        with self.assertRaises(m.RunStopped):self.run_tasks([task()])
        self.assertEqual(self.g.used,0)
        self.assertFalse(self.g.requests)

    def test_unknown_baseline_is_not_initialized_to_zero(self):
        self.g.quota_unknown=True
        with self.assertRaises(m.RunStopped):self.run_tasks([task()])
        self.assertEqual(self.g.used,0)
        self.assertFalse(self.g.requests)

    def test_one_common_account_cap_across_all_operators(self):
        self.g.limit=3
        items=[task(1000+i,family=f,oid=o,retained_tour_id=str(123+i))
               for i,(f,o) in enumerate([('anex',13),('biblio',18),('funsun',25),('intourist',43)])]
        with self.assertRaises(m.RunStopped) as e:self.run_tasks(items)
        self.assertEqual(self.g.used,3)
        self.assertEqual(e.exception.summary.attempted_http,{'tourvisor':3,'samo':0})
        self.assertEqual(len(self.g.requests),3)

    def test_reservation_binding_required_before_send(self):
        self.g.bad_reservation=True
        with self.assertRaises(m.RunStopped):self.run_tasks([task()])
        self.assertFalse(self.g.requests)

    def test_every_send_is_reserved_and_persisted_before_next_send(self):
        self.run_tasks([task(),task(1001)])
        io=[x[0] for x in self.g.events if x[0] in {'authorize','reserve','send','persist'}]
        self.assertEqual(io,['authorize','reserve','send','persist']*len(self.g.requests))
        barrier=self.g.events.index(('record','tourvisor_complete'))
        self.assertGreater(self.g.events.index(('send','samo.identity')),barrier)

    def test_429_and_404_are_saved_then_stop_without_retry(self):
        for status in (429,404,401,503):
            self.setUp();self.g.http=status
            with self.assertRaises(m.RunStopped):self.run_tasks([task()])
            self.assertEqual(len(self.g.requests),1)
            self.assertEqual(len(self.g.raw_replies),1)

    def test_unknown_transport_outcome_stops_and_cannot_replay(self):
        self.g.fail_send=True
        with self.assertRaises(m.RunStopped) as e:self.run_tasks([task()])
        self.assertEqual(e.exception.summary.attempted_http['tourvisor'],1)
        self.g.fail_send=False
        with self.assertRaises(RuntimeError):self.run_tasks([task()])
        self.assertEqual(len(self.g.requests),1)

    def test_storage_failure_prevents_following_requests(self):
        for flag in ('fail_persist','bad_receipt'):
            self.setUp();setattr(self.g,flag,True)
            with self.assertRaises(m.RunStopped):self.run_tasks([task(),task(1001)])
            self.assertEqual(len(self.g.requests),1)

    def test_terminal_receipt_failure_is_unknown_not_completed(self):
        self.g.fail_finish=True
        with self.assertRaises(m.RunStopped) as e:self.run_tasks([])
        self.assertEqual(e.exception.summary.state,'receipt_unknown_no_replay')

    def test_checkpoint_failure_cannot_start_next_phase(self):
        self.g.fail_record=True
        with self.assertRaises(m.RunStopped):self.run_tasks([task(saved=saved())])
        self.assertFalse(self.g.requests)

    def test_not_ready_polling_is_bounded_without_continue(self):
        self.g.ready=False
        r=self.run_tasks([task()],polls=2)
        self.assertEqual(self.actions(),{'tv.search':1,'tv.status':2})
        self.assertEqual(next(iter(r.outcomes.values()))['tourvisor'],'search_not_ready_no_replay')

    def test_empty_result_does_not_invent_a_missing_hotel(self):
        self.g.search_rows=[]
        r=self.run_tasks([task()])
        self.assertEqual(self.actions(),{'tv.search':1,'tv.status':1,'tv.results':1})
        self.assertEqual(next(iter(r.outcomes.values()))['tourvisor'],'not_found_in_context')

    def test_detail_identity_conflict_never_reaches_samo(self):
        for patch in ({'hotel_id':9999},{'operator_id':99},{'country_id':99},{'family':'biblio'},{'tour_id':'987'}):
            self.setUp();self.g.change_detail=patch
            self.run_tasks([task(retained_tour_id='123')])
            self.assertEqual(self.actions(),{'tv.detail':1})

    def test_new_hold_is_durable_but_not_accepted_or_requeried(self):
        self.g.detail_state='hold'
        r=self.run_tasks([task(retained_tour_id='123')])
        self.assertEqual(self.actions(),{'tv.detail':1})
        self.assertEqual(r.mapping_writes,0)
        self.assertEqual(len(self.g.raw_replies),1)

    def test_result_link_avoids_detail(self):
        t=task();self.g.search_rows=[self.g.row(t,evidence_state='ready')]
        self.run_tasks([t])
        self.assertEqual(self.actions(),{'tv.search':1,'tv.status':1,'tv.results':1,'samo.identity':1})

    def test_multiple_captured_links_are_held_without_first_link_wins(self):
        t=task();self.g.search_rows=[self.g.row(t,evidence_state='ready'),self.g.row(t,evidence_state='ready',tour_id='222')]
        r=self.run_tasks([t])
        self.assertEqual(r.outcomes[t.key]['tourvisor'],'multiple_captures_hold')
        self.assertFalse(self.actions()['samo.identity'])

    def test_signed_multivalue_raw_link_is_never_parsed_or_truncated(self):
        link='https://agent.anextour.ru/x?HOTELLIST=-1575330%2C41074&HOTELLIST=888'
        self.g.change_detail={'operator_link':link}
        self.run_tasks([task(retained_tour_id='123')])
        self.assertEqual(json.loads(self.g.raw_replies[0])['operator_link'],link)
        self.assertIsInstance(self.g.requests[-1].evidence,m.Evidence)

    def test_unbound_and_invalid_tasks_fail_before_io(self):
        for make in (lambda:task(hid=True),lambda:task(family='unavailable'),
                     lambda:replace(task(),context_json='{}'),lambda:task(retained_tour_id='invalid')):
            with self.assertRaises(ValueError):make()
        self.assertFalse(self.g.events)

    def test_completed_samo_evidence_deduplicates_across_operators(self):
        self.run_tasks([task(saved=saved()),task(family='biblio',oid=18,saved=saved())])
        self.assertEqual(self.actions(),{'samo.identity':1})

    def test_samo_bool_identity_is_rejected(self):
        self.g.normalized_override={'hotel_id':True,'evidence_state':'ready'}
        with self.assertRaises(m.RunStopped):self.run_tasks([task(1,saved=saved())])

    def test_reused_reservation_does_not_authorize_second_physical_call(self):
        self.g.reuse_reservation=True
        with self.assertRaises(m.RunStopped):self.run_tasks([task()])
        self.assertEqual(len(self.g.requests),1)

    def test_engine_instance_is_one_shot(self):
        e=m.BatchEngine(self.g);e.run('op1',[])
        with self.assertRaises(ValueError):e.run('op2',[])


if __name__=='__main__':
    unittest.main(verbosity=2)
