#!/usr/bin/env python3
"""Read-only CI assembly of the controller-pinned HOME LIVE artifact.

Uses the actual COUNTRY receipt and home_package admission for bytes only. It
does not invoke install/admission commands, SSH, production endpoints or writes.
All generated files stay in a new local test directory outside the checkout.
"""
import argparse
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import sys
import tempfile

sys.dont_write_bytecode = True  # Importing the controller must not write into the checkout.

FIXTURE_SOURCE = 'tests/search3-visual-live-fixture.cjs'
NEW_FORM_OWNERS = {'visual-search/public-config-v1.js', 'visual-search/public-bootstrap-v1.js',
                   'search3-canonical-profiles-v1.js', 'prototype-search/data.js'}


def need(condition, message):
    if not condition:
        raise RuntimeError('HOME_NATIVE_FIXTURE: ' + message)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def blob_sha(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def relative(name):
    need(isinstance(name, str) and name and '\\' not in name
         and not re.search(r'[\x00-\x1f\x7f]', name), 'invalid relative path')
    need(not PurePosixPath(name).is_absolute()
         and all(part not in ('', '.', '..') for part in name.split('/')), 'unbounded path: ' + name)
    return name


def unique_json(raw):
    def pairs(items):
        result = {}
        for key, value in items:
            need(key not in result, 'duplicate JSON member: ' + key)
            result[key] = value
        return result
    return json.loads(raw, object_pairs_hook=pairs)


def regular(path):
    need(path.is_file() and not path.is_symlink() and stat.S_ISREG(path.lstat().st_mode),
         'missing/non-regular file: ' + str(path))
    return path.read_bytes()


def source_record(data, mode='100644'):
    return {'size': len(data), 'mode': mode, 'sha256': sha(data), 'blob_sha': blob_sha(data)}


def load_controller(repo):
    directory = repo / 'scripts/deploy'
    sys.path.insert(0, str(directory))
    spec = importlib.util.spec_from_file_location('home_ci_controller', directory / 'search3_public_install.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def v2_tree(controller, tree_sha):
    tree = controller.api('/git/trees/' + tree_sha)
    need(tree.get('sha') == tree_sha and not tree.get('truncated'), 'tree identity/truncation')
    matches = [row for row in tree['tree'] if row['path'] == 'v2' and row['type'] == 'tree']
    need(len(matches) == 1, 'one pinned v2 source tree is required')
    subtree = controller.api('/git/trees/' + matches[0]['sha'] + '?recursive=1')
    need(subtree.get('sha') == matches[0]['sha'] and not subtree.get('truncated'), 'v2 tree identity/truncation')
    result = {}
    for row in subtree['tree']:
        name = 'v2/' + relative(row['path'])
        need(name not in result, 'duplicate source tree path')
        result[name] = row
    return result


def verify_site(controller, archive, manifest, payload, repo):
    need(manifest.get('schema_version') == 1 and manifest.get('deployable') is False
         and manifest.get('purpose') == 'local_public_live_install_candidate'
         and manifest.get('served_root') == 'site', 'not the native LIVE candidate schema')
    groups = manifest.get('files', {})
    need(set(groups) == {'added', 'replaced', 'protected'}, 'unexpected manifest groups')
    trees = {'source': v2_tree(controller, controller.HOME_TREE),
             'main': v2_tree(controller, manifest['main']['tree_sha'])}
    rows, contents = {}, {}
    for group, entries in groups.items():
        need(isinstance(entries, list), 'invalid manifest group')
        for row in entries:
            name = relative(row['path'])
            need(name not in rows, 'duplicate manifest path: ' + name)
            served = row['served']
            need(set(served) == {'size', 'mode', 'sha256'} and type(served['size']) is int
                 and 0 <= served['size'] <= controller.MAX_BYTES and served['mode'] in ('0644', '0755')
                 and re.fullmatch('[a-f0-9]{64}', served['sha256']), 'invalid served metadata: ' + name)
            data = archive.read('site/' + name)
            need(served['size'] == len(data) and served['sha256'] == sha(data), 'served bytes differ: ' + name)
            source_path = row['source_path']
            if source_path is None:
                need(group == 'added' and name == 'visual-search/public-build.json'
                     and unique_json(data) == {'schemaVersion': 1, 'profile': 'live', 'scenario': 'family'},
                     'unowned generated resource: ' + name)
            else:
                owner = trees['main' if group == 'protected' else 'source'].get(relative(source_path), {})
                source = row['source']
                need(owner.get('type') == 'blob' and owner.get('sha') == source.get('blob_sha')
                     and owner.get('size') == source.get('size') and owner.get('mode') == source.get('mode')
                     and re.fullmatch('[a-f0-9]{64}', source.get('sha256', '')),
                     'source record is outside its pinned Git tree: ' + name)
            rows[name], contents[name] = row, data
    actual = {name.removeprefix('site/') for name in archive.namelist()
              if name.startswith('site/') and not name.endswith('/')}
    need(actual == set(rows), 'complete site inventory differs from manifest')
    need(not {'config.php', 'site_conf.php'} & set(rows), 'installed/private configuration is forbidden')
    for name, encoded in payload.items():
        need(contents.get(name) == base64.b64decode(encoded, validate=True), 'controller package/site mismatch: ' + name)
    entry = controller.HOME_ENTRY
    need(rows['index.php']['source_path'] == entry['source_path'] == 'v2/home-entry-v1.php'
         and rows['index.php'] in groups['protected']
         and source_record(contents['index.php'])['blob_sha'] == entry['blob']
         and len(contents['index.php']) == entry['after']['size'] == 433,
         'root must remain the protected MAIN home entry alias')
    need(regular(repo / entry['source_path']) == contents['index.php'], 'checkout MAIN root owner drift')
    # The six shared MAIN sources must be the exact SOURCE blobs reviewed by
    # the controller; compiled asset bytes are independently checked above.
    for name in controller.HOME_SHARED_ORDER:
        target = controller.HOME_TARGETS[name]
        path = repo / relative(target['source_path'])
        mode = '100' + f'{stat.S_IMODE(path.lstat().st_mode):03o}'
        need(source_record(regular(path), mode) == target['source'], 'shared MAIN source drift: ' + name)
    for name, target in controller.HANDOFF_TARGETS.items():
        need({'state': 'present', **rows[name]['served']} == target['after'],
             'artifact does not retain the accepted COUNTRY parent: ' + name)
    for name, expected in controller.HOME_DEPENDENCIES.items():
        need({'state': 'present', **rows[name]['served']} == expected, 'HOME dependency drift: ' + name)
    return rows, contents


def prepare(args):
    repo = args.repo.resolve(strict=True)
    output = args.output.resolve()
    need(not output.exists() and not output.is_symlink(), 'output must be a new directory')
    need(not output.is_relative_to(repo), 'fixture must be outside the checkout')
    regular(repo / 'v2/index.php')  # A complete MAIN checkout is required for the retained legacy lane.
    controller = load_controller(repo)
    controller.home_pending_pins()
    need(len(controller.HOME_ORDER) == 16 and NEW_FORM_OWNERS <= set(controller.HOME_ORDER),
         'controller must pin the new 16-file HOME form; an old artifact cannot satisfy this gate')
    # Exactly the two controller-pinned artifacts. The controller's transport
    # authenticates only the GitHub API and verifies every downloaded ZIP hash.
    parent_raw = controller.download(controller.HOME_PARENT['artifact_id'], controller.HOME_PARENT['artifact_sha256'])
    home_raw = controller.download(controller.HOME_ARTIFACT, controller.HOME_ARTIFACT_SHA)
    parent = controller.home_parent_evidence(parent_raw)
    policy, payload = controller.home_package(home_raw, parent)
    with controller.zip_files(home_raw) as archive:
        manifest_raw = archive.read('manifest.json')
        need(sha(manifest_raw) == controller.HOME_MANIFEST_SHA, 'native manifest digest')
        manifest = unique_json(manifest_raw)
        rows, contents = verify_site(controller, archive, manifest, payload, repo)
        compiler_raw = archive.read('compiler-output.json')
        compiler = unique_json(compiler_raw)
        need(manifest['compilation']['manifest'] == 'compiler-output.json'
             and compiler['graphs']['public_live'] == manifest['graph']['initial'] == manifest['live_graph'],
             'native LIVE graph differs from compiler manifest')
    # Reuse one existing fictional transport from the same exact SOURCE SHA.
    helper = controller.api('/contents/' + FIXTURE_SOURCE + '?ref=' + controller.HOME_SOURCE)
    need(helper.get('type') == 'file' and helper.get('encoding') == 'base64'
         and helper.get('path') == FIXTURE_SOURCE, 'pinned SOURCE transport is unavailable')
    helper_raw = base64.b64decode(helper['content'].replace('\n', ''), validate=True)
    need(0 < len(helper_raw) <= controller.MAX_BYTES and len(helper_raw) == helper['size']
         and blob_sha(helper_raw) == helper['sha'], 'pinned SOURCE transport bytes')
    receipt = {'schema_version': 1, 'scope': 'CI native HOME/Country fixture; no production operation',
               'source_sha': controller.HOME_SOURCE, 'source_tree': controller.HOME_TREE,
               'main_sha': controller.HOME_MAIN_BASE, 'home_artifact_id': controller.HOME_ARTIFACT,
               'home_artifact_sha256': controller.HOME_ARTIFACT_SHA,
               'home_manifest_sha256': controller.HOME_MANIFEST_SHA,
               'parent_artifact_id': controller.HOME_PARENT['artifact_id'],
               'parent_artifact_sha256': controller.HOME_PARENT['artifact_sha256'],
               'parent_receipt_sha256': controller.HOME_PARENT['receipt_sha256'],
               'policy_sha256': policy['manifest'], 'compiler_manifest_sha256': sha(compiler_raw),
               'controller_sha256': sha(regular(repo / 'scripts/deploy/search3_public_install.py')),
               'transport': {'source_path': FIXTURE_SOURCE, 'source_sha': controller.HOME_SOURCE,
                             'blob_sha': helper['sha'], 'sha256': sha(helper_raw)},
               'site_files': {name: row['served'] for name, row in sorted(rows.items())},
               'production_operations': 0, 'supplier_http': 0, 'lead_http': 0}
    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='home-native-fixture-', dir=output.parent) as temporary:
        staged = Path(temporary) / 'fixture'
        site = staged / 'site'
        for name, data in contents.items():
            path = site / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(data)
            path.chmod(int(rows[name]['served']['mode'], 8))
        (staged / 'transport.cjs').write_bytes(helper_raw)
        (staged / 'manifest.json').write_bytes(manifest_raw)
        (staged / 'receipt.json').write_text(json.dumps(receipt, sort_keys=True, indent=2) + '\n')
        # Separate current MAIN source for existing legacy /index.php and content
        # assertions. No canonical wrapper is inserted into this copy.
        legacy = staged / 'legacy'
        for path in (repo / 'v2').rglob('*'):
            need(not path.is_symlink(), 'symlink in current MAIN source')
        shutil.copytree(repo / 'v2', legacy)
        need(not (legacy / 'config.php').exists() and not (legacy / 'site_conf.php').exists(),
             'legacy source contains installed configuration')
        logo = legacy / 'images/logo.svg'
        if logo.exists():
            need(regular(logo) == contents['images/logo.svg'], 'current MAIN logo drift')
        else:
            logo.parent.mkdir(parents=True, exist_ok=True)
            logo.write_bytes(contents['images/logo.svg'])
        staged.rename(output)
    print(json.dumps({'status': 'verified_fixture_ready', 'output': str(output),
                      'home_artifact_id': controller.HOME_ARTIFACT, 'source_sha': controller.HOME_SOURCE,
                      'files': len(rows), 'production_operations': 0}, sort_keys=True))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--output', type=Path, required=True)
    options = parser.parse_args()
    try:
        prepare(options)
    except (RuntimeError, OSError, ValueError, KeyError, TypeError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
