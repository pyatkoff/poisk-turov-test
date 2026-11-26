'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {canonicalLogoFixture} = require('./search3-visual-live-fixture.cjs');

const panelFile = 'v2/visual-search/filter-panel-v1.js';
const baselineBlob = '441f37c2ffc1619cb612230a05cc4b0addf7559a';
const viewport = {width:1280,height:720};

function measurePanel() {
  const mark = window.desktopFilterTiming = {};
  let adapter, factory;
  Object.defineProperty(window,'AnyTourPrototypeData',{
    configurable:true,
    get:()=>adapter,
    set(owner) {
      const descriptors=Object.getOwnPropertyDescriptors(owner);
      descriptors.search.value=function(criteria,emit,...rest) {
          return owner.search(criteria,event=>{
            if (mark.received!==undefined || event.type!=='results' || !event.hotels?.length) return emit(event);
            const seed = event.hotels.find(hotel=>hotel.offers?.length), example = seed.offers[0];
            const hotels = Array.from({length:120},(_,index)=>{
              const id=seed.id+index;
              const amenities=[...(seed.amenities||[]),...Array.from({length:8},(_,group)=>{
                const option=index%4+1;
                return {groupId:20+group,group:'Учебная группа '+(group+1),key:'desktop-'+group+'-'+option,
                  label:'Учебное условие '+(group+1)+'.'+option,filterable:true};
              })];
              const offers=Array.from({length:24},(_,slot)=>({
                ...example,hotelId:id,total:example.total+index*13+slot*17,key:'desktop-panel-'+id+'-'+slot,
                operator:'Учебный оператор '+(index%16+1),
              }));
              return {...seed,id,amenities,offers,name:seed.name+' '+(index+1),resort:'Учебный курорт '+(index%14+1)};
            });
            mark.received = performance.now();
            return emit({...event,hotels});
          },...rest);
      };
      adapter=Object.create(Object.getPrototypeOf(owner),descriptors);
    },
  });
  Object.defineProperty(window,'AnyTourFilterPanelV1',{
    configurable:true,
    get:()=>factory,
    set(owner) {
      factory=Object.create(owner,{
        create:{value(options) {
          const panel=owner.create(options);
          return Object.create(panel,{
            renderFilters:{value(...args) {
              if(mark.received===undefined || mark.panelMs!==undefined) return panel.renderFilters(...args);
              const started=performance.now();
              const result=panel.renderFilters(...args);
              mark.panelMs=performance.now()-started;
              requestAnimationFrame(()=>requestAnimationFrame(()=>{mark.frameMs=performance.now()-mark.received;}));
              return result;
            }},
          });
        }},
      });
    },
  });
}

