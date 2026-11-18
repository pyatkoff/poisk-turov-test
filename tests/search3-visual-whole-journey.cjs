'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Keep a visual record of one continuous session at each size. Small contact
// sheets fit the existing acceptance archive without duplicating full frames.
async function saveJourney(page, frames, width, evidence) {
 const sheet = await page.context().newPage();
 try {
  const result = await sheet.evaluate(async ({frames,width}) => {
   const tileWidth = width < 600 ? 320 : 512;
   const pad = 12, heading = 34;
   const tiles = await Promise.all(frames.map(async frame => {
    const img = new Image();
    img.src = 'data:image/jpeg;base64,' + frame.image;
    await img.decode();
    return {img,label:frame.label};
   }));
   const tileHeight = Math.round(tiles[0].img.height * tileWidth / tiles[0].img.width);
   const canvas = document.createElement('canvas');
   canvas.width = 3 * tileWidth + 4 * pad;
   canvas.height = 2 * (tileHeight + heading) + 3 * pad;
   const ctx = canvas.getContext('2d');
   ctx.fillStyle = '#f3f6fa';
   ctx.fillRect(0,0,canvas.width,canvas.height);
   ctx.font = 'bold 18px sans-serif';
   ctx.fillStyle = '#17263e';
   tiles.forEach(({img,label},index) => {
    const x = pad + index % 3 * (tileWidth + pad);
    const y = pad + Math.floor(index / 3) * (tileHeight + heading + pad);
    ctx.fillText(label,x,y + 22);
    ctx.drawImage(img,x,y + heading,tileWidth,tileHeight);
   });
   return canvas.toDataURL('image/webp',0.77).split(',')[1];
  },{frames,width});
  fs.writeFileSync(path.join(evidence,'whole-search-'+width+'.webp'),Buffer.from(result,'base64'));
 } finally { await sheet.close(); }
}

module.exports = async function wholeSearch({browser,origin,base,evidence}) {
 for(const width of [430,1280]){
  const context=await browser.newContext({viewport:{width,height:width===430?900:800},reducedMotion:'reduce'});
  const page=await context.newPage();
  const failures=[],external=[],frames=[];
  const frame=async label=>{
   await page.evaluate(()=>document.fonts.ready.then(()=>true));
   assert.equal(await page.evaluate(()=>{
    const modal=document.querySelector('dialog[open]');
    return document.documentElement.scrollWidth>innerWidth+1||Boolean(modal&&modal.scrollWidth>modal.clientWidth+1);
   }),false,label+' fits '+width+'px');
   frames.push({label,image:(await page.screenshot({type:'jpeg',quality:80})).toString('base64')});
  };
  const sum=async locator=>(await locator.innerText()).replace(/\s+/g,' ').trim();
  page.on('pageerror',error=>failures.push(error.message));
  try{
   await page.route('**/*',async route=>{
    const u=new URL(route.request().url());
    if(u.origin!==origin||!u.pathname.startsWith(base)||/\/(?:api-|data\/)|\.php$/.test(u.pathname)){
     external.push(u.pathname);await route.abort();return;
    }
    await route.continue();
   });
   const response=await page.goto(origin+base+'visual-search/?scenario=journey');
   assert.equal(response.status(),200);
   await page.locator('.search-submit:not(:disabled)').waitFor();
   const stars=page.locator('#quick-stars [data-value="4"]');
   await stars.click();
   assert.equal(await stars.getAttribute('aria-pressed'),'true');
   assert.equal(await page.locator('.hotel-card').count(),0);
   await frame('Параметры поиска');
   await page.locator('.search-submit').click();
   const card=page.locator('#hotel-900002');
   await card.waitFor();
   assert.equal(await page.locator('.hotel-card').count(),2,'both matching four-star hotels');
   await card.evaluate(el=>el.scrollIntoView({block:'start',behavior:'instant'}));
   await frame('Выдача с фильтром 4★');
   await card.getByRole('button',{name:/Номера и цены/}).click();
   const family=page.locator('.hotel-room-card[data-room="Семейный номер"]');
   await family.scrollIntoViewIfNeeded();
   await frame('Номера и питание');
   await family.locator('[data-action="select-hotel-offer"]').click();
   const form=page.locator('#prototype-lead-form');
   await form.waitFor();
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'169 500 ₽');
   await page.locator('[data-action="contact-flights"]').click();
   await page.locator('[name="flight-pair"][value="1"]').check();
   assert.match(await sum(page.locator('#flight-price-change')),/1 300 ₽.*20 кг.*Шереметьево.*06:05/);
   await frame('Выбор перелёта');
   await page.locator('[data-action="apply-flight"]').click();
   await form.waitFor();
   const heading=page.locator('.application-review-heading');
   assert.match(await sum(heading),/Palm Garden/);
   assert.match(await sum(heading),/Москва.*Кемер.*Турция/);
   assert.match(await sum(heading),/Семейный номер.*Всё включено/);
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'170 800 ₽');
   await frame('Итог тура и заявка');
   await form.locator('[name="phone"]').fill('70000000000');
   await page.locator('#modal-back').click();
   await page.locator('#hotel-detail-offers').waitFor();
   assert.equal(await sum(page.locator('#hotel-detail-min')),'170 800 ₽');
   await frame('Возврат к выбранному туру');
   await page.locator('#hotel-detail-offers').click();
   await form.waitFor();
   assert.equal(await form.locator('[name="phone"]').inputValue(),'70000000000');
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'170 800 ₽');
   await page.locator('[data-action="close-modal"]').click();
   assert.equal(await page.locator('.hotel-card').count(),2);
   assert.equal(new URL(page.url()).searchParams.get('stars'),'4');
   assert.deepEqual(external,[]);assert.deepEqual(failures,[]);
   await saveJourney(page,frames,width,evidence);
   console.log('PASS complete Search3 journey at '+width+'px from blank form to selected room, flight, contacts and return');
  } finally { await context.close(); }
 }
};
