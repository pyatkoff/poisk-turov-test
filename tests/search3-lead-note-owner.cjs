/* Native lead fields continue without a duplicate note injector. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const bundle=fs.readFileSync(process.argv[2]||path.join(__dirname,'../v2/search3-results-filters-v1.js'),'utf8');
assert.ok(!bundle.includes('search3-lead-protection'));
assert.ok(!bundle.includes('Мы отправим выбранный тур менеджеру'));
const lead=fs.readFileSync(path.join(__dirname,'../v2/lead-form-guard-v1.js'),'utf8');
for(const marker of ['input[name="phone"]','selectionSummary','v2:lead-started','v2:lead-success','v2:lead-error'])assert.ok(lead.includes(marker),'lead owner remains: '+marker);
console.log('PASS: duplicate lead note absent; lead form lifecycle owner remains');
