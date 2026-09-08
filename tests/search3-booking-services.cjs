/* Retired duplicate services card must not return to the public Search3 asset. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const bundle=fs.readFileSync(process.argv[2]||path.join(__dirname,'../v2/search3-results-filters-v1.js'),'utf8');
for(const marker of ['search3-final-sections','Состав размещения у туроператора','Топливный сбор тура','search3-lead-protection'])assert.ok(!bundle.includes(marker),'retired duplicate marker: '+marker);
const controller=fs.readFileSync(path.join(__dirname,'../v2/tour-controller-v4.js'),'utf8');
for(const marker of ['placement','fuelCharge','baggage'])assert.ok(controller.includes(marker),'original selected fact remains: '+marker);
console.log('PASS: duplicate services/note layer absent; original selected tour facts remain');
