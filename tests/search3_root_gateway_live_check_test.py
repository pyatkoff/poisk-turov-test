#!/usr/bin/env python3
"""Offline admission and bounded-runtime tests for the one TV control search."""
import copy
import contextlib
import datetime as dt
import hashlib
import importlib.util
import io
import json
import pathlib
import subprocess
import sys
import tempfile
import unittest
import urllib.error
import urllib.parse
from unittest import mock


ROOT = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts/deploy"))
SPEC = importlib.util.spec_from_file_location(
    "search3_root_gateway_live_check",
    ROOT / "scripts/deploy/search3_root_gateway_live_check.py",
)
CHECK = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CHECK)

CONTROL = "a" * 40
NOW = dt.datetime(2026, 10, 19, 7, 0, tzinfo=dt.timezone.utc)


def event_fixture():
    return {
        "action": "created",
        "repository": {"full_name": "pyatkoff/poisk-turov-test"},
        "issue": {"number": 4217},
        "comment": {
            "id": 6261700001,
            "user": {"id": 226193297},
            "author_association": "OWNER",
            "body": f"/check-search3-root-tv {CONTROL} tv-20261019-anex13-v1 6261699999",
        },
    }


def env_fixture():
    return {
        "GITHUB_REPOSITORY": "pyatkoff/poisk-turov-test",
        "GITHUB_REF": "refs/heads/main",
        "GITHUB_WORKFLOW_REF": "pyatkoff/poisk-turov-test/.github/workflows/search3-root-gateway-install.yml@refs/heads/main",
        "GITHUB_ACTOR": "pyatkoff",
        "GITHUB_TRIGGERING_ACTOR": "pyatkoff",
        "GITHUB_EVENT_NAME": "issue_comment",
        "GITHUB_RUN_ATTEMPT": "1",
        "GITHUB_SHA": CONTROL,
        "GITHUB_RUN_ID": "39190000001",
    }


def budget_fixture(**changes):
    value = {
        "schema_version": 1,
        "operation_id": "tv-20261019-anex13-v1",
        "account_env": "TOURVISOR_JWT",
        "provider_day": "2026-10-19",
        "source": "owner_explicit_operation_budget",
        "approval_reference": 6261576536,
        "reserved_search_units": 1,
        "max_http_attempts": 3,
    }
    value.update(changes)
    return {
        "id": 6261699999,
        "user": {"id": 226193297},
        "author_association": "OWNER",
        "body": "SEARCH3_ROOT_TV_BUDGET_V1\n" + json.dumps(value, sort_keys=True),
    }


def tour_fixture(tour_id="tour-1", **changes):
    value = {
        "id": tour_id, "operator": {"id": 13}, "currency": "RUB", "adults": 2,
        "childs": 0, "nights": 7, "date": "2026-11-03", "price": 125500,
    }
    value.update(changes)
    return value


def status_fixture(**changes):
    value = {"searchId": "2026101900001", "progress": 100}
    value.update(changes)
    return value


class AdmissionTests(unittest.TestCase):
    def setUp(self):
        self.network = mock.patch(
            "urllib.request.urlopen", side_effect=AssertionError("offline tests must not use HTTP")
        )
        self.network.start()
        self.addCleanup(self.network.stop)

    def reject_event(self, event=None, env=None):
        with self.assertRaises((RuntimeError, ValueError, TypeError)):
            CHECK.validate_event(event or event_fixture(), env or env_fixture())

    def test_owner_created_command_has_bound_identity(self):
        self.assertEqual(
            CHECK.validate_event(event_fixture(), env_fixture()),
            {
                "control_source_sha": CONTROL,
                "run_id": "39190000001",
                "command_comment_id": 6261700001,
                "budget_comment_id": 6261699999,
            },
        )

    def test_only_current_main_workflow_first_attempt_owner_actor(self):
        changes = {
            "GITHUB_REPOSITORY": "other/project",
            "GITHUB_REF": "refs/heads/feature/live-check",
            "GITHUB_WORKFLOW_REF": "pyatkoff/poisk-turov-test/.github/workflows/other.yml@refs/heads/main",
            "GITHUB_ACTOR": "other",
            "GITHUB_TRIGGERING_ACTOR": "other",
            "GITHUB_EVENT_NAME": "workflow_dispatch",
            "GITHUB_RUN_ATTEMPT": "2",
            "GITHUB_SHA": "not-a-sha",
            "GITHUB_RUN_ID": "0",
        }
        for field, bad in changes.items():
            with self.subTest(field=field):
                env = env_fixture()
                env[field] = bad
                self.reject_event(env=env)

    def test_repository_owner_issue_and_created_action_are_verified(self):
        for field, bad in (
            ("repository", {"full_name": "other/project"}),
            ("action", "edited"),
            ("issue", {"number": 3419}),
            ("issue", {"number": 4217, "pull_request": {}}),
        ):
            with self.subTest(field=field, bad=bad):
                event = event_fixture()
                event[field] = bad
                self.reject_event(event=event)
        for field, bad in (("user", {"id": 1}), ("author_association", "MEMBER")):
            with self.subTest(field=field):
                event = event_fixture()
                event["comment"][field] = bad
                self.reject_event(event=event)

    def test_command_cannot_change_operation_source_or_budget_reference(self):
        base = event_fixture()["comment"]["body"]
        bodies = (
            base + "\n",
            " " + base,
            base + " extra",
            base.replace(CONTROL, "b" * 40),
            base.replace("tv-20261019-anex13-v1", "tv-20261019-anex13-v2"),
            base.replace("6261699999", "0"),
            base.replace("6261699999", "-1"),
            base.replace("6261699999", "1.5"),
            base.replace("/check-search3-root-tv", "/install-search3-root-gateway"),
        )
        for body in bodies:
            with self.subTest(body=body):
                event = event_fixture()
                event["comment"]["body"] = body
                self.reject_event(event=event)

    def test_command_comment_id_requires_positive_integer_not_boolean(self):
        for bad in (0, -1, True, "6261700001", None):
            with self.subTest(bad=bad):
                event = event_fixture()
                event["comment"]["id"] = bad
                self.reject_event(event=event)

    def test_owner_operation_allocation_is_accepted_for_bound_account(self):
        value = CHECK.budget_note(budget_fixture(), NOW)
        self.assertEqual(value["account_env"], "TOURVISOR_JWT")
        self.assertEqual(value["approval_reference"], 6261576536)
        self.assertEqual(value["reserved_search_units"], 1)
        self.assertEqual(value["max_http_attempts"], 3)
        self.assertNotIn("used_search_units", value)
        self.assertNotIn("daily_limit", value)

    def test_owner_approval_reference_must_be_exact_integer(self):
        for reference in (6261576535, "6261576536", True, None):
            with self.subTest(reference=reference):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(approval_reference=reference), NOW)

    def test_budget_rejects_unbound_account_source_day_or_operation(self):
        for changes in (
            {"account_env": "TOURVISOR_TOKEN"},
            {"source": "owner_account_readback"},
            {"provider_day": "2026-10-18"},
            {"operation_id": "tv-20261019-anex13-v2"},
            {"schema_version": 2},
            {"reserved_search_units": 2},
            {"max_http_attempts": 4},
        ):
            with self.subTest(changes=changes):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(**changes), NOW)

    def test_budget_requires_owner_allocation_comment(self):
        for field, bad in (("user", {"id": 1}), ("author_association", "MEMBER")):
            with self.subTest(field=field):
                note = budget_fixture()
                note[field] = bad
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(note, NOW)

    def test_allocation_cannot_be_carried_to_another_provider_day(self):
        tomorrow = NOW + dt.timedelta(days=1)
        with self.assertRaises((RuntimeError, ValueError, TypeError)):
            CHECK.budget_note(budget_fixture(), tomorrow)
        with self.assertRaises((RuntimeError, ValueError, TypeError)):
            CHECK.budget_note(budget_fixture(provider_day="2026-10-20"), tomorrow)

    def test_allocation_does_not_accept_fabricated_balance_or_timestamp_fields(self):
        for extra in ({"used_search_units": 0}, {"daily_limit": 3000}, {"observed_at": "2026-10-19T06:59:00Z"}):
            with self.subTest(extra=extra):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(**extra), NOW)

    def test_budget_cannot_add_an_extra_target_or_silently_drop_fields(self):
        value = json.loads(budget_fixture()["body"].split("\n", 1)[1])
        variants = [dict(value, account="secondary")]
        for key in value:
            missing = dict(value)
            missing.pop(key)
            variants.append(missing)
        for altered in variants:
            with self.subTest(keys=sorted(altered)):
                note = budget_fixture()
                note["body"] = "SEARCH3_ROOT_TV_BUDGET_V1\n" + json.dumps(altered)
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(note, NOW)

    def test_budget_marker_and_json_are_required(self):
        for body in ("{}", "SEARCH3_ROOT_TV_BUDGET_V1\n{", "SEARCH3_ROOT_TV_BUDGET_V1\n[]"):
            with self.subTest(body=body):
                note = budget_fixture()
                note["body"] = body
                with self.assertRaises((RuntimeError, ValueError, TypeError, json.JSONDecodeError)):
                    CHECK.budget_note(note, NOW)

    def test_provider_day_uses_moscow_across_utc_midnight(self):
        now = dt.datetime(2026, 10, 18, 21, 1, tzinfo=dt.timezone.utc)
        value = CHECK.budget_note(
            budget_fixture(provider_day="2026-10-19"), now
        )
        self.assertEqual(value["provider_day"], "2026-10-19")


class FrozenDateTime(dt.datetime):
    @classmethod
    def now(cls, tz=None):
        return NOW if tz is None else NOW.astimezone(tz)


