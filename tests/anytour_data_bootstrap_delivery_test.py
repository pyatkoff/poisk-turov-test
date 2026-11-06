"""Offline dependency-delivery regression; no SSH, schema or supplier execution.

The real workflow copy step runs against local transport doubles. Only the
delivered TOP500 reader runs in PHP, with a synthetic in-memory SQLite database.
BOOTSTRAP_WORKFLOW can select the pre-fix workflow for defect reproduction;
PHP_TEST_COMMAND can supply a PHP executable and its extension options.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import sys
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
REGISTRY = Path('app/integrations/anex-search-mapping-registry.php')
HELPER = Path('v2/data/top500-anex-priority-v1.php')
STEP = 'Copy data tooling and initialize schema'


def copy_step(path: Path) -> str:
    """Read this literal YAML block, without executing or expanding other steps."""
    lines = path.read_text().splitlines()
    matches = [i for i, line in enumerate(lines) if line.strip() == '- name: ' + STEP]
    if len(matches) != 1:
        raise ValueError('expected one bootstrap copy step')
    start = matches[0]
    step_indent = len(lines[start]) - len(lines[start].lstrip())
    for i in range(start + 1, len(lines)):
        line = lines[i]
        if line.strip() and len(line) - len(line.lstrip()) <= step_indent:
            break
        if line.strip() == 'run: |':
            indent = len(line) - len(line.lstrip()) + 2
            block = []
            for body in lines[i + 1:]:
                if body.strip() and len(body) - len(body.lstrip()) < indent:
                    break
                block.append(body[indent:] if body.strip() else '')
            return '\n'.join(block) + '\n'
    raise ValueError('bootstrap shell block missing')


def fake_transport(tool: str, args: list[str]) -> int:
    """Local copies only; remote shell commands are parsed or recorded, never run."""
    repo = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_REPO']).resolve()
    remote = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_REMOTE']).resolve()
    log = Path(os.environ['ANYTOUR_BOOTSTRAP_TEST_LOG'])

    def record(kind, **fields):
        with log.open('a') as output:
            output.write(json.dumps(dict(kind=kind, **fields)) + '\n')

    def remote_path(value):
        for prefix in ('$HOME/www/anytoour.ru', '~/www/anytoour.ru'):
            if value == prefix or value.startswith(prefix + '/'):
                suffix = value[len(prefix):].lstrip('/')
                if '..' in Path(suffix).parts or '\\' in suffix:
                    raise ValueError('unsafe fake remote path')
                target = (remote / suffix).resolve()
                if not target.is_relative_to(remote):
                    raise ValueError('fake remote escape')
                return target
        raise ValueError('unexpected fake remote path')

    recursive = False
    while args and args[0].startswith('-'):
        option = args.pop(0)
        if option in ('-i', '-o') and args:
            args.pop(0)
        elif option == '-r':
            recursive = True
        else:
            raise ValueError('unexpected transport option')
    if tool == 'ssh':
        if len(args) != 2 or args[0] != 'fixture@offline.invalid':
            raise ValueError('unexpected SSH destination')
        command = args[1]
        if '\n' in command:
            # This is the existing schema/doctor block. Retain it as data only.
            record('schema_block_intercepted', command=command)
            return 0
        tokens = shlex.split(command)
        while tokens:
            split = tokens.index('&&') if '&&' in tokens else len(tokens)
            part, tokens = tokens[:split], tokens[split + 1:]
            if part[:2] == ['mkdir', '-p'] and len(part) == 3:
                remote_path(part[2]).mkdir(parents=True, exist_ok=True)
            elif part[:2] == ['test', '-s'] and len(part) == 3:
                if not remote_path(part[2]).is_file() or remote_path(part[2]).stat().st_size == 0:
                    return 1
            else:
                raise ValueError('remote command not allowed by offline double')
        return 0
    if tool != 'scp' or len(args) != 2 or not args[1].startswith('fixture@offline.invalid:'):
        raise ValueError('unexpected SCP command')
    source = (repo / args[0]).resolve()
    if not source.is_relative_to(repo):
        raise ValueError('fake source escape')
    destination = remote_path(args[1].split(':', 1)[1])
    if not destination.is_dir():
        raise ValueError('destination directory was not created')
    if source == repo / REGISTRY and os.environ.get('ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE') == '1':
        record('dependency_copy_failed')
        return 42
    if not source.exists():
        record('source_missing', source=str(source.relative_to(repo)))
        return 43
    if source.is_dir():
        if not recursive or source != repo / 'v2/data':
            raise ValueError('unexpected recursive copy')
        target_registry = remote / REGISTRY
        record('data_copy', dependency_ready=target_registry.is_file()
               and target_registry.read_bytes() == (repo / REGISTRY).read_bytes())
        shutil.copytree(source, destination, dirs_exist_ok=True)
    else:
        if source != repo / REGISTRY:
            raise ValueError('unexpected dependency file')
        shutil.copy2(source, destination / source.name)
        record('dependency_copied')
    return 0


PHP_READER = r'''
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY);
CREATE TABLE anex_hotel_search_mappings(anex_hotel_id INTEGER, catalog_hotel_id INTEGER,
 match_class TEXT, approval_policy TEXT, enabled INTEGER, scope TEXT);
CREATE TABLE anex_hotel_decisions(anex_hotel_id INTEGER, decision_status TEXT, catalog_hotel_id INTEGER);
CREATE TABLE anex_review_pair_exclusions(anex_hotel_id INTEGER, catalog_hotel_id INTEGER);
INSERT INTO catalog_hotels VALUES (8401),(900076);
INSERT INTO anex_hotel_search_mappings VALUES
 (8401,900076,'exact','owner_exact_and_strong_20260908',1,'preview');");
require $argv[1] . '/data/top500-anex-priority-v1.php';
$registry = v2_top500_registry($pdo);
$resolved = v2_top500_resolution($registry);
echo json_encode(['loaded' => $registry !== null, 'state' => $resolved['state'],
 'ids' => $resolved['tourvisor_ids']], JSON_THROW_ON_ERROR);
'''


class BootstrapDeliveryTest(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory(prefix='anytour-bootstrap-offline-')
        self.addCleanup(temporary.cleanup)
        self.directory = Path(temporary.name)
        self.repo = self.directory / 'source'
        self.remote = self.directory / 'fake-host'
        self.log = self.directory / 'transport.jsonl'
        for path in (HELPER, REGISTRY):
            (self.repo / path).parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / path, self.repo / path)
        self.remote.mkdir()
        (self.remote / 'config.php').write_text('offline fixture, not a configuration\n')
        binaries = self.directory / 'bin'
        binaries.mkdir()
        for tool in ('ssh', 'scp'):
            shim = binaries / tool
            shim.write_text('#!/bin/bash\nexec ' + shlex.quote(sys.executable) + ' '
                            + shlex.quote(str(Path(__file__).resolve()))
                            + ' --fake-' + tool + ' "$@"\n')
            shim.chmod(0o700)
        self.env = dict(os.environ, PATH=str(binaries), HOST_VALUE='offline.invalid', USER_VALUE='fixture',
                        RUNNER_TEMP=str(self.directory), ANYTOUR_BOOTSTRAP_TEST_REPO=str(self.repo),
                        ANYTOUR_BOOTSTRAP_TEST_REMOTE=str(self.remote), ANYTOUR_BOOTSTRAP_TEST_LOG=str(self.log))
        self.env.pop('ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE', None)

    def deliver(self, *, fail_copy=False):
        env = dict(self.env)
        if fail_copy:
            env['ANYTOUR_BOOTSTRAP_TEST_COPY_FAILURE'] = '1'
        workflow = Path(os.environ.get('BOOTSTRAP_WORKFLOW', str(ROOT / '.github/workflows/bootstrap-anytour-data.yml')))
        return subprocess.run(['/bin/bash', '-c', copy_step(workflow)], cwd=self.repo,
                              env=env, text=True, capture_output=True, timeout=15)

    def events(self):
        return [json.loads(line) for line in self.log.read_text().splitlines()] if self.log.exists() else []

    def read_delivered_identity(self):
        command = shlex.split(os.environ.get('PHP_TEST_COMMAND', 'php'))
        result = subprocess.run(command + ['-r', PHP_READER, str(self.remote)],
                                text=True, capture_output=True, timeout=15)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return json.loads(result.stdout)

    def test_dependency_first_delivery_loads_registry(self):
        result = self.deliver()
        self.assertEqual(result.returncode, 0, result.stderr)
        events = self.events()
        copied = next(event for event in events if event['kind'] == 'data_copy')
        self.assertTrue(copied['dependency_ready'], 'TOP500 consumers arrived without the MATCH dependency')
        self.assertEqual((self.remote / REGISTRY).read_bytes(), (ROOT / REGISTRY).read_bytes())
        self.assertEqual((self.remote / 'data' / HELPER.name).read_bytes(), (ROOT / HELPER).read_bytes())
        self.assertEqual(self.read_delivered_identity(),
                         {'loaded': True, 'state': 'partially_resolved', 'ids': [900076]})
        self.assertEqual(sum(event['kind'] == 'schema_block_intercepted' for event in events), 1)

    def test_missing_dependency_stays_unresolved(self):
        (self.remote / 'data').mkdir()
        shutil.copy2(self.repo / HELPER, self.remote / 'data' / HELPER.name)
        self.assertEqual(self.read_delivered_identity(),
                         {'loaded': False, 'state': 'registry_unavailable', 'ids': []})
        self.assertEqual(self.events(), [])

    def test_dependency_failure_stops_data_and_schema(self):
        for failure in ('copy_failed', 'missing_source'):
            with self.subTest(failure=failure):
                self.log.unlink(missing_ok=True)
                if failure == 'missing_source':
                    (self.repo / REGISTRY).unlink()
                result = self.deliver(fail_copy=failure == 'copy_failed')
                self.assertNotEqual(result.returncode, 0)
                self.assertFalse((self.remote / 'data' / HELPER.name).exists())
                self.assertFalse(any(event['kind'] in ('data_copy', 'schema_block_intercepted')
                                     for event in self.events()))


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] in ('--fake-ssh', '--fake-scp'):
        try:
            sys.exit(fake_transport(sys.argv[1][7:], sys.argv[2:]))
        except (ValueError, OSError) as error:
            print('OFFLINE_TRANSPORT_REFUSED: ' + str(error), file=sys.stderr)
            sys.exit(44)
    unittest.main()
