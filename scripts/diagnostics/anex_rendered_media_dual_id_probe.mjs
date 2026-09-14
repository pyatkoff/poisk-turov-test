#!/usr/bin/env node
import fs from 'node:fs';
import { chromium } from 'playwright';
const [,, evidencePath, outPath] = process.argv;
if (!evidencePath || !outPath) process.exit(2);
const ev=JSON.parse(fs.readFileSync(evidencePath,'utf8'));
// Source queue country_id follows the Tourvisor/AnyTour country identity.
const COUNTRY={1:'egypt',2:'thailand',4:'turkey',8:'maldives',9:'uae',10:'cuba',12:'sri-lanka',16:'vietnam'};
const rx=/https?:\/\/(?:files\.anextour\.(?:ru|com)|cdn\.anextour\.ru)\/[^\s"'<>]+/ig;
const prx=/\/hotel\/([^/]+)\/hotel\/([^/]+)\/o(\d+)(?:\/|$)/i;
function pageFrom(row){
  const aid=Number(row.anex_hotel_id||0),cid=Number(row.country_id||0); if(!aid||!COUNTRY[cid])return null;
  for(const h of (row.hits||[])){
    const d=(h||{}).detail||{}; if(Number(d.inc||0)!==aid)continue;
    for(const v0 of [d.b2cLink,d.slug,h?.path]){
      if(typeof v0!=='string'||!v0.trim())continue; const v=v0.trim();
      if(v.startsWith('http')) return {aid,cid,url:v};
      if(v.startsWith('/tours/')) return {aid,cid,url:'https://anextour.ru'+v};
      if(v.startsWith('/')) return {aid,cid,url:'https://anextour.ru'+v};
      return {aid,cid,url:`https://anextour.ru/tours/${COUNTRY[cid]}/${v.replace(/^\/+|\/+$/g,'')}`};
    }
  }
  return null;
}
function pair(url,aid){
  try{const u=new URL(url); const m=prx.exec(u.pathname); const hc=u.searchParams.get('hotelCode')||u.searchParams.get('hotelcode'); if(!m||!hc||!/^\d+$/.test(hc)||Number(hc)!==aid)return null; return {source_url:url,country_slug:m[1].toLowerCase(),hotel_slug:m[2],samo_object_id:Number(m[3]),anex_hotel_code:Number(hc),evidence:'rendered_page_or_network_dual_id'};}catch{return null;}
}
const seeds=[]; const seen=new Set();
for(const r of (ev.matched||[])){const s=pageFrom(r); if(s&&!seen.has(s.aid)){seen.add(s.aid);seeds.push(s)}}
const browser=await chromium.launch({headless:true}); const ctx=await browser.newContext({userAgent:'Mozilla/5.0 AnyTour-MATCH-render/1.0'});
let idx=0; const rows=[]; const workers=Array.from({length:Math.min(6,seeds.length)},async()=>{while(true){const i=idx++; if(i>=seeds.length)break; const s=seeds[i]; const page=await ctx.newPage(); const found=new Set(); const capture=x=>{for(const m of String(x||'').match(rx)||[]) if(/hotelCode=/i.test(m)) found.add(m.replaceAll('\\/','/').replace(/&amp;/g,'&'));}; page.on('request',r=>capture(r.url())); page.on('response',r=>capture(r.url())); let err=null; try{await page.goto(s.url,{waitUntil:'domcontentloaded',timeout:15000}); await page.waitForTimeout(2500); capture(await page.content()); for(const u of await page.locator('img').evaluateAll(xs=>xs.flatMap(x=>[x.src,x.currentSrc,x.getAttribute('data-src'),x.getAttribute('srcset')].filter(Boolean)))) capture(u);}catch(e){err=String(e).slice(0,220)} finally{await page.close()}
 const pairs=[...found].map(u=>pair(u,s.aid)).filter(Boolean); rows.push({...s,found_url_count:found.size,pairs,error:err}); }});
await Promise.all(workers); await browser.close(); rows.sort((a,b)=>a.aid-b.aid);
const flat=[]; const key=new Set(),byA={},byS={}; for(const r of rows)for(const p of r.pairs){const k=`${p.samo_object_id}:${p.anex_hotel_code}`; if(!key.has(k)){key.add(k);flat.push(p)};(byA[p.anex_hotel_code]??=new Set()).add(p.samo_object_id);(byS[p.samo_object_id]??=new Set()).add(p.anex_hotel_code)}
const conflicts={anex_to_multiple_samo:Object.fromEntries(Object.entries(byA).filter(([,s])=>s.size>1).map(([k,s])=>[k,[...s].sort((a,b)=>a-b)])),samo_to_multiple_anex:Object.fromEntries(Object.entries(byS).filter(([,s])=>s.size>1).map(([k,s])=>[k,[...s].sort((a,b)=>a-b)]))};
const out={status:'completed',source_seed_count:seeds.length,page_rows:rows.length,direct_pair_count:flat.length,unique_anex_with_pair:Object.keys(byA).length,unique_samo_with_pair:Object.keys(byS).length,conflicts,pairs:flat,rows,database_writes:0,mapping_writes:0}; fs.writeFileSync(outPath,JSON.stringify(out,null,2)); console.log(JSON.stringify({source_seed_count:out.source_seed_count,direct_pair_count:out.direct_pair_count,unique_anex_with_pair:out.unique_anex_with_pair,unique_samo_with_pair:out.unique_samo_with_pair,conflicts}));