class SourceAdmissionTests(unittest.TestCase):
    def setUp(self):
        self.identity = CHECK.validate_event(event_fixture(), env_fixture())
        self.source = "c" * 40
        self.tree = "d" * 40
        self.approval_body = "offline explicit owner approval"
        note = budget_fixture()
        note["issue_url"] = "https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/4217"
        self.responses = {
            "/git/ref/heads/main": {"object": {"sha": CONTROL}},
            "/git/ref/heads/release/search3-production-ready-v1": {"object": {"sha": CHECK.RELEASE}},
            "/contents/v2/api-v2.php?ref=" + CHECK.RELEASE: {"sha": CHECK.API_BLOB},
            "/issues/comments/6261576536": {
                "user": {"id": 226193297}, "author_association": "OWNER", "body": self.approval_body,
            },
            "/git/commits/" + CONTROL: {"parents": [{"sha": "b" * 40}, {"sha": self.source}], "tree": {"sha": self.tree}},
            "/git/commits/" + self.source: {"sha": self.source, "tree": {"sha": self.tree}},
            "/commits/" + self.source + "/check-runs?per_page=100": {
                "check_runs": [{"name": name, "status": "completed", "conclusion": "success", "app": {"slug": "github-actions"}} for name in ("guard", "contracts", "live-check-contracts")]
            },
            "/issues/4217/comments?per_page=100&page=1": [event_fixture()["comment"]],
            "/issues/comments/6261700001": event_fixture()["comment"],
            "/issues/comments/6261699999": note,
        }

    def admit(self):
        with (
            mock.patch.object(CHECK, "checked_api", side_effect=lambda path: copy.deepcopy(self.responses[path])),
            mock.patch.object(CHECK, "APPROVAL_HASH", hashlib.sha256(self.approval_body.encode()).hexdigest()),
            mock.patch("datetime.datetime", FrozenDateTime),
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
        ):
            return CHECK.admission(self.identity)

    def test_reviewed_merge_current_heads_green_source_and_owner_budget_admit(self):
        self.assertEqual(self.admit()["approval_reference"], 6261576536)

    def test_source_merge_tree_and_current_release_cannot_drift(self):
        cases = (
            ("/git/ref/heads/main", {"object": {"sha": "f" * 40}}),
            ("/git/ref/heads/release/search3-production-ready-v1", {"object": {"sha": "f" * 40}}),
            ("/contents/v2/api-v2.php?ref=" + CHECK.RELEASE, {"sha": "f" * 40}),
            ("/git/commits/" + CONTROL, {"parents": [{"sha": self.source}], "tree": {"sha": self.tree}}),
            ("/git/commits/" + self.source, {"sha": self.source, "tree": {"sha": "f" * 40}}),
        )
        for path, changed in cases:
            with self.subTest(path=path):
                before = self.responses[path]
                self.responses[path] = changed
                with self.assertRaises(RuntimeError):
                    self.admit()
                self.responses[path] = before

    def test_source_ci_must_be_unique_successful_github_checks(self):
        path = "/commits/" + self.source + "/check-runs?per_page=100"
        original = copy.deepcopy(self.responses[path])
        for field, bad in (("status", "in_progress"), ("conclusion", "skipped"), ("conclusion", "failure"), ("app", {"slug": "untrusted"})):
            with self.subTest(field=field, bad=bad):
                self.responses[path] = copy.deepcopy(original)
                self.responses[path]["check_runs"][0][field] = bad
                with self.assertRaises(RuntimeError):
                    self.admit()
        self.responses[path] = copy.deepcopy(original)
        self.responses[path]["check_runs"].append(copy.deepcopy(original["check_runs"][0]))
        with self.assertRaises(RuntimeError):
            self.admit()

    def test_prior_command_or_edited_current_command_cannot_be_replayed(self):
        path = "/issues/4217/comments?per_page=100&page=1"
        original = copy.deepcopy(self.responses[path])
        old = copy.deepcopy(original[0])
        old["id"] = 6261699000
        self.responses[path].insert(0, old)
        with self.assertRaisesRegex(RuntimeError, "operation_no_replay"):
            self.admit()
        self.responses[path] = original
        self.responses["/issues/comments/6261700001"]["body"] += " edited"
        with self.assertRaisesRegex(RuntimeError, "command_changed"):
            self.admit()

    def test_budget_reference_must_belong_to_canonical_coordination_issue(self):
        self.responses["/issues/comments/6261699999"]["issue_url"] = "https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/3419"
        with self.assertRaisesRegex(RuntimeError, "budget_issue"):
            self.admit()

    def test_original_owner_approval_cannot_be_edited_or_replaced(self):
        path = "/issues/comments/6261576536"
        original = copy.deepcopy(self.responses[path])
        for field, bad in (("body", "changed approval"), ("user", {"id": 1}), ("author_association", "MEMBER")):
            with self.subTest(field=field):
                self.responses[path] = copy.deepcopy(original)
                self.responses[path][field] = bad
                with self.assertRaisesRegex(RuntimeError, "approval_changed"):
                    self.admit()


class FakeResponse:
    def __init__(self, value, status=200):
        self.body = value if isinstance(value, bytes) else json.dumps(value).encode()
        self.status = status

    def read(self, limit):
        return self.body[:limit]

    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False


class RemoteTests(unittest.TestCase):
    """Execute the actual remote program against a temporary root and fake HTTP."""

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="search3-root-tv-offline-")
        self.addCleanup(self.temp.cleanup)
        self.home = pathlib.Path(self.temp.name)
        self.project = self.home / "www/anytoour.ru"
        self.project.mkdir(parents=True)
        self.api = self.project / "api-v2.php"
        self.api.write_bytes(b"offline installed API fixture")
        (self.project / "config.php").write_bytes(b"<?php /* offline config fixture: no credentials */")
        self.app = self.project / "_preview/search3-next-candidate/visual-search/app.js"
        self.app.parent.mkdir(parents=True)
        self.app.write_bytes(b"offline installed frontend fixture")
        self.private = self.home / ".anytoour-search3-root-gateway"
        self.private.mkdir(mode=0o700)
        self.policy = {
            "operation": CHECK.OPERATION,
            "api_hash": hashlib.sha256(self.api.read_bytes()).hexdigest(),
            "app_hash": hashlib.sha256(self.app.read_bytes()).hexdigest(),
            "params": copy.deepcopy(CHECK.PARAMS),
        }
        self.payload = {
            "control_source_sha": CONTROL,
            "run_id": "39190000001",
            "command_comment_id": 6261700001,
            "budget_comment_id": 6261699999,
            "budget": json.loads(budget_fixture()["body"].split("\n", 1)[1]),
        }
        self.calls = []

    def execute(self, responses=None, payload=None, raw=None, config=None, config_output=None):
        replies = iter(responses if responses is not None else [
            {"data": {"searchId": "2026101900001"}},
            status_fixture(),
            [{"id": 101, "tours": [tour_fixture(), tour_fixture("tour-2")]}],
        ])

        def open_request(request, timeout):
            self.calls.append((request.full_url, dict(request.header_items()), timeout))
            value = next(replies)
            if isinstance(value, BaseException):
                raise value
            return value if isinstance(value, FakeResponse) else FakeResponse(value)

        opener = mock.Mock()
        opener.open.side_effect = open_request
        data = raw if raw is not None else json.dumps(payload or self.payload).encode()
        binding = config if config is not None else {
            "root_configured": True, "anex_configured": True,
            "same_token": False, "same_subject": False,
        }
        config_result = subprocess.CompletedProcess(
            ["php", "offline-mocked-config-comparison"], 0,
            stdout=config_output if config_output is not None else json.dumps(binding).encode(), stderr=b"",
        )
        stdin = mock.Mock()
        stdin.buffer = io.BytesIO(data)
        output = io.StringIO()
        with (
            mock.patch("pathlib.Path.home", return_value=self.home),
            mock.patch("sys.stdin", stdin),
            mock.patch("datetime.datetime", FrozenDateTime),
            mock.patch("urllib.request.build_opener", return_value=opener),
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
            mock.patch("subprocess.run", return_value=config_result) as config_run,
            mock.patch("time.sleep") as sleep,
            contextlib.redirect_stdout(output),
        ):
            exec(compile(CHECK.REMOTE, "<remote-live-check>", "exec"), {"POLICY": self.policy})
        result = json.loads(output.getvalue())
        self.config_calls = config_run.call_args_list
        return result, sleep

    def action_params(self):
        return [urllib.parse.parse_qs(urllib.parse.urlsplit(url).query) for url, _, _ in self.calls]

    def test_one_new_search_status_and_first_results_have_exact_scope(self):
        api_before, app_before = self.api.read_bytes(), self.app.read_bytes()
        result, sleep = self.execute()
        self.assertEqual(result["status"], "fresh_populated_results_verified")
        self.assertEqual(result["search_id"], "2026101900001")
        self.assertEqual(result["generation_id"], CHECK.OPERATION)
        self.assertEqual(result["hotel_groups"], 1)
        self.assertEqual(result["tour_count"], 2)
        self.assertEqual(result["tariff_searches_reserved"], 1)
        self.assertEqual([r["action"] for r in result["requests"]], ["search_start", "search_status", "search_results"])
        params = self.action_params()
        self.assertEqual(params[0], {k: [str(v)] for k, v in {"action": "search_start", **CHECK.PARAMS}.items()})
        self.assertEqual(params[1], {"action": ["search_status"], "searchId": ["2026101900001"]})
        self.assertEqual(params[2], {"action": ["search_results"], "searchId": ["2026101900001"], "limit": ["25"]})
        self.assertTrue(all(urllib.parse.urlsplit(url).scheme == "https" and urllib.parse.urlsplit(url).netloc == "anytoour.ru" and urllib.parse.urlsplit(url).path == "/api-v2.php" for url, _, _ in self.calls))
        self.assertTrue(all(timeout == 55 for _, _, timeout in self.calls))
        self.assertTrue(all(not any(name.lower() in ("authorization", "x-ci-token", "cookie") for name in headers) for _, headers, _ in self.calls))
        sleep.assert_called_once_with(20)
        self.assertEqual((self.api.read_bytes(), self.app.read_bytes()), (api_before, app_before))
        self.assertEqual(result["real_leads"], 0)
        self.assertEqual(result["application_file_writes"], 0)
        self.assertTrue(result["normal_autosave_enabled"])
        self.assertEqual(result["database_write_count"], "not_measured")
        self.assertEqual(result["global_daily_usage"], "not_measured")
        self.assertEqual(result["budget_basis"], "owner_operation_allocation")
        self.assertEqual(len(self.config_calls), 1)

    def test_private_reservation_and_receipt_are_retained_without_response_bodies(self):
        result, _ = self.execute()
        operation = self.private / CHECK.OPERATION
        self.assertEqual(operation.stat().st_mode & 0o777, 0o700)
        self.assertEqual({p.name for p in operation.iterdir()}, {"reservation.json", "result.json"})
        for path in operation.iterdir():
            self.assertEqual(path.stat().st_mode & 0o777, 0o600)
        self.assertEqual(json.loads((operation / "reservation.json").read_text()), self.payload)
        self.assertEqual(json.loads((operation / "result.json").read_text()), result)
        self.assertNotIn("tour-1", json.dumps(result))
        self.assertTrue(all("sha256" in record for record in result["requests"]))

    def test_existing_operation_cannot_start_a_second_search(self):
        first, _ = self.execute()
        receipt = (self.private / CHECK.OPERATION / "result.json").read_bytes()
        self.calls.clear()
        second, sleep = self.execute()
        self.assertEqual(first["status"], "fresh_populated_results_verified")
        self.assertEqual(second["status"], "blocked_no_replay")
        self.assertFalse(second["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertEqual((self.private / CHECK.OPERATION / "result.json").read_bytes(), receipt)
        sleep.assert_not_called()

    def test_start_http_failure_is_consumed_without_retry_or_more_actions(self):
        failure = urllib.error.HTTPError("https://anytoour.ru/api-v2.php", 500, "internal", {}, None)
        result, sleep = self.execute([failure])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "http_failed")
        self.assertEqual(len(self.calls), 1)
        self.assertEqual(result["requests"][0]["http"], 500)
        self.assertTrue((self.private / CHECK.OPERATION / "reservation.json").exists())
        sleep.assert_not_called()

    def test_network_disconnect_is_unknown_and_never_retried(self):
        result, _ = self.execute([urllib.error.URLError("offline simulated disconnect")])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(len(self.calls), 1)
        self.assertIsNone(result["requests"][0]["http"])

    def test_bad_start_body_stops_before_status(self):
        for response in (FakeResponse(b"not-json"), {"data": {}}, {"searchId": "13905446138"}):
            with self.subTest(response=response):
                # A separate operation home models independent offline examples, never retries.
                operation = self.private / CHECK.OPERATION
                if operation.exists():
                    self.temp.cleanup()
                    self.setUp()
                result, sleep = self.execute([response])
                self.assertEqual(result["status"], "unknown_no_replay")
                self.assertEqual(len(self.calls), 1)
                sleep.assert_not_called()

    def test_malformed_status_stops_before_results(self):
        result, _ = self.execute([{"searchId": 2026101900001}, []])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "status_shape")
        self.assertEqual(len(self.calls), 2)

    def test_other_search_status_does_not_authorize_results_readback(self):
        result, _ = self.execute([{"searchId": "2026101900001"}, status_fixture(searchId="2026101900999")])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "status_shape")
        self.assertEqual(len(self.calls), 2)

    def test_pending_empty_first_portion_is_reported_separately_without_polling(self):
        result, sleep = self.execute([{"searchId": "2026101900001"}, status_fixture(progress=45), []])
        self.assertEqual(result["status"], "fresh_empty_results_verified")
        self.assertEqual(result["progress"], 45)
        self.assertEqual(result["hotel_groups"], 0)
        self.assertEqual(result["tour_count"], 0)
        self.assertEqual(result["samples"], [])
        self.assertEqual(len(self.calls), 3)
        sleep.assert_called_once_with(20)

    def test_other_operator_cannot_be_reported_as_the_approved_anex_search(self):
        result, _ = self.execute([
            {"searchId": "2026101900001"}, status_fixture(),
            [{"id": 101, "tours": [tour_fixture(operator={"id": 12})]}],
        ])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "result_scope")
        self.assertEqual(len(self.calls), 3)

    def test_remote_payload_identity_rejected_before_any_external_action(self):
        payload = copy.deepcopy(self.payload)
        payload["budget_comment_id"] = True
        result, _ = self.execute(payload=payload)
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(result["reason"], "payload_identity")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())

    def test_results_must_be_a_first_portion_of_at_most_25_hotel_groups(self):
        for response in ({"error": "unavailable"}, [{}] * 26):
            with self.subTest(response_type=type(response).__name__):
                operation = self.private / CHECK.OPERATION
                if operation.exists():
                    self.temp.cleanup()
                    self.setUp()
                result, _ = self.execute([{"searchId": "2026101900001"}, status_fixture(), response])
                self.assertEqual(result["status"], "unknown_no_replay")
                self.assertEqual(len(self.calls), 3)

    def test_oversize_http_body_is_terminal(self):
        result, _ = self.execute([FakeResponse(b" " * (8 * 1024 * 1024 + 1))])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "http_or_body_limit")
        self.assertEqual(len(self.calls), 1)

    def test_budget_day_is_rechecked_before_any_remote_reservation_or_http(self):
        payload = copy.deepcopy(self.payload)
        payload["budget"]["provider_day"] = "2026-10-18"
        result, sleep = self.execute(payload=payload)
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(result["reason"], "budget_day")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())
        sleep.assert_not_called()

    def test_shared_token_or_subject_blocks_search_before_reservation(self):
        for field in ("same_token", "same_subject"):
            with self.subTest(field=field):
                config = {"root_configured": True, "anex_configured": True,
                          "same_token": False, "same_subject": False}
                config[field] = True
                result, sleep = self.execute(config=config)
                self.assertEqual(result["status"], "blocked_no_replay")
                self.assertEqual(result["reason"], "shared_account_budget_unproven")
                self.assertEqual(self.calls, [])
                self.assertFalse((self.private / CHECK.OPERATION).exists())
                sleep.assert_not_called()

    def test_unconfigured_account_blocks_search_before_reservation(self):
        for field in ("root_configured", "anex_configured"):
            with self.subTest(field=field):
                config = {"root_configured": True, "anex_configured": True,
                          "same_token": False, "same_subject": False}
                config[field] = False
                result, sleep = self.execute(config=config)
                self.assertEqual(result["status"], "blocked_no_replay")
                self.assertEqual(result["reason"], "root_account_binding_unproven")
                self.assertEqual(self.calls, [])
                self.assertFalse((self.private / CHECK.OPERATION).exists())
                sleep.assert_not_called()

    def test_config_output_with_extra_secret_field_is_rejected_and_not_retained(self):
        config = {"root_configured": True, "anex_configured": True,
                  "same_token": False, "same_subject": False,
                  "token": "OFFLINE_SECRET_MUST_NOT_BE_RETAINED"}
        result, _ = self.execute(config=config)
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(result["reason"], "root_account_binding_unproven")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())
        self.assertNotIn("OFFLINE_SECRET_MUST_NOT_BE_RETAINED", json.dumps(result))

    def test_raw_credential_output_is_not_a_configuration_proof(self):
        result, _ = self.execute(config_output=b"OFFLINE_SECRET_MUST_NOT_BE_RETAINED")
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())
        self.assertNotIn("OFFLINE_SECRET_MUST_NOT_BE_RETAINED", json.dumps(result))

    def test_api_or_frontend_drift_blocks_all_supplier_calls(self):
        for path, reason in ((self.api, "api_drift"), (self.app, "frontend_drift")):
            with self.subTest(path=path.name):
                before = path.read_bytes()
                path.write_bytes(before + b" changed")
                result, _ = self.execute()
                self.assertEqual(result["status"], "blocked_no_replay")
                self.assertEqual(result["reason"], reason)
                self.assertEqual(self.calls, [])
                self.assertFalse((self.private / CHECK.OPERATION).exists())
                path.write_bytes(before)

    def test_symlink_installed_api_cannot_pass_hash_check(self):
        target = self.project / "other-api.php"
        self.api.rename(target)
        self.api.symlink_to(target)
        result, _ = self.execute()
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertIn(result["reason"], ("unsafe_file", "unsafe_parent"))
        self.assertEqual(self.calls, [])

    def test_oversize_payload_has_zero_external_effect(self):
        result, _ = self.execute(raw=b" " * 16385)
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(result["reason"], "payload_limit")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())


