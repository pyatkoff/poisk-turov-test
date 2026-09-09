'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {chromium} = require('playwright');
const root = process.env.ANEX_REVIEW_OWNER_TEST_ROOT;
if (!root || process.env.ANEX_REVIEW_TEST_DSN !== 'mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw Error('test_only');
const fixture = JSON.parse(fs.readFileSync(root+'/fixture-private.json','utf8'));
const base = 'http://127.0.0.1:8766';
const login = '/_preview/search3-anex-candidate/anex-owner-login.php';
const panel = '/_preview/search3-anex-candidate/anex-hotel-review.php';
let checks=0;
function ok(test,label){assert.ok(test,label);checks++;}
async function request(path, cookie='', data=null){
  const headers={}; if(cookie)headers.Cookie=cookie;
  if(data){headers['Content-Type']='application/x-www-form-urlencoded';headers.Origin='https://anytoour.ru';}
  const r=await fetch(base+path,{method:data?'POST':'GET',headers,body:data?new URLSearchParams(data):undefined,redirect:'manual'});
  return {status:r.status,headers:r.headers,html:await r.text()};
}
function cookie(r){return (r.headers.get('set-cookie')||'').split(';')[0];}
function csrf(r){const m=r.html.match(/name="csrf" value="([0-9a-f]{64})"/);assert.ok(m,'csrf');return m[1];}
(async()=>{
  const first=await request(login);ok(first.status===200,'login GET');
  const c0=cookie(first);ok(/Secure/i.test(first.headers.get('set-cookie'))&&/HttpOnly/i.test(first.headers.get('set-cookie'))&&/SameSite=Strict/i.test(first.headers.get('set-cookie')),'secure cookie flags');
  ok(first.headers.get('cache-control').includes('no-store')&&first.headers.get('x-robots-tag').includes('noindex'),'private headers');
  ok((await request(panel)).status===303,'anonymous panel redirects');
  ok((await request(login,c0,{action:'enroll',csrf:'bad',token:fixture.token,password:fixture.password,confirmation:fixture.password})).status===403,'CSRF blocks setup');
  const mismatch=await request(login,c0,{action:'enroll',csrf:csrf(first),token:fixture.token,password:fixture.password,confirmation:'mismatch'});
  ok(mismatch.status===403&&mismatch.html.includes('id="enroll">')&&mismatch.html.includes('id="setup-token" value="'+fixture.token+'"'),'enrollment retry preserves token, not password');
  ok(!mismatch.html.includes(fixture.password),'password never echoed');
  const enrolled=await request(login,c0,{action:'enroll',csrf:csrf(first),token:fixture.token,password:fixture.password,confirmation:fixture.password});
  ok(enrolled.status===303&&enrolled.headers.get('location')===panel,'setup redirects to panel');
  const c1=cookie(enrolled);ok(c1!==c0&&c1.startsWith('ANYTOUR_REVIEW_OWNER='),'session rotated');
  const logged=await request(login,c1);ok(logged.html.includes('Управление')||logged.html.includes('Открыть проверку отелей'),'authenticated control');
  const view=await request(panel,c1);ok(view.status===200,'authenticated durable panel read');
  ok((await request(panel,c1,{action:'accept',csrf:'unused'})).status===403,'read-only owner cannot write');
  const replay=await request(login,c1,{action:'enroll',csrf:csrf(logged),token:fixture.token,password:fixture.password,confirmation:fixture.password});ok(replay.status===403,'setup consumed');
  const out=await request(login,c1,{action:'logout',csrf:csrf(logged)});ok(out.status===303,'logout');
  ok((await request(panel,c1)).status===303,'old session invalid');
  const fresh=await request(login);const cf=cookie(fresh);
  const signed=await request(login,cf,{action:'login',csrf:csrf(fresh),password:fixture.password});ok(signed.status===303,'password login');
  ok((await request(login+'?token='+fixture.token)).status===400,'query token rejected');
  const browser=await chromium.launch({headless:true});
  // Native form navigation, without the manually supplied Origin used by API fixtures.
  // A synthetic origin is intercepted entirely in-memory; no live site is contacted.
  const native=await browser.newPage();
  for(const policy of ['no-referrer',first.headers.get('referrer-policy')]){
    let sent='';
    await native.route('https://owner.invalid/**',async route=>{
      if(route.request().method()==='POST'){
        sent=route.request().headers().origin;
        await route.fulfill({status:200,contentType:'text/html',body:'done'});
      }else await route.fulfill({status:200,contentType:'text/html',headers:{'Referrer-Policy':policy},body:'<form method="post"><button>Submit</button></form>'});
    });
    await native.goto('https://owner.invalid/');
    await Promise.all([native.waitForNavigation(),native.locator('button').click()]);
    ok(sent===(policy==='no-referrer'?'null':'https://owner.invalid'),'native form origin: '+policy);
    await native.unroute('https://owner.invalid/**');
  }
  ok(first.headers.get('referrer-policy')==='same-origin','published header keeps same-origin POST authority');
  await native.close();
  const shots=[];
  try {
    for(const width of [1280,390]){
      const page=await browser.newPage({viewport:{width,height:850}});
      await page.goto(base+login);ok(await page.title()==='Вход владельца — AnyTour','login title');
      const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);ok(!overflow,'no overflow');
      await page.screenshot({path:root+'/owner-login-'+width+'.png',fullPage:true});shots.push({width,overflow});await page.close();
    }
  } finally {await browser.close();}
  fs.writeFileSync(root+'/owner-browser-report.json',JSON.stringify({checks,shots,synthetic:true,live_owner:false,https_simulated_loopback:true,db_decisions:0},null,2));
  console.log('OWNER_HTTP_OK checks='+checks+' synthetic=true live_owner=false');
})().catch(()=>{console.error('OWNER_HTTP_TEST_FAILED (no credential output)');process.exitCode=1;});
