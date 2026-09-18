'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const crypto=require('node:crypto');
global.window={addEventListener(){},dispatchEvent(){},requestAnimationFrame(fn){fn();}};
global.document={readyState:'loading',documentElement:{dataset:{}},body:null,addEventListener(){},getElementById(){return null;},querySelector(){return null;}};
global.CustomEvent=function CustomEvent(){};
require('../v2/results-renderer-v5.js');
const api=window.V2Results;
const freeze=value=>{if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}return value;};
const tour=freeze({id:'first',date:'2026-09-16',nights:7,price:62400,meal:{name:'BB',fullName:'Завтраки'},roomType:'STANDARD',placement:'DBL',adults:2,childs:0,isCharter:true,operator:{name:'Fun&Sun (RU)'}});
const other=freeze({...tour,id:'second',nights:10,price:74900,meal:{name:'AI',fullName:'Всё включено'},operator:'ANEX TOUR',isCharter:false});
const hotel=freeze({id:'hotel',name:'Проверочный отель',price:62400,tours:[tour,other]});
const original=JSON.stringify(hotel);
const summary=api.toursHtml(hotel);
assert.match(summary,/hotel-offers-summary/);
assert.doesNotMatch(summary,/class="tour-row"|data-tid=|data-operator-brand=|Итого за тур|Чартер/,'collapsed minimum-offer facts never borrow selection, operator or flight actions');
assert.match(summary,/Вылет <b>16\.09\.2026<\/b>/);
assert.match(summary,/Ночей <b>7<\/b>/);
assert.match(summary,/Питание <b>Завтраки<\/b>/);
assert.equal((summary.match(/class="hotel-price"/g)||[]).length,1);
assert.match(summary,/от 62(?:\s| )?400/);
assert.match(summary,/Показать варианты · 2/);
for(const offer of [tour,other]){
  const row=api.tourRow(offer);
  assert.doesNotMatch(row,/от |7–10|Разные варианты перелёта|Завтрак · Всё включено/,'individual offer never inherits aggregate fields');
  assert.match(row,new RegExp(' · '+offer.nights+' ноч\\.'));
  assert.ok(row.includes('data-tid="'+offer.id+'"'));
  assert.ok(row.includes(api.money(offer.price)));
  assert.ok(row.includes('<b>'+(offer===tour?'Завтраки':'Всё включено')+'</b>'),'supplier-provided meal labels remain exact');
  assert.match(row,new RegExp(offer.isCharter?'Чартер':'Регулярный рейс'));
  assert.equal((row.match(/class="hotel-price"/g)||[]).length,1);
  assert.match(row,/<small>Номер<\/small><b>STANDARD · Двухместное<\/b>/,'exact supplier room and reviewed placement stay together as one offer fact');
  assert.doesNotMatch(row,/Источник|Tourvisor/,'exact offer keeps provider provenance out of customer copy');
  assert.doesNotMatch(row,/<small>(?:Туристы|Размещение)<\/small>/,'exact offer does not repeat search party or a second placement field');
  assert.doesNotMatch(row,/<small>Оператор<\/small>|class="hotel-operator-name"/,'known operator is represented by its logo without duplicate visible captions');
  const description='Туроператор: '+api.operatorIdentity(offer).label.replace(/&/g,'&amp;');
  assert.ok(row.includes('title="'+description+'"'),'operator tooltip uses the canonical brand name');
  assert.ok(row.includes('alt="'+description+'"'),'logo retains an accessible operator name');
}
assert.equal(api.operatorIdentity({provider:'anex'}),null,'provider is not tour operator');
assert.equal(api.operatorIdentity({operator:'ANEX SERVICES'}).logo,'','unknown similar name is not branded');
assert.equal(api.operatorIdentity({operator:'НТК Интурист'}).key,'intourist');
assert.equal(api.operatorIdentity({operator:'Библио Глобус'}).key,'biblio-globus');
assert.deepEqual(api.operatorIdentity({operator:'Biblioglobus'}),{key:'biblio-globus',label:'Библио-Глобус',logo:'biblio-globus.svg'},'exact supplier alias uses the canonical Biblio-Globus identity');
for(const [key,label,logo,aliases] of [
  ['coral','Coral Travel','coral.png',['Coral','CORAL TRAVEL','Корал','Корал Тревел']],
  ['sunmar','Sunmar','sunmar.svg',['SUNMAR','Санмар']],
  ['pegas','Pegas Touristik','pegas.png',['PEGAS TOURISTIK','Pegas','Пегас','Пегас Туристик']]
]){
  for(const operator of aliases)assert.deepEqual(api.operatorIdentity({operator}),{key,label,logo},operator+' uses its exact original brand artwork');
  assert.equal(api.operatorIdentity({provider:key}),null,'provider alone cannot invent '+label+' operator identity');
  assert.equal(api.operatorIdentity({operator:label+' Partner'}).logo,'','similar unknown operator names do not borrow '+label+' artwork');
  const row=api.tourRow({...tour,operator:aliases[0]});
  assert.match(row,new RegExp('data-operator-brand="'+key+'"'));
  assert.ok(row.includes('assets/operator-logos/'+logo),'known operator points to its shipped original asset');
  assert.ok(row.includes('title="Туроператор: '+label+'"')&&row.includes('alt="Туроператор: '+label+'"'),'new artwork preserves accessible operator identity');
  assert.doesNotMatch(row,/class="hotel-operator-name"|<small>Оператор<\/small>/,'known operator logo has no duplicated visible caption');
}
const fallback=api.tourRow({...tour,operator:'<img onerror="bad()">'});
assert.doesNotMatch(fallback,/<img onerror/);
assert.match(fallback,/&lt;img/,'unknown operator names remain escaped');
assert.match(fallback,/class="hotel-operator-name">&lt;img onerror=&quot;bad\(\)&quot;&gt;<\/span>/,'unknown operators retain their escaped visible name');
assert.match(fallback,/title="Туроператор: &lt;img onerror=&quot;bad\(\)&quot;&gt;"/,'operator tooltip escapes untrusted attribute content');
assert.doesNotMatch(api.tourRow({...tour,provider:'andromeda',selectionEnabled:false}),/class="direct-tour"/,'existing provider selection guard stays authoritative');
const unverified=freeze({...tour,provider:'andromeda',selectionEnabled:false,offerRef:'fuel-check',providerDetail:{eligible:true,open:true,status:'complete',data:{hotel:'Проверочный отель',price:62400}}});
const unverifiedRow=api.tourRow(unverified);
assert.match(unverifiedRow,/<small>Цена из поиска<\/small>/,'SAMO listing is not labelled as a verified total');
assert.doesNotMatch(unverifiedRow,/Итого за тур|Топливный сбор включён/);
assert.match(unverifiedRow,/Топливный сбор может потребовать доплаты/,'unknown fuel inclusion is disclosed without inventing a surcharge amount');
const inclusiveRow=api.tourRow({...unverified,fuelIncluded:true});
assert.match(inclusiveRow,/Топливный сбор учтён/);
assert.doesNotMatch(inclusiveRow,/может потребовать доплаты/);
assert.ok(unverifiedRow.includes(api.money(unverified.price)),'listing price is preserved without client-side arithmetic');
assert.match(api.tourRow(tour),/<small>Итого за тур<\/small>/,'other provider presentation is unchanged');
assert.equal(JSON.stringify(hotel),original,'frozen original identities, dates, prices and parameters are unchanged');
const assets=path.join(__dirname,'../v2/assets/operator-logos');
const provenance=JSON.parse(fs.readFileSync(path.join(assets,'provenance.json')));
assert.equal(provenance.artwork_modified,false);
assert.equal(provenance.assets.length,7);
assert.deepEqual(provenance.assets.map(asset=>asset.file).sort(),['anex.svg','biblio-globus.svg','coral.png','funsun.svg','intourist.png','pegas.png','sunmar.svg'],'all seven rendered brands have original artwork provenance');
for(const asset of provenance.assets){
  const data=fs.readFileSync(path.join(assets,asset.file));
  assert.equal(crypto.createHash('sha256').update(data).digest('hex'),asset.sha256,asset.file+' matches stored artwork provenance');
  assert.equal(data.length,asset.size);
  if(asset.file.endsWith('.svg')){
    const text=data.toString();
    assert.match(text,/<svg\b/);
    assert.doesNotMatch(text,/\r/,'stored SVG uses LF line endings');
    assert.doesNotMatch(text,/[ \t]+$/m,'stored SVG has no trailing whitespace');
    assert.doesNotMatch(text,/<(?:script|foreignObject|iframe|image)\b|\son\w+\s*=|(?:href|xlink:href)\s*=\s*["'](?:https?:|\/\/|data:)|url\(\s*["']?https?:/i,'brand SVG must be passive and self-contained');
  }else assert.equal(data.subarray(0,8).toString('hex'),'89504e470d0a1a0a');
}
console.log('SEARCH3_OPERATOR_CARDS_OK collapsed_hotel_level=1 compact_exact_offers=1 brands=7 provider_not_operator=1 frozen_source=1');
