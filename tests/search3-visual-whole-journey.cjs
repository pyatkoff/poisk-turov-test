'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawn} = require('node:child_process');
const {canonicalLogoFixture} = require('./search3-visual-live-fixture.cjs');

async function checkPhoneFlightOptions(evaluate) {
 const rows = await evaluate(`(() => {
  return [...document.querySelectorAll('.flight-option-compact')].slice(0, 3).map(option =>
   [...option.querySelectorAll('.flight-choice-row .flight-compact-leg')].map(leg => {
    const time = leg.querySelector('strong'), stops = leg.querySelector('.flight-route-stops');
    const range = document.createRange();
    range.selectNodeContents(time);
    const ink = range.getBoundingClientRect(), box = leg.getBoundingClientRect();
    return {text:time.textContent, top:ink.top, left:ink.left, right:ink.right,
     fits:range.getClientRects().length === 1 && ink.width > 0 && ink.left >= box.left - 1 && ink.right <= box.right + 1,
     stopsVisible:stops.getBoundingClientRect().height > 0 && stops.textContent.trim() !== ''};
   })
  );
 })()`);
 assert.equal(rows.length, 3);
 for (const pair of rows) {
  assert.equal(pair.length, 2);
  assert.ok(pair.every(leg => leg.fits && leg.stopsVisible), 'times and stops remain readable: ' + JSON.stringify(pair));
  const [outbound, inbound] = pair;
  assert.ok(outbound.right < inbound.left && Math.abs(outbound.top - inbound.top) <= 1,
   'outbound and return times line up for comparison: ' + JSON.stringify(pair));
 }
}

// Check what the traveler can actually see in the captured viewport, including
// clipping and overlays from sticky headers, footers, and the mobile toolbar.
async function assertVisibleInFrame(driver, selector, count) {
 const hidden = await driver.evaluate(`Array.from(document.querySelectorAll(${JSON.stringify(selector)}), element => {
  const rect = element.getBoundingClientRect();
  const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
  return rect.width > 0 && rect.height > 0 && rect.left >= -1 && rect.right <= innerWidth + 1
   && rect.top >= -1 && rect.bottom <= innerHeight + 1 && element.contains(hit) ? null : element.textContent;
 })`);
 assert.equal(hidden.length, count, 'expected elements for ' + selector);
 assert.deepEqual(hidden.filter(value => value !== null), [], 'content must be visible in the screenshot: ' + selector);
}

