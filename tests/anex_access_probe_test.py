#!/usr/bin/env python3
"""Offline checks: never contact ANEX or an SSH server."""

import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
import subprocess
import unittest
from unittest import mock
import urllib.error
import urllib.parse


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_probe", ROOT / "scripts/diagnostics/anex_access_probe.py")
probe = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(probe)

API_TOKEN = "test-api-secret-DO-NOT-LOG"
REFERENCE_TOKEN = "test-reference-secret-DO-NOT-LOG"
KEY = "test-ssh-key-DO-NOT-LOG"
TOKENS = {"ANEX_API_TOKEN": API_TOKEN, "ANEX_REFERENCE_TOKEN": REFERENCE_TOKEN}
ENV = dict(TOKENS, ANYTOOUR_DEPLOY_SSH_KEY=KEY,
           ANYTOOUR_DEPLOY_HOST="anytour-test.invalid",
           ANYTOOUR_DEPLOY_USER="anytour-test")
STAMP = "0x0000000000000042"


def response(body):
    return {"status": "ok", "http_status": 200}, body


def xml(inner):
    return ("<Response><Data>" + inner + "</Data></Response>").encode()


class RequestBoundaryTest(unittest.TestCase):
    def test_api_credential_is_form_body_and_reference_uses_fixed_origin(self):
        opener = mock.MagicMock()
        reply = opener.open.return_value.__enter__.return_value
        reply.status = 200
        reply.read.return_value = b"{}"
        with mock.patch.object(probe.urllib.request, "build_opener", return_value=opener) as build:
            probe.request({"samo_action": "api", "version": "1.0", "type": "json",
                           "action": "SearchTour_TOWNFROMS"}, API_TOKEN, post=True)
            request = opener.open.call_args.args[0]
            self.assertEqual(request.full_url, probe.ENDPOINT + "?samo_action=api&version=1.0")
            self.assertEqual(request.get_method(), "POST")
            self.assertEqual(urllib.parse.parse_qs(request.data.decode())["oauth_token"], [API_TOKEN])
            self.assertNotIn(API_TOKEN, request.full_url)
            self.assertTrue(any(isinstance(handler, probe.NoRedirect) for handler in build.call_args.args))
            self.assertEqual(opener.open.call_args.kwargs["timeout"], probe.TIMEOUT)
            reply.read.assert_called_with(probe.BODY_LIMIT + 1)
            probe.request({"samo_action": "reference", "type": "currentstamp"}, REFERENCE_TOKEN)
            request = opener.open.call_args.args[0]
            self.assertEqual(request.get_method(), "GET")
            parsed = urllib.parse.urlsplit(request.full_url)
            self.assertEqual((parsed.scheme, parsed.netloc, parsed.path),
                             ("https", "parser.anextour.ru", "/export/default.php"))
            self.assertEqual(urllib.parse.parse_qs(parsed.query)["oauth_token"], [REFERENCE_TOKEN])

    def test_hotel_details_rate_floor(self):
        probe._next_hotel_details_request_at = 0.0
        with mock.patch.object(probe.time, "monotonic", side_effect=[100.0, 100.25]), \
                mock.patch.object(probe.time, "sleep") as sleep:
            probe.wait_for_hotel_details_slot()
            probe.wait_for_hotel_details_slot()
        sleep.assert_called_once()
        self.assertAlmostEqual(sleep.call_args.args[0], 0.8)
        self.assertAlmostEqual(probe._next_hotel_details_request_at, 102.1)

    def test_hotel_details_api_waits_before_transport(self):
        checks = []
        payload = json.dumps({"Hotels_DETAILS": {"id": 469, "name": "Hotel"}}).encode()
        with mock.patch.object(probe, "wait_for_hotel_details_slot") as wait, \
                mock.patch.object(probe, "request", return_value=response(payload)) as request:
            result = probe.api_data(API_TOKEN, "Hotels_DETAILS", {"HOTELINC": 469}, checks)
        wait.assert_called_once_with()
        request.assert_called_once()
        self.assertEqual(result["id"], 469)
        self.assertEqual(checks[0]["check"], "api_hotel_details")

    def test_redirects_never_forward_credentials(self):
        self.assertIsNone(probe.NoRedirect().redirect_request(
            None, None, 302, "Found", {}, "https://elsewhere.invalid/?oauth_token=" + REFERENCE_TOKEN))

    def test_http_error_and_oversized_payload_do_not_escape(self):
        opener = mock.MagicMock()
        opener.open.side_effect = urllib.error.HTTPError(
            probe.ENDPOINT + "?oauth_token=" + API_TOKEN, 403, API_TOKEN, {}, io.BytesIO(API_TOKEN.encode()))
        with mock.patch.object(probe.urllib.request, "build_opener", return_value=opener):
            result, body = probe.request({}, API_TOKEN)
            self.assertEqual(result, {"status": "http_error", "http_status": 403})
            self.assertIsNone(body)
            self.assertNotIn(API_TOKEN, json.dumps(result))
            opener.open.side_effect = None
            opener.open.return_value.__enter__.return_value.read.return_value = b"x" * (probe.BODY_LIMIT + 1)
            self.assertEqual(probe.request({}, API_TOKEN), ({"status": "response_too_large"}, None))


