/* Real served Search3 form and canonical values; no supplier or lead requests. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {setParty,setBudget,checkMobileParameters}=require('./search3-mobile-parameters.cjs');
const {chromium,webkit}=require('playwright');
const nativeDateWebkit=process.env.SEARCH3_NATIVE_DATE_WEBKIT==='1';
const base=process.env.SEARCH3_VISUAL_BASE,sourceSha=process.env.SEARCH3_SOURCE_SHA;
assert.ok(base&&new URL(base).hostname==='127.0.0.1'&&process.env.SEARCH3_RESULTS_OUTPUT);
assert.match(sourceSha||'',/^[0-9a-f]{40}$/,'requires the exact checked source SHA');
const output=path.join(process.env.SEARCH3_RESULTS_OUTPUT,nativeDateWebkit?'native-date-webkit':'native-form');fs.mkdirSync(output,{recursive:true});
const widths=nativeDateWebkit?[320,350,375,390,430,760]:[320,350,375,430,760,761,1024,1025,1099,1100,1101,1199,1200,1366,1440,1600];
(async()=>{
 const browser=await(nativeDateWebkit?webkit:chromium).launch({headless:true});
 try{for(const width of widths){
  const page=await browser.newPage({viewport:{width,height:1000},...(nativeDateWebkit?{locale:'ru-RU',isMobile:true,hasTouch:true}:{})});
  const blocked=[],errors=[];page.on('pageerror',e=>errors.push(String(e)));
  await page.route('**/*',route=>{const request=route.request(),url=new URL(request.url());if(url.origin!==new URL(base).origin||request.method()!=='GET'||/\/(?:api[^/]*|lead[^/]*)\.php$/.test(url.pathname)){blocked.push(`${request.method()} ${url.pathname}`);return route.abort()}return route.continue()});
  try{
   assert.equal((await page.goto(base+'/poisk-turov/?count_people=3&child_count=1&child_age%5B%5D=8&daysFrom=7&daysTill=10',{waitUntil:'domcontentloaded'})).status(),200);
   await page.waitForFunction(()=>document.forms.tourSearch?.dataset.search3Ready==='1'&&document.forms.tourSearch.dataset.catalogSource&&window.V2SearchLifecycle);
   await page.evaluate(()=>document.fonts.ready);
   if(nativeDateWebkit){
    await page.locator('[data-search3-parameter=dates]').click();
    const dialog=page.locator('.search-parameter-dialog'),dates=dialog.locator('input');
    await dates.nth(0).fill('2099-09-16');await dates.nth(1).fill('2099-09-29');
    await dates.nth(0).focus();await page.keyboard.press('Tab');assert.equal(await dates.nth(1).evaluate(n=>n===document.activeElement),true,'native date drafts remain keyboard reachable');
    const native=await dates.evaluateAll(nodes=>nodes.map(n=>{const r=n.getBoundingClientRect(),f=n.parentElement.getBoundingClientRect(),s=getComputedStyle(n);return{type:n.type,value:n.value,appearance:s.appearance,fontSize:parseFloat(s.fontSize),height:r.height,left:r.left,right:r.right,fieldLeft:f.left,fieldRight:f.right}}));
    for(const [i,n]of native.entries()){assert.equal(n.type,'date');assert.equal(n.value,i===0?'2099-09-16':'2099-09-29');assert.equal(n.appearance,'none');assert.ok(n.height>=48&&n.fontSize>=16);assert.ok(n.left>=n.fieldLeft-1&&n.right<=n.fieldRight+1,'native WebKit date fits its field')}
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
    await dialog.screenshot({path:path.join(output,`dates-${width}.png`)});await dialog.locator('.search-parameter-apply').click();
    assert.deepEqual(await page.locator('#tourSearch').evaluate(f=>[new FormData(f).get('dateFrom'),new FormData(f).get('dateTo')]),['2099-09-16','2099-09-29'],'Apply retains exact ISO dates');
    await page.locator('#tourSearch').screenshot({path:path.join(output,`form-${width}.png`)});assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(output,`dates-${width}.json`),JSON.stringify({source_sha:sourceSha,width,browser:'webkit',locale:'ru-RU',native,blocked,errors,supplier_requests_sent:0,lead_sent:0,physical_iphone:'not_measured'},null,2)+'\n');continue;
   }
   const state=await page.evaluate(()=>{
    const f=document.forms.tourSearch,box=n=>{const r=n.getBoundingClientRect();return{top:r.top,bottom:r.bottom,left:r.left,right:r.right,width:r.width,height:r.height}},visible=n=>n.checkVisibility({visibilityProperty:true})&&n.getBoundingClientRect().height>0;
    return{overflow:document.documentElement.scrollWidth-innerWidth,form:box(f),submit:box(f.querySelector('.search-submit')),groups:[...f.querySelectorAll('.search-group')].map(box),preferences:[...f.querySelector('.search-preferences').children].map(box),controls:[...f.querySelectorAll('.field input:not([type=checkbox]),.field select')].filter(visible).map(n=>({...box(n),name:n.name,font:parseFloat(getComputedStyle(n).fontSize),appearance:getComputedStyle(n).appearance})),canonical:[...new FormData(f)]};
   });
   assert.ok(state.overflow<=1,`${width}: compact form fits viewport`);
   assert.ok(state.form.height<=650,`${width}: core form stays compact even with child summary`);
   assert.ok(state.submit.height>=48&&state.submit.bottom<=state.form.bottom,'search action is readable and inside form');
   if(width<=700)assert.ok(state.submit.width>=state.form.width-28,'phone action spans the form');
   assert.equal(state.controls.length,width<=700?3:4,'only route, category and meal use inline native editors');
   assert.ok(state.controls.every(n=>n.height>=44&&n.font>=16&&n.appearance==='none'),'visible native selects retain readable targets');
   const [route,dates,nights,party]=state.groups;
   assert.ok(Math.abs(nights.top-party.top)<=1&&party.left>=nights.right-1,'nights and party share a coherent row');
   if(width<=700){assert.ok(dates.top>=route.bottom-1&&nights.top>=dates.bottom-1);assert.ok(Math.abs(dates.width-route.width)<=1);assert.ok(Math.abs(state.preferences[1].top-state.preferences[2].top)<=12,'meal and budget share one row')}
   if(width>=1100)assert.ok(Math.max(...state.groups.map(n=>n.top))-Math.min(...state.groups.map(n=>n.top))<=1,'desktop has one primary row');
   for(const name of ['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people','child_count','region','hotel','stars','food','price_from','price_till'])assert.equal(await page.locator(`#tourSearch [name="${name}"]`).count(),1,`one canonical ${name}`);
   assert.equal(await page.locator('[data-search3-parameter]:visible').count(),4);for(const b of await page.locator('[data-search3-parameter]').all())assert.ok((await b.boundingBox()).height>=44);
   const categories=page.getByRole('radiogroup',{name:'Категория отеля'});assert.equal(await categories.isVisible(),width<=700);
   if(width<=700)for(const b of await categories.getByRole('radio').all()){const r=await b.boundingBox();assert.ok(r.width>=44&&r.height>=44)}
   const hotel=page.locator('[data-v2-hotel-query]');assert.equal(await hotel.count(),1);assert.equal(await hotel.isVisible(),false,'secondary destination is under All filters');
   await page.locator('#tourSearch').screenshot({path:path.join(output,`entry-${width}.png`)});
   fs.writeFileSync(path.join(output,`entry-${width}.json`),JSON.stringify({width,state},null,2)+'\n');
   const budget=[];
   for(const [from,to]of [['',''],['155500',''],['','200750'],['155500','200750']]){
    await setBudget(page,from,to);
    const actual=await page.locator('#tourSearch').evaluate(f=>[new FormData(f).get('price_from'),new FormData(f).get('price_till')]);assert.deepEqual(actual,[from,to]);
    assert.equal(await page.evaluate(()=>window.V2SearchLifecycle.validate({...window.V2SearchLifecycle.params(),departureId:'1',countryId:'4'})),'','exact and open budget bounds keep existing validation');budget.push(actual);
   }
   if(width===320)await checkMobileParameters(page,width,output);
   const partyStates=[];
   for(const count of [1,2,3,0]){
    const ages=['0','17','6'].slice(0,count);await setParty(page,3,ages);
    const data=await page.locator('#tourSearch').evaluate(f=>{const d=new FormData(f);return[d.get('count_people'),d.get('child_count'),d.getAll('child_age[]'),d.get('daysFrom'),d.get('daysTill')]});
    assert.deepEqual(data,['3',String(count),ages,'7','10']);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);partyStates.push(data);
    if(count===3){await page.locator('[data-search3-parameter=party]').click();const dialog=page.locator('.search-parameter-dialog');for(const age of await dialog.locator('[data-age]').all()){const b=await age.boundingBox();assert.ok(b.width>=120&&b.height>=44&&b.x>=0&&b.x+b.width<=width+1)}await page.keyboard.press('Escape')}
   }
   const accessibility=await page.context().newCDPSession(page);
   const advancedNodes=async()=>(await accessibility.send('Accessibility.getFullAXTree')).nodes.filter(n=>!n.ignored&&['combobox','option','checkbox'].includes(n.role?.value)).map(n=>({role:n.role.value,name:n.name?.value}));
   const closedAx=await advancedNodes();assert.equal(closedAx.some(n=>['Туроператор','Все операторы','Прямой','Чартер','Курорт / регион'].includes(n.name)),false,'closed filters expose no orphan options');
   const dataBefore=await page.locator('#tourSearch').evaluate(f=>[...new FormData(f)]),toggle=page.locator('.search-more-filters');
   await toggle.focus();await page.keyboard.press('Enter');assert.equal(await toggle.getAttribute('aria-expanded'),'true');
   const openAx=await advancedNodes();for(const name of ['Курорт / регион','Район / субкурорт','Рейтинг отеля','Аэропорт прилёта','Туроператор','Тип отеля'])assert.ok(openAx.some(n=>n.role==='combobox'&&n.name===name),name+' has a labelled accessible editor');
   assert.equal(await hotel.isVisible(),true);assert.equal(await hotel.isEnabled(),true);assert.equal(await page.locator('#tourSearch [name=hotel]').isVisible(),false);
   assert.equal(await page.locator('#tourSearch .service-picker').evaluate(n=>n.open),false,'nested services keep independent state');
   const advancedGeometry=await page.locator('.extra-grid').evaluate(grid=>[...grid.children].map(n=>{const r=n.getBoundingClientRect();return{left:r.left,right:r.right,width:r.width,top:r.top}}));
   assert.ok(advancedGeometry.every(r=>r.left>=0&&r.right<=width+1),'expanded filter fields fit viewport');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1),false);
   await page.locator('#tourSearch').screenshot({path:path.join(output,`entry-expanded-${width}.png`)});
   await toggle.focus();await page.keyboard.press('Enter');assert.equal(await toggle.getAttribute('aria-expanded'),'false');
   assert.deepEqual(await page.locator('#tourSearch').evaluate(f=>[...new FormData(f)]),dataBefore,'disclosure preserves exact FormData');
   assert.equal((await advancedNodes()).some(n=>n.name==='Все операторы'),false);await accessibility.detach();assert.deepEqual(errors,[]);
   fs.writeFileSync(path.join(output,`journey-${width}.json`),JSON.stringify({source_sha:sourceSha,width,party:partyStates,budget,advancedGeometry,closedAx,openAx,blocked,errors,supplier_requests_sent:0,lead_sent:0},null,2)+'\n');
  }finally{await page.close()}
 }}finally{await browser.close()}
 console.log(`SEARCH3_SERVED_ENTRY_GEOMETRY_OK source=${sourceSha} browser=${nativeDateWebkit?'webkit':'chromium'} widths=${widths.join(',')} lead_sent=0`);
})().catch(e=>{console.error(e);process.exitCode=1});
