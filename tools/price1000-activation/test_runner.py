"""Local tests only. Never calls main or any native/network command."""
import importlib.util,pathlib,sys,types,hashlib,ast
ROOT=pathlib.Path(__file__).parent
# Load helpers without depending on parent-owned admission/verifier availability.
for name in ['admit','postpass_verify']:sys.modules[name]=types.ModuleType(name)
spec=importlib.util.spec_from_file_location('runner_under_test',ROOT/'run.py');r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)
checks=0
def check(v):
 global checks
 assert v;checks+=1
original="const ACTIVATION_ENABLED=false;\nconst EXPECTED_NATIVE_RECEIPT_SHA='UNSET';\nconst EXPECTED_NATIVE_RESULT_SHA='UNSET';\nFIXED"
edits={"const ACTIVATION_ENABLED=false;":"const ACTIVATION_ENABLED=true;","const EXPECTED_NATIVE_RECEIPT_SHA='UNSET';":"const EXPECTED_NATIVE_RECEIPT_SHA='"+'a'*64+"';","const EXPECTED_NATIVE_RESULT_SHA='UNSET';":"const EXPECTED_NATIVE_RESULT_SHA='"+'b'*64+"';"}
bound=r.bind_installer(original,edits);check(bound.endswith('FIXED'));check('UNSET' not in bound)
for bad in [original+"const ACTIVATION_ENABLED=false;",original.replace('UNSET','OTHER')]:
 try:r.bind_installer(bad,edits);raise AssertionError('binding accepted')
 except RuntimeError:checks+=1
cmd=r.price_command('/fixed/op','4422');check(cmd.count('; php ')==1);check('set -C;' in cmd and '-started.json' in cmd);check(' --' not in cmd and 'status=$?' in cmd)
try:r.price_command('/fixed/op','1;rm');raise AssertionError('id accepted')
except RuntimeError:checks+=1
h={n:'a'*64 for n in ['hook','cohort','collector']}
raw=('a'*64+'  /x/monitor-price-hook.php\n'+'a'*64+'  /x/published-hotels-monitor-v1.php\n'+'a'*64+'  /x/top500-daily-collector-v1.php\n').encode();check(r.parse_code_hashes(raw)==h)
try:r.parse_code_hashes(raw+raw);raise AssertionError('duplicate accepted')
except RuntimeError:checks+=1
s=(ROOT/'run.py').read_text();ast.parse(s)
check(s.count("+' install'+")==0) # Actual explicit immutable install command checked below.
check(s.count("/paired-install.php install'")==1)
check(s.count("passraw=ssh(command,'price-pass',False)")==1)
check(s.index("journalraw=ssh(")>s.index("passraw=ssh("))
check(s.index("afterraw=ssh(")>s.index("journalraw=ssh("))
check(s.index("price=json.loads")>s.index("afterraw=ssh("))
check("readiness_stale" not in s) # No local override or waived expiry.
check("installer_template_sha256" in s and "'bound_sha256'" in s)
workflow=ROOT.parents[1]/'.github/workflows/price1000-activation-once.yml'
if not workflow.exists():workflow=ROOT/'workflow.yml'
w=workflow.read_text();check('anytour-scheduled-tour-collector' in w and 'cancel-in-progress: false' in w);check('path: price1000-consumer' in w);check('e47d578bde03698554428016120a22ceb5272baf' in w);check('if: always()' in w)
check(s.index('inspect_completed_at=int(time.time())')>s.index("raw=ssh('php '+scratch+'/monitor-price-hook.php --inspect'"));check(s.index('inspect_completed_at=int(time.time())')<s.index("after=pins('native-after')"))
print({'ok':True,'checks':checks,'network_calls':0,'production_calls':0})