def anex_event_fixture():
    value = event_fixture()
    value["comment"]["body"] = (
        f"/check-search3-tv-anex-key {CONTROL} tv-anex-key-20261019-v1 6261699999"
    )
    return value


def anex_budget_fixture(**changes):
    value = json.loads(budget_fixture()["body"].split("\n", 1)[1])
    value.update(
        operation_id="tv-anex-key-20261019-v1",
        account_env="TOURVISOR_ANEX_JWT",
        approval_reference=6262286069,
    )
    value.update(changes)
    note = budget_fixture()
    note["body"] = "SEARCH3_TV_ANEX_KEY_BUDGET_V1\n" + json.dumps(value, sort_keys=True)
    return note


class AnexKeyAdmissionTests(unittest.TestCase):
    """The second-key grant cannot silently reuse the consumed root-key grant."""

    def setUp(self):
        self.network = mock.patch(
            "urllib.request.urlopen", side_effect=AssertionError("offline tests must not use HTTP")
        )
        self.network.start()
        self.addCleanup(self.network.stop)

    def test_second_key_command_uses_new_fixed_operation_and_owner_identity(self):
        identity = CHECK.validate_event(anex_event_fixture(), env_fixture(), CHECK.ANEX_PROFILE)
        self.assertEqual(identity["control_source_sha"], CONTROL)
        self.assertEqual(identity["budget_comment_id"], 6261699999)
        for event in (event_fixture(), dict(anex_event_fixture(), action="edited")):
            with self.subTest(body=event["comment"]["body"], action=event["action"]):
                with self.assertRaises(RuntimeError):
                    CHECK.validate_event(event, env_fixture(), CHECK.ANEX_PROFILE)
        with self.assertRaises(RuntimeError):
            CHECK.validate_event(anex_event_fixture(), env_fixture())

    def test_second_key_budget_binds_account_grant_and_operation(self):
        value = CHECK.budget_note(anex_budget_fixture(), NOW, CHECK.ANEX_PROFILE)
        self.assertEqual(value["account_env"], "TOURVISOR_ANEX_JWT")
        self.assertEqual(value["approval_reference"], 6262286069)
        self.assertEqual(value["operation_id"], "tv-anex-key-20261019-v1")
        for note in (
            budget_fixture(),
            anex_budget_fixture(account_env="TOURVISOR_JWT"),
            anex_budget_fixture(approval_reference=6261576536),
            anex_budget_fixture(operation_id="tv-20261019-anex13-v1"),
            anex_budget_fixture(max_http_attempts=4),
            anex_budget_fixture(reserved_search_units=2),
        ):
            with self.subTest(body=note["body"]):
                with self.assertRaises(RuntimeError):
                    CHECK.budget_note(note, NOW, CHECK.ANEX_PROFILE)
        with self.assertRaises(RuntimeError):
            CHECK.budget_note(anex_budget_fixture(), NOW)

    def test_second_key_budget_is_not_reusable_on_another_provider_day(self):
        with self.assertRaises(RuntimeError):
            CHECK.budget_note(anex_budget_fixture(), NOW + dt.timedelta(days=1), CHECK.ANEX_PROFILE)

    def test_second_key_source_admission_keeps_root_operation_history_separate(self):
        source, tree = "c" * 40, "d" * 40
        approval_body = "offline second-key owner approval"
        note = anex_budget_fixture()
        note["issue_url"] = "https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/4217"
        responses = {
            "/git/ref/heads/main": {"object": {"sha": CONTROL}},
            "/git/ref/heads/release/search3-production-ready-v1": {"object": {"sha": CHECK.RELEASE}},
            "/contents/v2/api-v2.php?ref=" + CHECK.RELEASE: {"sha": CHECK.API_BLOB},
            "/issues/comments/6262286069": {
                "user": {"id": 226193297}, "author_association": "OWNER", "body": approval_body,
            },
            "/git/commits/" + CONTROL: {"parents": [{"sha": "b" * 40}, {"sha": source}], "tree": {"sha": tree}},
            "/git/commits/" + source: {"sha": source, "tree": {"sha": tree}},
            "/commits/" + source + "/check-runs?per_page=100": {
                "check_runs": [{"name": name, "status": "completed", "conclusion": "success", "app": {"slug": "github-actions"}} for name in ("guard", "contracts", "live-check-contracts")]
            },
            "/issues/4217/comments?per_page=100&page=1": [event_fixture()["comment"], anex_event_fixture()["comment"]],
            "/issues/comments/6261700001": anex_event_fixture()["comment"],
            "/issues/comments/6261699999": note,
        }
        profile = dict(CHECK.ANEX_PROFILE, approval_hash=hashlib.sha256(approval_body.encode()).hexdigest())
        identity = CHECK.validate_event(anex_event_fixture(), env_fixture(), profile)
        with (
            mock.patch.object(CHECK, "checked_api", side_effect=lambda path: copy.deepcopy(responses[path])),
            mock.patch("datetime.datetime", FrozenDateTime),
        ):
            self.assertEqual(CHECK.admission(identity, profile)["account_env"], "TOURVISOR_ANEX_JWT")
            prior = copy.deepcopy(anex_event_fixture()["comment"])
            prior["id"] = 6261698000
            responses["/issues/4217/comments?per_page=100&page=1"].insert(0, prior)
            with self.assertRaisesRegex(RuntimeError, "operation_no_replay"):
                CHECK.admission(identity, profile)


class ObservedCalls(list):
    def __init__(self, observe):
        super().__init__()
        self.observe = observe

    def append(self, value):
        super().append(value)
        self.observe(len(self))


