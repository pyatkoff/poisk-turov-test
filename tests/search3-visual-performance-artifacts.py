#!/usr/bin/env python3
"""Materialize the two pinned read-only CI artifacts for the browser experiment.

GH_TOKEN is used only for the GitHub API request. Redirected byte transfer has
no Authorization header. Nothing is executed from the archive by this helper.
Local ZIP arguments make the same integrity validation reproducible offline.
"""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import tarfile
import urllib.error
import urllib.parse
import urllib.request
import zipfile


def check(condition, message):
    if not condition:
        raise ValueError(message)


def digest(data):
    return hashlib.sha256(data).hexdigest()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def download(repo, artifact_id):
    token = os.environ.get('GH_TOKEN')
    check(token, 'GH_TOKEN is required for artifact lookup')
    url = f'https://api.github.com/repos/{repo}/actions/artifacts/{artifact_id}/zip'
    req = urllib.request.Request(url, headers={
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28',
    })
    opener = urllib.request.build_opener(NoRedirect)
    try:
        with opener.open(req, timeout=60) as response:
            return response.read()
    except urllib.error.HTTPError as error:
        check(error.code in (301, 302, 303, 307, 308),
              f'artifact API returned HTTP {error.code}')
        destination = error.headers.get('Location', '')
        check(urllib.parse.urlparse(destination).scheme == 'https',
              'artifact redirect must be HTTPS')
    # Deliberately create a new request without the API token.
    with urllib.request.urlopen(destination, timeout=90) as response:
        return response.read()


def safe_path(name):
    p = PurePosixPath(name)
    check(not p.is_absolute() and '..' not in p.parts and '\\' not in name,
          'unsafe archive path')
    return p


def materialize(data, pin, output):
    check(digest(data) == pin['zip_sha256'], 'pinned ZIP digest mismatch')
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        check(archive.testzip() is None, 'ZIP CRC mismatch')
        check(len(archive.namelist()) == len(set(archive.namelist())),
              'duplicate ZIP member')
        for name in archive.namelist():
            safe_path(name)
        tar_bytes = archive.read('search3-whole-site-preview.tar.gz')
        sidecar = archive.read('search3-whole-site-preview.tar.gz.sha256').decode()
        check(sidecar.split()[0] == digest(tar_bytes), 'TAR sidecar mismatch')
        outer_manifest = archive.read('search3-site-release/control/manifest.json')
        outer_checksums = archive.read('search3-site-release/control/payload.sha256')
    with tarfile.open(fileobj=io.BytesIO(tar_bytes), mode='r:gz') as archive:
        members = archive.getmembers()
        names = [member.name for member in members]
        check(len(names) == len(set(names)), 'duplicate TAR member')
        for member in members:
            safe_path(member.name)
            check(member.isfile() or member.isdir(), 'nonregular TAR member')
        files = {member.name: member for member in members if member.isfile()}
        check(archive.extractfile(files['control/manifest.json']).read() == outer_manifest,
              'outer and TAR manifest differ')
        check(archive.extractfile(files['control/payload.sha256']).read() == outer_checksums,
              'outer and TAR checksum list differ')
        manifest = json.loads(outer_manifest)
        check(manifest['source_sha'] == pin['source_sha'], 'source SHA mismatch')
        check(manifest['source_tree_sha'] == pin['source_tree_sha'], 'source tree mismatch')
        expected = {row['path']: row for row in manifest['files']}
        check(len(expected) == manifest['file_count'] == pin['payload_count'],
              'payload count mismatch')
        actual = {name.removeprefix('payload/') for name in files if name.startswith('payload/')}
        check(actual == set(expected), 'payload path set mismatch')
        checksum_rows = {}
        for line in outer_checksums.decode().splitlines():
            hash_value, name = line.split(None, 1)
            name = name.lstrip(' *').removeprefix('./').removeprefix('payload/')
            check(name not in checksum_rows, 'duplicate payload checksum')
            checksum_rows[name] = hash_value
        check(set(checksum_rows) == actual, 'payload checksum path set mismatch')
        output.mkdir(parents=True, exist_ok=True)
        check(not any(output.iterdir()), 'output must be an empty directory')
        asset_root = output / 'v2'
        for name, row in expected.items():
            content = archive.extractfile(files['payload/' + name]).read()
            check(len(content) == row['size'] and digest(content) == row['sha256'],
                  'payload bytes mismatch: ' + name)
            check(checksum_rows[name] == row['sha256'], 'payload checksum mismatch: ' + name)
            destination = asset_root.joinpath(*safe_path(name).parts)
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_bytes(content)
    for owner in pin['compiled_owners']:
        content = (asset_root / owner['path']).read_bytes()
        check(len(content) == owner['bytes'] and digest(content) == owner['sha256'],
              'compiled owner mismatch: ' + owner['path'])
    check(digest((asset_root / 'visual-search/asset-size.json').read_bytes()) ==
          pin['asset_size_sha256'], 'asset-size receipt mismatch')
    check(digest((asset_root / 'visual-search/fixtures/live-search-2026-09-23.json').read_bytes()) ==
          pin['snapshot_sha256'], 'snapshot mismatch')
    receipt = {'status': 'PASS', 'artifact_id': pin['artifact_id'],
               'source_sha': pin['source_sha'], 'source_tree_sha': pin['source_tree_sha'],
               'zip_sha256': pin['zip_sha256'], 'tar_sha256': digest(tar_bytes),
               'payload_verified': len(expected), 'compiled_owners_verified': len(pin['compiled_owners']),
               'snapshot_sha256': pin['snapshot_sha256']}
    (output / 'verified-artifact.json').write_text(json.dumps(receipt, indent=2) + '\n')
    return receipt


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--baseline-zip', type=Path)
    parser.add_argument('--candidate-zip', type=Path)
    args = parser.parse_args()
    pins = json.loads(Path(__file__).with_name('search3-visual-performance-pins.json').read_text())
    receipts = {}
    for arm in ('baseline', 'candidate'):
        local_zip = getattr(args, arm + '_zip')
        data = local_zip.read_bytes() if local_zip else download(pins['repo'], pins['arms'][arm]['artifact_id'])
        receipts[arm] = materialize(data, pins['arms'][arm], args.output / arm)
    check(receipts['baseline']['snapshot_sha256'] == receipts['candidate']['snapshot_sha256'],
          'comparison snapshot mismatch')
    print(json.dumps({'status': 'PASS', 'arms': receipts}))


if __name__ == '__main__':
    main()