async function check({browser,origin,base},panelSource,variant,checkNarrow) {
  const context=await browser.newContext({viewport,reducedMotion:'reduce',serviceWorkers:'block'});
  const page=await context.newPage();
  const errors=[],forbidden=[];
  page.on('pageerror',error=>errors.push(error.message));
  try {
    await page.addInitScript(measurePanel);
    await page.route('**/*',async route=>{
      const request=route.request(), url=new URL(request.url());
      const logo=canonicalLogoFixture(request,origin);
      if(logo)return route.fulfill(logo);
      if(request.method()!=='GET'||url.origin!==origin||!url.pathname.startsWith(base)||/\/(?:api-|data\/)|\.php$/.test(url.pathname)){
        forbidden.push(url.pathname);
        return route.abort();
      }
      if(url.pathname.endsWith('/filter-panel-v1.js'))return route.fulfill({contentType:'application/javascript',body:panelSource});
      return route.continue();
    });
    const response=await page.goto(origin+base+'visual-search/?scenario=journey');
    assert.equal(response.status(),200);
    const submit=page.locator('.search-submit:not(:disabled)');
    try { await submit.click({timeout:8000}); }
    catch (error) {
      error.message+=`\n${variant} fixture errors: ${JSON.stringify({errors,forbidden})}`;
      throw error;
    }
    await page.waitForFunction(()=>Number.isFinite(window.desktopFilterTiming?.frameMs));
    await page.evaluate(()=>document.fonts.ready.then(()=>true));
    const result=await page.evaluate(()=>{
      const filters=document.querySelector('#filters'), reference=filters.getBoundingClientRect();
      const controls=[...filters.querySelectorAll('h4,.check-row,.price-inputs,.star-options,[data-facet-search]')]
        .filter(node=>node.getClientRects().length).map(node=>{
          const r=node.getBoundingClientRect();
          return {text:node.textContent.trim(),placeholder:node.getAttribute('placeholder'),top:r.top-reference.top,left:r.left-reference.left,width:r.width,height:r.height};
        });
      return {controls,ids:[...document.querySelectorAll('#cards>.hotel-card')].map(node=>node.id),
        toggles:filters.querySelectorAll('.filter-section-toggle').length,...window.desktopFilterTiming};
    });
    assert.equal(result.ids.length,24);
    assert(result.controls.length>=24);
    assert.equal(result.toggles>0,variant==='before');
    if(checkNarrow){
      await page.setViewportSize({width:430,height:932});
      await page.locator('.results-toolbar [data-action="filters"]').click();
      await page.locator('.filter-operator-group>.filter-section-toggle').click();
      assert.equal(await page.locator('.filter-top h3').textContent(),'Туроператор');
      const query=page.locator('.filter-operator-group [data-facet-search="operators"]');
      await query.fill('Учебный оператор 2');
      await page.waitForFunction(()=>document.querySelector('.filter-operator-group .facet-search-status')?.textContent.includes('Найдено в списке: 1'));
      assert(await query.evaluate(node=>node===document.activeElement));
      await page.setViewportSize(viewport);
      await page.locator('#filter-panel.open').waitFor({state:'detached'});
      assert.equal(await page.locator('.filter-top h3').textContent(),'Фильтры');
      assert.equal(await page.locator('#filter-detail-back').getAttribute('hidden'),'');
    }
    assert.deepEqual(errors,[]);
    assert.deepEqual(forbidden,[]);
    return result;
  } finally {
    await context.close();
  }
}

const median=numbers=>{
  const values=numbers.slice().sort((a,b)=>a-b), at=Math.floor(values.length/2);
  return values.length%2?values[at]:(values[at-1]+values[at])/2;
};

module.exports = async function compareDesktopFilters(options) {
  const cwd=path.resolve(__dirname,'..'), filename=path.join(cwd,panelFile);
  const original=execFileSync('git',['cat-file','blob',baselineBlob],{cwd,encoding:'utf8'});
  const candidate=fs.readFileSync(filename,'utf8');
  const pairs=[];
  for(let index=0;index<8;index++){
    const pair={};
    for(const mode of index%2?['after','before']:['before','after']){
      pair[mode]=await check(options,mode==='before'?original:candidate,mode,index===0);
    }
    assert.deepEqual(pair.after.ids,pair.before.ids,'order of the first result cards');
    assert.deepEqual(pair.after.controls,pair.before.controls,'visible desktop filter content and geometry');
    if(index>=2)pairs.push(Object.fromEntries(Object.entries(pair).map(([key,row])=>[key,{panelMs:row.panelMs,frameMs:row.frameMs,toggles:row.toggles}])));
  }
  const stats=key=>({
    before:median(pairs.map(p=>p.before[key])),after:median(pairs.map(p=>p.after[key])),
    pairedSaving:median(pairs.map(p=>p.before[key]-p.after[key])),
    faster:pairs.filter(p=>p.before[key]>p.after[key]).length,
  });
  const payload={viewport,baselineBlob,panel:stats('panelMs'),frame:stats('frameMs'),pairs};
  fs.writeFileSync(path.join(options.evidence,'desktop-filter-timing.json'),JSON.stringify(payload,null,2)+'\n');
  console.log('PASS paired desktop filter timing',JSON.stringify(payload));
};

