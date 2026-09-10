"""The retired first-PRICE CLI must refuse before includes or checkpoint changes.

The workflow's historical checkout argument is intentionally not executed.
Actual retained capture/readback/unknown lifecycle coverage lives in the existing
saved-package runtime job; this regression prevents restoring the old algorithm.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PROBE = Path('scripts/diagnostics/andromeda-package-probe.php')
POISON = "<?php file_put_contents(getenv('FIXTURE_LOADED'), 'loaded'); throw new RuntimeException('LEGACY_DEPENDENCY_LOADED');"


def snapshot(root: Path) -> dict[str, bytes]:
    return {str(p.relative_to(root)): p.read_bytes() for p in root.rglob('*') if p.is_file()}


def main() -> None:
    cases = 0
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory) / 'anytoour.ru'
        app = root / 'app/integrations'
        app.mkdir(parents=True)
        for name in ('andromeda-client.php', 'andromeda-transport.php'):
            (app / name).write_text(POISON)
        script = root / PROBE
        script.parent.mkdir(parents=True)
        shutil.copyfile(ROOT / PROBE, script)
        env = dict(os.environ, ANDROMEDA_USERNAME='fixture-user',
                   ANDROMEDA_PASSWORD='fixture-password', FIXTURE_LOADED=str(root / 'loaded'))
        for state in ('absent', 'reserved', 'unknown', 'captured'):
            out = root / ('evidence-' + state)
            if state != 'absent':
                out.mkdir()
                (out / 'reservation.json').write_text(json.dumps({'state': state}))
                (out / 'result.json').write_text(json.dumps({'state': state, 'automatic_retry': False}))
                (out / 'private-package.json').write_text('{"private":"fixture-only"}')
            before = snapshot(root)
            # Repetition is a local refusal test, not replay of any supplier action.
            for _ in range(2):
                result = subprocess.run(['php', str(script), '--execute', str(out)], cwd=root,
                    env=env, input='not-a-selected-offer', capture_output=True, text=True, timeout=5)
                expected = {'status': 'blocked', 'reason': 'operation_refused', 'automatic_retry': False,
                            'identity_verified': False, 'quote_verified': False, 'selection_enabled': False}
                assert result.returncode == 1 and result.stderr == '', 'legacy mode must refuse cleanly'
                assert json.loads(result.stdout) == expected, 'legacy mode must not be treated as retained input'
                assert snapshot(root) == before, 'no dependencies, output directories or checkpoints may change'
                cases += 1
        for args in ([], ['--capture-retained-package', 'extra']):
            before = snapshot(root)
            result = subprocess.run(['php', str(script), *args], cwd=root, env=env,
                input='not-a-selected-offer', capture_output=True, text=True, timeout=5)
            assert result.returncode == 1 and result.stderr == ''
            assert json.loads(result.stdout)['reason'] == 'operation_refused'
            assert snapshot(root) == before
            cases += 1
    print(f'Probe replacement: {cases} real CLI refusal cases passed; no historical execution, includes, network or checkpoint mutation.')


if __name__ == '__main__':
    main()