class AnexKeyRemoteTests(unittest.TestCase):
    """Run the direct second-key program using fake HTTP and real temporary ledgers."""

    def setUp(self):
        RemoteTests.setUp(self)
        self.policy.update(
            operation="tv-anex-key-20261019-v1", direct=True,
            account_env="TOURVISOR_ANEX_JWT", approval_reference=6262286069,
        )
        self.payload["budget"] = json.loads(anex_budget_fixture()["body"].split("\n", 1)[1])
        self.quota_dir = self.home / ".anytoour-match/provider-quotas"
        self.quota_dir.mkdir(parents=True, mode=0o700)
        self.day_path = self.quota_dir / "tourvisor-anex-2026-10-19.json"
        self.op_path = self.quota_dir / "tourvisor-anex-tv-anex-key-20261019-v1.json"
        self.write_day()

    def write_day(self, **changes):
        value = {
            "provider": "tourvisor-anex", "provider_day": "2026-10-19",
            "owner_daily_limit": 3000, "tariff_search_units": 16,
            "physical_http_attempts": 67,
            "operations": {"other-writer": {"tariff_search_units": 2, "physical_http_attempts": 9}},
            "retained_metadata": {"owner": "other writer", "unknown": True},
        }
        value.update(changes)
        self.day_path.write_text(json.dumps(value, sort_keys=True))
        self.day_path.chmod(0o600)
        return value

    def execute(self, responses=None, **kwargs):
        kwargs.setdefault("config", {
            "token": "synthetic.secret.token", "root_equal": False, "same_subject": False,
        })
        return RemoteTests.execute(self, responses, **kwargs)

    def test_direct_second_key_uses_fixed_tourvisor_urls_and_internal_bearer(self):
        before = self.api.read_bytes(), self.app.read_bytes()
        result, sleep = self.execute()
        self.assertEqual(result["status"], "fresh_populated_results_verified")
        urls = [urllib.parse.urlsplit(url) for url, _, _ in self.calls]
        self.assertEqual([url.path for url in urls], [
            "/search/api/v1/tours/search", "/search/api/v1/tours/search/2026101900001/status",
            "/search/api/v1/tours/search/2026101900001",
        ])
        self.assertTrue(all(url.scheme == "https" and url.netloc == "api.tourvisor.ru" for url in urls))
        self.assertEqual(urllib.parse.parse_qs(urls[0].query), {
            **{key: [str(value)] for key, value in CHECK.PARAMS.items()},
            "onlyCharter": ["false"], "onlyDirect": ["false"],
        })
        self.assertEqual(urllib.parse.parse_qs(urls[1].query), {"operatorStatus": ["false"]})
        self.assertEqual(urllib.parse.parse_qs(urls[2].query), {"limit": ["25"]})
        for _, headers, timeout in self.calls:
            self.assertEqual(headers["Authorization"], "Bearer synthetic.secret.token")
            self.assertEqual(timeout, 55)
            self.assertFalse(any(key.lower() in ("cookie", "x-ci-token") for key in headers))
        self.assertEqual(result["account_env"], "TOURVISOR_ANEX_JWT")
        self.assertEqual(result["route"], "direct_tourvisor_api")
        self.assertFalse(result["normal_autosave_enabled"])
        self.assertEqual(result["database_write_count"], 0)
        self.assertEqual(result["application_file_writes"], 0)
        self.assertEqual((self.api.read_bytes(), self.app.read_bytes()), before)
        sleep.assert_called_once_with(20)

    def test_second_key_and_raw_tours_are_absent_from_retained_receipts(self):
        result, _ = self.execute()
        serialized = json.dumps(result)
        self.assertNotIn("synthetic.secret.token", serialized)
        self.assertNotIn("tour-1", serialized)
        self.assertEqual(result["global_daily_usage"], "not_measured")
        for path in self.home.rglob("*.json"):
            with self.subTest(path=path.name):
                text = path.read_text()
                self.assertNotIn("synthetic.secret.token", text)
                self.assertNotIn("tour-1", text)
        self.assertEqual(self.op_path.stat().st_mode & 0o777, 0o600)

    def test_shared_quota_preserves_prior_writer_fields_and_charges_before_http(self):
        before = json.loads(self.day_path.read_text())

        def observe(attempt):
            day, operation = json.loads(self.day_path.read_text()), json.loads(self.op_path.read_text())
            self.assertEqual(day["tariff_search_units"], before["tariff_search_units"] + 1)
            self.assertEqual(day["physical_http_attempts"], before["physical_http_attempts"] + attempt)
            self.assertEqual(operation["used"], attempt)
            self.assertEqual(operation["tariff_used"], 1)

        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        after = json.loads(self.day_path.read_text())
        self.assertEqual(after["operations"]["other-writer"], before["operations"]["other-writer"])
        self.assertEqual(after["retained_metadata"], before["retained_metadata"])
        self.assertEqual(after["operations"][self.policy["operation"]], {
            "tariff_search_units": 1, "physical_http_attempts": 3,
        })
        self.assertEqual(result["known_accounted_units_before"], 16)
        self.assertEqual(result["known_accounted_units_after"], 17)
        self.assertEqual(result["operation_physical_attempts"], 3)
        self.assertEqual(result["operation_tariff_units"], 1)

    def test_known_daily_cap_exhaustion_blocks_before_any_http(self):
        before = self.write_day(tariff_search_units=3000)
        result, sleep = self.execute()
        self.assertEqual(result["reason"], "quota_exhausted")
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertEqual(json.loads(self.day_path.read_text()), before)
        self.assertFalse(self.op_path.exists())
        sleep.assert_not_called()

    def test_last_daily_unit_allows_exactly_one_start_and_its_two_readbacks(self):
        self.write_day(tariff_search_units=2999)
        result, _ = self.execute()
        self.assertEqual(result["status"], "fresh_populated_results_verified")
        self.assertEqual(json.loads(self.day_path.read_text())["tariff_search_units"], 3000)
        self.assertEqual(len(self.calls), 3)

    def test_existing_operation_companion_is_never_overwritten(self):
        prior = {"operation": self.policy["operation"], "status": "unknown_no_replay", "retained": "prior owner receipt"}
        self.op_path.write_text(json.dumps(prior))
        self.op_path.chmod(0o600)
        retained = self.op_path.read_bytes(), self.day_path.read_bytes()
        result, _ = self.execute()
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertEqual((self.op_path.read_bytes(), self.day_path.read_bytes()), retained)

    def test_unknown_operation_in_shared_day_cannot_be_replayed(self):
        before = self.write_day(operations={self.policy["operation"]: {
            "tariff_search_units": 1, "physical_http_attempts": 1,
        }})
        result, _ = self.execute()
        self.assertEqual(result["reason"], "quota_operation_exists")
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertEqual(json.loads(self.day_path.read_text()), before)

    def test_malformed_or_wrong_day_shared_ledger_blocks_without_replacement(self):
        for changes in (
            {"provider_day": "2026-10-18"}, {"provider": "another-account"},
            {"owner_daily_limit": 3001}, {"tariff_search_units": True},
            {"physical_http_attempts": -1}, {"operations": []},
        ):
            with self.subTest(changes=changes):
                self.temp.cleanup()
                self.setUp()
                self.write_day(**changes)
                before = self.day_path.read_bytes()
                result, _ = self.execute()
                self.assertEqual(result["reason"], "quota_shape")
                self.assertEqual(self.calls, [])
                self.assertEqual(self.day_path.read_bytes(), before)

    def test_symlink_shared_ledger_never_follows_or_mutates_target(self):
        target = self.quota_dir / "other-ledger.json"
        self.day_path.rename(target)
        self.day_path.symlink_to(target)
        before = target.read_bytes()
        result, _ = self.execute()
        self.assertEqual(result["reason"], "unsafe_quota")
        self.assertEqual(self.calls, [])
        self.assertEqual(target.read_bytes(), before)

    def test_direct_results_500_retains_bounded_diagnostics_without_raw_body(self):
        body = b'{"error":"synthetic.secret.token must remain private"}'
        failure = urllib.error.HTTPError("https://api.tourvisor.ru/search/api/v1/tours/search/2026101900001", 500, "internal", {}, io.BytesIO(body))
        result, _ = self.execute([{"searchId": "2026101900001"}, status_fixture(), failure])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "http_failed")
        self.assertEqual(len(self.calls), 3)
        record = result["requests"][-1]
        self.assertEqual(record["http"], 500)
        self.assertEqual(record["bytes"], len(body))
        self.assertEqual(record["sha256"], hashlib.sha256(body).hexdigest())
        self.assertEqual(record["json_kind"], "dict")
        self.assertEqual(record["error_category"], "upstream_server_error")
        self.assertEqual(record["hash_scope"], "complete_body")
        self.assertFalse(record["body_truncated"])
        self.assertNotIn("synthetic.secret.token", json.dumps(result))
        self.assertEqual(result["before_files"], result["after_files"])
        operation = json.loads(self.op_path.read_text())
        self.assertEqual((operation["tariff_used"], operation["used"]), (1, 3))

    def test_auth_or_rate_limit_failure_consumes_one_attempt_without_retry(self):
        for code, category in ((401, "auth_denied"), (403, "auth_denied"), (429, "rate_limited")):
            with self.subTest(code=code):
                self.temp.cleanup()
                self.setUp()
                failure = urllib.error.HTTPError("https://api.tourvisor.ru/search/api/v1/tours/search", code, "blocked", {}, io.BytesIO(b'{}'))
                result, sleep = self.execute([failure])
                self.assertEqual(result["status"], "unknown_no_replay")
                self.assertEqual(result["requests"][0]["error_category"], category)
                self.assertEqual(len(self.calls), 1)
                self.assertEqual(json.loads(self.op_path.read_text())["used"], 1)
                self.assertEqual(json.loads(self.op_path.read_text())["tariff_used"], 1)
                sleep.assert_not_called()

    def test_large_error_body_has_explicit_bounded_prefix_hash(self):
        body = b"x" * (65536 + 200)
        failure = urllib.error.HTTPError("https://api.tourvisor.ru/search/api/v1/tours/search", 500, "internal", {}, io.BytesIO(body))
        result, _ = self.execute([failure])
        record = result["requests"][0]
        self.assertEqual(record["bytes"], 65537)
        self.assertTrue(record["body_truncated"])
        self.assertEqual(record["hash_scope"], "bounded_prefix")
        self.assertEqual(record["sha256"], hashlib.sha256(body[:65537]).hexdigest())
        self.assertEqual(record["json_kind"], "not_measured")
        self.assertEqual(len(self.calls), 1)

    def test_historical_search_ids_never_trigger_status_or_result_calls(self):
        for search_id in ("13905446138", "13943172077"):
            with self.subTest(search_id=search_id):
                self.temp.cleanup()
                self.setUp()
                result, sleep = self.execute([{"searchId": search_id}])
                self.assertEqual(result["reason"], "historical_search_identity")
                self.assertEqual(len(self.calls), 1)
                sleep.assert_not_called()

    def test_second_key_cannot_fall_back_to_missing_or_same_root_credentials(self):
        for config in (
            {"token": "", "root_equal": False, "same_subject": False},
            {"token": "synthetic.secret.token", "root_equal": True, "same_subject": False},
            {"token": "synthetic.secret.token", "root_equal": False, "same_subject": True},
            {"token": "synthetic.secret.token\r\nX-Header: injection", "root_equal": False, "same_subject": False},
        ):
            with self.subTest(config=config):
                result, sleep = self.execute(config=config)
                self.assertFalse(result["live_operation_started"])
                self.assertEqual(self.calls, [])
                self.assertFalse(self.op_path.exists())
                sleep.assert_not_called()

    def test_remote_budget_for_root_account_cannot_authorize_second_key(self):
        payload = copy.deepcopy(self.payload)
        payload["budget"] = json.loads(budget_fixture()["body"].split("\n", 1)[1])
        result, _ = self.execute(payload=payload)
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertFalse(self.op_path.exists())

    def test_provider_day_change_after_start_stops_before_status(self):
        def observe(attempt):
            if attempt == 1:
                tomorrow = mock.patch(__name__ + ".NOW", NOW + dt.timedelta(days=1))
                tomorrow.start()
                self.addCleanup(tomorrow.stop)

        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        self.assertEqual(result["reason"], "budget_day")
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(len(self.calls), 1)
        self.assertEqual(json.loads(self.op_path.read_text())["used"], 1)

    def test_pending_direct_results_are_partial_instead_of_complete_readiness(self):
        result, _ = self.execute([
            {"searchId": "2026101900001"}, status_fixture(progress=35),
            [{"id": 101, "tours": [tour_fixture()]}],
        ])
        self.assertEqual(result["status"], "fresh_partial_results_verified")
        self.assertEqual(result["progress"], 35)
        self.assertEqual(result["tour_count"], 1)
        self.assertEqual(len(self.calls), 3)

    def run_profile_main(self, receipt):
        runner = pathlib.Path(tempfile.mkdtemp(prefix="offline-main-", dir=self.home))
        event_path = runner / "event.json"
        event_path.write_text(json.dumps(anex_event_fixture()))
        env = dict(env_fixture(), RUNNER_TEMP=str(runner), GITHUB_EVENT_PATH=str(event_path))

        def ssh_fixture(work):
            key, known = work / "key", work / "known"
            key.write_text("synthetic private key")
            known.write_text("synthetic known hosts")
            return key, known, ["-F", "/dev/null"], "offline-host"

        output, error = io.StringIO(), None
        with (
            mock.patch.dict(CHECK.os.environ, env, clear=True),
            mock.patch.object(CHECK, "admission", return_value=self.payload["budget"]) as admission,
            mock.patch.object(CHECK, "ssh_options", side_effect=ssh_fixture),
            mock.patch.object(CHECK, "run", return_value=json.dumps(receipt).encode()) as remote_run,
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
            contextlib.redirect_stdout(output),
        ):
            try:
                CHECK.main(CHECK.ANEX_PROFILE)
            except RuntimeError as exc:
                error = exc
        work = runner / "search3-tv-anex-key-check"
        evidence = json.loads((work / "evidence.json").read_text())
        self.assertEqual(json.loads(output.getvalue()), evidence)
        self.assertFalse((work / "key").exists())
        self.assertFalse((work / "known").exists())
        admission.assert_called_once_with(
            CHECK.validate_event(anex_event_fixture(), env, CHECK.ANEX_PROFILE), CHECK.ANEX_PROFILE,
        )
        return evidence, remote_run, error

    def test_main_profile_dispatches_new_account_policy_and_accepts_safe_receipt(self):
        import ast

        receipt, _ = self.execute()
        evidence, remote_run, error = self.run_profile_main(receipt)
        self.assertIsNone(error)
        self.assertEqual(evidence["status"], "fresh_populated_results_verified")
        self.assertEqual(evidence["remote"], receipt)
        command = remote_run.call_args.args[0]
        self.assertEqual(command[:4], ["ssh", "-F", "/dev/null", "offline-host"])
        program = CHECK.shlex.split(command[-1])
        self.assertEqual(program[:2], ["python3", "-c"])
        policy = ast.literal_eval(program[2].split("\n", 1)[0].removeprefix("POLICY="))
        self.assertTrue(policy["direct"])
        self.assertEqual(policy["account_env"], "TOURVISOR_ANEX_JWT")
        self.assertEqual(policy["operation"], "tv-anex-key-20261019-v1")
        self.assertEqual(policy["approval_reference"], 6262286069)
        self.assertEqual(policy["params"], CHECK.PARAMS)
        self.assertNotIn("synthetic.secret.token", json.dumps(evidence))
        self.assertEqual(remote_run.call_args.kwargs["timeout"], 220)

    def test_main_rejects_secret_fields_and_unsanctioned_error_categories(self):
        receipt, _ = self.execute()
        variants = []
        extra = copy.deepcopy(receipt)
        extra["token"] = "DO_NOT_RETAIN_SYNTHETIC_SECRET"
        variants.append(extra)
        raw_body = copy.deepcopy(receipt)
        raw_body["requests"][0]["raw_body"] = "DO_NOT_RETAIN_SYNTHETIC_SECRET"
        variants.append(raw_body)
        category = copy.deepcopy(receipt)
        category["requests"][0]["error_category"] = "DO_NOT_RETAIN_SYNTHETIC_SECRET"
        variants.append(category)
        for altered in variants:
            with self.subTest(fields=sorted(altered)):
                evidence, _, error = self.run_profile_main(altered)
                self.assertIsInstance(error, RuntimeError)
                self.assertEqual(evidence["status"], "unknown_no_replay")
                self.assertNotIn("remote", evidence)
                self.assertNotIn("DO_NOT_RETAIN_SYNTHETIC_SECRET", json.dumps(evidence))

    def test_native_php_selects_actual_second_key_with_synthetic_config_only(self):
        import base64
        import shutil

        php = shutil.which("php")
        if php is None:
            if CHECK.os.environ.get("SEARCH3_ANEX_KEY_NATIVE_CI") == "1":
                self.fail("Native PHP is required for the ANEX credential selector in CI")
            self.skipTest("Local PHP is unavailable; CI must require the native selector check")
        self.execute()
        code = self.config_calls[0].args[0][2]
        config = self.home / "synthetic-native-config.php"
        # Deliberately do not inherit host credentials or any actual server config.
        env = {
            "PATH": str(pathlib.Path(php).parent), "HOME": str(self.home),
            "TOURVISOR_JWT": "synthetic.root.token",
            "TOURVISOR_ANEX_JWT": "synthetic.environment.token",
        }

        def select(config_body, active_env):
            config.write_text("<?php\n" + config_body)
            completed = subprocess.run(
                [php, "-r", code, "--", str(config)],
                env=active_env, cwd=self.home, capture_output=True, timeout=15, check=False,
            )
            self.assertEqual(completed.returncode, 0, "Generated PHP credential selector failed")
            self.assertEqual(completed.stderr, b"")
            selected = json.loads(completed.stdout)
            self.assertEqual(set(selected), {"token", "root_equal", "same_subject"})
            self.assertIs(type(selected["root_equal"]), bool)
            self.assertIs(type(selected["same_subject"]), bool)
            return selected

        constant = select("define('TOURVISOR_ANEX_JWT', 'synthetic.constant.token');", env)
        self.assertEqual(constant, {"token": "synthetic.constant.token", "root_equal": False, "same_subject": False})
        fallback = select("/* No ANEX constant: use its environment value. */", env)
        self.assertEqual(fallback["token"], "synthetic.environment.token")
        empty = select("define('TOURVISOR_ANEX_JWT', '');", env)
        self.assertEqual(empty["token"], "")
        same_key = select("define('TOURVISOR_ANEX_JWT', 'synthetic.root.token');", env)
        self.assertTrue(same_key["root_equal"])
        payload = base64.urlsafe_b64encode(b'{"iss":"synthetic","sub":"same-account"}').decode().rstrip("=")
        rotated_env = dict(env, TOURVISOR_JWT=f"header.{payload}.signature-a", TOURVISOR_ANEX_JWT=f"header.{payload}.signature-b")
        rotation = select("/* Different token strings with the same synthetic subject. */", rotated_env)
        self.assertFalse(rotation["root_equal"])
        self.assertTrue(rotation["same_subject"])


