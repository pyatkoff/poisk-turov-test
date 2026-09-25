const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM, VirtualConsole} = require('jsdom');

const root = path.resolve(__dirname, '../v2/visual-search');
const errors = [], requests = [];
const virtualConsole = new VirtualConsole();
virtualConsole.on('jsdomError', error => errors.push(error.message));
const dom = new JSDOM(fs.readFileSync(path.join(root, 'index.html'), 'utf8'), {
  url: 'https://prototype.test/?scenario=snapshot&origin=%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0&country=4&from=2026-10-01&to=2026-10-07&minNights=7&maxNights=7&adults=2&ages=&searched=1',
  runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole
});
const w = dom.window, d = w.document;
const legacySave='existing local selection';w.localStorage.setItem('anytour.prototype.v18.selected-tour.v1',legacySave);
w.innerWidth = 390;
w.structuredClone = structuredClone;
w.matchMedia = () => ({matches: true, addEventListener() {}, removeEventListener() {}});
w.CSS = {escape: value => String(value).replace(/[^a-zA-Z0-9_-]/g, x => '\\' + x)};
w.IntersectionObserver = class {observe() {} unobserve() {} disconnect() {}};
w.HTMLElement.prototype.scrollIntoView = function() {};
w.scrollTo = () => {};
w.HTMLDialogElement.prototype.showModal = function() {this.open = true;};
w.HTMLDialogElement.prototype.close = function() {this.open = false;};
w.fetch = async url => {
  requests.push(url);
  assert.equal(url, './fixtures/live-search-2026-09-23.json', 'No supplier or contact API may be contacted');
  return {ok: true, json: async () => JSON.parse(fs.readFileSync(path.join(root, url), 'utf8'))};
};
for (const name of ['fixture-data.js', 'recorded-data.js', 'search-lifecycle-v1.js', 'flight-picker-v18.js', 'preview-lead.js', 'app.js']) {
  w.eval(fs.readFileSync(path.join(root, name), 'utf8'));
}
const settle = (delay = 70) => new Promise(resolve => setTimeout(resolve, delay));
const click = selector => {const element = d.querySelector(selector); assert(element, selector); element.click();};

