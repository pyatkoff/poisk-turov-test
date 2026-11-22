#!/usr/bin/env python3
"""Exercise the workflow's actual public inspector with isolated HTTP responses."""

import base64
import contextlib
import http.client
import io
import json
import os
from pathlib import Path
import struct
import textwrap
import unittest
from unittest.mock import patch
import urllib.error
import urllib.parse


WORKFLOW = Path(__file__).resolve().parents[1] / '.github/workflows/deploy-favicon.yml'
PAGES = ['/', '/poisk-turov/', '/country/turkey/kemer/november/']
ASSETS = ['/favicon.ico', '/favicon.php?v=20260903', '/favicon.png', '/favicon.svg']
PNG = b'\x89PNG\r\n\x1a\n' + b'\x00\x00\x00\rIHDR' + struct.pack('>II', 32, 32) + b'owner artwork'
SVG = b'<svg><image href="data:image/png;base64,' + base64.b64encode(PNG) + b'"/></svg>'
ICO = struct.pack('<HHH', 0, 1, 1) + bytes(16) + PNG
HTML = b'<html><head><link rel="icon" href="/favicon.ico?v=abc123"><link rel="icon" href="/favicon.png?v=abc123"></head><body>page</body></html>'


class Response:
    def __init__(self, body, status=200, error=None):
        self.status = status
        self.headers = {'Content-Type': 'application/octet-stream'}
        self.body = body
        self.error = error

    def __enter__(self):
        return self

    def __exit__(self, *args):
        return False

    def read(self):
        if self.error is not None:
            raise self.error
        return self.body


class PublicFaviconInspectionTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        workflow = WORKFLOW.read_text(encoding='utf-8')
        step = workflow.split('      - name: Inspect public page declarations and favicon responses\n', 1)[1]
        code = step.split("          python3 - <<'PY'\n", 1)[1].split('\n          PY\n', 1)[0]
        cls.inspector = compile(textwrap.dedent(code), str(WORKFLOW) + ':public-http', 'exec')

    def run_inspector(self, overrides=None, strict=False):
        overrides = overrides or {}
        requested = []
        output = io.StringIO()

        def urlopen(request, timeout):
            url = urllib.parse.urlsplit(request.full_url)
            self.assertEqual((url.scheme, url.netloc), ('https', 'anytoour.ru'))
            self.assertEqual(timeout, 20)
            self.assertEqual(dict((k.lower(), v) for k, v in request.header_items()), {
                'cache-control': 'no-cache', 'user-agent': 'AnyTour-Favicon-Check',
            })
            path = url.path + ('?' + url.query if url.query else '')
            requested.append(path)
            if path in overrides:
                result = overrides[path]
                if isinstance(result, BaseException):
                    raise result
                return result
            if path in PAGES:
                return Response(HTML)
            suffix = url.path.rsplit('.', 1)[-1]
            self.assertIn(path, ASSETS + ['/favicon.ico?v=abc123', '/favicon.png?v=abc123'])
            return Response(SVG if suffix == 'svg' else ICO if suffix == 'ico' else PNG)

        def read_bytes(path):
            self.assertEqual(path, Path('v2/favicon.svg'))
            return SVG

        exit_code = 0
        with patch('urllib.request.urlopen', side_effect=urlopen), \
                patch.object(Path, 'read_bytes', read_bytes), \
                patch.dict(os.environ, {'REQUIRE_RESTORED_HEAD': '1' if strict else '0'}), \
                contextlib.redirect_stdout(output):
            try:
                exec(self.inspector, {'__name__': '__main__'})
            except SystemExit as error:
                exit_code = error.code
        rows = {}
        for line in output.getvalue().splitlines():
            label, payload = line.split(' ', 1)
            rows.setdefault(label, []).append(json.loads(payload))
        self.assertEqual(len(rows.get('FAVICON_PUBLIC_RESULT', [])), 1)
        return exit_code, requested, rows

    def assert_failed_transport(self, path, error, phase, response=None):
        code, requested, rows = self.run_inspector({path: response if response is not None else error})
        self.assertEqual(code, 1)
        self.assertEqual(requested, PAGES + ASSETS, 'All fixed paths must run once, without retries')
        result = rows['FAVICON_PUBLIC_RESULT'][0]
        self.assertFalse(result['ok'])
        self.assertIn(path + ' transport error', result['failures'])
        self.assertEqual(len(rows['FAVICON_TRANSPORT_ERROR']), 1)
        diagnostic = rows['FAVICON_TRANSPORT_ERROR'][0]
        self.assertEqual(diagnostic['path'], path)
        self.assertEqual(diagnostic['phase'], phase)
        self.assertEqual(diagnostic['type'], type(error).__name__)
        self.assertEqual(diagnostic['message'], str(error))
        self.assertIsInstance(diagnostic['elapsed_seconds'], (float, int))
        self.assertGreaterEqual(diagnostic['elapsed_seconds'], 0)
        self.assertEqual([row['path'] for row in rows['FAVICON_ASSET']], [p for p in ASSETS if p != path])

    def test_request_errors_do_not_skip_assets_or_pass_gate(self):
        for error in [TimeoutError('timed out'), urllib.error.URLError('unreachable'),
                      http.client.RemoteDisconnected('no HTTP response'), ConnectionResetError('reset')]:
            with self.subTest(error=type(error).__name__):
                self.assert_failed_transport(PAGES[-1], error, 'request')

    def test_body_errors_keep_remaining_pages_and_assets(self):
        for path, error in [('/', TimeoutError('body timed out')),
                            ('/favicon.ico', http.client.IncompleteRead(b'partial', 20))]:
            with self.subTest(path=path):
                self.assert_failed_transport(path, error, 'body', Response(b'', error=error))

    def test_http_error_still_fails_status_and_checks_assets(self):
        error = urllib.error.HTTPError('https://anytoour.ru' + PAGES[-1], 503, 'Unavailable', {}, io.BytesIO(HTML))
        code, requested, rows = self.run_inspector({PAGES[-1]: error})
        self.assertEqual(code, 1)
        self.assertEqual(requested, PAGES + ASSETS)
        self.assertNotIn('FAVICON_TRANSPORT_ERROR', rows)
        self.assertEqual(rows['FAVICON_PAGE'][-1]['status'], 503)
        self.assertIn(PAGES[-1], rows['FAVICON_PUBLIC_RESULT'][0]['failures'])

    def test_success_and_existing_artwork_and_head_failures(self):
        for strict in [False, True]:
            with self.subTest(strict=strict):
                code, requested, rows = self.run_inspector(strict=strict)
                self.assertEqual(code, 0)
                self.assertTrue(rows['FAVICON_PUBLIC_RESULT'][0]['ok'])
                self.assertEqual(rows['FAVICON_PUBLIC_RESULT'][0]['restored_head_required'], strict)
                expected_assets = ASSETS + (['/favicon.ico?v=abc123', '/favicon.png?v=abc123'] if strict else [])
                self.assertEqual(requested, PAGES + sorted(expected_assets))
        code, requested, rows = self.run_inspector({'/favicon.png': Response(b'wrong artwork')})
        self.assertEqual(code, 1)
        self.assertEqual(requested, PAGES + ASSETS)
        self.assertIn('/favicon.png', rows['FAVICON_PUBLIC_RESULT'][0]['failures'])
        code, requested, rows = self.run_inspector({'/': Response(b'<html><head></head></html>')}, strict=True)
        self.assertEqual(code, 1)
        self.assertIn('/ missing static png', rows['FAVICON_PUBLIC_RESULT'][0]['failures'])
        self.assertIn('/ missing static ico', rows['FAVICON_PUBLIC_RESULT'][0]['failures'])


if __name__ == '__main__':
    unittest.main()
