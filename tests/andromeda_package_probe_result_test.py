"""One CLI lifecycle fixture, with supplier calls replaced by local stubs."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PROBE = Path('scripts/diagnostics/andromeda-package-probe.php')
STUB = r'''<?php
final class AnyTourAndromedaClient {
    public function __construct(...$args) {}
    public static function priceProbeParams() { return ['PAGE'=>1]; }
    private function call($method) {
        $dir = getenv('FIXTURE_OUT');
        $reservation = json_decode(file_get_contents($dir.'/reservation.json'), true);
        if (($reservation['state']??null)!=='reserved') throw new RuntimeException('NO_RESERVATION');
        file_put_contents($dir.'/calls.txt', $method."\n", FILE_APPEND);
    }
    public function login(...$args) { $this->call('login'); }
    public function price($params) {
        $this->call('price');
        return ['PRICES'=>[['id'=>'fixture-offer']], 'PAGES_COUNT'=>1];
    }
    public function package($id) {
        $this->call('broninit');
        if (getenv('FIXTURE_FAIL')==='1') throw new RuntimeException('FIXTURE_UNKNOWN');
        return ['claimDocument'=>[['catalogKey'=>'fixture-catalog']]];
    }
}
'''


def run_fixture(source, work, fail=False):
    app = work / 'app/integrations'
    app.mkdir(parents=True)
    (app / 'andromeda-client.php').write_text(STUB)
    (app / 'andromeda-transport.php').write_text(
        '<?php final class AnyTourAndromedaTransport { public function __construct(...$args) {} }')
    script = work / PROBE
    script.parent.mkdir(parents=True)
    shutil.copyfile(source, script)
    out = work / 'evidence'
    out.mkdir()
    env = dict(os.environ, ANDROMEDA_USERNAME='fixture-user', ANDROMEDA_PASSWORD='fixture-password',
               FIXTURE_OUT=str(out), FIXTURE_FAIL='1' if fail else '0')
    command = ['php', str(script), '--execute', str(out)]
    result = subprocess.run(command, env=env, capture_output=True, text=True)
    return result, out, command, env


with tempfile.TemporaryDirectory() as tmp:
    root = Path(tmp)
    old, old_out, _, _ = run_fixture(Path(sys.argv[1]) / PROBE, root / 'old')
    assert old.returncode == 1 and json.loads(old.stdout)['state'] == 'reserved'
    assert (old_out / 'private-package.json').exists(), 'baseline must reach successful capture'

    ok, out, command, env = run_fixture(ROOT / PROBE, root / 'fixed')
    report = json.loads(ok.stdout)
    assert ok.returncode == 0 and report['state'] == 'captured'
    assert report == json.loads((out / 'result.json').read_text())
    assert report['private_package_sha256'] == hashlib.sha256((out / 'private-package.json').read_bytes()).hexdigest()
    assert report['selection_enabled'] is False and report['id_equals_catalog_key'] is False
    calls = (out / 'calls.txt').read_text()
    assert calls.splitlines() == ['login', 'price', 'broninit']
    retry = subprocess.run(command, env=env, capture_output=True, text=True)
    assert retry.returncode != 0 and (out / 'calls.txt').read_text() == calls
    assert json.loads((out / 'result.json').read_text()) == report

    unknown, out, _, _ = run_fixture(ROOT / PROBE, root / 'unknown', fail=True)
    assert unknown.returncode == 1 and json.loads(unknown.stdout)['state'] == 'unknown'
    assert json.loads(unknown.stdout)['phase'] == 'broninit'
    assert not (out / 'private-package.json').exists()
print('Probe lifecycle: baseline reproduced; captured, unknown and replay guard passed (offline).')
