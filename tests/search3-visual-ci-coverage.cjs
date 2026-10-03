// The visual gate must follow its actual entry graph, including reused LIVE
// owners outside visual-search. This test inspects dependency/trigger policy;
// it does not execute PHP, start a search or contact a provider.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const bootstrap=fs.readFileSync(path.join(root,'v2/visual-search/index.php'),'utf8');
const html=fs.readFileSync(path.join(root,'v2/visual-search/index.html'),'utf8');
const workflow=fs.readFileSync(path.join(root,'.github/workflows/search3-visual-migration.yml'),'utf8');
const leadSources=require('../scripts/build/search3-js/lead-runtime.cjs').owners.map(owner=>'v2/'+owner.file);
function patterns(workflow){
 const section=workflow.slice(workflow.indexOf('    paths:'),workflow.indexOf('\npermissions:'));
 return [...section.matchAll(/^\s+- '([^']+)'\s*$/gm)].map(m=>m[1]);
}
function matches(pattern,file){
 let regex='^';
 for(let i=0;i<pattern.length;i++){
  const c=pattern[i];
  if(c==='*'){if(pattern[i+1]==='*'){regex+='.*';i++;}else regex+='[^/]*';}
  else regex+=/[\\^$+?.()|{}\[\]]/.test(c)?'\\'+c:c;
 }
 return new RegExp(regex+'$').test(file);
}
function dependencies(bootstrap,html){
 const array=bootstrap.match(/\$scripts\s*=\s*\[([\s\S]*?)\];/);assert(array,'explicit LIVE graph');
 const live=[...array[1].matchAll(/'([^']+\.js)'/g)].map(m=>m[1]);
 const offline=[...html.matchAll(/(?:src|href)="(\.\/?[^"?]+\.(?:js|css))(?:\?[^" ]*)?"/g)].map(m=>m[1]);
 const optional=[...bootstrap.matchAll(/dirname\(__DIR__\)\s*\.\s*'\/([^']+\.php)'/g)].map(m=>'../'+m[1]);
 return [...new Set([...live,...offline,...optional].map(src=>path.posix.normalize(path.posix.join('v2/visual-search',src))).concat(leadSources))];
}
function verify(workflow,bootstrap,html){
 const rules=patterns(workflow),deps=dependencies(bootstrap,html);
 assert(deps.length>=16,'nonempty actual graph');
 const uncovered=deps.filter(file=>!rules.some(rule=>matches(rule,file)));
 assert.deepEqual(uncovered,[],'visual CI must trigger for every entry dependency');
 for(const test of ['tests/search3-visual-event-dispatch.cjs','tests/search3-visual-modal-history.cjs','tests/search3-visual-offer-rendering.cjs','tests/search3-prototype-source-receipt-v1.cjs'])assert(rules.some(rule=>matches(rule,test)),'characterization trigger: '+test);
 assert(workflow.includes('node tests/search3-visual-ci-coverage.cjs'),'closure guard executes in existing gate');
 assert(/types: \[[^\]]*ready_for_review/.test(workflow),'full gate restores on ready_for_review');
 const focused=workflow.slice(workflow.indexOf('  focused-presentation:'),workflow.indexOf('  visual-migration:'));
 assert(focused.includes('node tests/search3-visual-results-rendering.cjs')&&focused.includes('node tests/search3-visual-ci-coverage.cjs')&&!/^    if:/m.test(focused),'focused checks run on every draft and ready head');
 const full=workflow.slice(workflow.indexOf('  visual-migration:'));
 assert(full.includes('if: ${{ !github.event.pull_request.draft }}'),'full matrix is deferred only while draft');
 assert.equal((workflow.match(/ref: \$\{\{ github.event.pull_request.head.sha \}\}/g)||[]).length,2,'both jobs check exact source head');
 return deps;
}
const deps=verify(workflow,bootstrap,html);
assert.throws(()=>verify(workflow.replace("      - 'v2/prototype-search/data.js'\n",''),bootstrap,html),/every entry dependency/,'missing data owner detected');
assert.throws(()=>verify(workflow.replace("      - 'v2/prototype-search/source-receipt-v1.js'\n",''),bootstrap,html),/every entry dependency/,'missing receipt owner detected');
assert.throws(()=>verify(workflow,bootstrap.replace("'../prototype-search/data.js'","'../prototype-search/future-owner.js'"),html),/every entry dependency/,'future unregistered owner detected');
assert.throws(()=>verify(workflow.replace("      - 'tests/search3-prototype-*.cjs'\n",''),bootstrap,html),/characterization trigger/,'test-only change detected');
assert.throws(()=>verify(workflow.replace('ready_for_review, ',''),bootstrap,html),/restores/,'missing ready restoration detected');
assert.throws(()=>verify(workflow.replace('    # Drafts retain','    if: ${{ !github.event.pull_request.draft }}\n    # Drafts retain'),bootstrap,html),/every draft/,'disabled draft check detected');
for(const file of leadSources)assert.throws(()=>verify(workflow.replace("      - '"+file+"'\n",''),bootstrap,html),/every entry dependency/,'generated projection retains canonical source coverage: '+file);
console.log(`PASS visual CI dependency coverage: ${deps.length} actual dependencies; draft focus/full ready restoration; eight coverage mutations detected`);
require('./search3-visual-lead-runtime.cjs');
