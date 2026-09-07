#!/usr/bin/env python3
"""Restore prior successful AnyTour diagnostic artifact on the Actions runner."""
import json
import os
from pathlib import Path
import subprocess


def restore():
    repository = 'pyatkoff/poisk-turov-test'
    if os.environ.get('GITHUB_REPOSITORY') != repository:
        raise ValueError('wrong repository')
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    directory.mkdir(parents=True, exist_ok=True)
    runs = subprocess.run([
        'gh', 'run', 'list', '--repo', repository, '--workflow', 'anex-access-probe.yml',
        '--branch', 'feature/anex-search-adapter-20260907', '--status', 'success',
        '--limit', '1', '--json', 'databaseId'
    ], check=True, capture_output=True, text=True, timeout=60)
    previous = json.loads(runs.stdout)
    if len(previous) != 1 or type(previous[0].get('databaseId')) is not int:
        raise ValueError('previous successful run required')
    run_id = previous[0]['databaseId']
    subprocess.run([
        'gh', 'run', 'download', str(run_id), '--repo', repository,
        '--name', 'anex-hotel-catalog-match', '--dir', str(directory)
    ], check=True, capture_output=True, text=True, timeout=120)
    if not (directory / 'anex-hotel-geo-enrichment.json').is_file():
        raise ValueError('checkpoint missing')
    print(json.dumps({'checkpoint_restored_from_run': run_id}))


if __name__ == '__main__':
    try:
        restore()
    except (ValueError, KeyError, OSError, subprocess.SubprocessError):
        raise SystemExit('GEO_CHECKPOINT_RESTORE_FAILED: prior progress was not reset')
