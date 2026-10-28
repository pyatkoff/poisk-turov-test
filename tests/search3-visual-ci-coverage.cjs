// The visual gate must follow its actual entry graph, including reused LIVE
// owners outside visual-search. This test inspects dependency/trigger policy;
// it does not execute PHP, start a search or contact a provider.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..');
const bootstrap=fs.readFileSync(path.join(root,'v2/visual-search/index.php'),'utf8');
const html=fs.readFileSync(path.join(root,'v2/visual-search/index.html'),'utf8');
const workflow=fs.readFileSync(path.join(root,'.github/workflows/search3-visual-migration.yml'),'utf8');
const buildWorkflow=fs.readFileSync(path.join(root,'.github/workflows/build-search3-whole-site-preview.yml'),'utf8');
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
function job(workflow,name){
 const start=workflow.indexOf('  '+name+':\n');assert(start>=0,'required job '+name);
 const tail=workflow.slice(start),next=tail.slice(1).search(/^  [a-z][a-z0-9-]*:\s*$/m);
 return next<0?tail:tail.slice(0,next+1);
}
// The pre-split workflow had 46 Node test invocations and 37 distinct commands.
// Keep every distinct argv once; --browser and --messenger-only are separate cases.
const requiredCommands=[
 'local-tv-catalog-browser.cjs','local-tv-catalog-browser.cjs --browser',
 'search3-messenger-context.cjs','search3-messenger-flow.cjs','search3-prototype-messenger-handoff.cjs',
 'search3-visual-anex-flight-continuation.cjs','search3-visual-asset-size.cjs "$RUNNER_TEMP/visual-compiled"',
 'search3-visual-browser-dependencies.cjs','search3-visual-calendar-inventory.cjs','search3-visual-ci-coverage.cjs',
 'search3-visual-data-orchestration.cjs','search3-visual-event-dispatch.cjs','search3-visual-evidence-retention.cjs',
 'search3-visual-facet-presentation.cjs','search3-visual-flight-filter-work.cjs','search3-visual-flight-rendering.cjs',
 'search3-visual-formatting.cjs','search3-visual-hotel-content-text.cjs','search3-visual-hotel-details-browser.cjs',
 'search3-visual-hotel-details-lazy.cjs','search3-visual-hotel-details-rendering.cjs','search3-visual-lifecycle-scheduling.cjs',
 'search3-visual-live-bridge.cjs','search3-visual-live-browser.cjs','search3-visual-live-browser.cjs --messenger-only',
 'search3-visual-messenger-entry.cjs','search3-visual-migration.cjs','search3-visual-modal-history.cjs',
 'search3-visual-offer-list-browser.cjs','search3-visual-offer-list-lazy.cjs','search3-visual-offer-rendering.cjs',
 'search3-visual-passive-contexts.cjs','search3-visual-result-merge.cjs','search3-visual-results-rendering.cjs',
 'search3-visual-terminal-offer-browser.cjs','search3-visual-v120-recorded-journey-tests.cjs','search3-visual-v139-search-edit-tests.cjs'
].map(command=>'tests/'+command).sort();
const rawContracts=['offer-rendering','migration','live-bridge','anex-flight-continuation','v139-search-edit-tests','v120-recorded-journey-tests'].map(name=>'tests/search3-visual-'+name+'.cjs');
const commands=section=>[...section.matchAll(/(?:^|\s)node (tests\/\S+\.cjs[^\n]*)/gm)].map(match=>match[1]);
function verifyConcurrency(source,prefix){
 assert(source.includes('concurrency:\n  group: '+prefix+'-${{ github.event.pull_request.number }}\n  cancel-in-progress: true'),'PR-scoped obsolete-head cancellation');
}
function verifyJoin(join){
 assert(join.includes('    needs: [focused-presentation, integration-contracts, browser-matrix]\n'),'aggregate requires all source lanes');
 assert(join.includes('    if: ${{ always() && !github.event.pull_request.draft }}\n'),'aggregate must inspect failed and skipped lanes');
 for(const [variable,lane] of [['FOCUSED_RESULT','focused-presentation'],['CONTRACTS_RESULT','integration-contracts'],['BROWSER_RESULT','browser-matrix']])assert(join.includes(variable+': ${{ needs.'+lane+'.result }}'),'aggregate binds actual lane result');
 const script=join.match(/        run: \|\n([\s\S]*)/);assert(script,'aggregate shell check');
 const execute=values=>spawnSync('bash',['-c',script[1]],{env:{...process.env,...values},encoding:'utf8'});
 const success={FOCUSED_RESULT:'success',CONTRACTS_RESULT:'success',BROWSER_RESULT:'success'};
 assert.equal(execute(success).status,0,'aggregate accepts all-success source');
 for(const variable of Object.keys(success))for(const status of ['failure','cancelled','skipped'])assert.notEqual(execute({...success,[variable]:status}).status,0,'aggregate rejects '+variable+'='+status);
}
function verify(workflow,bootstrap,html){
 const rules=patterns(workflow),deps=dependencies(bootstrap,html);
 assert(deps.length>=16,'nonempty actual graph');
 const uncovered=deps.filter(file=>!rules.some(rule=>matches(rule,file)));
 assert.deepEqual(uncovered,[],'visual CI must trigger for every entry dependency');
 for(const test of ['tests/search3-visual-event-dispatch.cjs','tests/search3-visual-modal-history.cjs','tests/search3-visual-offer-rendering.cjs','tests/search3-prototype-source-receipt-v1.cjs'])assert(rules.some(rule=>matches(rule,test)),'characterization trigger: '+test);
 assert(workflow.includes('node tests/search3-visual-ci-coverage.cjs'),'closure guard executes in existing gate');
 assert(/types: \[[^\]]*ready_for_review/.test(workflow),'full gate restores on ready_for_review');
 const focused=job(workflow,'focused-presentation'),contracts=job(workflow,'integration-contracts'),full=job(workflow,'browser-matrix'),join=job(workflow,'visual-migration');
 assert(focused.includes('node tests/search3-visual-results-rendering.cjs')&&focused.includes('node tests/search3-visual-ci-coverage.cjs')&&!/^    if:/m.test(focused),'focused checks run on every draft and ready head');
 for(const lane of [contracts,full]){
  assert(lane.includes('    if: ${{ !github.event.pull_request.draft }}\n'),'full matrix is deferred only while draft');
  assert(!/^    needs:/m.test(lane),'independent contracts and browser lanes overlap');
 }
 for(const lane of [focused,contracts,full]){
  assert.equal((lane.match(/ref: \$\{\{ github.event.pull_request.head.sha \}\}/g)||[]).length,1,'every lane checks exact source head');
  assert(lane.includes("node-version: '22'"),'same Node version in every source lane');
  assert(!lane.includes('continue-on-error'),'source failures cannot be hidden');
 }
 assert.deepEqual(commands(contracts),rawContracts,'only independent raw contracts move out of browser');
 assert(!contracts.includes('SEARCH3_VISUAL_ASSET_ROOT')&&!contracts.includes('playwright')&&!contracts.includes('upload-artifact'),'raw contracts do not own compiled browser evidence');
 assert(rules.some(rule=>matches(rule,'scripts/ci/search3_visual_browser_dependencies.cjs')),'browser dependency preflight triggers its full gate');
 assert(focused.includes('node tests/search3-visual-browser-dependencies.cjs'),'dependency preflight regressions run in the joined focused lane');
 assert(rules.some(rule=>matches(rule,'scripts/ci/search3_visual_evidence_retention.cjs')),'lossless evidence owner triggers its full gate');
 assert(focused.includes('node tests/search3-visual-evidence-retention.cjs'),'lossless evidence regressions run in the joined focused lane');
 assert.deepEqual(commands(workflow).sort(),requiredCommands,'all distinct original Node commands and argv execute exactly once');
 verifyJoin(join);verifyConcurrency(workflow,'ci-search3-visual');
 assert(rules.some(rule=>matches(rule,'.github/workflows/build-search3-whole-site-preview.yml')),'build concurrency dependency triggers its guard');
 const setup=full.slice(full.indexOf('      - name: Inspect installed Chromium system dependencies'),full.indexOf('      - name: Build the exact compiled visual fixture graph'));
 assert(setup.includes('id: browser-deps')&&setup.includes('node scripts/ci/search3_visual_browser_dependencies.cjs'),'required dependency facts precede installation');
 assert(setup.includes('      - name: Prefer the existing HTTPS Ubuntu package mirror\n        if: ${{ steps.browser-deps.outputs.ready != \'true\' }}\n        run: sudo -- "$(command -v node)" scripts/ci/search3_visual_browser_dependencies.cjs --prefer-ubuntu-mirror'),'mirror preference is conditional and uses only the explicit fixed-file helper mode');
 assert(setup.includes('      - name: Install missing Chromium system dependencies\n        if: ${{ steps.browser-deps.outputs.ready != \'true\' }}\n        run: "$RUNNER_TEMP/visual-tools/node_modules/.bin/playwright install-deps chromium"'),'only positive package facts skip stock system installation');
 assert(setup.includes('playwright install-deps chromium'),'negative facts retain stock system installation');
 assert(setup.includes('      - name: Install pinned Playwright Chromium\n        run: "$RUNNER_TEMP/visual-tools/node_modules/.bin/playwright install chromium"'),'stock browser installation is unconditional');
 assert(!setup.includes('continue-on-error'),'installation failures must fail the full gate');
 return deps;
}
const deps=verify(workflow,bootstrap,html);
assert.throws(()=>verify(workflow.replace("      - 'v2/prototype-search/data.js'\n",''),bootstrap,html),/every entry dependency/,'missing data owner detected');
assert.throws(()=>verify(workflow.replace("      - 'v2/prototype-search/source-receipt-v1.js'\n",''),bootstrap,html),/every entry dependency/,'missing receipt owner detected');
assert.throws(()=>verify(workflow,bootstrap.replace("'../prototype-search/data.js'","'../prototype-search/future-owner.js'"),html),/every entry dependency/,'future unregistered owner detected');
assert.throws(()=>verify(workflow.replace("      - 'tests/search3-prototype-*.cjs'\n",''),bootstrap,html),/characterization trigger/,'test-only change detected');
assert.throws(()=>verify(workflow.replace('ready_for_review, ',''),bootstrap,html),/restores/,'missing ready restoration detected');
assert.throws(()=>verify(workflow.replace('    # Drafts retain','    if: ${{ !github.event.pull_request.draft }}\n    # Drafts retain'),bootstrap,html),/every draft/,'disabled draft check detected');
assert.throws(()=>verify(workflow.replace("      - 'scripts/ci/search3_visual_browser_dependencies.cjs'\n",''),bootstrap,html),/preflight triggers/,'setup helper cannot fall outside CI scope');
assert.throws(()=>verify(workflow.replaceAll('          node tests/search3-visual-browser-dependencies.cjs\n',''),bootstrap,html),/regressions run/,'preflight regression removal detected');
assert.throws(()=>verify(workflow.replace("      - 'scripts/ci/search3_visual_evidence_retention.cjs'\n",''),bootstrap,html),/evidence owner triggers/,'archive helper cannot fall outside CI scope');
assert.throws(()=>verify(workflow.replaceAll('          node tests/search3-visual-evidence-retention.cjs\n',''),bootstrap,html),/evidence regressions run/,'archive regression removal detected');
assert.throws(()=>verify(workflow.replace('      - name: Install missing Chromium system dependencies\n        if: ${{ steps.browser-deps.outputs.ready != \'true\' }}','      - name: Install missing Chromium system dependencies\n        if: ${{ steps.browser-deps.outputs.ready == \'false\' }}'),bootstrap,html),/positive package facts/,'missing output cannot bypass stock installation');
assert.throws(()=>verify(workflow.replace(' --prefer-ubuntu-mirror',''),bootstrap,html),/fixed-file helper mode/,'mirror preference cannot silently use a different helper mode');
assert.throws(()=>verify(workflow.replace('      - name: Prefer the existing HTTPS Ubuntu package mirror\n        if: ${{ steps.browser-deps.outputs.ready != \'true\' }}\n','      - name: Prefer the existing HTTPS Ubuntu package mirror\n'),bootstrap,html),/fixed-file helper mode/,'mirror preference cannot run when system packages already satisfy the gate');
assert.throws(()=>verify(workflow.replace('      - name: Install pinned Playwright Chromium\n','      - name: Install pinned Playwright Chromium\n        if: false\n'),bootstrap,html),/unconditional/,'browser installation cannot be skipped');
assert.throws(()=>verify(workflow.replace('      - name: Install pinned Playwright Chromium\n','      - name: Install pinned Playwright Chromium\n        continue-on-error: true\n'),bootstrap,html),/unconditional|failures/,'browser installation cannot hide failures');
assert.throws(()=>verify(workflow.replace('focused-presentation, integration-contracts, browser-matrix','focused-presentation, browser-matrix'),bootstrap,html),/aggregate requires/,'missing contract dependency detected');
assert.throws(()=>verify(workflow.replace('always() && !github.event.pull_request.draft','!github.event.pull_request.draft'),bootstrap,html),/inspect failed/,'skipped failure join detected');
for(const variable of ['FOCUSED_RESULT','CONTRACTS_RESULT','BROWSER_RESULT'])assert.throws(()=>verify(workflow.replace('          test "$'+variable+'" = success\n',''),bootstrap,html),/aggregate rejects/,'every unsuccessful lane blocks final success');
for(const command of rawContracts)assert.throws(()=>verify(workflow.replace('          node '+command+'\n',''),bootstrap,html),/raw contracts|original Node commands/,'moved contract cannot disappear: '+command);
assert.throws(()=>verify(workflow.replace(' --browser\n','\n'),bootstrap,html),/original Node commands/,'actual LOCAL browser variant retained');
assert.throws(()=>verify(workflow.replace(' --messenger-only\n','\n'),bootstrap,html),/original Node commands/,'dedicated messenger variant retained');
assert.throws(()=>verify(workflow.replace('cancel-in-progress: true','cancel-in-progress: false'),bootstrap,html),/obsolete-head/,'obsolete visual heads cannot consume the full matrix');
assert.throws(()=>verify(workflow.replace("      - '.github/workflows/build-search3-whole-site-preview.yml'\n",''),bootstrap,html),/build concurrency dependency/,'build workflow changes retain concurrency validation');
verifyConcurrency(buildWorkflow,'ci-search3-build');
assert.throws(()=>verifyConcurrency(buildWorkflow.replace('cancel-in-progress: true','cancel-in-progress: false'),'ci-search3-build'),/obsolete-head/,'obsolete build heads are cancelled independently');
for(const file of leadSources)assert.throws(()=>verify(workflow.replace("      - '"+file+"'\n",''),bootstrap,html),/every entry dependency/,'generated projection retains canonical source coverage: '+file);
console.log(`PASS visual CI dependency coverage: ${deps.length} actual dependencies; 37 original commands once; parallel exact-source lanes/strict result join; draft restoration/setup/concurrency mutations detected`);
require('./search3-visual-lead-runtime.cjs');