class SupplierResponseTest(unittest.TestCase):
    def test_both_tokens_four_read_requests_and_counts_only(self):
        responses = [
            response(json.dumps({"SearchTour_TOWNFROMS": [{"id": 1, "name": "private city"}]}).encode()),
            response(xml('<currentstamp stamp="' + STAMP + '"/>')),
            response(xml('<state inc="1" name="private country"/><state inc="2"/>')),
            response(xml('<townstate town="1" state="2"/>')),
        ]
        with mock.patch.object(probe, "request", side_effect=responses) as request:
            result = probe.clean_report(probe.remote_probe(TOKENS))
        self.assertTrue(result["ok"])
        self.assertEqual([row["count"] for row in result["checks"]], [1, 1, 2, 1])
        self.assertEqual(request.call_count, 4)
        self.assertEqual([call.args[1] for call in request.call_args_list],
                         [API_TOKEN, REFERENCE_TOKEN, REFERENCE_TOKEN, REFERENCE_TOKEN])
        self.assertEqual(request.call_args_list[0].args[0]["action"], "SearchTour_TOWNFROMS")
        self.assertFalse(request.call_args_list[0].kwargs.get("post", False))
        self.assertEqual(request.call_args_list[2].args[0],
                         {"samo_action": "reference", "type": "state",
                          "laststamp": "0x0000000000000000", "delstamp": STAMP})
        for private in (API_TOKEN, REFERENCE_TOKEN, "private city", "private country", STAMP):
            self.assertNotIn(private, json.dumps(result))

    def test_reference_failure_skips_state_page_and_fails_whole_probe(self):
        with mock.patch.object(probe, "request", side_effect=[
            response(b'{"SearchTour_TOWNFROMS": []}'),
            response(b'<Response><Error message="secret supplier text"/></Response>'),
            response(xml('<townstate town="1" state="2"/>')),
        ]) as request:
            result = probe.remote_probe(TOKENS)
        self.assertFalse(result["ok"])
        self.assertEqual(request.call_count, 3)
        self.assertEqual(result["checks"][1]["status"], "supplier_error")
        self.assertEqual(result["checks"][2]["status"], "skipped")
        self.assertNotIn("secret supplier text", json.dumps(result))

    def test_api_errors_and_non_api_pages_fail_without_raw_response(self):
        for body, status in [(b'{"error":"private"}', "supplier_error"),
                             (b'<html>private</html>', "invalid_response"),
                             (b'{"SearchTour_TOWNFROMS":[{"wrong":"private"}]}', "invalid_response")]:
            with self.subTest(body=body), mock.patch.object(probe, "request", return_value=response(body)):
                result = probe.api_check(API_TOKEN)
            self.assertEqual(result["status"], status)
            self.assertNotIn("private", json.dumps(result))

    def test_xml_requires_valid_stamp_record_shape_and_no_entities(self):
        examples = [("currentstamp", xml('<currentstamp stamp="nonsense"/>')),
                    ("state", xml('<state name="private"/>')),
                    ("townstate", xml('<townstate town="1"/>')),
                    ("state", b'<!DOCTYPE x [<!ENTITY x "private">]><Response><Data>&x;</Data></Response>'),
                    ("state", b'<html>private</html>')]
        for kind, body in examples:
            with self.subTest(kind=kind, body=body), mock.patch.object(probe, "request", return_value=response(body)):
                result, stamp = probe.reference_check(REFERENCE_TOKEN, kind)
            self.assertEqual(result["status"], "invalid_response")
            self.assertIsNone(stamp)


