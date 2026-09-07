#!/usr/bin/env python3
"""Restore the newest preserved AnyTour diagnostic artifact on the Actions runner."""
import json
import os
from pathlib import Path
import subprocess


def choose_checkpoint(run_ids, artifacts):
    candidates = [a for a in artifacts
                  if a.get('workflow_run', {}).get('id') in run_ids
                  and (a.get('name') == 'anex-hotel-catalog-match'
                       or a.get('name', '').startswith('anex-hotel-catalog-match-'))]
    if not candidates:
        raise ValueError('checkpoint required')
    latest = max(candidates, key=lambda a: a['id'])
    if latest.get('expired') is not False:
        raise ValueError('latest checkpoint expired')
    return latest


def restore():
    repository = 'pyatkoff/poisk-turov-test'
    if os.environ.get('GITHUB_REPOSITORY') != repository:
        raise ValueError('wrong repository')
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    directory.mkdir(parents=True, exist_ok=True)
    runs = subprocess.run([
        'gh', 'run', 'list', '--repo', repository, '--workflow', 'anex-access-probe.yml',
        '--branch', 'feature/anex-search-adapter-20260907',
        '--limit', '100', '--json', 'databaseId'
    ], check=True, capture_output=True, text=True, timeout=60)
    previous = json.loads(runs.stdout)
    if not previous or any(type(r.get('databaseId')) is not int for r in previous):
        raise ValueError('workflow history required')
    result = subprocess.run([
        'gh', 'api', 'repos/' + repository + '/actions/artifacts?per_page=100'
    ], check=True, capture_output=True, text=True, timeout=60)
    checkpoint = choose_checkpoint({r['databaseId'] for r in previous},
                                   json.loads(result.stdout)['artifacts'])
    run_id = checkpoint['workflow_run']['id']
    subprocess.run([
        'gh', 'run', 'download', str(run_id), '--repo', repository,
        '--name', checkpoint['name'], '--dir', str(directory)
    ], check=True, capture_output=True, text=True, timeout=120)
    if not (directory / 'anex-hotel-geo-enrichment.json').is_file():
        raise ValueError('checkpoint missing')
    print(json.dumps({'checkpoint_restored_from_run': run_id, 'checkpoint_artifact_id': checkpoint['id']}))


if __name__ == '__main__':
    try:
        restore()
    except (ValueError, KeyError, OSError, subprocess.SubprocessError):
        raise SystemExit('GEO_CHECKPOINT_RESTORE_FAILED: prior progress was not reset')
