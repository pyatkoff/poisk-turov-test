'use strict';
// Check the compiled Search3 UI with fictional supplier results and local HTTP only.

const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(process.env.SEARCH3_VISUAL_ASSET_ROOT || path.join(__dirname, '../v2'));
const evidence = path.resolve('visual-live-evidence/family-price-return');
fs.mkdirSync(evidence, {recursive:true});
const types = {'.html':'text/html','.js':'application/javascript','.css':'text/css','.json':'application/json','.jpg':'image/jpeg','.svg':'image/svg+xml'};
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://local');
  const filename = path.join(root, url.pathname.endsWith('/') ? url.pathname + 'index.html' : url.pathname);
  if (req.method !== 'GET' || !filename.startsWith(root + '/') || !fs.existsSync(filename)) {
    res.writeHead(404).end();
    return;
  }
  res.writeHead(200, {'Content-Type':types[path.extname(filename)] || 'application/octet-stream'});
  res.end(fs.readFileSync(filename));
});

const fixture = `
(() => {
  const base = window.AnyTourPrototypeData;
  window.familyChecks = {ages:[],quotes:[]};
  window.AnyTourPrototypeData = Object.freeze({...base,
    messengerContactPreview: {ready:'Готово · тест',unavailable:'Недоступно · тест'},
    async search(params,emit,...args){
      window.familyChecks.ages.push([...params.ages]);
      await base.search(params,event=>{
        if (event.hotels) {
          const source = event.hotels[0];
          const extras = [
            [930001,'SETTE SERENITY HOTEL ADULTS ONLY 16+','Отель только для взрослых!'],
            [930002,'KLEOPATRA ATLAS ADULTS ONLY 16+','Отель только для взрослых (16+).'],
            [930003,'Hotel Adults Only 16+ · conflicting rule','Отель только для взрослых (18+).'],
            [930004,'Hotel without an age limit in the feed','Отель только для взрослых!'],
            [930005,'Family hotel with a restricted pool','Бассейн только для взрослых 18+.'],
            [930006,'TUI MAGIC LIFE BODRUM ADULT ONLY 16+ (EX. ERSAN EXCLUSIVE RESORT & SPA)',''],
            [930007,'LURES HOTEL (EX. THE LURE) ADULT ONLY 16+','']
          ].map(([id,name,description])=>({...structuredClone(source),id,name,raw:{description},offers:source.offers.map(o=>({...o,hotelId:id,key:'test-'+id}))}));
          event.hotels.push(...extras);
          event.hotels.forEach(h=>h.offers.forEach(o=>{o.provider='tourvisor';o.placement='DBL + 1 CHD';}));
        }
        emit(event);
      },...args);
    },
    async quote(o){
      window.familyChecks.quotes.push(o.key);
      const result = await base.quote(o);
      if (o.hotelId === 900003) return {...result,price:o.total+9000,fuelCharge:3000};
      if (o.hotelId === 900004) {
        const failure = new Error('Fictional quote rejected');failure.code='offer_unavailable';failure.retryable=false;throw failure;
      }
      return {...result,fuelCharge:3000};
    },
    async flights(tour){return (await base.flights(tour)).map(v=>({...v,fuelCharge:3000}));},
    fuel(tour,pair){return (pair||tour)?.fuelCharge??null;}
  });
})();
`;