def root_direct_event_fixture():
    event = event_fixture()
    event["comment"]["body"] = f"/check-search3-tv-root-direct {CONTROL} tv-root-direct-20261019-v1 6261699999"
    return event


def root_direct_budget_fixture(**changes):
    value = json.loads(budget_fixture()["body"].split("\n", 1)[1])
    value.update(operation_id="tv-root-direct-20261019-v1", approval_reference=6262670660)
    value.update(changes)
    note = budget_fixture()
    note["body"] = "SEARCH3_ROOT_TV_DIRECT_BUDGET_V1\n" + json.dumps(value, sort_keys=True)
    return note


class RootDirectAdmissionTests(unittest.TestCase):
    """A first-key direct comparison has a distinct grant and no historical replay."""

    def test_direct_root_grant_and_command_cannot_authorize_prior_profiles(self):
        profile = CHECK.ROOT_DIRECT_PROFILE
        identity = CHECK.validate_event(root_direct_event_fixture(), env_fixture(), profile)
        self.assertEqual(identity["control_source_sha"], CONTROL)
        budget = CHECK.budget_note(root_direct_budget_fixture(), NOW, profile)
        self.assertEqual(budget["account_env"], "TOURVISOR_JWT")
        self.assertEqual(budget["approval_reference"], 6262670660)
        self.assertEqual(profile["approval_hash"], "059425ea173d935837747e139aeecebc9bb131b91aa31a0f778a2335bf3f62b8")
        for previous in (None, CHECK.ANEX_PROFILE):
            with self.subTest(previous=previous):
                with self.assertRaises(RuntimeError):
                    CHECK.validate_event(root_direct_event_fixture(), env_fixture(), previous)
                with self.assertRaises(RuntimeError):
                    CHECK.budget_note(root_direct_budget_fixture(), NOW, previous)
        for event in (event_fixture(), anex_event_fixture()):
            with self.assertRaises(RuntimeError):
                CHECK.validate_event(event, env_fixture(), profile)

    def test_direct_root_budget_rejects_other_accounts_grants_caps_and_days(self):
        for note in (
            budget_fixture(), anex_budget_fixture(),
            root_direct_budget_fixture(account_env="TOURVISOR_ANEX_JWT"),
            root_direct_budget_fixture(approval_reference=6261576536),
            root_direct_budget_fixture(operation_id="tv-anex-key-20261019-v1"),
            root_direct_budget_fixture(reserved_search_units=2),
            root_direct_budget_fixture(max_http_attempts=4),
        ):
            with self.subTest(body=note["body"]):
                with self.assertRaises(RuntimeError):
                    CHECK.budget_note(note, NOW, CHECK.ROOT_DIRECT_PROFILE)
        with self.assertRaises(RuntimeError):
            CHECK.budget_note(root_direct_budget_fixture(), NOW + dt.timedelta(days=1), CHECK.ROOT_DIRECT_PROFILE)

    def test_direct_root_admission_reads_prior_commands_but_rejects_own_duplicate(self):
        source, tree = "c" * 40, "d" * 40
        approval_body = "offline direct-root owner approval"
        note = root_direct_budget_fixture()
        note["issue_url"] = "https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/4217"
        responses = {
            "/git/ref/heads/main": {"object": {"sha": CONTROL}},
            "/git/ref/heads/release/search3-production-ready-v1": {"object": {"sha": CHECK.RELEASE}},
            "/contents/v2/api-v2.php?ref=" + CHECK.RELEASE: {"sha": CHECK.API_BLOB},
            "/issues/comments/6262670660": {
                "user": {"id": 226193297}, "author_association": "OWNER", "body": approval_body,
            },
            "/git/commits/" + CONTROL: {"parents": [{"sha": "b" * 40}, {"sha": source}], "tree": {"sha": tree}},
            "/git/commits/" + source: {"sha": source, "tree": {"sha": tree}},
            "/commits/" + source + "/check-runs?per_page=100": {
                "check_runs": [{"name": name, "status": "completed", "conclusion": "success", "app": {"slug": "github-actions"}} for name in ("guard", "contracts", "live-check-contracts")]
            },
            "/issues/4217/comments?per_page=100&page=1": [event_fixture()["comment"], anex_event_fixture()["comment"], root_direct_event_fixture()["comment"]],
            "/issues/comments/6261700001": root_direct_event_fixture()["comment"],
            "/issues/comments/6261699999": note,
        }
        profile = dict(CHECK.ROOT_DIRECT_PROFILE, approval_hash=hashlib.sha256(approval_body.encode()).hexdigest())
        identity = CHECK.validate_event(root_direct_event_fixture(), env_fixture(), profile)
        with (
            mock.patch.object(CHECK, "checked_api", side_effect=lambda path: copy.deepcopy(responses[path])),
            mock.patch("datetime.datetime", FrozenDateTime),
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
        ):
            self.assertEqual(CHECK.admission(identity, profile)["operation_id"], "tv-root-direct-20261019-v1")
            prior = copy.deepcopy(root_direct_event_fixture()["comment"])
            prior["id"] = 6261698000
            responses["/issues/4217/comments?per_page=100&page=1"].insert(0, prior)
            with self.assertRaisesRegex(RuntimeError, "operation_no_replay"):
                CHECK.admission(identity, profile)