async function checkFamilyResultRecap(driver, url, width, capture) {
 const entry = new URL(url);
 entry.searchParams.set('adults', '2');
 entry.searchParams.set('ages', '7');
 entry.searchParams.set('meals', 'Всё включено');
 entry.searchParams.set('stars', '4');
 const card = '#hotel-900002', selected = card + ' .current-card-selection';
 const recap = width === 430 ? '#compact-details' : '#applied-search .applied-trip';
 const edit = width === 430 ? '#compact-search .secondary[data-action="top"]' : '#applied-search [data-action="edit-search"]';
 const total = '#modal-footer .footer-total > strong';
 const flightHeading = '.application-flight-heading h3';
 const floatingFlightNotice = () => driver.evaluate('document.querySelector("#toast:not([hidden])")?.textContent === "Перелёт выбран"');
 const text = async selector => (await driver.text(selector)).replace(/\s+/g, ' ').trim();

 await driver.go(entry.href);
 const chooseHotel = width === 430;
 const place = chooseHotel ? 'Palm Garden · демо' : 'Кемер';
 const route = (chooseHotel ? '#compact-details' : '#applied-search') + ' .applied-destination-name';
 await driver.click('#search-form [data-action="destination"]:not(:disabled)');
 if (chooseHotel) {
  await driver.fill('#destination-query', 'Palm');
  await driver.click('[data-action="destination-hotel"][data-id="900002"]');
 } else {
  await driver.click('[data-action="destination-resort"][data-value="Кемер"]');
 }
 await driver.click('[data-action="apply-destination"]');
 assert.equal(await text('#destination-detail'), place);
 await driver.click('.search-submit:not(:disabled)');
 await driver.wait(card + ' [data-card-entry]');
 assert.equal(await text(route), place);
 const placeFits = await driver.evaluate(`(() => {
  const name = document.querySelector(${JSON.stringify(route)});
  const box = name.getBoundingClientRect();
  const range = document.createRange();
  range.selectNodeContents(name);
  const ink = range.getBoundingClientRect();
  return box.width > 0 && box.height > 0 && box.left >= 0 && box.right <= innerWidth + 1
   && ink.left >= box.left - 1 && ink.right <= box.right + 1;
 })()`);
 assert.equal(placeFits, true, 'the chosen destination is legible at ' + width + 'px');
 assert.equal(await driver.count('.hotel-card'), 1);
 assert.match(await text(recap), /7 лет/);
 assert.equal(await text(card + ' .starting-price strong'), '161 600 ₽');
 await driver.click(card + ' [data-card-entry]');
 await driver.click('[data-room="Семейный номер"] [data-action="select-hotel-offer"]');
 assert.equal(await text(flightHeading), 'Перелёт', 'the tour starts without a selected flight');
 await driver.click('[data-action="contact-flights"]');
 await driver.click('[name="flight-pair"][value="1"]');
 if (width === 430) await checkPhoneFlightOptions(script => driver.evaluate(script));
 await driver.click('[data-action="apply-flight"]');
 await driver.wait('#prototype-lead-form');
 assert.equal(await text(flightHeading), 'Перелёт выбран', 'the flight section confirms the selected pair');
 assert.match(await text('.summary-flight-details > summary'), /06:05 → 10:40.*08:05 → 12:40/);
 assert.equal(await floatingFlightNotice(), false, 'flight feedback leaves the application form and total unobstructed');
 assert.match(await text('.application-review-heading'), /7 лет/);
 assert.match(await text('.application-review-heading'), /Palm Garden.*Кемер/);
 assert.equal(await text(total), '170 800 ₽');
 const breakdown = '.application-price-breakdown';
 const priceParts = [
  ['tour', 'Тур в поиске', '169 500 ₽'],
  ['flight', 'Выбранный перелёт', '+1 300 ₽'],
  ['total', 'Итого за всех', '170 800 ₽'],
 ];
 for (const [part, label, amount] of priceParts) {
  const row = breakdown + ' [data-price-part="' + part + '"]';
  assert.equal(await text(row + ' dt'), label);
  assert.equal(await text(row + ' dd'), amount);
 }
 await assertVisibleInFrame(driver, breakdown + ' dt, ' + breakdown + ' dd', 6);
 await capture('family-contact', 'Заявка на тур · 7 лет');
 await driver.click('[data-action="close-modal"]');
 await driver.wait(selected);
 assert.equal(await floatingFlightNotice(), false, 'returning to results dismisses the flight confirmation');
 assert.equal(await text(selected + ' .card-selection-price strong'), '170 800 ₽');
 assert.equal(await text(card + ' .starting-price strong'), '161 600 ₽');
 assert.match(await text(recap), /7 лет/);
 await driver.evaluate(`document.querySelector(${JSON.stringify(card)}).scrollIntoView({block:"start", behavior:"instant"})`);
 const selectionPrecedesPhotos = await driver.evaluate(`(() => {
  const hotel = document.querySelector(${JSON.stringify(card)});
  const selection = hotel.querySelector('.current-card-selection');
  return selection.getBoundingClientRect().bottom <= hotel.querySelector('.hotel-photos').getBoundingClientRect().top + 1;
 })()`);
 assert.ok(selectionPrecedesPhotos, 'the saved tour precedes the hotel photos');
 const resume = selected + ' [data-card-selection]';
 await assertVisibleInFrame(driver, selected + ' .card-selection-price strong, ' + resume, 2);
 await capture('family-return', 'Выбранный тур в выдаче');
 await driver.click(resume);
 await driver.wait('#detail-total');
 assert.equal(await text('#detail-total'), '170 800 ₽');
 await driver.click('#modal-footer [data-action="confirm-tour"]');
 await driver.wait('#prototype-lead-form');
 assert.match(await text('.application-review-heading'), /Семейный номер.*Всё включено/);
 assert.match(await text('.summary-flight-details > summary'), /06:05 → 10:40.*08:05 → 12:40/);
 assert.equal(await text(total), '170 800 ₽');
 await driver.click('[data-action="close-modal"]');

 await driver.evaluate('scrollTo({top:0, behavior:"instant"})');
 await driver.click(edit);
 assert.equal(await text('#destination-detail'), place);
 assert.equal(await floatingFlightNotice(), false, 'editing search conditions has no previous flight confirmation');
 assert.equal(await text('#meal-label'), 'Всё включено');
 await driver.click('#search-form [data-action="guests"]');
 await driver.selectOption('#inline-search-guests [data-inline-child-age="0"]', '10');
 await driver.click('#inline-search-guests [data-action="apply-guests"]');
 assert.match(await text('#guests-detail'), /10 лет/);
 assert.equal(await driver.evaluate('new URL(location.href).searchParams.get("ages")'), '7');
 await driver.evaluate('scrollTo({top:0, behavior:"instant"})');
 await capture('family-edit', 'Параметры поиска · 10 лет');
 await driver.click('.search-submit');
 await driver.wait(card + ' [data-card-entry]');
 assert.equal(await driver.count(selected), 0, 'the tour for the previous party is no longer selected');
 assert.equal(await floatingFlightNotice(), false, 'the new party search has no previous flight confirmation');
 const applied = await text(recap);
 assert.match(applied, /10 лет/);
 assert.doesNotMatch(applied, /7 лет/);
 assert.equal(await text(route), place);
 assert.equal(await driver.evaluate('new URL(location.href).searchParams.get(' + JSON.stringify(chooseHotel ? 'hotel' : 'resorts') + ')'), chooseHotel ? '900002' : 'Кемер');
 assert.equal(await driver.evaluate('new URL(location.href).searchParams.get("ages")'), '10');
 assert.equal(await text(card + ' .starting-price strong'), '161 600 ₽');
 assert.match(await text(card), /Всё включено/);
 await driver.evaluate('scrollTo({top:0, behavior:"instant"})');
 await capture('family-result-age', 'Новая выдача · 10 лет');
}

