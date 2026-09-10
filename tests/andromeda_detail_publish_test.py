"""One local two-file publication scenario. No SSH, supplier or persistent DB."""
import base64
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('publisher', root / 'scripts/diagnostics/andromeda_detail_publish.py')
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)
before = (Path(sys.argv[1]) / 'v2/api-andromeda-search3-preview.php').read_bytes()
files = {p: (root / (p if p.startswith('app/') else 'v2/' + p)).read_bytes() for p in publisher.FILES}
with tempfile.TemporaryDirectory() as temp:
    home = Path(temp)
    project = home / 'www/anytoour.ru'
    target = project / '_preview/search3-anex-candidate'
    (target / 'app/integrations').mkdir(parents=True)
    private = home / '.anytoour-andromeda'; private.mkdir()
    api = target / 'api-andromeda-search3-preview.php'
    module = target / 'app/integrations/andromeda-selected-offer.php'
    def run(candidate=files):
        request = {'files': {p: base64.b64encode(b).decode() for p, b in candidate.items()}}
        return json.loads(subprocess.check_output(['php', '-r', publisher.SOURCE], cwd=project,
            input=json.dumps(request).encode()))
    api.write_bytes(before)
    module.write_bytes(b'existing module')
    assert run()['reason'] == 'module_already_exists'
    assert api.read_bytes() == before and module.read_bytes() == b'existing module'
    module.unlink()
    api.write_bytes(b'drift')
    assert run()['reason'] == 'predecessor_changed' and not module.exists()
    api.write_bytes(before)
    bad = dict(files); bad['api-andromeda-search3-preview.php'] = b'invalid'
    assert run(bad)['reason'] == 'candidate_hash' and api.read_bytes() == before and not module.exists()
    assert run()['status'] == 'published'
    assert all((target / p).read_bytes() == b for p, b in files.items())
    release = private / ('detail-selection-' + publisher.SOURCE_SHA)
    assert (release / 'previous-api.php').read_bytes() == before
    assert json.loads((release / 'reservation.json').read_text())['before']['app/integrations/andromeda-selected-offer.php'] is None
    assert run()['status'] == 'already_published'
    (release / 'completed.json').unlink()
    assert run()['reason'] == 'previous_outcome_unknown'
    assert all((target / p).read_bytes() == b for p, b in files.items())
print('DETAIL_PUBLISHER_OK conflict/drift/candidate/backup/readback/repeat/unknown; external_calls=0')
