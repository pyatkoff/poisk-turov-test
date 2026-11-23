#!/usr/bin/env python3
"""Execute the collector's real shell with inert SSH/PHP; never query a supplier."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[1]
REAL_PHP = shutil.which('php')
LOCAL_SOURCE = Path(os.environ.get('LOCAL_TV_CAPTURE_SOURCE_ROOT', str(ROOT / 'local-tv-source')))
# Original stdout from run 43642921435, job 132594373152, 2026-11-14 21:42:20 UTC.
ACTUAL_PARTIAL_RECEIPT = (
    'TOP500_MONTHLY_DONE {"processed":64,"logical_processed":76,"page_gap_processed":0,'
    '"failed":1,"partial":1,"observations_written":24440,"fresh_targets":436,'
    '"pending_targets":2699,"complete":false,"months":{'
    '"2026-11":{"total":165,"fresh":8,"pending":157},'
    '"2026-12":{"total":275,"fresh":39,"pending":236},'
    '"2027-01":{"total":275,"fresh":40,"pending":235},'
    '"2027-02":{"total":220,"fresh":39,"pending":181},'
    '"2027-03":{"total":275,"fresh":40,"pending":235},'
    '"2027-04":{"total":275,"fresh":40,"pending":235},'
    '"2027-05":{"total":275,"fresh":38,"pending":237},'
    '"2027-06":{"total":275,"fresh":38,"pending":237},'
    '"2027-07":{"total":275,"fresh":39,"pending":236},'
    '"2027-08":{"total":275,"fresh":39,"pending":236},'
    '"2027-09":{"total":275,"fresh":38,"pending":237},'
    '"2027-10":{"total":275,"fresh":38,"pending":237}}}\n'
)

def completed_partial(**changes):
    receipt = {'processed': 2, 'logical_processed': 2, 'page_gap_processed': 0,
               'failed': 1, 'partial': 0, 'observations_written': 7,
               'fresh_targets': 1, 'pending_targets': 1, 'complete': False,
               'months': {'2026-11': {'total': 2, 'fresh': 1, 'pending': 1}}}
    receipt.update(changes)
    return 'TOP500_MONTHLY_DONE ' + json.dumps(receipt, separators=(',', ':')) + '\n'

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
    def test_validation_and_production_pin_the_same_publisher_commit(self):
        source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
        blocks = re.split(r'(?m)^      - ', source)[1:]
        publishers = [block for block in blocks
                      if re.search(r'(?m)^          repository: pyatkoff/max-search-bot$', block)]
        self.assertEqual(len(publishers), 2, 'validation and production must each check out the publisher')
        refs = []
        for block in publishers:
            values = re.findall(r'(?m)^          ref:\s*(\S+)\s*$', block)
            self.assertEqual(len(values), 1, 'each publisher checkout needs one explicit ref')
            self.assertRegex(values[0], r'\A[0-9a-f]{40}\Z', 'publisher ref must be an immutable commit')
            refs.append(values[0])
        self.assertEqual(refs[0], refs[1], 'production must use the publisher checked by validation')

    def test_price27316_compatible_bundle_is_checked_before_production_copy(self):
        source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
        expected = '7245b007e8b7fe2d0c3e0fe7b60a9cdff108d0d8d7a6a7b9ef74d69d191baf3e'
        self.assertEqual(source.count('ref: dc84691743def1d0e6e1390c9f90999b6ccbbbdf'), 2)
        guard = 'test "$(sha256sum "$RUNNER_TEMP/monitor-price-hook.php" | cut -d' + "' '" + ' -f1)" = ' + expected
        self.assertEqual(source.count(guard), 2)
        self.assertIn('php hotel-price-publisher/tools/direct_demand/operations/test-monitor-price-27316-cycle.php', source)
        production = source.split('\n  collect-production:\n', 1)[1]
        self.assertLess(production.index(guard), production.index('      - name: Prepare SSH key'))
        self.assertIn('"$RUNNER_TEMP/monitor-price-hook.php"', production.split('          scp ', 1)[1])

    def test_only_production_owns_the_existing_collector_lock(self):
        source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
        top, jobs = source.split('\njobs:\n', 1)
        validate, production = jobs.split('\n  collect-production:\n', 1)
        self.assertNotIn('\nconcurrency:', top, 'PR validation must not wait for the production lock')
        self.assertNotIn('\n    concurrency:', validate, 'validation must not acquire the production lock')
        self.assertIn('\n    concurrency:\n      group: anytour-scheduled-tour-collector\n      cancel-in-progress: false\n', production)
        self.assertEqual(source.count('group: anytour-scheduled-tour-collector'), 1)
        self.assertEqual(source.count('\n    concurrency:'), 1)

    def test_observer_deploy_uses_single_reviewed_source(self):
        source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
        production = source.split('  collect-production:', 1)[1]
        self.assertIn('ref: 4473719a21b10633fcdc506fb007ad3496282f4b', production)
        self.assertIn('            local-tv-source/v2/data/price-observer-v1.php \\\n', production)
        self.assertNotIn('            v2/data/price-observer-v1.php \\\n', production)
        self.assertEqual(production.count('            local-tv-source/v2/data/price-observer-v1.php \\\n'), 1)
        self.assertIn('            local-tv-source/v2/data/local-tv-catalog-v1.php \\\n', production)
        self.assertIn('            v2/data/db-v1.php \\\n', production)
        # MAIN still owns DB/price helpers, but blanket SCP must not rewrite
        # their metadata on every successful scheduled delivery.
        helpers = production.split('          deliver_shared_helpers \\\n', 1)[1].split('          ssh ', 1)[0]
        self.assertEqual(re.findall(r'v2/data/([a-z0-9-]+\.php)', helpers),
                         ['db-v1.php', 'price-segment-v1.php', 'price-confidence-v1.php', 'price-intelligence-v1.php'])
        copied = production.split('          scp "${opts[@]}" \\\n', 1)[1].split('          ssh ', 1)[0]
        for name in ('db-v1.php', 'price-segment-v1.php', 'price-confidence-v1.php', 'price-intelligence-v1.php'):
            self.assertNotIn('v2/data/'+name, copied)
        self.assertIn("if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'", production)

    def test_production_keeps_its_event_and_successful_validation_gate(self):
        source = (ROOT / '.github/workflows/collect-anytour-tours.yml').read_text()
        production = source.split('\n  collect-production:\n', 1)[1].split('\n    steps:\n', 1)[0]
        self.assertEqual([line for line in production.splitlines() if line.startswith('    if:')],
                         ["    if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'"])
        self.assertIn('\n    needs: validate\n', production)
        self.assertIn('    timeout-minutes: 65', production.splitlines())

    def run_pass(self, event='schedule', attempt=1, collector=0, hook=0, exact_rollup=0,
                 collector_stdout='', collector_stderr='', tee=0, scp=0, scp_fail_at=1,
                 promoted_installed=False, promoted_status=0, tamper='', helper_failure=0):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); binary = root / 'bin'; binary.mkdir(); log = root / 'calls.jsonl'
            fixture_home = root / 'home'
            installed = fixture_home / 'www/anytoour.ru'; (installed / 'data').mkdir(parents=True)
            for path, origin in [('v2/data/db-v1.php', ROOT),
                                 ('v2/data/price-segment-v1.php', ROOT),
                                 ('v2/data/price-confidence-v1.php', ROOT),
                                 ('v2/data/price-intelligence-v1.php', ROOT),
                                 ('local-tv-source/v2/data/price-observer-v1.php', LOCAL_SOURCE)]:
                destination = root / path; destination.parent.mkdir(parents=True, exist_ok=True)
                source = origin / path.removeprefix('local-tv-source/')
                shutil.copyfile(source, destination)
            promoted = root / 'apply_promoted_hotel_prices.php'
            if promoted_installed:
                promoted.touch()
            scripts = {
                'scp': '''#!/usr/bin/env python3
import json,os,shutil,sys
from pathlib import Path
log=Path(os.environ['SCP_TEST_LOG'])
call_number=len(log.read_text().splitlines())+1 if log.exists() else 1
with log.open('a') as f:f.write(json.dumps(sys.argv[1:])+'\\n')
status=int(os.environ['SCP_STATUS']) if call_number==int(os.environ['SCP_FAIL_AT']) else 0
if status==0:
    for arg in sys.argv[1:]:
        if arg in ('v2/data/db-v1.php','local-tv-source/v2/data/price-observer-v1.php'):
            target=Path(os.environ['COLLECTOR_FIXTURE_ROOT'])/'data'/Path(arg).name
            shutil.copyfile(arg,target)
            if target.name==os.environ['COPY_TAMPER']:target.write_text('synthetic corrupted copy')
raise SystemExit(status)
''',
                'ssh': '''#!/usr/bin/env python3
import os,subprocess,sys
from pathlib import Path
command=sys.argv[-1]
if command=='python3 -' and int(os.environ['HELPER_STATUS']):
    sys.stdin.buffer.read()
    raise SystemExit(int(os.environ['HELPER_STATUS']))
result=subprocess.run(['bash','-c',command],input=sys.stdin.buffer.read())
# Preserve the original readback regression at the actual post-delivery seam.
if command=='python3 -' and result.returncode==0 and os.environ['COPY_TAMPER']=='db-v1.php':
    (Path(os.environ['COLLECTOR_FIXTURE_ROOT'])/'data/db-v1.php').write_text('synthetic corrupted copy')
raise SystemExit(result.returncode)
''',
                'tee': '''#!/usr/bin/env python3
import os,sys
from pathlib import Path
raw=sys.stdin.buffer.read()
Path(sys.argv[1]).write_bytes(raw)
sys.stdout.buffer.write(raw)
raise SystemExit(int(os.environ['TEE_STATUS']))
''',
                'php': '''#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
a=sys.argv[1:]
# Exercise the workflow's real receipt parser; only external operations are inert.
if a[0]=='-r':
    native=os.environ.get('REAL_PHP')
    if not native:raise SystemExit('PHP CLI unavailable: receipt parser was not executed')
    os.execv(native,[native,*a])
with open(os.environ['HOOK_TEST_LOG'],'a') as f:f.write(json.dumps(a)+'\\n')
name=Path(a[0]).name
if name=='top500-daily-collector-v1.php':
    sys.stdout.write(os.environ['COLLECTOR_STDOUT'])
    sys.stderr.write(os.environ['COLLECTOR_STDERR'])
    raise SystemExit(int(os.environ['COLLECTOR_STATUS']))
if name=='rollup-price-daily-exact-v1.php':raise SystemExit(int(os.environ['EXACT_ROLLUP_STATUS']))
if name=='apply_promoted_hotel_prices.php':raise SystemExit(int(os.environ['PROMOTED_STATUS']))
if name=='monitor-price-hook.php':
    Path(os.environ['HOOK_CONTEXT_LOG']).write_text(json.dumps({'collection_started_at':os.environ.get('ANYTOUR_MONITOR_COLLECTION_STARTED_AT'),'collector_run_id':os.environ.get('ANYTOUR_MONITOR_COLLECTOR_RUN_ID')}))
    raise SystemExit(int(os.environ['HOOK_STATUS']))
'''}
            for name, script in scripts.items():
                f=binary/name; f.write_text(script); f.chmod(0o755)
            env=dict(os.environ, HOME=str(fixture_home), PATH=str(binary)+os.pathsep+os.environ['PATH'], RUNNER_TEMP=str(root),
                     HOST_VALUE='fixture.invalid', USER_VALUE='fixture', BUDGET_VALUE='2', MATRIX_BUDGET_VALUE='9',
                     MATRIX_MONTHS_VALUE='12', MONTHLY_BUDGET_VALUE='64', TARGET_MONTH_VALUE='', TARGET_COUNTRY_VALUE='0',
                     HOOK_TEST_LOG=str(log), HOOK_CONTEXT_LOG=str(root/'hook-context.json'), COLLECTOR_STATUS=str(collector), HOOK_STATUS=str(hook), EXACT_ROLLUP_STATUS=str(exact_rollup),
                     COLLECTOR_STDOUT=collector_stdout, COLLECTOR_STDERR=collector_stderr,
                     TEE_STATUS=str(tee), REAL_PHP=REAL_PHP or '',
                     SCP_TEST_LOG=str(root/'scp-calls.jsonl'), SCP_STATUS=str(scp), SCP_FAIL_AT=str(scp_fail_at),
                     PROMOTED_STATUS=str(promoted_status), COLLECTOR_FIXTURE_ROOT=str(installed), COPY_TAMPER=tamper,
                     HELPER_STATUS=str(helper_failure))
            script = actual_shell(event,attempt).replace(
                r'\$HOME/www/app.anytoour.ru/tools/apply_promoted_hotel_prices.php', str(promoted))
            result=subprocess.run(['bash','-c',script],env=env,cwd=root,capture_output=True,text=True,timeout=10)
            calls=[json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []
            scp_log=root/'scp-calls.jsonl'
            self.scp_calls=[json.loads(line) for line in scp_log.read_text().splitlines()] if scp_log.exists() else []
            self.hook_context=json.loads((root/'hook-context.json').read_text()) if (root/'hook-context.json').exists() else None
            return result,[Path(a[0]).name for a in calls],calls

    def test_promoted_editor_is_installed_and_serial_after_successful_hook(self):
        result, names, calls = self.run_pass(promoted_installed=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(names.count('apply_promoted_hotel_prices.php'), 1)
        self.assertEqual(names.index('apply_promoted_hotel_prices.php'), names.index('monitor-price-hook.php') + 1)
        self.assertEqual(calls[names.index('apply_promoted_hotel_prices.php')][1:], ['--apply'])
        for params in [dict(hook=5), dict(event='workflow_dispatch'), dict(attempt=2)]:
            with self.subTest(params=params):
                _, names, _ = self.run_pass(promoted_installed=True, **params)
                self.assertNotIn('apply_promoted_hotel_prices.php', names)

    def test_promoted_failure_does_not_repeat_or_stop_aggregates(self):
        result, names, _ = self.run_pass(promoted_installed=True, promoted_status=8)
        self.assertEqual(result.returncode, 8, result.stderr)
        self.assertEqual(names.count('apply_promoted_hotel_prices.php'), 1)
        self.assertIn('rollup-price-daily-exact-v1.php', names)

    def test_failed_scp_stops_before_any_production_php(self):
        for failed_copy in (1, 2):
            with self.subTest(failed_copy=failed_copy):
                result,names,_=self.run_pass(scp=23,scp_fail_at=failed_copy)
                self.assertEqual(result.returncode,23,result.stderr)
                self.assertEqual(len(self.scp_calls),failed_copy)
                if failed_copy==2:
                    self.assertIn('v2/data/hotel-monitor-prices-v1.php',self.scp_calls[-1])
                self.assertEqual(names,[], 'failed copy must not reach collection, callback or aggregates')
                self.assertIsNone(self.hook_context)

    def test_failed_shared_delivery_stops_before_scp_or_any_production_php(self):
        result,names,_=self.run_pass(helper_failure=73)
        self.assertEqual(result.returncode,73,result.stderr)
        self.assertEqual(self.scp_calls,[])
        self.assertEqual(names,[])
        self.assertIsNone(self.hook_context)

    def test_mismatched_installed_file_stops_before_any_production_php(self):
        for filename in ('db-v1.php', 'price-observer-v1.php'):
            with self.subTest(filename=filename):
                result, names, _ = self.run_pass(tamper=filename)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn('FAILED', result.stdout)
                self.assertNotIn('ANYTOUR_LOCAL_PRICE_READBACK_OK', result.stdout)
                self.assertEqual(names, [])
                self.assertIsNone(self.hook_context)

    def test_success_runs_once_after_collection_before_aggregates(self):
        result,names,calls=self.run_pass()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertIn('ANYTOUR_LOCAL_PRICE_READBACK_OK db=', result.stdout)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        hook_call=next(a for a in calls if Path(a[0]).name=='monitor-price-hook.php')
        self.assertEqual(hook_call[1:],['--nightly'])
        self.assertEqual(self.hook_context['collector_run_id'],'43205530035')
        self.assertRegex(self.hook_context['collection_started_at'],r'^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$')
        hook_index=names.index('monitor-price-hook.php')
        self.assertEqual(names[names.index('top500-daily-collector-v1.php')+1:hook_index],['top500-coverage-v1.php'])
        self.assertEqual(names[hook_index+1:],['rollup-price-daily-v1.php','rollup-price-daily-exact-v1.php','build-seo-offer-snapshots-v1.php'])
        collect=next(a for a in calls if Path(a[0]).name=='top500-daily-collector-v1.php')
        self.assertIn('--budget=64',collect);self.assertIn('--months=12',collect)
        self.assertNotIn('scheduled-collector-v1.php',names)

    def test_failed_search_does_not_start_post_edits(self):
        result,names,_=self.run_pass(collector=2)
        self.assertEqual(result.returncode,2)
        self.assertNotIn('monitor-price-hook.php',names)
        self.assertIn('build-seo-offer-snapshots-v1.php',names)

    @unittest.skipUnless(REAL_PHP, 'PHP CLI required to verify the real completion receipt parser')
    def test_completed_partial_runs_hook_and_preserves_collector_failure(self):
        result,names,_=self.run_pass(collector=2,collector_stdout='TOP500_MONTHLY_PLAN {}\n'+ACTUAL_PARTIAL_RECEIPT)
        self.assertEqual(result.returncode,2,result.stderr)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        self.assertIn('TOP500_MONTHLY_COMPLETED_PARTIAL collector_status=2',result.stdout)
        self.assertEqual(names[names.index('monitor-price-hook.php')+1:],['rollup-price-daily-v1.php','rollup-price-daily-exact-v1.php','build-seo-offer-snapshots-v1.php'])

    @unittest.skipUnless(REAL_PHP, 'PHP CLI required to verify the real completion receipt parser')
    def test_unproven_partial_receipts_do_not_start_post_edits(self):
        receipt=completed_partial()
        invalid={
            'malformed': 'TOP500_MONTHLY_DONE {\n',
            'duplicate': receipt+receipt,
            'trailing_output': receipt+'unexpected output\n',
            'truncated': receipt[:-1],
            'zero_processed': completed_partial(processed=0),
            'zero_failures': completed_partial(failed=0),
            'zero_written': completed_partial(observations_written=0),
            'boolean_counter': completed_partial(failed=True),
            'duplicate_json_key': receipt.replace('"failed":1','"failed":0,"failed":1'),
            'unknown_field': completed_partial(extra=1),
            'inconsistent_coverage': completed_partial(pending_targets=2),
        }
        for reason,output in invalid.items():
            with self.subTest(reason=reason):
                result,names,_=self.run_pass(collector=2,collector_stdout=output)
                self.assertEqual(result.returncode,2,result.stderr)
                self.assertNotIn('monitor-price-hook.php',names)
                self.assertNotIn('TOP500_MONTHLY_COMPLETED_PARTIAL',result.stdout)
                self.assertIn('build-seo-offer-snapshots-v1.php',names)
        result,names,_=self.run_pass(collector=2,collector_stderr=receipt)
        self.assertEqual(result.returncode,2)
        self.assertNotIn('monitor-price-hook.php',names,'stderr cannot supply the completion receipt')

    def test_terminal_receipt_does_not_override_fatal_or_unknown_exit(self):
        for status in (1,3,130,255):
            with self.subTest(status=status):
                result,names,_=self.run_pass(collector=status,collector_stdout=completed_partial())
                self.assertEqual(result.returncode,status,result.stderr)
                self.assertNotIn('monitor-price-hook.php',names)

    def test_failed_capture_cannot_admit_even_a_complete_receipt(self):
        for status in (0,2):
            with self.subTest(collector=status):
                result,names,_=self.run_pass(collector=status,collector_stdout=completed_partial(),tee=7)
                self.assertEqual(result.returncode,status or 7,result.stderr)
                self.assertIn(f'TOP500_MONTHLY_CAPTURE_FAILED collector_status={status} tee_status=7',result.stdout)
                self.assertNotIn('monitor-price-hook.php',names)
                self.assertEqual(names[names.index('top500-daily-collector-v1.php')+1:],
                                 ['top500-coverage-v1.php','rollup-price-daily-v1.php',
                                  'rollup-price-daily-exact-v1.php','build-seo-offer-snapshots-v1.php'])

    def test_partial_receipt_keeps_manual_and_replay_guards(self):
        for event,attempt in (('workflow_dispatch',1),('schedule',2)):
            with self.subTest(event=event,attempt=attempt):
                result,names,_=self.run_pass(event=event,attempt=attempt,collector=2,collector_stdout=completed_partial())
                self.assertEqual(result.returncode,2,result.stderr)
                self.assertNotIn('monitor-price-hook.php',names)
                self.assertNotIn('TOP500_MONTHLY_COMPLETED_PARTIAL',result.stdout)

    @unittest.skipUnless(REAL_PHP, 'PHP CLI required to verify the real completion receipt parser')
    def test_partial_hook_failure_is_visible_and_aggregates_still_run(self):
        result,names,_=self.run_pass(collector=2,collector_stdout=completed_partial(),hook=7)
        self.assertEqual(result.returncode,7,result.stderr)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        self.assertIn('build-seo-offer-snapshots-v1.php',names)

    def test_manual_search_does_not_trigger_automatic_post_edits(self):
        result,names,_=self.run_pass(event='workflow_dispatch')
        self.assertEqual(result.returncode,0)
        self.assertNotIn('monitor-price-hook.php',names)

    def test_repeated_original_run_cannot_replay_hook(self):
        result,names,_=self.run_pass(attempt=2)
        self.assertEqual(result.returncode,0)
        self.assertNotIn('monitor-price-hook.php',names)

    def test_hook_failure_remains_visible_and_aggregates_still_run(self):
        result,names,_=self.run_pass(hook=2)
        self.assertEqual(result.returncode,2)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        self.assertEqual(names[names.index('monitor-price-hook.php')+1:],['rollup-price-daily-v1.php','rollup-price-daily-exact-v1.php','build-seo-offer-snapshots-v1.php'])

    def test_exact_rollup_failure_keeps_completed_callback_and_original_status(self):
        result,names,_=self.run_pass(exact_rollup=7)
        self.assertEqual(result.returncode,7)
        self.assertEqual(names.count('monitor-price-hook.php'),1)
        self.assertEqual(names[names.index('monitor-price-hook.php')+1:],['rollup-price-daily-v1.php','rollup-price-daily-exact-v1.php'])
        self.assertNotIn('build-seo-offer-snapshots-v1.php',names)

if __name__=='__main__':unittest.main()

