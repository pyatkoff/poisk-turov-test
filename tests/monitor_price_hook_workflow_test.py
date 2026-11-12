#!/usr/bin/env python3
"""Execute the collector's real shell with inert SSH/PHP; never query a supplier."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]

def actual_shell(event, attempt):
    source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
    source = source.split('      - name: Collect due TOP500 months and refresh production aggregates\n', 1)[1]
    source = source.split('        run: |\n', 1)[1]
    lines = []
    for line in source.splitlines():
        if line.strip() and not line.startswith('          '):
            break
        lines.append(line)
    return textwrap.dedent('\n'.join(lines)).replace('${{ github.event_name }}', event).replace('${{ github.run_attempt }}', str(attempt)).replace('${{ github.run_id }}', '43205530035')

class ExistingCollectorHook(unittest.TestCase):
    def run_pass(self, event='schedule', attempt=1, collector=0, hook=0):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); binary = root / 'bin'; binary.mkdir(); log = root / 'calls.jsonl'
            scripts = {
                'scp': '#!/usr/bin/env python3\nimport sys\n',
                'ssh': "#!/usr/bin/env python3\nimport subprocess,sys\nraise SystemExit(subprocess.run(['bash','-c',sys.argv[-1]]).returncode)\n",
                'php': '''#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
a=sys.argv[1:]
with open(os.environ['HOOK_TEST_LOG'],'a') as f:f.write(json.dumps(a)+'\\n')
name=Path(a[0]).name
if name=='top500-daily-collector-v1.php':raise SystemExit(int(os.environ['COLLECTOR_STATUS']))
if name=='monitor-price-hook.php':
    Path(os.environ['HOOK_CONTEXT_LOG']).write_text(json.dumps({'collection_started_at':os.environ.get('ANYTOUR_MONITOR_COLLECTION_STARTED_AT'),'collector_run_id':os.environ.get('ANYTOUR_MONITOR_COLLECTOR_RUN_ID')}))
    raise SystemExit(int(os.environ['HOOK_STATUS']))
'''}
            for name, script in scripts.items():
                f=binary/name; f.write_text(script); f.chmod(0o755)
            env=dict(os.environ, PATH=str(binary)+os.pathsep+os.environ['PATH'], RUNNER_TEMP=str(root),
                     HOST_VALUE='fixture.invalid', USER_VALUE='fixture', BUDGET_VALUE='2', MATRIX_BUDGET_VALUE='9',
                     MATRIX_MONTHS_VALUE='12', MONTHLY_BUDGET_VALUE='64', TARGET_MONTH_VALUE='', TARGET_COUNTRY_VALUE='0',
                     HOOK_TEST_LOG=str(log), HOOK_CONTEXT_LOG=str(root/'hook-context.json'), COLLECTOR_STATUS=str(collector), HOOK_STATUS=str(hook))
            result=subprocess.run(['bash','-c',actual_shell(event,attempt)],env=env,cwd=ROOT,capture_output=True,text=True,timeout=10)
            calls=[json.loads(line) for line in log.read_text().splitlines()]
            self.hook_context=json.loads((root/'hook-context.json').read_text()) if (root/'hook-context.json').exists() else None
            return result,[Path(a[0]).name for a in calls],calls

    def test_success_runs_once_after_retained_collector_and_aggregates(self):
        result,names,calls=self.run_pass()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        hook_call=next(a for a in calls if Path(a[0]).name=='monitor-price-hook.php')
        self.assertEqual(hook_call[1:],['--nightly'])
        self.assertEqual(self.hook_context['collector_run_id'],'43205530035')
        self.assertRegex(self.hook_context['collection_started_at'],r'^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$')
        self.assertGreater(names.index('monitor-price-hook.php'),names.index('build-seo-offer-snapshots-v1.php'))
        collect=next(a for a in calls if Path(a[0]).name=='top500-daily-collector-v1.php')
        self.assertIn('--budget=64',collect);self.assertIn('--months=12',collect)
        self.assertNotIn('scheduled-collector-v1.php',names)

    def test_failed_search_does_not_start_post_edits(self):
        result,names,_=self.run_pass(collector=2)
        self.assertEqual(result.returncode,2)
        self.assertNotIn('monitor-price-hook.php',names)
        self.assertIn('build-seo-offer-snapshots-v1.php',names)

    def test_manual_search_does_not_trigger_automatic_post_edits(self):
        result,names,_=self.run_pass(event='workflow_dispatch')
        self.assertEqual(result.returncode,0)
        self.assertNotIn('monitor-price-hook.php',names)

    def test_repeated_original_run_cannot_replay_hook(self):
        result,names,_=self.run_pass(attempt=2)
        self.assertEqual(result.returncode,0)
        self.assertNotIn('monitor-price-hook.php',names)

    def test_hook_failure_remains_visible(self):
        result,names,_=self.run_pass(hook=2)
        self.assertEqual(result.returncode,2)
        self.assertEqual(names.count('monitor-price-hook.php'),1)

if __name__=='__main__':unittest.main()
