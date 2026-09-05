"""Finish whitespace left by deleted rules; verify all surviving declarations."""
from pathlib import Path
import importlib.util, json, re, subprocess

root = Path.cwd()
spec = importlib.util.spec_from_file_location('audit', root/'scripts/oneoff/search3_cascade_cleanup_once.py')
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)
contract_path = root/'docs/project/search3-cascade-sections.json'
contract = json.loads(contract_path.read_text())
source = root/contract['source_root']
paths = [source/name for name in contract['sections']]
before = b''.join(p.read_bytes() for p in paths)
rows_before = audit.records(before.decode())[0]
asset = root/'v2/search3-results-filters-v1.css'
asset_before = asset.read_bytes()
for path in paths:
    text = re.sub(r'(?m)^[ \t]+$', '', path.read_text())
    text = '\n\n' + re.sub(r'\n{3,}', '\n\n', text.strip('\n')) + '\n'
    path.write_text(text)
after = b''.join(p.read_bytes() for p in paths)
rows_after = audit.records(after.decode())[0]
assert [r[:5] for r in rows_after] == [r[:5] for r in rows_before]
contract['combined_bytes'] = len(after)
contract['combined_git_blob_sha'] = audit.blob(after)
contract_path.write_text(json.dumps(contract, ensure_ascii=False, indent=2)+'\n')
subprocess.run(['python3', 'scripts/build/search3_assets.py', '--write'], check=True)
assert asset_before.count(before) == 1
assert asset.read_bytes() == asset_before.replace(before,after)
report_path = root/'docs/project/search3-cascade-cleanup.json'
report = json.loads(report_path.read_text())
report['cascade_after'] = audit.stats(after)
report['asset_after'] = audit.stats(asset.read_bytes())
for row in report['sections']:
    row['after'] = audit.stats((source/row['name']).read_bytes())
report_path.write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
print('FINAL_CASCADE', json.dumps(report['cascade_after']))
print('FINAL_ASSET', json.dumps(report['asset_after']))
