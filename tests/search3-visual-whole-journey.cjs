'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Keep a visual record of one continuous session at each size. Small contact
// sheets fit the existing acceptance archive without duplicating full frames.
async function saveJourney(context, frames, width, evidence, options = {}) {
 const sheet = await context.newPage();
 try {
  const tileWidth = options.tileWidth || (width < 600 ? 320 : 512);
  const result = await sheet.evaluate(async ({frames,tileWidth}) => {
   const pad = 12, heading = 34;
   const tiles = await Promise.all(frames.map(async frame => {
    const img = new Image();
    img.src = 'data:image/' + (frame.format || 'jpeg') + ';base64,' + frame.image;
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
  },{frames,tileWidth});
  fs.writeFileSync(path.join(evidence,options.filename || 'whole-search-'+width+'.webp'),Buffer.from(result,'base64'));
 } finally { await sheet.close(); }
}

module.exports = async function wholeSearch({browser,origin,base,evidence}) {
 if (process.platform === 'linux') {
  const root = process.env.SEARCH3_VISUAL_ASSET_ROOT || path.resolve(__dirname, '../v2');
  const temp = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'search3-rdm-'));
  const raw = path.join(temp, 'firefox-rdm');
  const retained = path.join(evidence, 'firefox-rdm');
  fs.mkdirSync(retained, {recursive:true});
  try {
   require('node:child_process').execFileSync('xvfb-run', [
    '-a', '--server-args=-screen 0 1366x1400x24', process.execPath,
    path.join(__dirname, 'search3-visual-firefox-rdm.cjs'), root, temp,
   ], {stdio:'inherit', timeout:180000});
   const report = JSON.parse(fs.readFileSync(path.join(raw, 'result.json'), 'utf8'));
   const labels = ['Параметры поиска', 'Выдача с фильтром 4★', 'Номера и питание',
    'Выбор перелёта', 'Итог тура и заявка', 'Цена при возврате к туру'];
   assert.equal(report.selection.frames.length, labels.length);
   const frames = report.selection.frames.map((file, i) => ({
    label: labels[i], format: 'png', image: fs.readFileSync(path.join(raw, file)).toString('base64'),
   }));
   const filename = 'whole-search-firefox-430.webp';
   const context = await browser.newContext();
   try {
    await saveJourney(context, frames, 430, retained, {filename, tileWidth:430});
   } finally { await context.close(); }
   report.selection.frames = report.selection.frames.map((file, i) => ({stage:path.parse(file).name, label:labels[i]}));
   report.selection.contactSheet = filename;
   fs.writeFileSync(path.join(retained,'result.json'), JSON.stringify(report,null,2));
  } catch (error) {
   const last = path.join(raw,'last-state.png');
   if (fs.existsSync(last)) fs.copyFileSync(last,path.join(retained,'last-state.png'));
   throw error;
  } finally { fs.rmSync(temp,{recursive:true,force:true}); }
 }
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
   assert.equal(await page.locator('#cards:empty').count(),1,'the ready form has no duplicate prompt');
   await frame('Параметры поиска');
   await page.locator('.search-submit').click();
   const card=page.locator('#hotel-900002');
   await card.waitFor();
   assert.equal(await page.locator('.hotel-card').count(),2,'both matching four-star hotels');
   assert.equal(await sum(card.locator('.starting-from')),'от');
   await card.evaluate(el=>el.scrollIntoView({block:'start',behavior:'instant'}));
   await frame('Выдача с фильтром 4★');
   await card.getByRole('button',{name:/Номера и цены/}).click();
   const family=page.locator('.hotel-room-card[data-room="Семейный номер"]');
   const standard=page.locator('.hotel-room-card[data-room="Стандартный номер"] .room-offer-choice');
   await standard.first().waitFor();
   await page.locator('[data-target="hotel-rooms-heading"][aria-current="location"]').waitFor();
   await page.locator('#hotel-detail-offers').waitFor({state:'hidden'});
   const offers=await standard.evaluateAll(rows=>rows.map(row=>{
    const meal=row.querySelector('.room-offer-meal'),price=row.querySelector('.hotel-room-price > strong');
    const range=document.createRange();range.selectNodeContents(meal);
    const label=range.getBoundingClientRect(),amount=price.getBoundingClientRect();
    const nav=document.querySelector('.hotel-section-nav').getBoundingClientRect(),body=document.querySelector('#modal-body').getBoundingClientRect();
    return {meal:meal.textContent.trim(),price:price.textContent.replace(/\s+/g,' ').trim(),
     sameLine:label.top<amount.bottom&&amount.top<label.bottom,visible:amount.top>=nav.bottom-1&&amount.bottom<=body.bottom+1,
     gap:amount.left-label.right};
   }));
   assert.deepEqual(offers.map(o=>[o.meal,o.price]),[['Завтраки','154 800 ₽'],['Всё включено','161 600 ₽']]);
   assert.ok(offers.every(o=>o.sameLine&&o.visible&&o.gap>=0&&o.gap<=360),'both meals and exact prices can be compared on entry at '+width+'px: '+JSON.stringify(offers));
   await frame('Номера и питание');
   await family.locator('[data-action="select-hotel-offer"]').click();
   const form=page.locator('#prototype-lead-form');
   await form.waitFor();
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'169 500 ₽');
   await page.locator('[data-action="contact-flights"]').click();
   await page.locator('[name="flight-pair"][value="1"]').check();
   assert.match(await sum(page.locator('[data-flight-index="1"] .flight-comparison')),/1\s?300.*06:05.*20\s?кг/);
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
   await page.locator('[data-action="contact-flights"]').click();
   const reopenedPair=page.locator('[data-flight-index="1"]');
   await reopenedPair.waitFor();
   assert.ok(await reopenedPair.getByRole('radio').isChecked());
   assert.equal(await sum(page.locator('[data-flight-index="0"] [data-flight-difference]')),'Без доплаты');
   assert.equal(await sum(reopenedPair.locator('[data-flight-difference]')),'+1 300 ₽ к туру');
   assert.equal(await sum(page.locator('#flight-total')),'170 800 ₽');
   if(width===430)await reopenedPair.screenshot({path:path.join(evidence,'reopened-flight-price-430.jpg'),quality:74});
   await page.locator('[data-action="apply-flight"]').click();
   await form.waitFor();
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'170 800 ₽');
   assert.equal(await form.locator('[name="phone"]').inputValue(),'70000000000');
   await page.locator('#modal-back').click();
   await page.locator('#hotel-detail-offers').waitFor();
   assert.equal(await sum(page.locator('#hotel-detail-min')),'170 800 ₽');
   await page.locator('#hotel-detail-offers').click();
   await form.waitFor();
   assert.equal(await form.locator('[name="phone"]').inputValue(),'70000000000');
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'170 800 ₽');
   await page.locator('[data-action="close-modal"]').click();
   assert.equal(await page.locator('.hotel-card').count(),2);
   assert.equal(new URL(page.url()).searchParams.get('stars'),'4');
   const selected=card.locator('.current-card-selection');
   assert.equal(await sum(card.locator('.starting-price strong')),'154 800 ₽');
   assert.equal(await sum(selected.locator('.card-selection-price strong')),'170 800 ₽');
   assert.match(await sum(selected),/Семейный номер.*Всё включено/);
   await selected.getByRole('button',{name:/Продолжить выбранный тур/}).click();
   const footer=page.locator('#modal-footer');
   assert.equal(await sum(footer.locator('#detail-total')),'170 800 ₽');
   assert.match(await sum(footer.locator('.tour-price-history')),/Этот тур в поиске 169 500 ₽/i);
   await frame('Цена при возврате к туру');
   await footer.locator('[data-action="confirm-tour"]').click();
   assert.equal(await form.locator('[name="phone"]').inputValue(),'70000000000');
   assert.equal(await sum(footer.locator('.footer-total>strong')),'170 800 ₽');
   assert.deepEqual(external,[]);assert.deepEqual(failures,[]);
   await saveJourney(context,frames,width,evidence);
   console.log('PASS complete Search3 journey at '+width+'px from blank form to selected room, flight, contacts and return');
  } finally { await context.close(); }
 }
};
