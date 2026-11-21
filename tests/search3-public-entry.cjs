'use strict';
// Source graph contracts only. PHP rendering is exercised by the companion real-PHP suite.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {inventory,stylesheetInventory}=require('../scripts/build/search3-js/visual-entry.cjs');
const root=path.resolve(__dirname,'../v2'),entry=fs.readFileSync(path.join(root,'visual-search/entry-v1.php'),'utf8'),html=fs.readFileSync(path.join(root,'visual-search/index.html'),'utf8');
const expectedLive=['../prototype-search/config.js','../runtime-v3.js','../lead-search-context.js','./lead-runtime-v1.js','../search3-canonical-profiles-v1.js','../search3-local-db-provider-v1.js','../prototype-search/data.js','../prototype-search/calendar-exact-hotel-v1.js','../prototype-search/source-receipt-v1.js','../prototype-search/search-lifecycle-v1.js','../prototype-search/lead.js','../prototype-search/hotel-popularity-v1.js','./live-bridge.js','./flight-picker-v18.js','../prototype-search/messenger-context-v1.js','../prototype-search/messenger-flow-v1.js','./filter-panel-v1.js','./app.js'];
const expectedOffline=['./fixture-data.js','./local-db-parser.js','./recorded-data.js','../prototype-search/search-lifecycle-v1.js','./flight-picker-v18.js','./preview-lead.js','./filter-panel-v1.js','./app.js'];
const graphs=inventory(root);
assert.deepEqual(graphs.live,expectedLive,'accepted preview LIVE owners/order preserved');
assert.deepEqual(graphs.offline,expectedOffline,'accepted preview OFFLINE owners/order preserved');
assert.deepEqual(graphs.ondemand,['./offer-list-v1.js','./hotel-details-v1.js','./flight-picker-ui-v1.js']);
assert.deepEqual(stylesheetInventory(root).initial,['./styles.css','./mobile-controls-v1.css','../site-header-v2.css']);
assert.deepEqual(graphs.public_live,['./public-config-v1.js','../runtime-v3.js','../analytics-v4.js',...expectedLive.slice(2,-1),'./public-url-v1.js','./public-bootstrap-v1.js','./public-analytics-v1.js','./app.js']);
assert.deepEqual(graphs.public_review,['./public-config-v1.js',...expectedOffline.slice(0,-1),'./public-url-v1.js','./public-bootstrap-v1.js','./app.js']);
assert(!graphs.public_review.some(value=>/analytics|prototype-search\/config|live-bridge/.test(value)));
for(const graph of Object.values(graphs))assert.equal(new Set(graph).size,graph.length,'no duplicate script owner');
const preview=fs.readFileSync(path.join(root,'visual-search/index.php'),'utf8'),publicEntry=fs.readFileSync(path.join(root,'visual-search/public-entry-v1.php'),'utf8');
assert(preview.includes("if (!str_starts_with((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/_preview/search3-next-candidate/visual-search/'))"));
assert(preview.indexOf('http_response_code(403)')<preview.indexOf("require __DIR__ . '/entry-v1.php'"),'guard precedes renderer include');
assert(!/\$_GET|\$_REQUEST/.test(publicEntry+entry),'public profile cannot originate from a query');
assert(!/\$_SERVER\['SCRIPT_NAME'\]\s*=/.test(publicEntry),'no preview guard spoof');
assert(!/<base\b/i.test(entry),'resource rewriting leaves local anchors/history untouched');
assert(publicEntry.includes("$build['profile'] !== 'review'"),'whole-site prefix cannot admit live');
assert(entry.includes("require_once dirname(__DIR__) . '/site-header-v2.php';"),'Search3 uses the canonical PHP header owner');
assert(entry.includes("v2_render_site_header($phone, v2_phone_href($phone), '/poisk-turov/', true);"));
assert.equal((html.match(/data-site-header-slot/g)||[]).length,1);
assert(!html.includes('class="compact-brand"'),'compact route summary has no repeated logo');
const app=fs.readFileSync(path.join(root,'visual-search/app.js'),'utf8'),start=app.indexOf('function handleSearchParameterAction('),end=app.indexOf('\nfunction ',start+1);
assert(start>=0&&end>start);
const vm=require('node:vm'),events=[],context={editSearch(){events.push('edit');}};
vm.createContext(context);vm.runInContext(app.slice(start,end),context);
for(const action of ['top','edit-search']){
 events.length=0;
 const menu={removeAttribute(name){assert.equal(name,'open');events.push('close');}};
 const current={closest(selector){assert.equal(selector,'.at-global-header__mobile');return menu;}};
 assert.equal(context.handleSearchParameterAction(action,current,0),true);
 assert.deepEqual(events,['close','edit'],'current search closes its own mobile menu then calls the existing edit owner');
 events.length=0;context.handleSearchParameterAction(action,{closest(){return null;}},0);
 assert.deepEqual(events,['edit'],'existing search controls outside the header keep their edit path');
}
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'search3-entry-graph-'));
try{
 fs.mkdirSync(path.join(temp,'visual-search'));
 fs.writeFileSync(path.join(temp,'visual-search/index.html'),html);
 for(const changed of [
  entry.replace("'../runtime-v3.js'","$dynamicRuntime"),
  entry.replace("'./public-bootstrap-v1.js'","'./unknown.js'"),
  entry.replace("$publicAnalyticsScript = '../analytics-v4.js';","$publicAnalyticsScript = '../new-analytics.js';"),
  entry.replace("'../lead-search-context.js'","'../runtime-v3.js'"),
 ]){
  assert.notEqual(changed,entry);fs.writeFileSync(path.join(temp,'visual-search/entry-v1.php'),changed);
  assert.throws(()=>inventory(temp),'graph drift cannot silently change a public payload');
 }
}finally{fs.rmSync(temp,{recursive:true,force:true});}
console.log('PASS public entry source graphs: preview18/offline8/lazy3 unchanged; publicLIVE22/review11; exact guards, no GET profile, graph drift rejected');
