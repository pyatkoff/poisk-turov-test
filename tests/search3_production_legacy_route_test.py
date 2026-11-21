#!/usr/bin/env python3
"""Exercise the legacy entry with the standalone publisher's real file layout.

Requires a real PHP CLI. Does not emulate PHP, start a server, make network
requests, load production credentials, or send a lead. The maintained-search
fixture exposes the selected entry and unchanged request/configuration context.
HTTP response headers need a separate HTTP check; this CLI test does not claim it.
"""

import argparse
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


PHP = shutil.which("php")
ROUTE = Path(__file__).resolve().parents[1] / "v2/poisk-turov-old/index.php"
QUERY = (
    "from=1&country=4&dateFrom=2026-12-10&dateTo=2026-12-17"
    "&daysFrom=7&daysTill=10&count_people=2&child_age%5B%5D=0"
    "&child_age%5B%5D=8&hotel=3678&food=7&stars=5"
    "&price_from=186400&price_till=190000"
)
EXPECTED_QUERY = {
    "from": "1", "country": "4", "dateFrom": "2026-12-10",
    "dateTo": "2026-12-17", "daysFrom": "7", "daysTill": "10",
    "count_people": "2", "child_age": ["0", "8"], "hotel": "3678",
    "food": "7", "stars": "5", "price_from": "186400", "price_till": "190000",
}
LOCAL_CONFIG = "<?php return ['marker' => 'server-local', 'api' => '/api-v2.php', 'lead' => '/lead-adapter-v2.php'];\n"
SEARCH = """<?php
$local = require __DIR__ . '/config.php';
echo json_encode([
    'entry' => 'maintained-search',
    'filename' => basename(__FILE__),
    'query' => $_GET,
    'request_uri' => $_SERVER['REQUEST_URI'],
    'config' => $local,
    'presentation' => V2_SEARCH3_PRESENTATION,
    'legacy' => V2_LEGACY_SEARCH,
    'force_search' => V2_FORCE_SEARCH_PAGE,
    'public_base' => V2_PUBLIC_BASE_PATH,
    'canonical' => V2_CANONICAL_PATH,
], JSON_THROW_ON_ERROR);
"""
HOME = "<?php echo json_encode(['entry' => 'homepage'], JSON_THROW_ON_ERROR);\n"
BOOTSTRAP = """
parse_str($argv[2], $_GET);
$_SERVER['HTTP_HOST'] = 'anytoour.ru';
$_SERVER['DOCUMENT_ROOT'] = $argv[3];
$_SERVER['SCRIPT_NAME'] = '/poisk-turov-old/index.php';
$_SERVER['REQUEST_URI'] = '/poisk-turov-old/?' . $argv[2];
require $argv[1];
"""


@unittest.skipUnless(PHP, "A real PHP CLI is unavailable; routing runtime is not verified")
class ProductionLegacyRoute(unittest.TestCase):
    def check_layout(self, deployed: bool) -> None:
        with tempfile.TemporaryDirectory(prefix="search3-legacy-route-") as temp:
            root = Path(temp)
            route = root / "poisk-turov-old/index.php"
            route.parent.mkdir()
            shutil.copyfile(ROUTE, route)
            (root / "config.php").write_text(LOCAL_CONFIG, encoding="utf-8")
            (root / "index.php").write_text(SEARCH, encoding="utf-8")
            (root / "home-entry-v1.php").write_text(HOME, encoding="utf-8")
            config_before = hashlib.sha256((root / "config.php").read_bytes()).hexdigest()
            if deployed:
                # Same alias-copy order as main's deploy-anytoour.yml.
                shutil.copyfile(root / "index.php", root / "search-page-v2.php")
                shutil.copyfile(root / "home-entry-v1.php", root / "index.php")
            result = subprocess.run(
                [PHP, "-d", "allow_url_fopen=0", "-d", "allow_url_include=0",
                 "-d", "display_errors=stderr", "-r", BOOTSTRAP,
                 str(route), QUERY, str(root)],
                capture_output=True, text=True, timeout=10, check=False,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(result.stderr, "")
            observed = json.loads(result.stdout)
            self.assertEqual(observed.get("entry"), "maintained-search", observed)
            self.assertEqual(observed["filename"], "search-page-v2.php" if deployed else "index.php")
            self.assertEqual(observed["query"], EXPECTED_QUERY)
            self.assertEqual(observed["request_uri"], "/poisk-turov-old/?" + QUERY)
            self.assertEqual(observed["config"], {
                "marker": "server-local", "api": "/api-v2.php", "lead": "/lead-adapter-v2.php",
            })
            self.assertIs(observed["presentation"], False)
            self.assertIs(observed["legacy"], True)
            self.assertIs(observed["force_search"], True)
            self.assertEqual(observed["public_base"], "")
            self.assertEqual(observed["canonical"], "/poisk-turov/")
            self.assertEqual(hashlib.sha256((root / "config.php").read_bytes()).hexdigest(), config_before)

    def test_standalone_alias_copy_keeps_maintained_search(self) -> None:
        self.check_layout(deployed=True)

    def test_source_checkout_falls_back_to_maintained_search(self) -> None:
        self.check_layout(deployed=False)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--route", type=Path, default=ROUTE,
                        help="Exact route source to check, including an unpatched regression baseline")
    options = parser.parse_args()
    ROUTE = options.route.resolve()
    if not ROUTE.is_file():
        parser.error("route source is missing")
    result = unittest.main(argv=[__file__], verbosity=2, exit=False).result
    # An unavailable interpreter must not become a green runtime gate.
    raise SystemExit(77 if result.skipped else (0 if result.wasSuccessful() else 1))