async function checkSelectedFlightTimes(page, selector, layout) {
 await page.evaluate(() => document.fonts.ready.then(() => true));
 const times = await page.locator(selector).evaluateAll(nodes => nodes.map(node => {
  const range = document.createRange();
  range.selectNodeContents(node);
  const lines = [...range.getClientRects()];
  const box = node.getBoundingClientRect();
  const ink = range.getBoundingClientRect();
  return {text:node.textContent, lines:lines.length, top:ink.top, bottom:ink.bottom, left:ink.left, right:ink.right,
   fits:ink.width > 0 && ink.left >= box.left - 1 && ink.right <= box.right + 1};
 }));
 const viewport = JSON.stringify(page.viewportSize());
 const expected = layout === 'summary' ? ['Туда 06:05 → 10:40', 'Обратно 08:05 → 12:40'] : ['06:05 → 10:40', '08:05 → 12:40'];
 assert.deepEqual(times.map(time => time.text), expected);
 assert.ok(times.every(time => time.lines === 1 && time.fits), 'each departure and arrival fits on one line at ' + viewport + ': ' + JSON.stringify(times));
 const [outbound, inbound] = times;
 const inColumns = outbound.right < inbound.left && Math.abs(outbound.top - inbound.top) <= 1;
 const inRows = inbound.top >= outbound.bottom - 1;
 if (layout === 'columns') {
  assert.ok(inColumns, 'both directions align side by side at ' + viewport);
 } else if (layout === 'rows') {
  assert.ok(inRows, 'flight directions have separate rows at ' + viewport);
 } else if (layout === 'adaptive') {
  assert.ok(inColumns || inRows, 'both directions remain separate at ' + viewport);
 }
}

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
   const columns = Math.ceil(tiles.length / 2);
   canvas.width = columns * tileWidth + (columns + 1) * pad;
   canvas.height = 2 * (tileHeight + heading) + 3 * pad;
   const ctx = canvas.getContext('2d');
   ctx.fillStyle = '#f3f6fa';
   ctx.fillRect(0,0,canvas.width,canvas.height);
   ctx.font = 'bold 18px sans-serif';
   ctx.fillStyle = '#17263e';
   tiles.forEach(({img,label},index) => {
    const x = pad + index % columns * (tileWidth + pad);
    const y = pad + Math.floor(index / columns) * (tileHeight + heading + pad);
    ctx.fillText(label,x,y + 22);
    ctx.drawImage(img,x,y + heading,tileWidth,tileHeight);
   });
   return canvas.toDataURL('image/webp',0.77).split(',')[1];
  },{frames,tileWidth});
  fs.writeFileSync(path.join(evidence,options.filename || 'whole-search-'+width+'.webp'),Buffer.from(result,'base64'));
 } finally { await sheet.close(); }
}

