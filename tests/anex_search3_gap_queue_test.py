import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('gaps', ROOT / 'scripts/diagnostics/anex_search3_gap_queue.py')
gaps = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gaps)


class GapQueueTests(unittest.TestCase):
    def setUp(self):
        self.queue = gaps.load_queue()
        self.cp = {'schema_version': 1, 'scope': 'preview', 'queue_sha256': gaps.QUEUE_SHA,
                   'rows': [], 'in_flight': [], 'completed_total': 0, 'remaining': 112}

    def test_bootstrap_only_from_reviewed_artifact(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': gaps.BOOTSTRAP_ARTIFACT}))
            self.assertEqual(gaps.restore(path, self.queue), self.cp)
            (path / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': gaps.BOOTSTRAP_ARTIFACT + 1}))
            with self.assertRaises(ValueError):
                gaps.restore(path, self.queue)
            gaps.save(path, self.cp, self.queue)
            self.assertEqual(gaps.restore(path, self.queue), self.cp)

    def test_ssh_failure_reports_only_allowlisted_reason_without_retry(self):
        secret = 'private-host-user-and-token'
        env = {'ANYTOOUR_DEPLOY_SSH_KEY': 'fake-key', 'ANYTOOUR_DEPLOY_HOST': 'example.invalid',
               'ANYTOOUR_DEPLOY_USER': 'test'}
        for message, reason in (
            ('kex_exchange_identification: Connection closed by remote host', 'ssh_connection_closed'),
            ('Permission denied (publickey).', 'ssh_authentication_failed'),
            ('Host key verification failed.', 'ssh_host_key_rejected'),
            ('unexpected diagnostic', 'ssh_exit_nonzero'),
        ):
            response = type('Response', (), {'returncode': 255, 'stdout': secret, 'stderr': message + secret})()
            with patch.dict(os.environ, env), patch.object(gaps.subprocess, 'run', return_value=response) as run:
                with self.assertRaises(gaps.SSHBatchError) as caught:
                    gaps.ssh_batch([], {}, 1)
            self.assertEqual(run.call_count, 1)
            report = gaps.failure_report(caught.exception, 'preflight')
            self.assertEqual((report['reason_code'], report['exit_code']), (reason, 255))
            self.assertNotIn(secret, json.dumps(report) + str(caught.exception))
            self.assertNotIn(message, json.dumps(report))

    def test_oversized_response_is_not_reported_as_successful_ssh_exit(self):
        env = {'ANYTOOUR_DEPLOY_SSH_KEY': 'fake-key', 'ANYTOOUR_DEPLOY_HOST': 'example.invalid',
               'ANYTOOUR_DEPLOY_USER': 'test'}
        response = type('Response', (), {'returncode': 0, 'stdout': 'x' * 4000001, 'stderr': ''})()
        with patch.dict(os.environ, env), patch.object(gaps.subprocess, 'run', return_value=response):
            with self.assertRaises(gaps.SSHBatchError) as caught:
                gaps.ssh_batch([], {}, 1)
        self.assertEqual(gaps.failure_report(caught.exception, 'observed_batch')['reason_code'], 'response_size_limit')

    def test_verbose_ssh_progress_preserves_only_fixed_flags(self):
        secret = 'private-host-key-path-and-command'
        message = ('debug1: Connection established.\nAuthenticated to ' + secret
                   + '\ndebug1: Sending command: ' + secret + '\nexec request failed on channel 0')
        error = gaps.SSHBatchError(255, message)
        report = gaps.failure_report(error, 'preflight')
        self.assertEqual(report['reason_code'], 'ssh_session_rejected')
        self.assertEqual(report['ssh_progress'], {'tcp_connected': True, 'authenticated': True,
                                                'multiplexing_seen': False, 'command_sent': True, 'remote_exit_seen': False})
        self.assertNotIn(secret, json.dumps(report) + str(error))
        self.assertIn('stage=command_sent', str(error))
        self.assertEqual(gaps.ssh_progress(''), dict.fromkeys(report['ssh_progress'], False))

    def test_only_direct_preauth_close_retries_once(self):
        from types import SimpleNamespace
        closed = SimpleNamespace(returncode=255, stdout='', stderr=
            'debug1: auto-mux: Trying existing master\nControl socket does not exist\n'
            'debug1: Connection established.\nkex_exchange_identification: Connection closed by remote host')
        ok = SimpleNamespace(returncode=0, stdout='{}', stderr='')
        with patch.object(gaps.subprocess, 'run', side_effect=[closed, ok]) as run, \
                patch.object(gaps.time, 'sleep') as wait:
            self.assertEqual(gaps.run_ssh(['ssh'], '{}', {}), (ok, 2))
            self.assertEqual(run.call_count, 2)
            wait.assert_called_once_with(2)
        with patch.object(gaps.subprocess, 'run', return_value=closed) as run, \
                patch.object(gaps.time, 'sleep'), self.assertRaises(gaps.SSHBatchError) as caught:
            gaps.run_ssh(['ssh'], '{}', {})
        self.assertEqual(run.call_count, 2)
        self.assertEqual(caught.exception.attempts, 2)

    def test_uncertain_command_mux_and_auth_failure_are_never_replayed(self):
        from types import SimpleNamespace
        prefix = 'debug1: Connection established.\n'
        for message, stdout in (
            ('Authenticated to host\nConnection closed', ''),
            ('debug1: Sending command: work\nConnection closed', ''),
            ('debug1: mux_client_request_session: master session id: 2\nConnection closed', ''),
            ('Permission denied (publickey)', ''),
            ('Host key verification failed', ''),
            ('Connection closed', '{"partial":"response"}'),
        ):
            failed = SimpleNamespace(returncode=255, stdout=stdout, stderr=prefix + message)
            with patch.object(gaps.subprocess, 'run', return_value=failed) as run, \
                    patch.object(gaps.time, 'sleep') as wait, self.assertRaises(gaps.SSHBatchError):
                gaps.run_ssh(['ssh'], '{}', {})
            self.assertEqual(run.call_count, 1)
            wait.assert_not_called()

    def test_merge_preserves_old_rows_and_rejects_replay(self):
        first, second = [r['anex_hotel_id'] for r in self.queue['rows'][:2]]
        self.cp['in_flight'] = [first]
        row = {'external_id': first, 'status': 'review', 'reason': 'competing_candidates', 'candidates': []}
        after = gaps.merge(self.cp, [row], self.queue)
        after['in_flight'] = [second]
        with self.assertRaises(ValueError):
            gaps.merge(after, [row], self.queue)
        result = gaps.merge(after, [dict(row, external_id=second)], self.queue)
        self.assertEqual(result['rows'][0], row)
        self.assertEqual(result['remaining'], 110)

    def test_inflight_cannot_overlap_completed(self):
        identifier = self.queue['rows'][0]['anex_hotel_id']
        self.cp.update(rows=[{'external_id': identifier, 'status': 'review'}], in_flight=[identifier], completed_total=1, remaining=111)
        with self.assertRaises(ValueError):
            gaps.validate_checkpoint(self.cp, self.queue)

    def test_bad_counts_and_foreign_ids_rejected(self):
        for cp in [dict(self.cp, remaining=0), dict(self.cp, in_flight=[99999999]),
                   dict(self.cp, in_flight=[r['anex_hotel_id'] for r in self.queue['rows'][:31]])]:
            with self.assertRaises(ValueError):
                gaps.validate_checkpoint(cp, self.queue)

    def test_strict_matching_reused(self):
        ns = {}
        exec(gaps.matching_source(), ns)
        api = {'id': 528, 'name': 'Sharm Holiday Resort', 'country': 'Egypt', 'latitude': 27.9, 'longitude': 34.3}
        xml = dict(api, alternate_name='')
        local = {'id': 425, 'name': api['name'], 'country_name': 'Египет', 'latitude': 27.9, 'longitude': 34.3}
        candidate = ns['candidate_rank'](api, xml, local)
        self.assertEqual(ns['geo_decision'](api, [candidate], 'same_record')[0], 'strong_candidate')
        self.assertEqual(ns['geo_decision'](api, [candidate, dict(candidate, id=426)], 'same_record')[0], 'review')
        self.assertEqual(ns['geo_decision'](api, [dict(candidate, distance_m=None)], 'same_record')[0], 'review')
        self.assertEqual(ns['geo_decision'](api, [dict(candidate, country_match=False)], 'same_record')[0], 'review')

    def test_delta_recomputes_evidence_and_rejects_forged_score(self):
        import hashlib
        ns = {}
        exec(gaps.matching_source(), ns)
        identifier = self.queue['rows'][0]['anex_hotel_id']
        original = {'external_id': identifier, 'name': 'Sharm Holiday Resort', 'alternate_name': '', 'country': 'Egypt', 'status': 'review'}
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': '', 'town_id': None}
        api = dict(xml, country='Egypt', latitude=27.9, longitude=34.3)
        candidate = ns['candidate_rank'](api, xml, {'id': 425, 'name': original['name'], 'country_name': 'Egypt', 'latitude': 27.9, 'longitude': 34.3})
        row = {'external_id': identifier, 'status': 'strong_candidate', 'reason': 'name_country_coordinates',
               'xml': xml, 'api': api, 'candidates': [candidate]}
        self.cp.update(rows=[row], completed_total=1, remaining=111)
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            queue = copy.deepcopy(self.queue)
            for key, name, value in [('catalog_sha256', 'anex-hotel-catalog-match.json', {'matches': [original]}),
                                     ('geo_sha256', 'anex-hotel-geo-enrichment.json', {'rows': []})]:
                raw = json.dumps(value).encode()
                (directory / name).write_bytes(raw)
                queue['sources'][key] = hashlib.sha256(raw).hexdigest()
            gaps.save(directory, self.cp, queue)
            with patch.object(gaps, 'load_queue', return_value=queue):
                result = gaps.approved_delta(directory / gaps.CHECKPOINT)
                self.assertEqual(result['rows'][0]['catalog_hotel_id'], 425)
                candidate['distance_m'] = 9999
                gaps.save(directory, self.cp, queue)
                with self.assertRaises(ValueError):
                    gaps.approved_delta(directory / gaps.CHECKPOINT)

    def test_remote_rate_limit_stops_remaining_api_calls(self):
        import ast
        source = Path(gaps.__file__).read_text()
        node = next(n for n in ast.parse(source).body if isinstance(n, ast.FunctionDef) and n.name == 'remote_batch')
        ns = {'time': gaps.time, 'DETAILS_PHP': 'fixed'}
        exec(gaps.matching_source(), ns)
        exec(ast.get_source_segment(source, node), ns)
        selected = self.queue['rows'][:3]
        originals = {str(r['anex_hotel_id']): {'name': r['hotel_name'], 'alternate_name': '', 'status': 'review'} for r in selected}
        response = type('Response', (), {'returncode': 0, 'stdout': '{"status":"source_error","reason":"rate_limited"}'})()
        snapshot = type('Response', (), {'returncode': 0, 'stdout': '{"status":"ok","staging_total":8362}'})()
        with patch.object(ns['subprocess'], 'run', side_effect=[snapshot, response, snapshot]) as run:
            rows = ns['remote_batch'](selected, originals, 1)['rows']
        self.assertEqual(run.call_count, 3)  # two DB snapshots, only one supplier call
        self.assertEqual([r['reason'] for r in rows], ['rate_limited'] * 3)

    def test_empty_php_details_do_not_abort_next_valid_hotel(self):
        import ast
        source = Path(gaps.__file__).read_text()
        node = next(n for n in ast.parse(source).body if isinstance(n, ast.FunctionDef) and n.name == 'remote_batch')
        ns = {'time': gaps.time, 'DETAILS_PHP': 'details', 'CATALOG_PHP': 'catalog'}
        exec(gaps.matching_source(), ns)
        exec(ast.get_source_segment(source, node), ns)
        selected = self.queue['rows'][:4]
        originals = {str(r['anex_hotel_id']): {'name': 'Sharm Holiday Resort', 'alternate_name': '',
                     'country': 'Egypt', 'status': 'review'} for r in selected}
        last = selected[-1]['anex_hotel_id']
        def response(value):
            return type('Response', (), {'returncode': 0, 'stdout': json.dumps(value)})()
        snapshot = response({'status': 'ok', 'staging_total': 8362})
        details = {'id': last, 'name': 'Sharm Holiday Resort', 'state': 'Egypt',
                   'town': 'Sharm', 'latitude': 27.9, 'longitude': 34.3}
        candidate = {'id': 425, 'name': details['name'], 'country_name': 'Egypt',
                     'subregion_name': 'Sharm', 'latitude': 27.9, 'longitude': 34.3}
        responses = [snapshot] + [response({'status': 'ok', 'details': empty}) for empty in ([], {}, None)]
        responses += [response({'status': 'ok', 'details': details}),
                      response({'status': 'ok', 'items': [{'key': last, 'candidates': [candidate]}]}), snapshot]
        with patch.object(ns['subprocess'], 'run', side_effect=responses) as run:
            result = ns['remote_batch'](selected, originals, 1)
        rows = result['rows']
        self.assertEqual([r['reason'] for r in rows[:3]], ['details_empty', 'details_empty', 'details_invalid'])
        self.assertEqual(rows[-1]['status'], 'strong_candidate')
        self.assertEqual(rows[-1]['candidates'][0]['resort_evidence']['shared_names'], ['sharm'])
        self.assertEqual(run.call_count, 7)
        query = json.loads(run.call_args_list[-2].kwargs['input'])['queries'][0]
        self.assertEqual(query['country_id'], 1)
        self.assertEqual(result['preservation']['staging_total'], 8362)
        countries = {str(r['anex_hotel_id']): 4 for r in selected}
        with patch.object(ns['subprocess'], 'run', side_effect=responses) as run:
            result = ns['remote_batch'](selected, originals, countries)
        query = json.loads(run.call_args_list[-2].kwargs['input'])['queries'][0]
        self.assertEqual(query['country_id'], 4)
        self.assertEqual(result['rows'][-1]['status'], 'strong_candidate')

    def test_failure_diagnostic_excludes_arbitrary_error_text(self):
        secret = 'test-secret-must-never-be-logged'
        report = gaps.failure_report(ValueError(secret), 'remote_batch')
        self.assertNotIn(secret, json.dumps(report))
        report = gaps.failure_report(gaps.RemoteBatchError({'remote_error': 'AttributeError',
            'function': 'supplier_record', 'line': 55, 'message': secret}), 'remote_batch')
        self.assertEqual(report['remote_function'], 'supplier_record')
        self.assertEqual(report['error_kind'], 'AttributeError')
        self.assertNotIn(secret, json.dumps(report))
        report = gaps.failure_report(gaps.RemoteBatchError({'remote_error': secret,
            'function': secret, 'line': secret}), 'remote_batch')
        self.assertNotIn(secret, json.dumps(report))


if __name__ == '__main__':
    unittest.main()