class ProcessBoundaryTest(unittest.TestCase):
    def test_report_allowlist_drops_untrusted_content(self):
        result = probe.clean_report({"ok": True, "token": API_TOKEN, "checks": [
            {"check": "api_townfroms", "status": "http_error", "http_status": 403,
             "count": API_TOKEN, "elapsed_ms": True, "message": API_TOKEN}]})
        self.assertEqual(result, {"ok": False, "checks": [
            {"check": "api_townfroms", "status": "http_error", "http_status": 403}]})
        with self.assertRaises(ValueError):
            probe.clean_report({"checks": [{"check": "api_townfroms", "status": API_TOKEN}]})

    def test_missing_configuration_never_starts_ssh_or_network(self):
        with mock.patch.dict(os.environ, {}, clear=True), mock.patch.object(probe.subprocess, "run") as run:
            output = io.StringIO()
            with contextlib.redirect_stdout(output):
                result = probe.ssh_probe()
            run.assert_not_called()
        self.assertEqual(result["checks"][0]["status"], "missing_secret")
        self.assertIn("ANEX_API_TOKEN", output.getvalue())
        with mock.patch.object(probe, "request") as request:
            result = probe.remote_probe({"ANEX_API_TOKEN": API_TOKEN})
            request.assert_not_called()
        self.assertFalse(result["ok"])

    def test_ssh_credentials_only_stdin_or_private_runner_file(self):
        report = {"ok": True, "checks": [{"check": "api_townfroms", "status": "ok", "count": 1}]}
        captured_key_path = []

        def run(command, **kwargs):
            for secret in (API_TOKEN, REFERENCE_TOKEN, KEY):
                self.assertNotIn(secret, " ".join(command))
                self.assertNotIn(secret, json.dumps(kwargs["env"]))
            self.assertEqual(json.loads(kwargs["input"]), TOKENS)
            key = Path(command[command.index("-i") + 1])
            captured_key_path.append(key)
            self.assertEqual(stat.S_IMODE(key.stat().st_mode), 0o600)
            self.assertEqual(key.read_text(), KEY + "\n")
            self.assertTrue(command[-1].startswith('cd "$HOME/www/anytoour.ru" && python3 -B -c '))
            self.assertTrue(command[-1].endswith(" --remote"))
            self.assertEqual(kwargs["timeout"], 110)
            self.assertTrue(kwargs["capture_output"])
            return subprocess.CompletedProcess(command, 0, json.dumps(report), "")

        with mock.patch.dict(os.environ, ENV, clear=True), mock.patch.object(probe.subprocess, "run", side_effect=run):
            self.assertTrue(probe.ssh_probe()["ok"])
        self.assertFalse(captured_key_path[0].exists())

    def test_ssh_failure_stderr_and_top_level_exception_are_sanitized(self):
        with mock.patch.dict(os.environ, ENV, clear=True), mock.patch.object(probe.subprocess, "run", return_value=
                subprocess.CompletedProcess([], 255, "", "Permission denied " + API_TOKEN)):
            result = probe.ssh_probe()
        self.assertEqual(result["checks"][0]["status"], "ssh_auth_failed")
        self.assertNotIn(API_TOKEN, json.dumps(result))
        output = io.StringIO()
        with mock.patch.object(probe.sys, "argv", ["probe"]), mock.patch.object(probe, "ssh_probe", side_effect=ValueError(API_TOKEN)), contextlib.redirect_stdout(output):
            self.assertEqual(probe.main(), 1)
        self.assertEqual(json.loads(output.getvalue())["checks"][0]["status"], "unexpected_probe_failure")
        self.assertNotIn(API_TOKEN, output.getvalue())

    def test_ssh_failure_cannot_be_overridden_by_successful_stdout(self):
        report = {"ok": True, "checks": [{"check": "api_townfroms", "status": "ok", "count": 1}]}
        with mock.patch.dict(os.environ, ENV, clear=True), mock.patch.object(probe.subprocess, "run", return_value=
                subprocess.CompletedProcess([], 255, json.dumps(report), API_TOKEN)):
            result = probe.ssh_probe()
        self.assertFalse(result["ok"])
        self.assertEqual(result["checks"][0]["status"], "ssh_failed")
        self.assertNotIn(API_TOKEN, json.dumps(result))


if __name__ == "__main__":
    unittest.main()