async function runBrowserJourney(root, temp, name, output, timeout, ...scenario) {
 const log = path.join(temp, name + '.log');
 const fd = fs.openSync(log, 'wx');
 try {
  await new Promise((resolve, reject) => {
   const child = spawn('xvfb-run', [
    '-a', '--server-args=-screen 0 1366x1400x24', process.execPath,
    path.join(__dirname, 'search3-visual-' + name + '.cjs'), root, output, ...scenario,
   ], {stdio:['ignore', fd, fd], timeout});
   child.once('error', reject);
   child.once('close', (code, signal) => {
    if (code === 0) resolve();
    else reject(new Error(name + ' exited with ' + (signal || code)));
   });
  });
 } finally {
  fs.closeSync(fd);
  process.stdout.write(fs.readFileSync(log));
 }
}

module.exports = async function wholeSearch({browser,origin,base,evidence}) {
 if (process.platform === 'linux') {
  const root = process.env.SEARCH3_VISUAL_ASSET_ROOT || path.resolve(__dirname, '../v2');
  if (process.env.SEARCH3_VISUAL_PERFORMANCE_LANE !== 'separate') {
   require('./search3-visual-performance-suite.cjs')(root);
  }
  const temp = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'search3-rdm-'));
  const raw = path.join(temp, 'firefox-rdm');
  const retained = path.join(evidence, 'firefox-rdm');
  fs.mkdirSync(retained, {recursive:true});
  try {
   // Each subprocess gets its own display, profile, and loopback port.
   // Wait for both groups before reading receipts or cleaning up evidence.
   const started = performance.now();
   const outcomes = await Promise.allSettled([
    runBrowserJourney(root, temp, 'firefox-rdm', temp, 180000),
    (async () => {
     await runBrowserJourney(root, temp, 'family-250', path.join(temp,'family-250'), 180000, 'summary-first');
     await runBrowserJourney(root, temp, 'picker-recovery', path.join(temp,'pickers'), 90000);
    })(),
   ]);
   const failures = outcomes.filter(result => result.status === 'rejected');
   if (failures.length) throw new AggregateError(failures.map(result => result.reason), 'Independent browser journeys failed');
   console.log('PASS independent browser journeys', JSON.stringify({duration_ms:Math.round(performance.now()-started)}));
   const report = JSON.parse(fs.readFileSync(path.join(raw, 'result.json'), 'utf8'));
   const labels = ['Параметры поиска', 'Выдача с фильтром 4★', 'Номера и питание',
    'Выбор перелёта', 'Итог тура и заявка', 'Цена при возврате к туру'];
   assert.equal(report.selection.frames.length, labels.length);
   const frames = report.selection.frames.map((file, i) => ({
    label: labels[i], format: 'png', image: fs.readFileSync(path.join(raw, file)).toString('base64'),
   }));
   const filename = 'whole-search-firefox-430.webp';
   const familyFilename = 'family-edit-journey-430.webp';
   assert.equal(report.selection.familyFrames.length, 4);
   const familyFrames = report.selection.familyFrames.map(({file,label}) => ({
    label, format:'png', image:fs.readFileSync(path.join(raw,file)).toString('base64'),
   }));
   const context = await browser.newContext();
   try {
    await saveJourney(context, frames, 430, retained, {filename, tileWidth:430});
    await saveJourney(context, familyFrames, 430, retained, {filename:familyFilename, tileWidth:430});
   } finally { await context.close(); }
   report.selection.frames = report.selection.frames.map((file, i) => ({stage:path.parse(file).name, label:labels[i]}));
   report.selection.contactSheet = filename;
   report.selection.familyContactSheet = familyFilename;
   report.selection.familyFrames = report.selection.familyFrames.map(({file,label}) => ({stage:path.parse(file).name, label}));
   for(const file of [report.selection.similarRoomsFrame, report.selection.returnedRoomFrame, report.selection.selectedMealFrame,
    report.selection.departureFilterFrame, 'family-return-430.png', 'family-contact-430.png']){
    fs.copyFileSync(path.join(raw,file), path.join(retained,file));
   }
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
  const failures=[],external=[],archiveRequests=[],frames=[];
  const frame=async (label,collection=frames,file)=>{
   await page.evaluate(()=>document.fonts.ready.then(()=>true));
   assert.equal(await page.evaluate(()=>{
    const modal=document.querySelector('dialog[open]');
    return document.documentElement.scrollWidth>innerWidth+1||Boolean(modal&&modal.scrollWidth>modal.clientWidth+1);
   }),false,label+' fits '+width+'px');
   const picture=await page.screenshot(file ? {path:path.join(evidence,file),type:'png'} : {type:'jpeg',quality:80});
   collection.push({label,format:file?'png':'jpeg',image:picture.toString('base64')});
  };
  const sum=async locator=>(await locator.innerText()).replace(/\s+/g,' ').trim();
  page.on('pageerror',error=>failures.push(error.message));
  page.on('request',request=>{
   const url=new URL(request.url());
   if(url.origin===origin&&url.pathname===base+'visual-search/fixtures/live-search-2026-09-23.json')archiveRequests.push(url.pathname);
  });
  try{
   await page.route('**/*',async route=>{
    const logo=canonicalLogoFixture(route.request(),origin);if(logo){await route.fulfill(logo);return;}
    const u=new URL(route.request().url());
    if(u.origin!==origin||!u.pathname.startsWith(base)||/\/(?:api-|data\/)|\.php$/.test(u.pathname)){
     external.push(u.pathname);await route.abort();return;
    }
    await route.continue();
   });
   const response=await page.goto(origin+base+'visual-search/?scenario=journey');
   assert.equal(response.status(),200);
   await page.locator('.search-submit:not(:disabled)').waitFor();
   assert.deepEqual(archiveRequests,[],'the public journey enables Find without the saved archive');
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
   if(width===430){
    const difference=standard.nth(1).locator('.room-price-difference');
    const lines=await difference.evaluate(el=>{
     const range=document.createRange();range.selectNodeContents(el);
     return new Set([...range.getClientRects()].map(rect=>Math.round(rect.top))).size;
    });
    assert.equal(lines,1,'the price difference fits on one line beside the meal on a phone');
   }
   await frame('Номера и питание');
   await family.locator('[data-action="select-hotel-offer"]').click();
   const form=page.locator('#prototype-lead-form');
   await form.waitFor();
   assert.equal(await sum(page.locator('#modal-footer .footer-total>strong')),'169 500 ₽');
   await page.locator('[data-action="contact-flights"]').click();
   await page.locator('[name="flight-pair"][value="1"]').check();
   if(width===430)await checkPhoneFlightOptions(script=>page.evaluate(script));
   assert.match(await sum(page.locator('[data-flight-index="1"] .flight-comparison')),/1\s?300.*06:05.*20\s?кг/);
   assert.match(await sum(page.locator('#flight-price-change')),/1 300 ₽.*20 кг.*Шереметьево.*06:05/);
   await frame('Выбор перелёта');
   await page.locator('[data-action="apply-flight"]').click();
   await form.waitFor();
   const heading=page.locator('.application-review-heading');
   assert.match(await sum(heading),/Palm Garden/);
   assert.match(await sum(heading),/Москва.*Кемер.*Турция/);
   assert.match(await sum(heading),/Семейный номер.*Всё включено/);
   const flightDetails=page.locator('.application-flight-overview .summary-flight-details');
   if(width===430){
    assert.equal(await flightDetails.evaluate(el=>el.open),false);
    assert.match(await sum(flightDetails.locator('summary')),/Багаж 20 кг/);
   }else{
    assert.match(await sum(flightDetails),/Багаж\s*20 кг/);
   }
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
   const tourTimes='.tour-overview .chosen-flight-pair .flight-compact-leg > strong';
   await checkSelectedFlightTimes(page,tourTimes,width===1280?'columns':'rows');
   await frame('Цена при возврате к туру');
   if(width===1280){
    await page.setViewportSize({width:901,height:720});
    await checkSelectedFlightTimes(page,tourTimes,'adaptive');
    await page.setViewportSize({width,height:720});
    await checkSelectedFlightTimes(page,tourTimes,'columns');
   }
   await footer.locator('[data-action="confirm-tour"]').click();
   await checkSelectedFlightTimes(page,'.application-flight-overview .summary-flight-times > span:not(.selected-flight-line)','summary');
   if(width===430)await flightDetails.locator('summary').click();
   await checkSelectedFlightTimes(page,'.application-flight-overview .saved-flight-times','rows');
   if(width===1280)await page.setViewportSize({width,height:800});
   assert.equal(await form.locator('[name="phone"]').inputValue(),'70000000000');
   assert.equal(await sum(footer.locator('.footer-total>strong')),'170 800 ₽');
   assert.deepEqual(external,[]);assert.deepEqual(failures,[]);
   assert.equal(frames.length,6);
   await saveJourney(context,frames,width,evidence);
   if(width===1280){
    const familyFrames=[];
    await checkFamilyResultRecap({
     go:url=>page.goto(url), click:selector=>page.locator(selector).click(), fill:(selector,value)=>page.locator(selector).fill(value),
     wait:selector=>page.locator(selector).waitFor(), text:selector=>page.locator(selector).innerText(),
     selectOption:(selector,value)=>page.locator(selector).selectOption(value),
     count:selector=>page.locator(selector).count(), evaluate:script=>page.evaluate(script)
    },origin+base+'visual-search/?scenario=journey',width,(stage,label)=>
     frame(label,familyFrames,['family-contact','family-return'].includes(stage) ? stage+'-'+width+'.png' : undefined));
    assert.equal(familyFrames.length,4);
    await saveJourney(context,familyFrames,width,evidence,{filename:'family-edit-journey-1280.webp'});
    assert.deepEqual(external,[]);assert.deepEqual(failures,[]);
   }
   assert.deepEqual(archiveRequests,[],'the public journey and family return stay independent of the saved archive');
   console.log('PASS complete Search3 journey at '+width+'px from blank form to selected room, flight, contacts and return');
  } finally { await context.close(); }
 }
};
module.exports.checkFamilyResultRecap = checkFamilyResultRecap;
