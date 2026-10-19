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
        "observed_at": "2026-10-19T06:59:00Z",
        "source": "owner_account_readback",
        "daily_limit": 3000,
        "used_search_units": 12,
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

    def test_fresh_budget_proof_is_accepted_for_bound_account(self):
        value = CHECK.budget_note(budget_fixture(), NOW)
        self.assertEqual(value["account_env"], "TOURVISOR_JWT")
        self.assertEqual(value["reserved_search_units"], 1)
        self.assertEqual(value["max_http_attempts"], 3)

    def test_each_allowed_account_daily_limit_has_room_for_one_search(self):
        for limit in (300, 3000):
            with self.subTest(limit=limit):
                value = CHECK.budget_note(
                    budget_fixture(daily_limit=limit, used_search_units=limit - 1), NOW
                )
                self.assertEqual(value["daily_limit"], limit)

    def test_budget_rejects_unbound_account_source_day_or_operation(self):
        for changes in (
            {"account_env": "TOURVISOR_TOKEN"},
            {"source": "automation_estimate"},
            {"provider_day": "2026-10-18"},
            {"operation_id": "tv-20261019-anex13-v2"},
            {"schema_version": 2},
            {"reserved_search_units": 2},
            {"max_http_attempts": 4},
            {"daily_limit": 5000},
        ):
            with self.subTest(changes=changes):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(**changes), NOW)

    def test_budget_requires_owner_readback_comment(self):
        for field, bad in (("user", {"id": 1}), ("author_association", "MEMBER")):
            with self.subTest(field=field):
                note = budget_fixture()
                note[field] = bad
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(note, NOW)

    def test_budget_proof_expires_and_cannot_be_future_dated(self):
        for stamp in ("2026-10-19T06:54:59Z", "2026-10-19T07:00:01Z", "not-a-time"):
            with self.subTest(stamp=stamp):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(observed_at=stamp), NOW)

    def test_budget_used_units_are_integer_and_below_daily_limit(self):
        for units in (-1, 3000, 3001, True, 1.5, "12", None):
            with self.subTest(units=units):
                with self.assertRaises((RuntimeError, ValueError, TypeError)):
                    CHECK.budget_note(budget_fixture(used_search_units=units), NOW)

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
            budget_fixture(provider_day="2026-10-19", observed_at="2026-10-18T21:00:00Z"), now
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
        note = budget_fixture()
        note["issue_url"] = "https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/4217"
        self.responses = {
            "/git/ref/heads/main": {"object": {"sha": CONTROL}},
            "/git/ref/heads/release/search3-production-ready-v1": {"object": {"sha": CHECK.RELEASE}},
            "/contents/v2/api-v2.php?ref=" + CHECK.RELEASE: {"sha": CHECK.API_BLOB},
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
            mock.patch("datetime.datetime", FrozenDateTime),
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
        ):
            return CHECK.admission(self.identity)

    def test_reviewed_merge_current_heads_green_source_and_owner_budget_admit(self):
        self.assertEqual(self.admit()["used_search_units"], 12)

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

    def execute(self, responses=None, payload=None, raw=None):
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
        stdin = mock.Mock()
        stdin.buffer = io.BytesIO(data)
        output = io.StringIO()
        with (
            mock.patch("pathlib.Path.home", return_value=self.home),
            mock.patch("sys.stdin", stdin),
            mock.patch("datetime.datetime", FrozenDateTime),
            mock.patch("urllib.request.build_opener", return_value=opener),
            mock.patch("urllib.request.urlopen", side_effect=AssertionError("real HTTP forbidden")),
            mock.patch("time.sleep") as sleep,
            contextlib.redirect_stdout(output),
        ):
            exec(compile(CHECK.REMOTE, "<remote-live-check>", "exec"), {"POLICY": self.policy})
        result = json.loads(output.getvalue())
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

    def test_budget_is_rechecked_before_any_remote_reservation_or_http(self):
        payload = copy.deepcopy(self.payload)
        payload["budget"]["observed_at"] = "2026-10-19T06:54:59Z"
        result, sleep = self.execute(payload=payload)
        self.assertEqual(result["status"], "blocked_no_replay")
        self.assertEqual(result["reason"], "budget_stale")
        self.assertEqual(self.calls, [])
        self.assertFalse((self.private / CHECK.OPERATION).exists())
        sleep.assert_not_called()

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


if __name__ == "__main__":
    unittest.main()