async function run(browser, origin, width, height) {
  const context = await browser.newContext({
    viewport:{width,height},isMobile:true,hasTouch:true,deviceScaleFactor:2,serviceWorkers:'block'
  });
  const page = await context.newPage();
  page.setDefaultTimeout(5000);
  const requests = [],errors = [];
  page.on('pageerror', error=>errors.push(error.message));
  await page.route('**/*', async route=>{
    const request=route.request();
    const url=new URL(request.url());
    if(url.origin!==origin || request.method()!=='GET'){
      if(request.resourceType()!=='image')requests.push([request.method(),url.pathname]);
      await route.abort();return;
    }
    if(url.pathname.endsWith('/fixture-data.js')||url.pathname.endsWith('/preview-lead.js')){
      const response=await route.fetch();
      const extra=url.pathname.endsWith('/fixture-data.js')?fixture:`
        ;window.AnyTourPrototypeLead=Object.freeze({...window.AnyTourPrototypeLead,
           canApply:o=>o?.provider==='tourvisor'&&!!o.tour&&!o.quoteError&&!o.pricePending});`;
      await route.fulfill({response,body:(await response.text())+'\n'+extra});return;
    }
    await route.continue();
  });
  await page.addInitScript(()=>{window.V2_CONFIG={search3ContactMode:'messenger-only',search3PublicEntry:'review'};});
  const cards=page.locator('#cards .hotel-card');
  const getCard=id=>page.locator('#hotel-'+id);
  const close=()=>page.locator('#modal [data-action="close-modal"]').first().tap();
  const done=()=>page.locator('#modal').waitFor({state:'hidden'});
  const goto=async age=>{
    await page.goto(origin+'/visual-search/?scenario=mixed&adults=2&ages='+age+'&sort=price&max=200000');
    try{await getCard(900001).waitFor();}catch(error){
      console.error({errors,requests,body:(await page.locator('body').innerText()).slice(0,3000),fixtures:await page.evaluate(()=>window.familyChecks)});
      throw error;
    }
    assert.deepEqual(await page.evaluate(()=>window.familyChecks.ages.at(-1)),[age]);
    assert.deepEqual(await page.evaluate(()=>[innerWidth,innerHeight]),[width,height]);
  };
  const hotelButton=card=>card.locator('[data-card-entry]');
  const titleY=card=>card.locator('.hotel-card-name').evaluate(el=>el.getBoundingClientRect().top);
  const openRoom=async card=>{
    const button=hotelButton(card);
    await button.evaluate(el=>el.scrollIntoView({behavior:'instant',block:'center'}));
    const before=await titleY(card);
    await button.tap();
    await page.locator('#hotel-room-count').waitFor();
    return before;
  };
  const contact=()=>page.getByRole('heading',{name:'Связь по выбранному туру'}).waitFor();
  const quoteFor=()=>page.locator('#modal .room-offer-choice [data-action="select-hotel-offer"]').filter({visible:true}).first();
  const assertPrice=async (oldPrice,finalPrice,withFlights=false)=>{
    const price=page.locator('.application-cost');
    const moneyText=text=>text.replace(/\D/g,'');
    assert.equal(moneyText(await price.locator('[data-price-part="tour"] dd').textContent()),String(oldPrice));
    assert.equal(moneyText(await price.locator('[data-price-part="total"] dd').textContent()),String(finalPrice));
    assert.match(await price.locator('[data-price-part="flight"] dd').textContent(),withFlights?/Без доплаты/:/Уточнит менеджер/);
    assert.match(await price.locator('[data-price-part="fuel"] dd').textContent(),/3\s*000.*включён/);
    assert.match(await page.locator('.application-review-heading').textContent(),/ребёнка: 8 лет/);
    const boxes=await page.locator('#messenger-entry button[data-action="messenger-open"]').evaluateAll(elements=>elements.map(el=>{
      const r=el.getBoundingClientRect(),footer=document.querySelector('#modal-footer').getBoundingClientRect();
      return {height:r.height,top:r.top,bottom:r.bottom,footer:footer.top,left:r.left,right:r.right};
    }));
    assert.equal(boxes.length,2);
    assert(boxes.every(r=>r.height>=44&&r.top>=0&&r.bottom<=r.footer+1&&r.left>=0&&r.right<=width));
  };
  const measureReturn=async (card,before)=>{
    await done();
    await page.waitForFunction(()=>history.scrollRestoration==='auto');
    const after=await titleY(card);
    assert(Math.abs(after-before)<=2,JSON.stringify({before,after,card:await card.getAttribute('id')}));
    return Math.round((after-before)*100)/100;
  };

  try{
    await goto(15);
    assert.equal(await getCard(930001).count(),0);
    assert.equal(await getCard(930002).count(),0);
    assert.equal(await getCard(930006).count(),0);
    assert.equal(await getCard(930007).count(),0);
    await goto(16);
    assert.equal(await getCard(930001).count(),1);
    assert.equal(await getCard(930002).count(),1);
    assert.equal(await getCard(930006).count(),1);
    assert.equal(await getCard(930007).count(),1);
    assert.equal(await getCard(930003).locator('[data-hotel-age-policy="age-policy-conflict"]').count(),1);
    await goto(17);
    assert.equal(await getCard(930001).count(),1);
    await goto(8);
    assert.equal(await getCard(930001).count(),0);
    assert.equal(await getCard(930002).count(),0);
    assert.equal(await getCard(930006).count(),0);
    assert.equal(await getCard(930007).count(),0);
    assert.equal(await getCard(930004).locator('[data-hotel-age-policy="age-limit-unknown"]').count(),1);
    assert.equal(await getCard(930005).count(),1);
    const searchURL=page.url();
    const order=await cards.evaluateAll(elements=>elements.map(e=>e.id));
    const deltas=[];
    const first=getCard(900002),second=getCard(900003),third=getCard(900005);
    let before=await openRoom(first);
    assert.match(await page.locator('#hotel-contact-next').textContent(),/Telegram или MAX/);
    assert.match(await quoteFor().getAttribute('aria-label'),/^Проверить и к связи:/);
    assert((await quoteFor().boundingBox()).height>=44);
    await quoteFor().tap();
    await contact();
    await assertPrice(119000,119000);
    await page.screenshot({path:path.join(evidence,`price-without-flights-${width}.png`)});
    await close();
    deltas.push(await measureReturn(first,before));
    const resume=first.locator('[data-card-selection]');
    await resume.evaluate(el=>el.scrollIntoView({behavior:'instant',block:'center'}));
    const beforeBack=await titleY(first);
    await resume.tap();
    await page.getByRole('heading',{name:'Ваш тур в деталях'}).waitFor();
    assert.match(await page.locator('.tour-contact-action').textContent(),/Telegram или MAX/);
    assert.match(await page.locator('.chosen-stay').textContent(),/2 взрослых и 1 ребёнок/);
    await page.goBack();
    await measureReturn(first,beforeBack);
    assert.equal(await first.locator('[data-card-selection]').count(),1);

    before=await openRoom(second);
    await quoteFor().tap();
    const accept=page.locator('#modal [data-action="accept-selected-price"]');
    await accept.waitFor();
    assert.equal(await page.locator('#messenger-entry').count(),0);
    assert.match(await page.locator('#hotel-detail-price-status').textContent(),/Цена изменилась/);
    await accept.tap();
    await contact();
    await assertPrice(143000,152000);
    await close();
    deltas.push(await measureReturn(second,before));

    before=await openRoom(third);
    await quoteFor().tap();
    await contact();
    await page.locator('[data-action="contact-flights"]').tap();
    const pair=page.locator('#tour-flight-options [name="flight-pair"][value="0"]');
    await pair.waitFor();
    await pair.check();
    await page.locator('#modal-footer [data-action="apply-flight"]').tap();
    await contact();
    await assertPrice(191000,191000,true);
    await page.screenshot({path:path.join(evidence,`price-with-flights-${width}.png`)});
    // The first history Back navigates to the prior step; further Back closes the journey.
    await close();
    deltas.push(await measureReturn(third,before));
    await third.locator('[data-card-selection]').tap();
    await page.getByRole('heading',{name:'Ваш тур в деталях'}).waitFor();
    assert.match(await page.locator('.flight-summary').textContent(),/Перелёт выбран/);
    await page.goBack();
    await done();

    const rejected=getCard(900004);
    await openRoom(rejected);
    await quoteFor().tap();
    await page.getByRole('button',{name:'К результатам',exact:true}).waitFor();
    assert.equal(await page.locator('#messenger-entry').count(),0);
    await close();
    await done();

    assert.deepEqual(await cards.evaluateAll(elements=>elements.map(e=>e.id)),order);
    assert.equal(page.url(),searchURL,'back and close retain the budget and sort filters');
    assert.equal(await page.getByRole('combobox',{name:'Сортировать'}).inputValue(),'price');
    assert.equal(await page.evaluate(()=>window.familyChecks.ages.length),1);
    assert.deepEqual(await page.evaluate(()=>window.familyChecks.quotes.length),4);
    assert.deepEqual(errors,[]);
    assert.deepEqual(requests,[]);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    return {width,height,ageCases:[8,15,16,17],deltas,searches:1,fictionalQuotes:4,externalRequests:0,leads:0};
  } finally {
    await context.close();
  }
}

(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const origin='http://127.0.0.1:'+server.address().port;
  const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_EXECUTABLE_PATH});
  try{
    const results=[];
    for (const [width,height] of [[390,844],[430,932]]) results.push(await run(browser,origin,width,height));
    fs.writeFileSync(path.join(evidence,'receipt.json'),JSON.stringify(results,null,2)+'\n');
    console.log('PASS',evidence,JSON.stringify(results));
  } finally {
    await browser.close();
    await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
