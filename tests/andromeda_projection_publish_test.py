"""Exercise the actual remote PHP locally; no SSH, supplier, or persistent DB."""
import base64
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', root / 'scripts/diagnostics/andromeda_projection_publish.py')
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)
before = (Path(sys.argv[1]) / 'v2/api-andromeda-search3-preview.php').read_bytes()
after = (root / 'v2/api-andromeda-search3-preview.php').read_bytes()
with tempfile.TemporaryDirectory() as temp:
    home = Path(temp)
    project = home / 'www/anytoour.ru'
    target = project / '_preview/search3-anex-candidate/api-andromeda-search3-preview.php'
    target.parent.mkdir(parents=True)
    private = home / '.anytoour-andromeda'
    private.mkdir()
    def run(content=after):
        reply = subprocess.check_output(['php', '-r', publisher.SOURCE], cwd=project,
            input=json.dumps({'content': base64.b64encode(content).decode()}).encode())
        return json.loads(reply)
    target.write_bytes(b'drift')
    assert run()['reason'] == 'predecessor_changed'
    assert target.read_bytes() == b'drift'
    target.write_bytes(before)
    assert run(b'not the candidate')['reason'] == 'candidate_hash'
    assert target.read_bytes() == before
    assert run()['status'] == 'published'
    assert target.read_bytes() == after
    release = private / ('projection-' + publisher.SOURCE_SHA)
    assert (release / 'previous.php').read_bytes() == before
    assert run()['status'] == 'already_published'
    (release / 'completed.json').unlink()
    assert run()['reason'] == 'previous_outcome_unknown'
    assert target.read_bytes() == after
print('PROJECTION_PUBLISHER_OK predecessor/candidate/backup/readback/repeat/unknown; external_calls=0')
