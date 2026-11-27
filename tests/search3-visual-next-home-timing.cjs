'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {createHash}=require('node:crypto');
const {execFileSync}=require('node:child_process');
const {compile}=require('../scripts/build/search3-js/visual-entry.cjs');
const {canonicalLogoFixture}=require('./search3-visual-live-fixture.cjs');

const root=path.resolve(__dirname,'..');
const sha=bytes=>createHash('sha256').update(bytes).digest('hex');
const previous={
  source:'eb6cd3afb123ae6112684f55101470454be07d65',
  app:{blob:'b6eea6149f23516976456ba5ae687b65b9c916fb',sha:'dcb19a092f463d387964ac3383753446effde8c8836e3f68a5d7362adabca1db'}
};
const fixtureQuery=new URLSearchParams({
  scenario:'family',origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-07',
  minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'
});

async function variants(nextHome){
  const home=nextHome.html.get(nextHome.prefix+'/');
  const search=nextHome.html.get(nextHome.prefix+'/poisk-turov/');
  assert(home&&search&&!home.includes('/filter-panel-v1.js?'));
  const source=execFileSync('git',['cat-file','blob',previous.app.blob],{cwd:root,encoding:'utf8',maxBuffer:1024*1024});
  const oldApp=await compile(source,{privateFunctions:true});
  assert.equal(sha(oldApp),previous.app.sha,'The exact installed baseline app compiles byte-for-byte.');
  const newApp=fs.readFileSync(path.join(nextHome.payload,'visual-search/app.js'));
  const oldVersion=previous.app.sha.slice(0,12),newVersion=sha(newApp).slice(0,12);
  assert.notEqual(oldVersion,newVersion,'These versions are expected to differ in app.js.');
  const marker='/visual-search/app.js?v='+newVersion+'"';
  const pin=html=>{
    assert.equal(html.split(marker).length,2,'One versioned app.js link per document');
    return html.replace(marker,()=>'/visual-search/app.js?v='+oldVersion+'"');
  };
  return {before:{home:pin(home),search:pin(search),app:oldApp,version:oldVersion},
    after:{home,search,app:newApp,version:newVersion}};
}

function measureJourney(routes){
  const submitSelector='#search-form .search-submit:not([disabled])';
  const home=location.pathname===routes.home;
  if(!home&&location.pathname!==routes.search)return;
  const clickKey='search3:fixture-home-click';
  const absoluteTime=()=>performance.timeOrigin+performance.now();
  if(home)document.addEventListener('click',event=>{
    if(event.target.closest?.(submitSelector))sessionStorage.setItem(clickKey,String(absoluteTime()));
  },{capture:true});
  const selector=home?submitSelector:'#cards .hotel-card h3';
  let pending=false;
  const observe=new MutationObserver(()=>{
    const target=document.querySelector(selector);
    if(!target||pending||target.closest('[hidden],[inert]'))return;
    if(home&&target.closest('#search-form')?.getAttribute('aria-busy')==='true')return;
    const discovered=performance.now();
    pending=true;
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      const current=document.querySelector(selector);
      if(!current){pending=false;return;}
      const box=current.getBoundingClientRect();
      const hit=document.elementFromPoint(box.left+box.width/2,box.top+box.height/2);
      const visible=box.width>0&&box.height>0&&box.top>=0&&box.bottom<=innerHeight+1&&Boolean(hit&&current.contains(hit));
      const clicked=home?null:Number(sessionStorage.getItem(clickKey));
      window.search3HomeTiming={enabled:discovered,actionable:performance.now(),
        firstCard:clicked?absoluteTime()-clicked:null,visible,width:box.width,height:box.height};
      observe.disconnect();
    }));
  });
  observe.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled','hidden','aria-busy']});
}

