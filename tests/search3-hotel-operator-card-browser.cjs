'use strict';
const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
module.exports=async function checkOperatorCards(page,width,output){
  const picture='data:image/svg+xml,'+encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="640" height="360" fill="#dce8f4"/><path fill="#b1c9dc" d="M0 240 150 110 310 230 480 90 640 240V360H0Z"/></svg>');
  const base={date:'2026-09-16',price:62400,nights:7,adults:2,childs:0,meal:{name:'BB',fullName:'Завтраки'},roomType:'STANDARD',placement:'DBL',isCharter:true};
  const offers=[
    {...base,id:'brand-funsun-7',operator:{name:'Fun&Sun (RU)'}},
    {...base,id:'brand-anex-10',date:'2026-09-17',nights:10,price:74900,meal:{name:'AI',fullName:'Всё включено'},operator:'ANEX TOUR',isCharter:false},
    {...base,id:'brand-intourist-8',nights:8,price:69500,meal:{name:'HB',fullName:'Полупансион'},operator:'Интурист'},
    {...base,id:'brand-biblio-9',date:'2026-09-18',nights:9,price:71800,meal:{name:'AI',fullName:'Всё включено'},operator:'Библио Глобус',isCharter:false},
    {...base,id:'brand-local-7',nights:7,price:66700,meal:{name:'HB',fullName:'Полупансион'},operator:'LOCAL OPERATOR'},
    {...base,id:'brand-funsun-8',date:'2026-09-17',nights:8,price:68100,meal:{name:'AI',fullName:'Всё включено'},operator:'FUN & SUN',isCharter:false},
    {...base,id:'brand-anex-9',nights:9,price:70300,operator:'Анекс'},
    {...base,id:'brand-intourist-10',date:'2026-09-18',nights:10,price:77200,meal:{name:'AI',fullName:'Всё включено'},operator:'НТК Интурист',isCharter:false},
    {...base,id:'brand-biblio-7',nights:7,price:65500,meal:{name:'HB',fullName:'Полупансион'},operator:'Библио-Глобус'},
    {...base,id:'brand-pegas-8',date:'2026-09-17',nights:8,price:68900,meal:{name:'AI',fullName:'Всё включено'},operator:'Pegas Touristik',isCharter:false},
    {...base,id:'brand-coral-7',nights:7,price:78200,meal:{name:'AI',fullName:'Всё включено'},operator:'CORAL TRAVEL',isCharter:false},
    {...base,id:'brand-sunmar-9',date:'2026-09-18',nights:9,price:78700,meal:{name:'HB',fullName:'Полупансион'},operator:'Санмар'}
  ];
  const hotel={id:'brand-hotel',name:'ARES CITY (EX. KAMI HOTEL)',country:{name:'Турция'},region:{name:'Кемер'},subRegion:{name:'Кемер — центр'},category:3,rating:3,seaDistance:500,picturelink:picture,price:62400,tours:offers};
  const sent=[];const listener=request=>{if(/\/(?:api[^/]*|lead[^/]*)\.php$/.test(new URL(request.url()).pathname)||request.method()!=='GET')sent.push(request.url());};page.on('request',listener);
  try{
    await page.evaluate(h=>{window.dispatchEvent(new CustomEvent('v2:search-reset'));window.V2Runtime.setSearchId(9410);const freeze=v=>{if(v&&typeof v==='object'){Object.values(v).forEach(freeze);Object.freeze(v);}return v;};window.__brandOriginal=freeze(h);window.V2Results.render([window.__brandOriginal]);window.dispatchEvent(new CustomEvent('search3:local-results-filtered',{detail:{items:[window.__brandOriginal]}}));},hotel);
    const card=page.locator('[data-hotel-id="brand-hotel"].hotel-card');
    await card.scrollIntoViewIfNeeded();
    assert.equal(await card.locator('[data-operator-brand],.hotel-operator-logo').count(),0,'collapsed multi-offer hotel does not borrow one concrete operator');
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(),0,'collapsed multi-offer hotel has no concrete offer row, Select, or Compare');
    const collapsedText=await card.innerText();
    assert.doesNotMatch(collapsedText,/16\.09\.2026|17\.09\.2026|18\.09\.2026|7 ноч\.|8 ноч\.|9 ноч\.|10 ноч\.|Завтраки|Полупансион|Всё включено|STANDARD|DBL|Двухместное|Tourvisor|FUN&SUN|ANEX|Интурист|Библио|Coral|Sunmar|Санмар|Чартер|Регулярный рейс/,'collapsed hotel-level surface excludes concrete offer parameters');
    assert.equal(await card.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от62400₽','collapsed multi-offer hotel exposes only the truthful group minimum');
    assert.equal(await card.locator('.tour-more-toggle').count(),1);
    const toggle=card.locator('.tour-more-toggle');
    assert.equal(await toggle.innerText(),'Показать варианты · 12');
    assert.ok((await toggle.boundingBox()).height>=44);
    assert.ok((await toggle.boundingBox()).width<260,'collapsed disclosure is a compact secondary action');
    assert.notEqual(await toggle.evaluate(node=>getComputedStyle(node).backgroundColor),'rgb(216, 61, 0)','collapsed disclosure does not pretend to be a concrete offer CTA');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    const collapsedComposition=[];
    if(width===375||width===1440){
      const viewport=page.viewportSize();
      for(const inspectedWidth of width===375?[320,350,375,390,700]:[1199,1200,1440]){
        await page.setViewportSize({...viewport,width:inspectedWidth});
        const geometry=await card.evaluate(node=>{
          const box=element=>{const r=element.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
          const summary=node.querySelector('.hotel-offers-summary');
          const price=summary.querySelector('.hotel-price');
          return{card:box(node),main:box(node.querySelector('.hotel-main')),photo:box(node.querySelector('.hotel-photo')),summary:box(summary),price:box(price),priceFontSize:parseFloat(getComputedStyle(price).fontSize),priceLineHeight:parseFloat(getComputedStyle(price).lineHeight),toggle:box(summary.querySelector('.tour-more-toggle')),overflow:node.scrollWidth>node.clientWidth+1};
        });
        assert.ok(geometry.toggle.height>=44,`${inspectedWidth}: collapsed disclosure keeps a full touch target`);
        assert.equal(geometry.overflow,false,`${inspectedWidth}: collapsed hotel card stays contained`);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
        assert.ok(geometry.summary.y>=0&&geometry.summary.bottom<=geometry.card.bottom+1,`${inspectedWidth}: hotel-level offer summary stays inside the hotel card`);
        assert.ok(geometry.price.right<=geometry.card.right+1&&geometry.toggle.right<=geometry.card.right+1,`${inspectedWidth}: minimum and disclosure stay within the card`);
        assert.ok(geometry.price.height<=geometry.priceLineHeight+1,`${inspectedWidth}: minimum prefix, amount and currency remain one readable line`);
        assert.ok(geometry.priceFontSize>=22,`${inspectedWidth}: hotel minimum is readable at a glance`);
        if(inspectedWidth<=350)assert.ok(geometry.toggle.y>=geometry.price.bottom,`${inspectedWidth}: narrow disclosure follows the complete minimum price`);
        if(inspectedWidth>=1200){
          assert.ok(geometry.summary.x>=geometry.main.right-1,`${inspectedWidth}: minimum and disclosure form a side block beside the hotel`);
          assert.ok(geometry.toggle.y>=geometry.price.bottom,`${inspectedWidth}: disclosure follows the minimum in the decision block`);
          assert.ok(geometry.photo.height>=200,`${inspectedWidth}: real hotel photo has useful height`);
          assert.ok(geometry.card.height<=240,`${inspectedWidth}: side block avoids the former full-width footer strip`);
        }else assert.ok(geometry.summary.y>=geometry.main.bottom-1,`${inspectedWidth}: summary follows hotel content without overlap`);
        assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle').count(),0,`${inspectedWidth}: no concrete offer leaks into collapsed hotel`);
        collapsedComposition.push({width:inspectedWidth,geometry});
        await card.screenshot({path:path.join(output,`operator-card-collapsed-action-${inspectedWidth}.png`),animations:'disabled'});
      }
      await page.setViewportSize(viewport);
    }
    await card.screenshot({path:path.join(output,`operator-card-collapsed-${width}.png`),animations:'disabled'});
    await toggle.focus();await toggle.press('Enter');
    assert.equal(await card.locator('.hotel-trip-summary').count(),0,'expanded detail is exact offers, not another nested aggregate');
    assert.equal(await card.locator('.tour-row').count(),3,'operator examples start with three exact offers');
    assert.equal(await toggle.evaluate(node=>node===document.activeElement),true);
    for(const count of [6,9,12]){
      await card.locator('.tour-list-more').click();
      assert.equal(await card.locator('.tour-row').count(),count,'all operator examples remain reachable through bounded disclosure');
    }
    await page.waitForFunction(()=>document.querySelectorAll('[data-hotel-id="brand-hotel"] .search3-shortlist-toggle').length===12);
    await page.waitForFunction(()=>{const img=document.querySelector('[data-hotel-id="brand-hotel"] .hotel-operator-logo');return img&&img.complete&&img.naturalWidth>0;});
    assert.equal(await card.locator('.hotel-offers-heading>strong').innerText(),'12 вариантов');
    assert.equal(await toggle.getAttribute('aria-expanded'),'true');
    for(let index=0;index<offers.length;index++){
      const offer=offers[index],action=card.locator('.direct-tour[data-tid="'+offer.id+'"]'),row=action.locator('xpath=ancestor::div[contains(@class,"tour-row")]');
      assert.equal(await row.locator('.direct-tour').getAttribute('data-tid'),offer.id);
      assert.match(await row.locator('.tour-meta>small').innerText(),new RegExp(' · '+offer.nights+' ноч\\.'));
      assert.match(await row.innerText(),new RegExp(offer.isCharter?'Чартер':'Регулярный рейс'));
      assert.doesNotMatch(await row.innerText(),/от \d|7–10|Разные варианты перелёта/);
      assert.doesNotMatch(await row.innerText(),/Источник|Tourvisor/,'expanded exact offer keeps provider provenance out of customer copy');
      assert.doesNotMatch(await row.innerText(),/Туристы|Размещение/,'expanded offer row does not repeat search party or a second placement field');
      assert.match(await row.innerText(),/Стандарт · Двухместное/,'reviewed room and placement labels stay together as one compact exact-offer fact');
      assert.equal(await row.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),String(offer.price)+'₽');
      const identity=await page.evaluate(t=>window.V2Results.operatorIdentity(t),offer),badge=row.locator('.hotel-operator');
      assert.equal(await badge.getAttribute('title'),'Туроператор: '+identity.label,'hover text names the actual operator');
      assert.equal(await row.locator('.tour-operator>small').count(),0,'redundant operator caption is removed');
      if(identity.logo){
        assert.equal(await badge.locator('.hotel-operator-name').count(),0,'brand name is not repeated beside its logo');
        assert.equal(await row.getByRole('img',{name:'Туроператор: '+identity.label,exact:true}).count(),1,'logo has an accessible operator name');
      }else assert.equal(await badge.innerText(),identity.label,'unknown operator retains its visible name');
    }
    const loadedLogos=[];
    for(const brand of ['funsun','anex','intourist','biblio-globus','coral','sunmar','pegas']){
      const logo=card.locator('[data-operator-brand="'+brand+'"] .hotel-operator-logo').first();
      await logo.scrollIntoViewIfNeeded();
      await logo.evaluate(img=>img.decode());
      assert.equal(await logo.evaluate(img=>img.complete&&img.naturalWidth>0),true,'visible original '+brand+' artwork loads');
      const artwork=await logo.evaluate(img=>({src:new URL(img.currentSrc).pathname,width:img.naturalWidth,height:img.naturalHeight,objectFit:getComputedStyle(img).objectFit,renderedWidth:img.getBoundingClientRect().width,renderedHeight:img.getBoundingClientRect().height}));
      if(brand==='coral'||brand==='sunmar'||brand==='pegas'){
        const [file,sourceWidth,sourceHeight]=brand==='coral'?['coral.png',352,212]:brand==='sunmar'?['sunmar.svg',89,51]:['pegas.png',500,105];
        assert.ok(artwork.src.endsWith('/assets/operator-logos/'+file),'new operator loads its local original asset');
        assert.equal(artwork.width,sourceWidth,'original '+brand+' intrinsic width is preserved');
        assert.equal(artwork.height,sourceHeight,'original '+brand+' intrinsic height is preserved');
        assert.equal(artwork.objectFit,'contain','original '+brand+' artwork is contained without cropping');
        assert.ok(artwork.renderedWidth>0&&artwork.renderedHeight>0,'decoded '+brand+' artwork has a visible display box');
      }
      loadedLogos.push({brand,...artwork});
    }
    assert.deepEqual([...new Set(await card.locator('[data-operator-brand]').evaluateAll(nodes=>nodes.map(node=>node.dataset.operatorBrand)))].sort(),['anex','biblio-globus','coral','funsun','intourist','pegas','sunmar']);
    assert.equal(await card.locator('.hotel-operator:not([data-operator-brand]) img').count(),0,'unknown operators keep a text fallback');
    const mobileComposition=[];
    if(width===375){
      const viewport=page.viewportSize();
      for(const inspectedWidth of [320,375,390,430]){
        await page.setViewportSize({...viewport,width:inspectedWidth});
        const rows=await card.locator('.tour-row').evaluateAll(nodes=>nodes.map(node=>{
          const box=element=>{const r=element.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
          const action=node.querySelector('.tour-action'),price=node.querySelector('.hotel-price'),select=node.querySelector('.direct-tour'),compare=node.querySelector('.search3-shortlist-toggle');
          return{row:box(node),operator:box(node.querySelector('.hotel-operator')),action:box(action),price:box(price),select:box(select),compare:box(compare),captionVisible:!!node.querySelector('.tour-action>small')&&getComputedStyle(node.querySelector('.tour-action>small')).display!=='none',overflow:node.scrollWidth>node.clientWidth+1};
        }));
        for(const row of rows){
          assert.equal(row.captionVisible,false,'mobile exact price does not spend a separate row on the redundant caption');
          assert.ok(row.row.height<=(inspectedWidth===320?260:215),`${inspectedWidth}: exact offers stay compact enough to compare without the former 264–317 px rows (${row.row.height})`);
          assert.ok(row.operator.height<=32,'operator identity stays a compact secondary fact, including original logos and unknown-name fallbacks');
          assert.ok(row.select.height>=44&&row.compare.height>=44,'mobile selection and comparison retain full touch targets');
          assert.ok(Math.abs(row.select.y-row.compare.y)<2,'mobile selection and comparison share one action row');
          assert.ok(row.price.right<=row.action.right+1&&row.select.right<=row.action.right+1&&row.compare.right<=row.action.right+1,'mobile price and both actions stay inside the canonical action group');
          assert.ok(row.select.right<=row.compare.x+1,'mobile actions do not overlap');
          if(inspectedWidth>=375)assert.ok(Math.abs((row.price.y+row.price.height/2)-(row.select.y+row.select.height/2))<2,'375/390/430 vertically center price and both actions in one compact row');
          else assert.ok(row.select.y>=row.price.bottom-1,'320 keeps one price row followed by one shared action row');
          assert.equal(row.overflow,false,'mobile exact offer row has no overflow');
        }
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
        mobileComposition.push({width:inspectedWidth,rows});
        await card.locator('.tour-row').first().screenshot({path:path.join(output,`operator-card-mobile-action-${inspectedWidth}.png`),animations:'disabled'});
        if(inspectedWidth===375||inspectedWidth===430)for(const brand of ['coral','sunmar','pegas'])await card.locator('.tour-row').filter({has:page.locator('[data-operator-brand="'+brand+'"]')}).screenshot({path:path.join(output,`operator-card-${brand}-${inspectedWidth}.png`),animations:'disabled'});
      }
      await page.setViewportSize(viewport);
    }
    const desktopComposition=[];
    if(width===1440){
      const viewport=page.viewportSize();
      for(const inspectedWidth of [1024,1199,1200,1440]){
        await page.setViewportSize({...viewport,width:inspectedWidth});
        const rows=await card.locator('.tour-row').evaluateAll(nodes=>nodes.map(node=>{
          const box=element=>{const r=element.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
          const action=node.querySelector('.direct-tour'),compare=node.querySelector('.search3-shortlist-toggle');
          return{row:box(node),date:box(node.querySelector('.tour-meta>strong')),caption:box(node.querySelector('.tour-meta>small')),facts:box(node.querySelector('.tour-facts')),select:box(action),compare:compare?box(compare):null,price:box(node.querySelector('.tour-action')),overflow:node.scrollWidth>node.clientWidth+1};
        }));
        for(const row of rows){
          assert.equal(row.overflow,false,'all exact offer facts remain within their row');
          assert.ok(row.select.height>=44&&(!row.compare||row.compare.height>=44),'selection and comparison retain usable targets');
          assert.ok(row.price.right<=row.row.right&&row.select.right<=row.price.right+1,'price/action group is contained');
          if(inspectedWidth>=1200){
            assert.ok(row.facts.x>=row.date.right-1&&Math.abs(row.facts.y-row.date.y)<2,'date and primary conditions share one desktop reading row');
            assert.ok(row.caption.y>=row.date.bottom-1&&Math.abs(row.caption.x-row.date.x)<2,'departure caption follows its actual date in the same column');
            if(row.compare)assert.ok(row.compare.x>=row.select.right-1&&Math.abs(row.compare.y-row.select.y)<2,'selection and comparison share one action row');
          }
          if(inspectedWidth===1440)assert.ok(row.row.height<=160,'compact heterogeneous desktop offer stays below the former tall nested action card');
        }
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
        desktopComposition.push({width:inspectedWidth,rows});
        await card.screenshot({path:path.join(output,`operator-card-composition-${inspectedWidth}.png`),animations:'disabled'});
        if(inspectedWidth===1024||inspectedWidth===1440)for(const brand of ['coral','sunmar','pegas'])await card.locator('.tour-row').filter({has:page.locator('[data-operator-brand="'+brand+'"]')}).screenshot({path:path.join(output,`operator-card-${brand}-${inspectedWidth}.png`),animations:'disabled'});
      }
      await page.setViewportSize(viewport);
    }
    await card.screenshot({path:path.join(output,`operator-card-expanded-${width}.png`),animations:'disabled'});
    await toggle.press('Space');
    assert.equal(await toggle.getAttribute('aria-expanded'),'false');
    assert.equal(await toggle.evaluate(node=>node===document.activeElement),true);
    assert.equal(await card.locator('.tour-row,.direct-tour,.search3-shortlist-toggle,[data-operator-brand]').count(),0,'collapsing returns to a hotel-only surface');
    assert.equal(await card.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от62400₽');
    assert.equal(await page.evaluate(()=>JSON.stringify(window.__brandOriginal)),JSON.stringify(hotel));
    if(width===375){
      const viewport=page.viewportSize();
      const longPriceHotel={...hotel,id:'brand-long-price',price:1234567.89,tours:Array.from({length:100},(_,i)=>({...base,id:'long-'+i,price:1234567.89+i}))};
      await page.evaluate(h=>window.V2Results.render([h]),longPriceHotel);
      const longCard=page.locator('.hotel-card[data-hotel-id="brand-long-price"]');
      for(const inspectedWidth of [320,375,1200,1440]){
        await page.setViewportSize({...viewport,width:inspectedWidth});
        const geometry=await longCard.evaluate(node=>{
          const summary=node.querySelector('.hotel-offers-summary'),price=summary.querySelector('.hotel-price'),toggle=summary.querySelector('.tour-more-toggle');
          const card=node.getBoundingClientRect(),p=price.getBoundingClientRect(),t=toggle.getBoundingClientRect();
          return{contained:p.right<=card.right+1&&t.right<=card.right+1&&t.bottom<=card.bottom+1,priceHeight:p.height,lineHeight:parseFloat(getComputedStyle(price).lineHeight),targetHeight:t.height};
        });
        assert.equal(await longCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от1234567,89₽');
        assert.equal(await longCard.locator('.tour-more-toggle').innerText(),'Показать варианты · 100');
        assert.ok(geometry.contained&&geometry.priceHeight<=geometry.lineHeight+1&&geometry.targetHeight>=44,`${inspectedWidth}: long minimum and three-digit offer count stay readable and contained`);
        if(inspectedWidth>=1200)assert.ok(geometry.targetHeight<=45,`${inspectedWidth}: a three-digit offer count fits one desktop disclosure line`);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
        await longCard.screenshot({path:path.join(output,`operator-card-long-minimum-${inspectedWidth}.png`),animations:'disabled'});
      }
      await page.setViewportSize(viewport);
    }
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
    const operatorField=page.locator('.search3-operator-filter'),operator=operatorField.locator('select');
    if(width<1025){const panel=page.locator('.search3-mobile-filter-panel');if(!(await panel.evaluate(node=>node.open)))await panel.locator('summary').click();}
    assert.equal(await operatorField.isVisible(),true,'one loaded hotel with several real operators exposes a useful local choice');
    const operatorChoices=await operator.locator('option').evaluateAll(nodes=>nodes.map(node=>[node.value,node.textContent]));
    assert.deepEqual(operatorChoices,[['','Все туроператоры'],['anex','ANEX'],['funsun','FUN&SUN'],['name:local operator','LOCAL OPERATOR']],'facet reuses reviewed display identities and retains unknown labels without new guesses');
    assert.equal(await page.locator('.search3-provider-filter').count(),0,'provider provenance is not exposed as a customer filter');
    await operator.selectOption('anex');
    const aliasCard=page.locator('.hotel-card[data-hotel-id="operator-alias-hotel"]');
    assert.deepEqual(await page.evaluate(()=>['alias-tv','alias-direct','same-source-other-operator','unknown-brand'].map(id=>window.V2Results.offerAlternatives(id)?.count||0)),[2,2,0,0],'ANEX aliases match across provider sources while other operators remain excluded');
    assert.equal(await aliasCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от72000₽','hotel-level minimum comes from the same locally matched offers');
    await page.evaluate(()=>window.V2Results.rerender());
    assert.equal(await operator.inputValue(),'anex','canonical choice survives ordinary renderer updates');
    await page.screenshot({path:path.join(output,`operator-alias-facet-${width}.png`),fullPage:true});
    assert.deepEqual(await page.evaluate(()=>['alias-tv','alias-direct','same-source-other-operator'].map(id=>window.V2Results.offerAlternatives(id)?.count||0)),[2,2,0],'operator matching remains provider-neutral for the same real tour operator');
    assert.equal(await aliasCard.locator('.hotel-price').innerText().then(t=>t.replace(/\s/g,'')),'от72000₽');
    assert.equal(await page.evaluate(()=>window.V2Results.state.items[0]===window.__operatorAliasOriginal),true,'projection keeps the canonical source object');
    assert.equal(await page.evaluate(()=>JSON.stringify(window.__operatorAliasOriginal)),JSON.stringify(aliasHotel),'operator filtering never mutates provider identity, original labels, offers or prices');
    await page.evaluate(()=>window.V2Results.render([{...window.__operatorAliasOriginal,tours:[...window.__operatorAliasOriginal.tours,{id:'missing-operator',provider:'anex',price:60000}]}]));
    assert.equal(await operatorField.isVisible(),false,'provider name cannot fill incomplete operator data');
    assert.equal(await operator.inputValue(),'','incomplete data clears the old local operator choice');
    await page.evaluate(()=>window.V2Results.render([{...window.__operatorAliasOriginal,tours:window.__operatorAliasOriginal.tours.slice(0,2)}]));
    assert.equal(await operatorField.isVisible(),false,'two spellings of one operator do not invent a second facet choice');
    assert.deepEqual(sent,[],'local disclosure sends no supplier, lead, or other mutation requests');
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false);
    fs.writeFileSync(path.join(output,`operator-card-${width}.json`),JSON.stringify({width,collapsedComposition,mobileComposition,desktopComposition,collapsed_operator:null,collapsed_exact_offer:null,hotel_level_only:true,compact_exact_offer_rows:true,known_logo_coverage:['funsun','anex','intourist','biblio-globus','coral','sunmar','pegas'],loadedLogos,exact_nights:[7,10,8,9,7,8,9,10,7,8,7,9],meal_variants:3,flight_variants:['charter','regular'],exact_offer_count:12,operatorChoices,aliasMatches:[2,2,0,0],providerFacet:false,sourceUnchanged:true,incompleteReset:true,supplier_calls:0,leads:0,fixture:true,physical_safari:'deferred'},null,2)+'\n');
  }finally{page.off('request',listener);}
};
