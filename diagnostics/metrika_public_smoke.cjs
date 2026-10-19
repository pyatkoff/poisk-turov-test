'use strict';
// Public production evidence only: fresh browser profiles, no forms submitted,
// no supplier searches, no artificial goals and no consent override.
const { chromium } = require('playwright');
const fs = require('node:fs');
const routes = ['/', '/poisk-turov/', '/country/turkey/kemer/november/'];
const base = 'https://anytoour.ru';
const isMetrika = raw => { try { return /(^|\.)(mc\.yandex\.(ru|com)|mc\.webvisor\.(org|com)|mc\.yandex\.by)$/.test(new URL(raw).hostname); } catch { return false; } };
const publicUrl = raw => { const u = new URL(raw); return u.origin + u.pathname; };
(async () => {
  const browser = await chromium.launch({headless:true});
  const report = {checkedAt:new Date().toISOString(), routes:[]};
  try {
    for (const path of routes) {
      const context = await browser.newContext();
      const page = await context.newPage();
      const network = [], errors = [];
      page.on('request', r => { if (isMetrika(r.url())) network.push({event:'request',url:publicUrl(r.url()),method:r.method()}); });
      page.on('response', r => { if (isMetrika(r.url())) network.push({event:'response',url:publicUrl(r.url()),status:r.status()}); });
      page.on('requestfailed', r => { if (isMetrika(r.url())) network.push({event:'failed',url:publicUrl(r.url()),error:r.failure()?.errorText}); });
      page.on('pageerror', e => errors.push(String(e.message).slice(0,400)));
      const row = {path, network, errors};
      try {
        const response = await page.goto(base+path, {waitUntil:'domcontentloaded', timeout:45000});
        row.httpStatus = response?.status();
        const html = await response.text();
        row.emittedCounters = [...html.matchAll(/metrikaCounter\s*[:=]\s*(\d+)/g)].map(m=>Number(m[1]));
        row.legacyInitCounters = [...html.matchAll(/ym\(\s*(\d+)\s*,\s*['"]init['"]/g)].map(m=>Number(m[1]));
        row.htmlHasTag = html.includes('mc.yandex.ru/metrika/tag.js');
        await page.waitForTimeout(8000);
        row.browser = await page.evaluate(() => ({
          counter:Number(window.V2_CONFIG?.metrikaCounter||0),
          analyticsVersion:Number(window.V2Analytics?.version||0),
          initialized:Number(window.__V2_METRIKA_INIT||0),
          ymType:typeof window.ym,
          registered:typeof window.Ya?.Metrika2?.counters === 'function' ? window.Ya.Metrika2.counters().map(c=>c.id) : [],
          scripts:[...document.scripts].map(s=>s.src).filter(Boolean),
          consentKeys:Object.keys(localStorage).filter(k=>/consent|cookie/i.test(k)),
          consentControls:[...document.querySelectorAll('button')].map(n=>n.textContent.trim()).filter(t=>/cookie|куки|соглас|принять|отказ/i.test(t))
        }));
      } catch (e) { row.error=String(e.message).slice(0,700); }
      report.routes.push(row);
      console.log('METRIKA_PUBLIC_ROUTE '+JSON.stringify(row));
      await context.close();
    }
  } finally { await browser.close(); }
  fs.mkdirSync('artifacts',{recursive:true});
  fs.writeFileSync('artifacts/metrika-public-smoke.json',JSON.stringify(report,null,2));
  const counters=report.routes.map(r=>r.browser?.counter||r.legacyInitCounters?.[0]||0);
  const ok=report.routes.every(r=>r.httpStatus===200&&!r.error) && counters.every(n=>n>0&&n===counters[0]);
  console.log('METRIKA_PUBLIC_SUMMARY '+JSON.stringify({ok,counters,networkRequests:report.routes.map(r=>r.network.filter(n=>n.event==='request').length)}));
  if(!ok)process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1;});