async function sample(browser,nextHome,fixture,viewport,origin){
  const context=await browser.newContext({viewport,serviceWorkers:'block',reducedMotion:'reduce',hasTouch:viewport.width===430});
  try{
    const page=await context.newPage(),blocked=[],errors=[],requests=[],documents=[];
    page.on('pageerror',error=>errors.push(error.message));
    const home=nextHome.prefix+'/',search=home+'poisk-turov/';
    await page.addInitScript(measureJourney,{home,search});
    await page.route('**/*',async route=>{
      const request=route.request(),url=new URL(request.url());
      if(request.method()!=='GET'||url.origin!==origin){blocked.push(url.pathname);return route.abort();}
      const logo=canonicalLogoFixture(request,origin);
      if(logo)return route.fulfill(logo);
      if(/^\/favicon\.(?:svg|ico|png)$/.test(url.pathname)){
        const file=path.join(root,'v2',url.pathname.slice(1));
        if(fs.existsSync(file))return route.fulfill({path:file});
      }
      if(url.pathname===home||url.pathname===search){
        assert.equal(request.resourceType(),'document');
        documents.push(url.pathname);
        return route.fulfill({contentType:'text/html; charset=utf-8',body:url.pathname===home?fixture.home:fixture.search});
      }
      if(!url.pathname.startsWith(home)){blocked.push(url.pathname);return route.abort();}
      const asset=url.pathname.slice(home.length);
      if(asset==='visual-search/app.js'){
        assert.equal(url.searchParams.get('v'),fixture.version);
        requests.push('app.js');
        return route.fulfill({contentType:'application/javascript; charset=utf-8',body:fixture.app});
      }
      const local=path.resolve(nextHome.payload,asset);
      if(asset.startsWith('data/')||!/\.(?:css|js|json|svg|ico|png|jpe?g|webp|woff2?)$/i.test(asset)||
        !local.startsWith(nextHome.payload+path.sep)||!fs.existsSync(local)||!fs.statSync(local).isFile()){
        blocked.push(url.pathname);return route.abort();
      }
      return route.fulfill({path:local});
    });
    const response=await page.goto(origin+home+'?'+fixtureQuery,{waitUntil:'domcontentloaded'});
    assert.equal(response.status(),200);
    await page.waitForFunction(()=>window.search3HomeTiming,{},{timeout:8000});
    const timing=await page.evaluate(()=>window.search3HomeTiming);
    assert(timing.visible&&timing.width>=44&&timing.height>=44,'The ready button is visible and actionable.');
    await page.waitForLoadState('load');
    const form=await page.locator('#search-form').evaluate(async node=>{
      await document.fonts.ready;
      await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
      return node.outerHTML;
    });
    await Promise.all([
      page.waitForURL(url=>url.pathname===search),
      page.locator('#search-form .search-submit').click()
    ]);
    await page.waitForFunction(()=>window.search3HomeTiming?.firstCard>0,{},{timeout:8000});
    const result=await page.evaluate(()=>window.search3HomeTiming);
    assert(result.visible,'The first hotel heading is visible.');
    const cards=await page.locator('#cards .hotel-card').evaluateAll(nodes=>nodes.map(n=>[
      n.id,n.querySelector('h3')?.textContent,n.querySelector('.starting-price strong')?.textContent
    ]));
    assert(cards.length&&cards[0][2],'An actual priced hotel arrives.');
    assert.deepEqual(documents,[home,search]);
    assert(requests.length>=1);
    assert.deepEqual(blocked,[]);assert.deepEqual(errors,[]);
    return {enabled:timing.enabled,actionable:timing.actionable,firstCard:result.firstCard,form:sha(form),cards};
  }finally{await context.close();}
}

function median(rows){
  const sorted=[...rows].sort((a,b)=>a-b),at=Math.floor(sorted.length/2);
  return sorted.length%2?sorted[at]:(sorted[at-1]+sorted[at])/2;
}
module.exports=async function compareHome(browser,nextHome,base,output){
  const versions=await variants(nextHome),groups=[],origin=new URL(base).origin;
  for(const viewport of [{width:430,height:932},{width:1280,height:720}]){
    const pairs=[];
    for(let index=0;index<10;index++){
      const pair={};
      for(const mode of index%2?['after','before']:['before','after']){
        pair[mode]=await sample(browser,nextHome,versions[mode],viewport,origin);
      }
      assert.equal(pair.before.form,pair.after.form,'Same configured form before search.');
      assert.deepEqual(pair.before.cards,pair.after.cards,'Same cards, title, order and price.');
      if(index>=2)pairs.push(pair);
    }
    const summary={viewport};
    for(const metric of ['enabled','actionable','firstCard'])summary[metric]={
      before:median(pairs.map(p=>p.before[metric])),after:median(pairs.map(p=>p.after[metric])),
      saving:median(pairs.map(p=>p.before[metric]-p.after[metric])),
      faster:pairs.filter(p=>p.after[metric]<p.before[metric]).length,
    };
    console.log('SEARCH3_NEXT_HOME_PAIRED '+JSON.stringify(summary));
    groups.push({...summary,pairs});
  }
  fs.writeFileSync(path.join(output,'canonical-next-home-paired.json'),
    JSON.stringify({source:process.env.SEARCH3_SOURCE_SHA,previous,groups},null,2)+'\n');
};