class RootDirectRemoteTests(unittest.TestCase):
    """Execute real generated direct code with fake HTTP and a private root account."""

    def setUp(self):
        RemoteTests.setUp(self)
        self.policy.update(
            operation="tv-root-direct-20261019-v1", direct=True,
            account_env="TOURVISOR_JWT", approval_reference=6262670660,
            blocked_search_ids=CHECK.ROOT_DIRECT_PROFILE["blocked_search_ids"],
        )
        self.payload["budget"] = json.loads(root_direct_budget_fixture()["body"].split("\n", 1)[1])
        self.operation_path = self.private / self.policy["operation"]

    def execute(self, responses=None, **kwargs):
        kwargs.setdefault("config", {
            "token": "synthetic.root.token", "root_equal": False, "same_subject": False,
        })
        return RemoteTests.execute(self, responses, **kwargs)

    def test_direct_root_uses_same_fixed_api_payload_with_selected_root_bearer(self):
        before = self.api.read_bytes(), self.app.read_bytes()
        result, sleep = self.execute()
        urls = [urllib.parse.urlsplit(url) for url, _, _ in self.calls]
        self.assertEqual([url.path for url in urls], [
            "/search/api/v1/tours/search", "/search/api/v1/tours/search/2026101900001/status",
            "/search/api/v1/tours/search/2026101900001",
        ])
        self.assertTrue(all(url.scheme == "https" and url.netloc == "api.tourvisor.ru" for url in urls))
        self.assertEqual(urllib.parse.parse_qs(urls[0].query), {
            **{key: [str(value)] for key, value in CHECK.PARAMS.items()},
            "onlyCharter": ["false"], "onlyDirect": ["false"],
        })
        self.assertEqual(urllib.parse.parse_qs(urls[1].query), {"operatorStatus": ["false"]})
        self.assertEqual(urllib.parse.parse_qs(urls[2].query), {"limit": ["25"]})
        for _, headers, timeout in self.calls:
            self.assertEqual(headers["Authorization"], "Bearer synthetic.root.token")
            self.assertEqual(timeout, 55)
            self.assertFalse(any(key.lower() in ("cookie", "x-ci-token") for key in headers))
        self.assertEqual(result["status"], "fresh_populated_results_verified")
        self.assertEqual(result["account_env"], "TOURVISOR_JWT")
        self.assertEqual(result["route"], "direct_tourvisor_api")
        self.assertIs(result["normal_autosave_enabled"], False)
        self.assertEqual(result["database_write_count"], 0)
        self.assertEqual(result["application_file_writes"], 0)
        self.assertEqual(result["before_files"], result["after_files"])
        self.assertEqual((self.api.read_bytes(), self.app.read_bytes()), before)
        sleep.assert_called_once_with(20)

    def test_direct_root_never_creates_shared_anex_quota_storage(self):
        result, _ = self.execute()
        self.assertFalse((self.home / ".anytoour-match").exists())
        self.assertEqual(result["quota_scope"], "owner_single_operation_only")
        self.assertEqual(result["global_daily_usage"], "not_measured")
        self.assertNotIn("known_accounted_units_before", result)
        self.assertNotIn("known_accounted_units_after", result)

    def test_existing_foreign_anex_ledger_is_byte_identical_after_direct_root(self):
        quota = self.home / ".anytoour-match/provider-quotas"
        quota.mkdir(parents=True, mode=0o700)
        for name, body in (
            ("tourvisor-anex-2026-10-19.json", b'{"tariff_search_units":3000,"retained":"foreign writer"}\n'),
            ("tourvisor-anex-tv-anex-key-20261019-v1.json", b'{"status":"unknown_no_replay","retained":true}\n'),
        ):
            (quota / name).write_bytes(body)
            (quota / name).chmod(0o600)
        before = {str(path.relative_to(quota)): path.read_bytes() for path in quota.iterdir()}
        result, _ = self.execute()
        self.assertEqual(result["status"], "fresh_populated_results_verified")
        self.assertEqual({str(path.relative_to(quota)): path.read_bytes() for path in quota.iterdir()}, before)

    def test_private_attempt_records_reserve_each_physical_attempt_before_http(self):
        def observe(attempt):
            files = sorted(self.operation_path.glob("http-attempt-*.json"))
            self.assertEqual([path.name for path in files], [f"http-attempt-{i:02}.json" for i in range(1, attempt + 1)])
            for index, path in enumerate(files, 1):
                record = json.loads(path.read_text())
                self.assertEqual(record["action"], ("search_start", "search_status", "search_results")[index - 1])
                self.assertEqual(record, {
                    "operation": self.policy["operation"], "account_env": "TOURVISOR_JWT",
                    "action": ("search_start", "search_status", "search_results")[index - 1],
                    "physical_attempt": index, "tariff_search_units": 1,
                    "provider_day": "2026-10-19",
                })
                self.assertEqual(path.stat().st_mode & 0o777, 0o600)

        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        self.assertEqual(result["operation_physical_attempts"], 3)
        self.assertEqual(result["operation_tariff_units"], 1)
        self.assertEqual(self.operation_path.stat().st_mode & 0o777, 0o700)
        for path in self.operation_path.iterdir():
            self.assertEqual(path.stat().st_mode & 0o777, 0o600)
            self.assertNotIn("synthetic.root.token", path.read_text())
            self.assertNotIn("tour-1", path.read_text())

    def test_prior_private_operation_is_retained_and_not_replayed(self):
        self.operation_path.mkdir(mode=0o700)
        reservation = self.operation_path / "reservation.json"
        reservation.write_text('{"status":"unknown_no_replay","retained":"previous attempt"}\n')
        reservation.chmod(0o600)
        before = reservation.read_bytes()
        result, sleep = self.execute()
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertEqual(reservation.read_bytes(), before)
        self.assertEqual(list(self.operation_path.iterdir()), [reservation])
        sleep.assert_not_called()

    def test_first_attempt_auth_failure_retains_safe_metadata_and_own_charge_only(self):
        body = b'{"error":"synthetic.root.token must stay private"}'
        failure = urllib.error.HTTPError("https://api.tourvisor.ru/search/api/v1/tours/search", 401, "denied", {}, io.BytesIO(body))
        result, sleep = self.execute([failure])
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(result["reason"], "http_failed")
        self.assertEqual(result["requests"][0]["error_category"], "auth_denied")
        self.assertEqual(result["requests"][0]["sha256"], hashlib.sha256(body).hexdigest())
        self.assertEqual(result["operation_physical_attempts"], 1)
        self.assertEqual(result["operation_tariff_units"], 1)
        self.assertEqual(len(self.calls), 1)
        self.assertNotIn("synthetic.root.token", json.dumps(result))
        self.assertFalse((self.home / ".anytoour-match").exists())
        sleep.assert_not_called()

    def test_direct_root_results_error_has_bounded_body_hash_without_raw_error(self):
        body = b"synthetic.root.token " + b"x" * 66000
        failure = urllib.error.HTTPError("https://api.tourvisor.ru/search/api/v1/tours/search/2026101900001", 500, "failed", {}, io.BytesIO(body))
        result, _ = self.execute([{"searchId": "2026101900001"}, status_fixture(), failure])
        self.assertEqual(result["reason"], "http_failed")
        self.assertEqual(result["requests"][-1]["http"], 500)
        self.assertEqual(result["requests"][-1]["error_category"], "upstream_server_error")
        self.assertEqual(result["requests"][-1]["bytes"], 65537)
        self.assertTrue(result["requests"][-1]["body_truncated"])
        self.assertEqual(result["requests"][-1]["hash_scope"], "bounded_prefix")
        self.assertEqual(result["operation_physical_attempts"], 3)
        self.assertEqual(result["operation_tariff_units"], 1)
        self.assertEqual(len(self.calls), 3)
        self.assertNotIn("synthetic.root.token", json.dumps(result))

    def test_all_consumed_search_ids_stop_after_start_without_status_or_results(self):
        for search_id in ("13905446138", "13943172077", "13943616554"):
            with self.subTest(search_id=search_id):
                self.temp.cleanup()
                self.setUp()
                result, sleep = self.execute([{"searchId": search_id}])
                self.assertEqual(result["reason"], "historical_search_identity")
                self.assertEqual(len(self.calls), 1)
                self.assertEqual(result["operation_physical_attempts"], 1)
                sleep.assert_not_called()

    def test_wrong_account_budget_cannot_authorize_direct_root_call(self):
        payload = copy.deepcopy(self.payload)
        payload["budget"] = json.loads(anex_budget_fixture()["body"].split("\n", 1)[1])
        result, sleep = self.execute(payload=payload)
        self.assertFalse(result["live_operation_started"])
        self.assertEqual(self.calls, [])
        self.assertFalse(self.operation_path.exists())
        self.assertFalse((self.home / ".anytoour-match").exists())
        sleep.assert_not_called()

    def test_root_missing_invalid_or_known_shared_credentials_block_before_http(self):
        for config in (
            {"token": "", "root_equal": False, "same_subject": False},
            {"token": "synthetic.root.token", "root_equal": True, "same_subject": False},
            {"token": "synthetic.root.token", "root_equal": False, "same_subject": True},
            {"token": "synthetic.root.token\r\nX-Header: injection", "root_equal": False, "same_subject": False},
        ):
            with self.subTest(config=config):
                result, sleep = self.execute(config=config)
                self.assertFalse(result["live_operation_started"])
                self.assertEqual(self.calls, [])
                self.assertFalse(self.operation_path.exists())
                sleep.assert_not_called()

    def test_provider_day_change_after_root_start_stops_before_second_charge(self):
        def observe(attempt):
            if attempt == 1:
                tomorrow = mock.patch(__name__ + ".NOW", NOW + dt.timedelta(days=1))
                tomorrow.start()
                self.addCleanup(tomorrow.stop)

        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        self.assertEqual(result["reason"], "budget_day")
        self.assertEqual(result["status"], "unknown_no_replay")
        self.assertEqual(len(self.calls), 1)
        self.assertEqual(result["operation_physical_attempts"], 1)
        self.assertEqual(result["operation_tariff_units"], 1)
        self.assertEqual([path.name for path in self.operation_path.glob("http-attempt-*.json")], ["http-attempt-01.json"])
        self.assertFalse((self.home / ".anytoour-match").exists())

    def run_profile_main(self, receipt):
        runner = pathlib.Path(tempfile.mkdtemp(prefix="offline-root-direct-main-", dir=self.home))
        event_path = runner / "event.json"
        event_path.write_text(json.dumps(root_direct_event_fixture()))
        env = dict(env_fixture(), RUNNER_TEMP=str(runner), GITHUB_EVENT_PATH=str(event_path))

        def ssh_fixture(work):
            key, known = work / "key", work / "known"
            key.write_text("synthetic private key")
            known.write_text("synthetic known hosts")
            return key, known, ["-F", "/dev/null"], "offline-host"

        output, error = io.StringIO(), None
        with (
            mock.patch.dict(CHECK.os.environ, env, clear=True),
            mock.patch.object(CHECK, "admission", return_value=self.payload["budget"]) as admission,
            mock.patch.object(CHECK, "ssh_options", side_effect=ssh_fixture),
            mock.patch.object(CHECK, "run", return_value=json.dumps(receipt).encode()) as remote_run,
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
            contextlib.redirect_stdout(output),
        ):
            try:
                CHECK.main(CHECK.ROOT_DIRECT_PROFILE)
            except RuntimeError as exc:
                error = exc
        work = runner / "search3-tv-root-direct-check"
        evidence = json.loads((work / "evidence.json").read_text())
        self.assertEqual(json.loads(output.getvalue()), evidence)
        self.assertFalse((work / "key").exists())
        self.assertFalse((work / "known").exists())
        admission.assert_called_once_with(
            CHECK.validate_event(root_direct_event_fixture(), env, CHECK.ROOT_DIRECT_PROFILE), CHECK.ROOT_DIRECT_PROFILE,
        )
        return evidence, remote_run, error

    def test_main_dispatches_root_direct_policy_and_accepts_only_its_account_receipt(self):
        import ast

        receipt, _ = self.execute()
        evidence, remote_run, error = self.run_profile_main(receipt)
        self.assertIsNone(error)
        self.assertEqual(evidence["status"], "fresh_populated_results_verified")
        self.assertEqual(evidence["remote"], receipt)
        command = remote_run.call_args.args[0]
        program = CHECK.shlex.split(command[-1])
        policy = ast.literal_eval(program[2].split("\n", 1)[0].removeprefix("POLICY="))
        self.assertTrue(policy["direct"])
        self.assertEqual(policy["account_env"], "TOURVISOR_JWT")
        self.assertEqual(policy["operation"], "tv-root-direct-20261019-v1")
        self.assertEqual(policy["approval_reference"], 6262670660)
        self.assertEqual(policy["params"], CHECK.PARAMS)
        for altered in (
            dict(receipt, account_env="TOURVISOR_ANEX_JWT"),
            dict(receipt, known_accounted_units_before=0),
            dict(receipt, quota_scope="tourvisor-anex-shared"),
            dict(receipt, operation_physical_attempts=4),
            dict(receipt, operation_tariff_units=2),
        ):
            with self.subTest(fields=sorted(altered)):
                retained, _, error = self.run_profile_main(altered)
                self.assertIsInstance(error, RuntimeError)
                self.assertNotIn("remote", retained)
        self.assertNotIn("synthetic.root.token", json.dumps(evidence))

    def test_native_php_root_selector_matches_api_env_first_then_constant(self):
        import shutil

        php = shutil.which("php")
        if php is None:
            if CHECK.os.environ.get("SEARCH3_ANEX_KEY_NATIVE_CI") == "1":
                self.fail("Native PHP is required for the direct root credential selector in CI")
            self.skipTest("Local PHP is unavailable; CI requires the native selector check")
        self.execute()
        code = self.config_calls[0].args[0][2]
        config = self.home / "synthetic-root-native-config.php"
        env = {
            "PATH": str(pathlib.Path(php).parent), "HOME": str(self.home),
            "TOURVISOR_JWT": "synthetic.root.environment",
            "TOURVISOR_ANEX_JWT": "synthetic.anex.environment",
        }

        def select(config_body, active_env):
            config.write_text("<?php\n" + config_body)
            completed = subprocess.run(
                [php, "-r", code, "--", str(config)],
                env=active_env, cwd=self.home, capture_output=True, timeout=15, check=False,
            )
            self.assertEqual(completed.returncode, 0, "Generated root PHP selector failed")
            self.assertEqual(completed.stderr, b"")
            selected = json.loads(completed.stdout)
            self.assertEqual(set(selected), {"token", "root_equal", "same_subject"})
            return selected

        both = "define('TOURVISOR_JWT', 'synthetic.root.constant'); define('TOURVISOR_ANEX_JWT', 'synthetic.anex.constant');"
        self.assertEqual(select(both, env)["token"], "synthetic.root.environment")
        self.assertEqual(select(both, dict(env, TOURVISOR_JWT=""))["token"], "synthetic.root.constant")
        self.assertEqual(select(both, dict(env, TOURVISOR_JWT="   "))["token"], "synthetic.root.constant")
        no_root = "define('TOURVISOR_ANEX_JWT', 'synthetic.anex.constant');"
        self.assertEqual(select(no_root, dict(env, TOURVISOR_JWT=""))["token"], "")