(async()=>{
 await settle(200);
 const initialRequests=requests.length;
 const change=async value=>{const e=d.querySelector('#fixture-scenario');e.value=value;e.dispatchEvent(new w.Event('change',{bubbles:true}));await settle(180);assert.equal(w.AnyTourPrototypeData.scenario,value);};
 for(const scenario of ['mixed','family','incomplete','price-change','flights']){
  await change(scenario);
  assert.equal(d.querySelectorAll('.hotel-card').length,scenario==='mixed'?5:1);
  if(scenario==='family')assert.deepEqual([...w.AnyTourPrototypeData.initialSearch.ages],[0,8]);
  if(scenario==='mixed'){
   click('.hotel-card [data-action="hotel-details"]');await settle();
   const gallery=d.querySelector('.hotel-detail-photos');assert(gallery?.classList.contains('photo-count-2'),'Two supplied photos use the filled two-photo gallery');
   assert.equal(gallery.querySelectorAll('button').length,2);
   const services=d.querySelector('#hotel-services-heading'),rooms=d.querySelector('#hotel-rooms-heading');
   assert(services.compareDocumentPosition(rooms)&w.Node.DOCUMENT_POSITION_FOLLOWING,'Services precede the room-price list');
   assert.deepEqual([...d.querySelectorAll('.hotel-section-nav button')].map(b=>b.textContent),['Фото','Об отеле','Услуги','Номера и цены']);

   click('[data-action="close-modal"]');await settle();
  }
  click('.hotel-card [data-action="offer"]');await settle(130);
  if(scenario==='mixed'){
   const before=d.querySelector('#modal-footer .footer-total strong').textContent;
   const beforeFlight=d.querySelector('.flight-summary').textContent;
   click('[data-action="change-room"]');
   assert(d.querySelector('.offers-dialog'),'Direct card entry can reach other room/meal choices');
   assert([...d.querySelectorAll('.offer-group-heading')].every(el=>el.getAttribute('aria-expanded')==='false'),'Multiple choices start as an overview');
   click('#modal-back');await settle();
   assert.equal(d.querySelector('#modal-footer .footer-total strong').textContent,before,'Browsing other rooms does not alter the chosen price');
   assert.equal(d.querySelector('.flight-summary').textContent,beforeFlight,'Cancel preserves the selected flight pair');
  }
  if(scenario==='price-change'){assert.match(d.querySelector('#detail-total').textContent,/195/);const notice=d.querySelector('.quote-price-change').textContent.replace(/\s/g,'');assert(notice.includes('186400')&&notice.includes('195400'));}else assert(!d.querySelector('.quote-price-change'));
  if(scenario==='incomplete')assert.match(d.querySelector('#modal-body').textContent,/Багаж.*уточняется/s);
  const expected=d.querySelector('#detail-total').textContent;
  const chosenConditions=d.querySelector('.chosen-trip').textContent;
  const exactRows=[...d.querySelectorAll('.chosen-stay dd')].map(el=>el.textContent);
  click('[data-action="confirm-tour"]');await settle();
  const form=d.querySelector('#prototype-lead-form');assert(form,scenario+' reaches contact check');
  assert.equal(d.querySelector('.chosen-trip').textContent,chosenConditions,scenario+' carries the same trip into the application');
  assert.deepEqual([...d.querySelectorAll('.chosen-stay dd')].map(el=>el.textContent),exactRows);
  assert.equal(d.querySelectorAll('.summary-price').length,1,'One application total');
  assert(d.querySelector('#modal-footer .summary-price'),'The application total remains beside its action');
  assert(!form.querySelector('.rehearsal-optional').open,'Optional fields start collapsed');
  const flightDetails=d.querySelector('.summary-flight-details');assert(flightDetails,scenario+' keeps flight details');
  assert.equal(flightDetails.open,false,scenario+' keeps mobile flight details collapsed');
  assert(form.compareDocumentPosition(flightDetails)&w.Node.DOCUMENT_POSITION_FOLLOWING,scenario+' shows application before flight details');
  assert(flightDetails.querySelector('.saved-flight-summary'),scenario+' reveals both supplied flight legs');
  assert.equal(d.querySelector('#modal-title').textContent,'Заявка на тур');
  assert(!d.querySelector('[data-copy-tour]'),'No separate summary/copy detour');
  assert(!d.querySelector('#saved-tour-controls'),'No My tour controls in application');
  assert.equal(w.localStorage.getItem('anytour.prototype.v18.selected-tour.v1'),legacySave,'Selection is not automatically saved or legacy record overwritten');
  assert([...d.querySelectorAll('[data-action="selected-tour"]')].every(e=>e.hidden),'My tour is not in navigation');
  assert.equal(d.querySelector('.summary-price strong').textContent,expected);
  if(scenario==='price-change')assert(d.querySelector('.quote-price-change'),'Changed price stays visible in summary');
  form.elements.phone.value='123';form.dispatchEvent(new w.Event('submit',{cancelable:true,bubbles:true}));assert(!form.dataset.checked);
  form.elements.phone.value='+7 000 000-00-00';form.elements.consent.checked=true;
  form.dispatchEvent(new w.Event('submit',{cancelable:true,bubbles:true}));assert.equal(form.dataset.checked,'1',scenario);
  const status=form.querySelector('[role="status"]').textContent;assert(status.includes(expected.replace(/\s/g,' '))||status.replace(/\s/g,'').includes(expected.replace(/\s/g,'')));
  assert.match(status,/Заявка не отправлена/);if(scenario==='family')assert.match(status,/дети: до года, 8 лет/);
  assert(!JSON.stringify(w.localStorage).includes('000 000-00-00'),'Contacts not persisted');
  form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));assert(!form.dataset.checked,'Editing clears success');
  click('#modal-back');await settle();assert.equal(d.querySelector('#detail-total').textContent,expected,'Back retains the selected total');assert.match(d.querySelector('[data-action="confirm-tour"]').textContent,/К заявке/);
  assert(!d.querySelector('#saved-tour-controls'),'No My tour detour in exact-tour details');
 }
 await change('mixed');
 let hotels;await w.AnyTourPrototypeData.search(w.AnyTourPrototypeData.initialSearch,e=>{if(e.type==='results')hotels=e.hotels;});
 const chosen=hotels[1].offers[1],before=await w.AnyTourPrototypeData.quote(chosen),oldNow=w.Date.now;
 w.Date.now=()=>oldNow()+86400000*2;const after=await w.AnyTourPrototypeData.quote(chosen);assert.equal(before.price,after.price);assert.equal(before.id,after.id);w.Date.now=oldNow;
 for(const scenario of ['expired','unavailable']){await change(scenario);click('.hotel-card [data-action="offer"]');await settle(130);assert(d.querySelector('.error-text[role="alert"]'));assert(!d.querySelector('[data-action="confirm-tour"]'));assert(!d.querySelector('#prototype-lead-form'));assert(d.querySelector('#modal-footer [data-action="close-modal"]'));click('#modal-footer [data-action="close-modal"]');}
 await change('flight-error');click('.hotel-card [data-action="offer"]');await settle(130);assert(d.querySelector('[data-action="retry-flights"]'));assert(!d.querySelector('[data-action="confirm-tour"]'));
 await change('empty');assert.equal(d.querySelectorAll('.hotel-card').length,0);
 await change('snapshot');
 const hotelQuery=d.querySelector('#hotel-query');hotelQuery.value='THE LAILA';hotelQuery.dispatchEvent(new w.Event('input',{bubbles:true}));await settle(220);
 click('.hotel-card [data-action="hotel-details"]');await settle();
 click('[data-action="close-modal"]');await settle();click('.hotel-card [data-action="offer"]');await settle();assert(!d.querySelector('[data-action="confirm-tour"]'),'Snapshot cannot open a fake application or summary');assert.match(d.querySelector('.tour-recording-limit').textContent,/Перейти к заявке здесь нельзя/);assert(!d.querySelector('#prototype-lead-form'),'Real snapshot must not gain invented quote/flights');click('#modal-footer [data-action="close-modal"]');await settle();assert(!d.querySelector('#modal').open,'Snapshot returns to results');
 await change('family');
 let recordedHotels;await w.AnyTourPrototypeData.search(w.AnyTourPrototypeData.initialSearch,e=>{if(e.type==='results')recordedHotels=e.hotels;});
 const record={schemaVersion:1,kind:'demo',currency:'RUB',countryName:'Турция',capturedAt:'2026-09-24T12:00:00Z',source:'Synthetic fixture for import regression',search:structuredClone(w.AnyTourPrototypeData.initialSearch),hotels:structuredClone(recordedHotels),entries:[]};
 for(const h of recordedHotels)for(const o of h.offers){const quote=await w.AnyTourPrototypeData.quote(o);record.entries.push({offerKey:o.key,quote,flights:await w.AnyTourPrototypeData.flights(quote)});}
 if(process.env.RECORDING_QA_OUTPUT)fs.writeFileSync(process.env.RECORDING_QA_OUTPUT,JSON.stringify(record));
 const bad=modify=>{const r=structuredClone(record);modify(r);assert.throws(()=>w.AnyTourRecording.validate(r));};
 bad(r=>delete r.entries);bad(r=>r.entries[0].offerKey='wrong');bad(r=>r.entries[0].quote.roomType='Different room');bad(r=>r.hotels[0].offers[0].ages=[8]);bad(r=>r.entries[0].flights[0].price++);bad(r=>r.entries[0].flights[0].forward[0].departure.date='2026-10-02');bad(r=>r.currency='EUR');bad(r=>r.entries.push(r.entries[0]));
 const beforeRecordingStorage=JSON.stringify(w.localStorage);
 const input=d.querySelector('#recording-file');Object.defineProperty(input,'files',{configurable:true,value:[{size:1000,text:async()=>JSON.stringify(record)}]});input.dispatchEvent(new w.Event('change'));await settle(250);
 assert.equal(w.AnyTourPrototypeData.scenario,'recorded');assert.match(d.querySelector('#recording-status').textContent,/Загружено 1 отелей и 1 туров/);assert.match(w.AnyTourPrototypeData.describe(),/Демонстрационная запись/);
 click('.hotel-card [data-action="offer"]');await settle(180);assert.match(d.querySelector('#detail-total').textContent,/186/);click('[data-action="confirm-tour"]');await settle();
 const recordedForm=d.querySelector('#prototype-lead-form');assert(recordedForm);recordedForm.elements.phone.value='+7 000 000-00-00';recordedForm.elements.consent.checked=true;recordedForm.dispatchEvent(new w.Event('submit',{cancelable:true,bubbles:true}));assert.equal(recordedForm.dataset.checked,'1');assert.match(recordedForm.textContent,/дети: до года, 8 лет/);
 assert.equal(JSON.stringify(w.localStorage),beforeRecordingStorage,'Imported selection is not persisted');
 assert.equal(d.querySelectorAll('[data-action="save-tour-for-later"]').length,0);
 const clock=w.Date.now;w.Date.now=()=>clock()+86400000*30;assert.equal((await w.AnyTourPrototypeData.quote(record.hotels[0].offers[0])).price,186400);w.Date.now=clock;
 const wrongParty={...record.search,ages:[]};let mismatch;await w.AnyTourPrototypeData.search(wrongParty,e=>{if(e.type==='results')mismatch=e.hotels;});assert.equal(mismatch.length,0,'No silently fabricated tours for another party');
 Object.defineProperty(input,'files',{configurable:true,value:[{size:1000,text:async()=>'{}'}]});input.dispatchEvent(new w.Event('change'));await settle();assert.match(d.querySelector('#recording-status').textContent,/не загружена/);assert.equal(w.AnyTourPrototypeData.scenario,'recorded','Invalid replacement retains current recording');
 await change('flights');assert.equal(d.querySelectorAll('.hotel-card').length,1);
 assert.equal(requests.length,initialRequests,'No provider, quote, contact or live API request');assert.deepEqual(errors,[]);
 console.log('PASS v132 direct application, no My tour persistence, services before rooms; v120 recorded import/identity/30-day/local-application guards plus v118: mixed/family/incomplete/reprice journeys to dry-run application, 48h stability, expired/unavailable/error guards, contact privacy, snapshot honesty and no external requests');dom.window.close();
})().catch(e=>{console.error(e);dom.window.close();process.exitCode=1;});
