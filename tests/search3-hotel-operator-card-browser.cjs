'use strict';
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
module.exports=async function checkOperatorCards(page,width,output){
  const picture='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="640" height="360" fill="#dce8f4"/><path fill="#b1c9dc" d="M0 240 150 110 310 230 480 90 640 240V360H0Z"/></svg>');
  const base={date:'2026-09-16',price:62400,nights:7,adults:2,childs:0,meal:{name:'BB',fullName:'Завтраки'},roomType:'STANDARD',placement:'DBL',isCharter:true};
  const offers=[{...base,id:'brand-funsun',operator:{name:'Fun&Sun (RU)'}},{...base,id:'brand-anex',nights:10,price:74900,meal:{name:'AI',fullName:'Всё включено'},operator:'ANEX TOUR',isCharter:false},{...base,id:'brand-intourist',nights:8,price:69500,operator:'Интурист'}];
  const hotel={id:'brand-hotel',name:'ARES CITY (EX. KAMI HOTEL)',country:{name:'Турция'},region:{name:'Кемер'},subRegion:{name:'Кемер — центр'},category:3,rating:3,seaDistance:500,picturelink:picture,price:62400,tours:offers};
  const sent=[];const listener=request=>{if(/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)||request.method()!=='GET')sent.push(request.url());};page.on('request',listener);
  try{
    await page.evaluate(h=>{window.dispatchEvent(new CustomEvent('v2:search-reset'));const freeze=v=>{if(v&&typeof v==='object'){Object.values(v).forEach(freeze);Object.freeze(v);}return v;};window.__brandOriginal=freeze(h);window.V2Results.render([window.__brandOriginal]);},hotel);
    const card=page.locator('[data-hotel-id="brand-hotel"].hotel-card');
    const logos=card.locator('.hotel-operator-logo');
    await card.scrollIntoViewIfNeeded();
    await page.waitForFunction(()=>Array.from(document.querySelectorAll('.hotel-operator-logo')).length===3&&Array.from(document.querySelectorAll('.hotel-operator-logo')).every(img=>img.complete&&img.naturalWidth>0));
    assert.deepEqual(await card.locator('[data-operator-brand]').evaluateAll(nodes=>nodes.map(node=>node.dataset.operatorBrand)),['funsun','anex','intourist']);
    assert.equal(await logos.evaluateAll(nodes=>nodes.every(n=>new URL(n.src).origin===location.origin)),true,'brand artwork is local, not hotlinked');
    assert.equal(await card.locator('.direct-tour,.tour-row').count(),0,'collapsed hotel does not pretend to be a specific tour');
    assert.match(await card.locator('.hotel-trip-summary').innerText(),/7–10 ноч\./);
    assert.match(await card.locator('.hotel-trip-summary').innerText(),/Возможны чартеры/);
    assert.match(await card.locator('.hotel-trip-summary').innerText(),/Завтрак · Всё включено/);
    assert.equal(await card.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от62400₽');
    assert.equal(await card.locator('.tour-more-toggle').count(),1);
    const toggle=card.locator('.tour-more-toggle');
    assert.ok((await toggle.boundingBox()).height>=44);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    await card.screenshot({path:path.join(output,`operator-card-collapsed-${width}.png`),animations:'disabled'});
    await toggle.focus();await toggle.press('Enter');
    assert.equal(await card.locator('.hotel-trip-summary').count(),0,'expanded detail is exact offers, not another nested aggregate');
    assert.equal(await card.locator('.tour-row').count(),3);
    assert.equal(await toggle.getAttribute('aria-expanded'),'true');
    assert.equal(await toggle.evaluate(node=>node===document.activeElement),true);
    const rows=card.locator('.tour-row');
    for(let index=0;index<offers.length;index++){
      const row=rows.nth(index),offer=offers[index];
      assert.equal(await row.locator('.direct-tour').getAttribute('data-tid'),offer.id);
      assert.match(await row.locator('.tour-meta>small').innerText(),new RegExp(' · '+offer.nights+' ноч\\.'));
      assert.doesNotMatch(await row.innerText(),/от \d|7–10|Возможны чартеры/);
      assert.equal(await row.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),String(offer.price)+'₽');
    }
    await card.screenshot({path:path.join(output,`operator-card-expanded-${width}.png`),animations:'disabled'});
    await toggle.press('Space');
    assert.equal(await toggle.getAttribute('aria-expanded'),'false');
    assert.equal(await toggle.evaluate(node=>node===document.activeElement),true);
    assert.equal(await card.locator('.direct-tour').count(),0);
    assert.equal(await page.evaluate(()=>JSON.stringify(window.__brandOriginal)),JSON.stringify(hotel));
    // A single concrete Biblio-Globus offer has a real operator logo and no minimum prefix.
    await page.evaluate(({hotel,base})=>window.V2Results.render([{...hotel,id:'brand-single',tours:[{...base,id:'brand-bg',operator:'Библио Глобус'}]}]),{hotel,base});
    const single=page.locator('.hotel-card[data-hotel-id="brand-single"]');
    await page.waitForFunction(()=>{const img=document.querySelector('[data-operator-brand="biblio-globus"] img');return img&&img.complete&&img.naturalWidth>0;});
    assert.equal(await single.locator('.hotel-trip-summary,.tour-more-toggle').count(),0);
    assert.equal(await single.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'62400₽');
    await single.screenshot({path:path.join(output,`operator-card-single-${width}.png`),animations:'disabled'});
    const aliasHotel={...hotel,id:'operator-alias-hotel',price:65000,tours:[
      {...base,id:'alias-tv',price:75000,operator:'ANEX TOUR',provider:'tourvisor'},
      {...base,id:'alias-direct',price:72000,operator:'Анекс',provider:'anex',selectionEnabled:false},
      {...base,id:'same-source-other-operator',price:65000,operator:'Fun & Sun',provider:'anex',selectionEnabled:false},
      {...base,id:'unknown-brand',price:81000,operator:'LOCAL OPERATOR',provider:'tourvisor'}
    ]};
    await page.evaluate(h=>{const freeze=v=>{if(v&&typeof v==='object'){Object.values(v).forEach(freeze);Object.freeze(v);}return v;};window.__operatorAliasOriginal=freeze(h);window.V2Results.render([h]);},aliasHotel);
    const operatorField=page.locator('.search3-operator-filter'),operator=operatorField.locator('select'),provider=page.locator('.search3-provider-filter select');
    if(width<1025){const panel=page.locator('.search3-mobile-filter-panel');if(!(await panel.evaluate(node=>node.open)))await panel.locator('summary').click();}
    assert.equal(await operatorField.isVisible(),true,'one loaded hotel with several real operators exposes a useful local choice');
    const operatorChoices=await operator.locator('option').evaluateAll(nodes=>nodes.map(node=>[node.value,node.textContent]));
    assert.deepEqual(operatorChoices,[['','Все туроператоры'],['anex','ANEX'],['funsun','FUN&SUN'],['name:local operator','LOCAL OPERATOR']],'facet reuses reviewed display identities and retains unknown labels without new guesses');
    await operator.selectOption('anex');
    const aliasCard=page.locator('.hotel-card[data-hotel-id="operator-alias-hotel"]');
    assert.deepEqual(await page.evaluate(()=>['alias-tv','alias-direct','same-source-other-operator','unknown-brand'].map(id=>window.V2Results.offerAlternatives(id)?.count||0)),[2,2,0,0],'ANEX aliases match across provider sources while other operators remain excluded');
    assert.equal(await aliasCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от72000₽','representative price comes from the same locally matched offers');
    await page.evaluate(()=>window.V2Results.rerender());
    assert.equal(await operator.inputValue(),'anex','canonical choice survives ordinary renderer updates');
    await page.screenshot({path:path.join(output,`operator-alias-facet-${width}.png`),fullPage:true});
    await provider.selectOption('anex');
    assert.deepEqual(await page.evaluate(()=>['alias-tv','alias-direct','same-source-other-operator'].map(id=>window.V2Results.offerAlternatives(id)?.count||0)),[0,1,0],'provider and operator are independent conditions on the same exact offer');
    assert.equal(await aliasCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'72000₽');
    assert.equal(await page.evaluate(()=>window.V2Results.state.items[0]===window.__operatorAliasOriginal),true,'projection keeps the canonical source object');
    assert.equal(await page.evaluate(()=>JSON.stringify(window.__operatorAliasOriginal)),JSON.stringify(aliasHotel),'operator filtering never mutates provider identity, original labels, offers or prices');
    await provider.selectOption('');
    await page.evaluate(()=>window.V2Results.render([{...window.__operatorAliasOriginal,tours:[...window.__operatorAliasOriginal.tours,{id:'missing-operator',provider:'anex',price:60000}]}]));
    assert.equal(await operatorField.isVisible(),false,'provider name cannot fill incomplete operator data');
    assert.equal(await operator.inputValue(),'','incomplete data clears the old local operator choice');
    await page.evaluate(()=>window.V2Results.render([{...window.__operatorAliasOriginal,tours:window.__operatorAliasOriginal.tours.slice(0,2)}]));
    assert.equal(await operatorField.isVisible(),false,'two spellings of one operator do not invent a second facet choice');
    assert.deepEqual(sent,[],'local disclosure sends no supplier, lead, or other mutation requests');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    fs.writeFileSync(path.join(output,`operator-card-${width}.json`),JSON.stringify({width,logos:['funsun','anex','intourist','biblio-globus'],exact_nights:[7,10,8],operatorChoices,aliasMatches:[2,2,0,0],providerIntersection:[0,1,0],sourceUnchanged:true,incompleteReset:true,supplier_calls:0,leads:0,fixture:true,physical_safari:'deferred'},null,2)+'\n');
  }finally{page.off('request',listener);}
};