def recovery_event_fixture():
    event = event_fixture()
    event['comment']['body'] = f'/check-search3-root-recovery {CONTROL} tv-gateway-recovery-20261019-v1 6261699999'
    return event


def recovery_budget_fixture(**changes):
    note = budget_fixture()
    value = json.loads(note['body'].split('\n', 1)[1])
    value.update(operation_id='tv-gateway-recovery-20261019-v1', approval_reference=6263435322)
    value.update(changes)
    note['body'] = 'SEARCH3_ROOT_TV_RECOVERY_BUDGET_V1\n' + json.dumps(value, sort_keys=True)
    return note


class RecoveryAdmissionTests(unittest.TestCase):
    def setUp(self):
        source, tree = 'c' * 40, 'd' * 40
        self.approval_body = 'offline recovery owner approval'
        self.profile = dict(CHECK.RECOVERY_PROFILE, approval_hash=hashlib.sha256(self.approval_body.encode()).hexdigest())
        self.identity = CHECK.validate_event(recovery_event_fixture(), env_fixture(), self.profile)
        note = recovery_budget_fixture()
        note['issue_url'] = 'https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/4217'
        self.responses = {
            '/git/ref/heads/main': {'object': {'sha': CONTROL}},
            '/git/ref/heads/release/search3-production-ready-v1': {'object': {'sha': self.profile['release']}},
            '/contents/v2/api-v2.php?ref=' + self.profile['release']: {'sha': self.profile['api_blob']},
            '/issues/comments/6263435322': {'user': {'id': 226193297}, 'author_association': 'OWNER', 'body': self.approval_body},
            '/git/commits/' + CONTROL: {'parents': [{'sha': 'b' * 40}, {'sha': source}], 'tree': {'sha': tree}},
            '/git/commits/' + source: {'sha': source, 'tree': {'sha': tree}},
            '/commits/' + source + '/check-runs?per_page=100': {'check_runs': [
                {'name': name, 'status': 'completed', 'conclusion': 'success', 'app': {'slug': 'github-actions'}}
                for name in ('guard', 'contracts', 'live-check-contracts')]},
            '/commits/' + CONTROL + '/check-runs?per_page=100': {'check_runs': [
                {'name': 'guard', 'status': 'completed', 'conclusion': 'success', 'app': {'slug': 'github-actions'}}]},
            '/issues/4217/comments?per_page=100&page=1': [event_fixture()['comment'], anex_event_fixture()['comment'], root_direct_event_fixture()['comment'], recovery_event_fixture()['comment']],
            '/issues/comments/6261700001': recovery_event_fixture()['comment'],
            '/issues/comments/6261699999': note,
        }
        for path, blob in self.profile.get('main_source_blobs', {}).items():
            self.responses['/contents/' + path + '?ref=' + CONTROL] = {'sha': blob}

    def admit(self):
        with (mock.patch.object(CHECK, 'checked_api', side_effect=lambda path: copy.deepcopy(self.responses[path])),
              mock.patch('datetime.datetime', FrozenDateTime)):
            return CHECK.admission(self.identity, self.profile)

    def test_recovery_has_new_immutable_grant_without_rewriting_consumed_profiles(self):
        self.assertEqual(self.admit()['operation_id'], 'tv-gateway-recovery-20261019-v1')
        self.assertEqual(CHECK.RECOVERY_PROFILE['approval_hash'], '68502f397f771f6d757c943372bba0671e6a0f83d402047c4429ddf465acd7c2')
        self.assertEqual(CHECK.RELEASE, '34bb747dcd1ed625590a60de17112cfd8ab0b28b')
        self.assertEqual(CHECK.API_BLOB, '6fb963e68a99945118d04f8e46845ecd6bcb6bc9')
        for prior in (None, CHECK.ANEX_PROFILE, CHECK.ROOT_DIRECT_PROFILE):
            with self.subTest(prior=prior):
                with self.assertRaises(RuntimeError):CHECK.validate_event(recovery_event_fixture(), env_fixture(), prior)
                with self.assertRaises(RuntimeError):CHECK.budget_note(recovery_budget_fixture(), NOW, prior)
        for prior in (budget_fixture(), anex_budget_fixture(), root_direct_budget_fixture()):
            with self.assertRaises(RuntimeError):CHECK.budget_note(prior, NOW, self.profile)

    def test_recovery_requires_current_reviewed_merge_release_main_ci_and_source_blobs(self):
        cases = [('/git/ref/heads/release/search3-production-ready-v1', {'object': {'sha': CHECK.RELEASE}}),
                 ('/contents/v2/api-v2.php?ref=' + self.profile['release'], {'sha': CHECK.API_BLOB}),
                 ('/commits/' + CONTROL + '/check-runs?per_page=100', {'check_runs': []})]
        cases += [('/contents/' + path + '?ref=' + CONTROL, {'sha': 'f' * 40}) for path in self.profile.get('main_source_blobs', {})]
        for path, changed in cases:
            with self.subTest(path=path):
                original = self.responses[path]
                self.responses[path] = changed
                with self.assertRaises(RuntimeError):self.admit()
                self.responses[path] = original

    def test_recovery_does_not_replay_even_after_other_completed_commands(self):
        path = '/issues/4217/comments?per_page=100&page=1'
        duplicate = copy.deepcopy(recovery_event_fixture()['comment'])
        duplicate['id'] = 6261698000
        self.responses[path].insert(0, duplicate)
        with self.assertRaisesRegex(RuntimeError, 'operation_no_replay'):self.admit()

    def test_recovery_budget_does_not_expand_account_day_or_attempts(self):
        for changes in ({'account_env': 'TOURVISOR_ANEX_JWT'}, {'max_http_attempts': 4},
                        {'reserved_search_units': 2}, {'approval_reference': 6262670660},
                        {'provider_day': '2026-10-20'}):
            with self.subTest(changes=changes):
                with self.assertRaises(RuntimeError):CHECK.budget_note(recovery_budget_fixture(**changes), NOW, self.profile)


