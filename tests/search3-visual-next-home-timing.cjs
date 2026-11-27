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
const versionOf=bytes=>sha(bytes).slice(0,12);
const assetNames=['app.js','filter-panel-v1.js'];
const previous={
  'app.js':{blob:'8a2ecc5d2d43f3c00f7a2f368c1f44ad8e96464e',sha:'860046f1cc9d03b3ffa072b8af2d15d6539417226d56a47851b44acd971b906f'},
  'filter-panel-v1.js':{blob:'441f37c2ffc1619cb612230a05cc4b0addf7559a',sha:'3232fde9377df3950ce2fe754b9395df33dab5f442d20bf67109346d94203bde'},
};
const fixtureQuery=new URLSearchParams({scenario:'family',origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-07',
  minNights:'7',maxNights:'7',adults:'3',ages:'0,12',stars:'4|5',meals:'Всё включено',max:'240000'});

async function versions(nextHome){
  const before={},after={};
  const home=nextHome.html.get(nextHome.prefix+'/');
  assert(home&&!home.includes('/filter-panel-v1.js?'),'candidate Home omits the filter script');
  for(const name of assetNames){
    const original=execFileSync('git',['cat-file','blob',previous[name].blob],{cwd:root,encoding:'utf8',maxBuffer:1024*1024});
    before[name]=await compile(original,{privateFunctions:name==='app.js'});
    assert.equal(sha(before[name]),previous[name].sha,'compiled installed baseline '+name);
    after[name]=fs.readFileSync(path.join(nextHome.payload,'visual-search',name));
  }
  const prefix=nextHome.prefix+'/visual-search/';
  const newTag='<script src="'+prefix+'app.js?v='+versionOf(after['app.js'])+'" defer></script>';
  const script=(name,assets)=>'<script src="'+prefix+name+'?v='+versionOf(assets[name])+'" defer></script>';
  assert.equal(home.split(newTag).length,2,'compiled application tag');
  const publicURL=home.match(/<script src="[^"\r\n]*\/visual-search\/public-url-v1\.js\?v=[a-f0-9]{12}" defer><\/script>/)?.[0];
  assert(publicURL&&home.split(publicURL).length===2,'public URL owner tag');
  const original=home.replace(newTag,()=>script('app.js',before))
    .replace(publicURL,()=>script('filter-panel-v1.js',before)+'\n'+publicURL);
  return {before:{html:original,assets:before},after:{html:home,assets:after}};
}

function measureButton(){
  const observer=new MutationObserver(()=>{
    const submit=document.querySelector('#search-form .search-submit');
    if(!submit||submit.disabled)return;
    observer.disconnect();
    const enabled=performance.now();
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
      const box=submit.getBoundingClientRect();
      const hit=document.elementFromPoint(box.x+box.width/2,box.y+box.height/2);
      window.search3HomeTiming={enabled,actionable:performance.now(),
        visible:box.width>=44&&box.height>=44&&box.top>=0&&box.bottom<=innerHeight+1&&submit.contains(hit)};
    }));
  });
  observer.observe(document,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
}

async function sample(browser,nextHome,fixture,viewport,origin){
  const context=await browser.newContext({viewport,serviceWorkers:'block',reducedMotion:'reduce',hasTouch:viewport.width===430});
  try{
    const page=await context.newPage(),blocked=[],errors=[],requests=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.addInitScript(measureButton);
    const home=nextHome.prefix+'/';
    await page.route('**/*',async route=>{
      const request=route.request(),url=new URL(request.url());
      if(request.method()!=='GET'||url.origin!==origin){blocked.push(url.pathname);return route.abort();}
      const logo=canonicalLogoFixture(request,origin);
      if(logo)return route.fulfill(logo);
      if(/^\/favicon\.(?:svg|ico|png)$/.test(url.pathname)){
        const file=path.join(root,'v2',url.pathname.slice(1));
        if(fs.existsSync(file))return route.fulfill({path:file});
      }
      if(url.pathname===home)return route.fulfill({contentType:'text/html; charset=utf-8',body:fixture.html});
      if(!url.pathname.startsWith(home)){blocked.push(url.pathname);return route.abort();}
      const asset=url.pathname.slice(home.length);
      if(assetNames.some(name=>asset==='visual-search/'+name)){
        const name=path.posix.basename(asset),bytes=fixture.assets[name];
        assert.equal(url.searchParams.get('v'),versionOf(bytes));
        requests.push(name);
        return route.fulfill({contentType:'application/javascript; charset=utf-8',body:bytes});
      }
      const local=path.resolve(nextHome.payload,asset);
      if(!/\.(?:css|js|json|svg|ico|png|jpe?g|webp|woff2?)$/i.test(asset)||
        !local.startsWith(nextHome.payload+path.sep)||!fs.existsSync(local)||!fs.statSync(local).isFile()){
        blocked.push(url.pathname);return route.abort();
      }
      return route.fulfill({path:local});
    });
    const response=await page.goto(origin+home+'?'+fixtureQuery,{waitUntil:'domcontentloaded'});
    assert.equal(response.status(),200);
    await page.waitForFunction(()=>window.search3HomeTiming,{},{timeout:8000});
    const timing=await page.evaluate(()=>window.search3HomeTiming);
    assert(timing.visible,'the enabled button is actually visible and hit-testable');
    assert.deepEqual(blocked,[]);assert.deepEqual(errors,[]);
    const form=await page.locator('#search-form').evaluate(node=>node.outerHTML);
    return {...timing,requests,form:sha(form)};
  }finally{await context.close();}
}

const median=rows=>{
  const sorted=rows.slice().sort((a,b)=>a-b),at=Math.floor(sorted.length/2);
  return sorted.length%2?sorted[at]:(sorted[at-1]+sorted[at])/2;
};
module.exports=async function compareHome(browser,nextHome,base,output){
  const variants=await versions(nextHome),groups=[],origin=new URL(base).origin;
  for(const viewport of [{width:430,height:932},{width:1280,height:720}]){
    const pairs=[];
    for(let index=0;index<10;index++){
      const pair={};
      for(const mode of index%2?['after','before']:['before','after']){
        pair[mode]=await sample(browser,nextHome,variants[mode],viewport,origin);
        assert.deepEqual(pair[mode].requests.sort(),mode==='before'?assetNames:['app.js']);
      }
      assert.equal(pair.before.form,pair.after.form,'identical configured home form');
      if(index>=2)pairs.push(pair);
    }
    const summary={viewport};
    for(const metric of ['enabled','actionable'])summary[metric]={
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