class RecoveryRemoteTests(unittest.TestCase):
    def setUp(self):
        RemoteTests.setUp(self)
        self.catalogue = self.project / 'data/local-tv-catalog-v1.php'
        self.catalogue.parent.mkdir()
        self.catalogue.write_bytes(b'offline repaired LOCAL include owner')
        self.policy.update(operation='tv-gateway-recovery-20261019-v1', direct=False, gateway_recovery=True,
                           account_env='TOURVISOR_JWT', approval_reference=6263435322,
                           catalogue_hash=hashlib.sha256(self.catalogue.read_bytes()).hexdigest(),
                           blocked_search_ids=CHECK.RECOVERY_PROFILE['blocked_search_ids'])
        self.payload['budget'] = json.loads(recovery_budget_fixture()['body'].split('\n', 1)[1])
        self.operation_path = self.private / self.policy['operation']
        install_path = self.private / 'install-20261019-dependency-reuse-v1'
        install_path.mkdir(mode=0o700)
        self.install_receipt_path = install_path / 'result.json'
        protected = {'_preview/search3-next-candidate/visual-search/app.js': self.policy['app_hash'], 'config.php': 'b' * 64}
        self.install_receipt = {
            'operation_id': 'install-20261019-dependency-reuse-v1', 'status': 'installed_verified',
            'source_sha': 'd6780854c16f390ec9ad3117a484e72e0851c884',
            'release_sha': CHECK.RECOVERY_PROFILE['release'], 'local_source_sha': '8bb7336cfa8e6e702ea656c762fe331a1c8283c6',
            'control_source_sha': CONTROL, 'run_id': '39190000000', 'command_comment_id': 6261699000,
            **dict.fromkeys(('supplier_calls', 'database_writes', 'profile_writes', 'mapping_writes', 'schema_writes', 'lead_calls', 'booking_calls', 'rollback_attempts'), 0),
            'rollback_files': [], 'backup_retained': True,
            'syntax_previous': 'ok', 'syntax_candidate': 'ok', 'syntax_installed': 'ok',
            'after_files': {key: {'sha256': value} for key, value in {
                'api-v2.php': self.policy['api_hash'], 'data/local-tv-catalog-v1.php': self.policy['catalogue_hash']}.items()},
            'production_before': copy.deepcopy(protected), 'production_after': protected,
        }
        self.install_receipt_path.write_text(json.dumps(self.install_receipt))
        self.install_receipt_path.chmod(0o600)

    execute = RemoteTests.execute

    def test_recovery_uses_existing_gateway_with_no_direct_bearer_or_extra_calls(self):
        result, sleep = self.execute()
        self.assertEqual(result['status'], 'fresh_populated_results_verified')
        self.assertEqual(result['route'], 'existing_root_gateway')
        self.assertEqual(result['normal_autosave_enabled'], True)
        self.assertEqual(result['database_write_count'], 'not_measured')
        self.assertEqual(result['quota_scope'], 'owner_single_operation_only')
        self.assertEqual(result['global_daily_usage'], 'not_measured')
        self.assertEqual(result['before_files'], result['after_files'])
        self.assertEqual(set(result['before_files']), {'api-v2.php', 'frontend_app', 'data/local-tv-catalog-v1.php'})
        queries = []
        for url, headers, timeout in self.calls:
            parts = urllib.parse.urlsplit(url)
            self.assertEqual((parts.scheme, parts.netloc, parts.path), ('https', 'anytoour.ru', '/api-v2.php'))
            self.assertNotIn('Authorization', headers)
            self.assertEqual(timeout, 55)
            queries.append(urllib.parse.parse_qs(parts.query))
        self.assertEqual(queries, [
            {'action': ['search_start'], **{key: [str(value)] for key, value in CHECK.PARAMS.items()}},
            {'action': ['search_status'], 'searchId': ['2026101900001']},
            {'action': ['search_results'], 'searchId': ['2026101900001'], 'limit': ['25']}])
        self.assertNotIn('"token"', self.config_calls[0].args[0][2])
        sleep.assert_called_once_with(20)

    def test_recovery_records_each_private_attempt_before_http_without_anex_quota_access(self):
        quota = self.home / '.anytoour-match/provider-quotas'
        quota.mkdir(parents=True)
        foreign = quota / 'tourvisor-anex-2026-10-19.json'
        foreign.write_bytes(b'{"retained":"do not touch","tariff_search_units":3000}\n')
        original = foreign.read_bytes()
        def observe(attempt):
            files = sorted(self.operation_path.glob('http-attempt-*.json'))
            self.assertEqual(len(files), attempt)
            record = json.loads(files[-1].read_text())
            self.assertEqual(record, {'operation': self.policy['operation'], 'account_env': 'TOURVISOR_JWT',
                'action': ('search_start', 'search_status', 'search_results')[attempt - 1],
                'physical_attempt': attempt, 'tariff_search_units': 1, 'provider_day': '2026-10-19'})
            self.assertEqual(files[-1].stat().st_mode & 0o777, 0o600)
        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        self.assertEqual((result['operation_physical_attempts'], result['operation_tariff_units']), (3, 1))
        self.assertEqual(foreign.read_bytes(), original)
        self.assertEqual(list(quota.iterdir()), [foreign])

    def test_recovery_preserves_unknown_operation_instead_of_retrying(self):
        self.operation_path.mkdir(mode=0o700)
        previous = self.operation_path / 'reservation.json'
        previous.write_bytes(b'{"status":"unknown_no_replay"}')
        result, sleep = self.execute()
        self.assertFalse(result['live_operation_started'])
        self.assertEqual(self.calls, [])
        self.assertEqual(previous.read_bytes(), b'{"status":"unknown_no_replay"}')
        sleep.assert_not_called()

    def test_recovery_missing_or_changed_catalogue_blocks_before_supplier_access(self):
        for missing in (False, True):
            with self.subTest(missing=missing):
                if missing:self.catalogue.unlink()
                else:self.catalogue.write_bytes(b'old LOCAL owner')
                result, _ = self.execute()
                self.assertFalse(result['live_operation_started'])
                self.assertEqual(self.calls, [])
                self.assertFalse(self.operation_path.exists())

    def test_recovery_unverified_installation_blocks_before_live_reservation_or_http(self):
        for changed in (dict(self.install_receipt, status='unknown_no_replay'),
                        dict(self.install_receipt, source_sha='f' * 40),
                        dict(self.install_receipt, control_source_sha='f' * 40),
                        dict(self.install_receipt, supplier_calls=1),
                        dict(self.install_receipt, syntax_installed='not_run'),
                        dict(self.install_receipt, after_files={}),
                        dict(self.install_receipt, production_after={}),
                        dict(self.install_receipt, reason='operation_failed')):
            with self.subTest(fields=sorted(changed)):
                self.install_receipt_path.write_text(json.dumps(changed))
                result, sleep = self.execute()
                self.assertEqual(result['reason'], 'installation_unverified')
                self.assertFalse(result['live_operation_started'])
                self.assertFalse(self.operation_path.exists())
                self.assertEqual(self.calls, [])
                sleep.assert_not_called()
        self.install_receipt_path.unlink()
        result, _ = self.execute()
        self.assertFalse(result['live_operation_started'])
        self.assertFalse(self.operation_path.exists())
        self.assertEqual(self.calls, [])

    def test_recovery_all_consumed_search_ids_stop_after_one_attempt(self):
        for sid in CHECK.RECOVERY_PROFILE['blocked_search_ids']:
            with self.subTest(sid=sid):
                self.temp.cleanup();self.setUp()
                result, sleep = self.execute([{'searchId': sid}])
                self.assertEqual(result['reason'], 'historical_search_identity')
                self.assertEqual(len(self.calls), 1)
                self.assertEqual(result['operation_physical_attempts'], 1)
                sleep.assert_not_called()

    def test_recovery_http_error_retains_bounded_metadata_not_body_or_config(self):
        body = b'synthetic private error text ' + b'x' * 66000
        failure = urllib.error.HTTPError('https://anytoour.ru/api-v2.php', 500, 'failed', {}, io.BytesIO(body))
        result, _ = self.execute([{'searchId': '2026101900001'}, status_fixture(), failure])
        entry = result['requests'][-1]
        self.assertEqual(entry, {'action': 'search_results', 'http': 500, 'bytes': 65537,
            'json_kind': 'not_measured', 'sha256': hashlib.sha256(body[:65537]).hexdigest(),
            'body_truncated': True, 'hash_scope': 'bounded_prefix', 'error_category': 'gateway_server_error'})
        self.assertEqual(len(self.calls), 3)
        self.assertEqual(result['before_files'], result['after_files'])
        self.assertNotIn('synthetic private error text', json.dumps(result))
        self.assertEqual(result['database_write_count'], 'not_measured')

    def test_recovery_partial_results_do_not_claim_complete_search(self):
        result, _ = self.execute([{'searchId': '2026101900001'}, status_fixture(progress=50), []])
        self.assertEqual(result['status'], 'fresh_partial_results_verified')
        self.assertEqual(len(self.calls), 3)
        self.assertFalse((self.home / '.anytoour-match').exists())

    def test_recovery_day_change_stops_before_second_http_and_preserves_first_charge(self):
        def observe(attempt):
            if attempt == 1:
                tomorrow = mock.patch(__name__ + '.NOW', NOW + dt.timedelta(days=1))
                tomorrow.start();self.addCleanup(tomorrow.stop)
        self.calls = ObservedCalls(observe)
        result, _ = self.execute()
        self.assertEqual(result['reason'], 'budget_day')
        self.assertEqual(len(self.calls), 1)
        self.assertEqual(result['operation_physical_attempts'], 1)

    def run_profile_main(self, receipt):
        runner = pathlib.Path(tempfile.mkdtemp(prefix='offline-recovery-main-', dir=self.home))
        event_path = runner / 'event.json';event_path.write_text(json.dumps(recovery_event_fixture()))
        env = dict(env_fixture(), RUNNER_TEMP=str(runner), GITHUB_EVENT_PATH=str(event_path))
        def ssh_fixture(work):
            key, known = work / 'key', work / 'known';key.write_text('synthetic');known.write_text('synthetic')
            return key, known, ['-F', '/dev/null'], 'offline-host'
        output, error = io.StringIO(), None
        with (mock.patch.dict(CHECK.os.environ, env, clear=True),
              mock.patch.object(CHECK, 'admission', return_value=self.payload['budget']),
              mock.patch.object(CHECK, 'ssh_options', side_effect=ssh_fixture),
              mock.patch.object(CHECK, 'run', return_value=json.dumps(receipt).encode()) as remote_run,
              contextlib.redirect_stdout(output)):
            try:CHECK.main(CHECK.RECOVERY_PROFILE)
            except RuntimeError as exc:error = exc
        work = runner / 'search3-root-recovery-check'
        evidence = json.loads((work / 'evidence.json').read_text())
        self.assertEqual(json.loads(output.getvalue()), evidence)
        self.assertFalse((work / 'key').exists());self.assertFalse((work / 'known').exists())
        return evidence, remote_run, error

    def test_recovery_main_dispatches_fixed_repaired_policy_and_does_not_assert_db_zero(self):
        import ast
        receipt, _ = self.execute()
        # Runtime fixtures use synthetic file bytes; the controller must enforce published pins.
        receipt['before_files'] = receipt['after_files'] = {
            'api-v2.php': CHECK.RECOVERY_PROFILE['api_hash'], 'frontend_app': CHECK.APP_HASH,
            'data/local-tv-catalog-v1.php': CHECK.RECOVERY_PROFILE['catalogue_hash']}
        evidence, remote_run, error = self.run_profile_main(receipt)
        self.assertIsNone(error)
        self.assertEqual(evidence['remote'], receipt)
        program = CHECK.shlex.split(remote_run.call_args.args[0][-1])
        policy = ast.literal_eval(program[2].split('\n', 1)[0].removeprefix('POLICY='))
        self.assertFalse(policy['direct']);self.assertTrue(policy['gateway_recovery'])
        self.assertEqual(policy['api_hash'], CHECK.RECOVERY_PROFILE['api_hash'])
        self.assertEqual(policy['catalogue_hash'], CHECK.RECOVERY_PROFILE['catalogue_hash'])
        self.assertEqual(evidence['release_sha'], CHECK.RECOVERY_PROFILE['release'])
        for changed in (dict(receipt, database_write_count=0), dict(receipt, normal_autosave_enabled=False),
                        dict(receipt, route='direct_tourvisor_api'), dict(receipt, operation_physical_attempts=4),
                        dict(receipt, after_files={'api-v2.php': CHECK.API_HASH}), dict(receipt, token='must-not-retain')):
            with self.subTest(fields=sorted(changed)):
                retained, _, error = self.run_profile_main(changed)
                self.assertIsInstance(error, RuntimeError)
                self.assertNotIn('remote', retained)


if __name__ == "__main__":
    unittest.main()


