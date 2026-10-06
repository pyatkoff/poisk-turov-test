'use strict';
// Executes the actual NEXT scripts (or compiled assets via ANYTOUR_QA_ROOT).
// Every fetch is restricted to committed local
// assets. jsdom verifies state/interaction only; browser layout is checked separately.
const {JSDOM,VirtualConsole}=require(process.env.ANYTOUR_QA_DOM||'jsdom');
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(process.env.ANYTOUR_QA_ROOT||path.join(__dirname,'../v2/visual-search')),evidence=path.resolve(process.env.ANYTOUR_QA_EVIDENCE||path.join(__dirname,'../visual-live-evidence/approved-interface'));
const pause=()=>new Promise(resolve=>setTimeout(resolve,30));
async function run(width,monthRange=null){
 const errors=[],reads=[];
 const vc=new VirtualConsole();vc.on('jsdomError',error=>errors.push(error.message));
 const dom=new JSDOM(fs.readFileSync(path.join(root,'index.html'),'utf8'),{url:'https://site.test/?scenario=snapshot&searched=1'+(monthRange?'&from='+monthRange[0]+'&to='+monthRange[1]:''),runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc});
 const w=dom.window,d=w.document;
 // Use the same quoted-selector shim as the existing saved/live DOM harnesses.
 w.CSS||={escape:value=>String(value).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};
 w.IntersectionObserver=class{constructor(callback){this.callback=callback;}observe(target){if(target.classList.contains('calendar-month'))queueMicrotask(()=>this.callback([{target,isIntersecting:true}]));}disconnect(){}unobserve(){}};w.innerWidth=width;w.structuredClone=structuredClone;w.scrollTo=()=>{};w.scrollY=0;
 w.HTMLElement.prototype.scrollIntoView=function(){};
 w.matchMedia=query=>({matches:/max-width/.test(query)?width<=Number(query.match(/\d+/)?.[0]||0):/min-width/.test(query)?width>=Number(query.match(/\d+/)?.[0]||0):false,addEventListener(){},removeEventListener(){}});
 w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
 w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
 w.fetch=async input=>{const url=new URL(String(input),w.location.href);assert.equal(url.origin,'https://site.test','No external/provider request');const file=path.resolve(root,'.'+url.pathname);assert.ok(file.startsWith(root+path.sep));reads.push(url.pathname);return new Response(fs.readFileSync(file),{status:200,headers:{'content-type':file.endsWith('.json')?'application/json':'text/plain'}});};
 w.addEventListener('error',event=>errors.push(event.error?.stack||event.message));
 for(const file of ['fixture-data.js','local-db-parser.js','recorded-data.js','search-lifecycle-v1.js','flight-picker-v18.js','preview-lead.js','filter-panel-v1.js','offer-list-v1.js','hotel-details-v1.js','flight-picker-ui-v1.js','app.js'])w.eval(fs.readFileSync(path.join(root,file),'utf8'));
 await pause();await pause();
 const $=selector=>{const node=d.querySelector(selector);assert.ok(node,selector);return node;};
 const click=selector=>$(selector).click();
 const input=(selector,value)=>{const node=$(selector);node.value=value;node.dispatchEvent(new w.Event('input',{bubbles:true}));};
 const action=(name,scope='#modal')=>click(scope+' [data-action="'+name+'"]');
 const close=async()=>{action('close-modal');await pause();};
 const lookup=async()=>{for(let i=0;i<35&&$('#destination-query').getAttribute('aria-busy')==='true';i++)await pause();assert.notEqual($('#destination-query').getAttribute('aria-busy'),'true');};
 const isOpen=()=>$('#modal').hasAttribute('open');
 if(monthRange){
  const [from,to,label]=monthRange;
  assert.equal($('#calendar-month-label').textContent,label);
  assert.equal($('#price-strip button').dataset.date,from);assert.equal($('#price-strip button:last-child').dataset.date,to);
  assert.ok([...d.querySelectorAll('#price-strip button')].every(b=>!b.disabled),'Unknown-price dates remain selectable');
  assert.deepEqual(errors,[]);dom.window.close();return {width,from,to,label,status:'PASS',local_fetches:reads,provider_requests:0};
 }
 assert.match($('#results-summary').textContent,/1047/);
 if(width<=1100){
  action('filters','.results-toolbar');$('#filter-panel').scrollTop=42;click('.filter-operator-group>.filter-section-toggle');
  assert.equal($('.filter-top h3').textContent,'Туроператор');assert.equal($('#filter-panel').classList.contains('filter-detail-open'),true);assert.equal($('#filter-panel').scrollTop,0);assert.equal(d.activeElement,$('#filter-detail-back'));
  click('#filter-detail-back');assert.equal($('.filter-top h3').textContent,'Фильтры');assert.equal($('#filter-panel').classList.contains('filter-detail-open'),false);assert.equal($('#filter-panel').scrollTop,42);assert.equal(d.activeElement,$('.filter-operator-group>.filter-section-toggle'));
  action('close-filters','#filter-panel');await pause();assert.equal($('#filter-panel').classList.contains('open'),false);
 }
 // Approved results header retains the search range while one departure is selected.
 assert.equal($('#compact-search').hidden,width<=760?false:$('#search').getBoundingClientRect().bottom>88);
 assert.equal(d.body.classList.contains('mobile-results'),width<=760);
 assert.equal($('#calendar-month-label').textContent,'Октябрь 2026');
 const headerRange=$('#compact-details').textContent;
 assert.match(headerRange,/Вылет.*1.*7/);
 click('#price-strip [data-date="2026-10-05"]');
 assert.equal($('#compact-details').textContent,headerRange);
 assert.equal($('#price-strip').children.length,7);
 assert.equal($('#calendar-month-label').textContent,'Октябрь 2026','Selected departure keeps the month of the search range');
 assert.equal($('#price-strip [data-date="2026-10-05"] strong').textContent,'78,1');
 assert.match($('#calendar-minimum-legend').textContent.replace(/\s/g,''),/78087₽/);
 assert.match($('#calendar-selected-date').textContent,/5 октября/);
 assert.match($('#results-summary').textContent,/974/);
 click('#clear-date');assert.equal($('#calendar-month-label').textContent,'Октябрь 2026');assert.equal($('#calendar-selected-date').parentElement.hidden,true);
 assert.match($('#results-summary').textContent,/1047/);
 // The price rail is always open; an unknown price never disables its date.
 assert.equal($('#price-calendar').tagName,'SECTION');assert.ok($('#price-strip').children.length>1);
 action('calendar','#price-calendar');await pause();await pause();
 click('[data-action="day-pick"][data-date="2026-10-05"]');
 assert.match($('#date-selection-price').textContent,/Минимум: 5 окт/);
 assert.match($('#date-selection-price').textContent.replace(/\s/g,''),/78087₽/);
 assert.ok($('#date-calendar [data-date="2026-10-05"]').classList.contains('is-cheap'));
 assert.equal($('#date-calendar [data-date="2026-10-05"] small').textContent,'78,1');
 await close();
 // Exact offers are independent, sorted cards, and return retains the same choice.
 click('#cards [data-action="all-offers"][data-id="3678"]');
 const rows=[...d.querySelectorAll('#all-offers-list .grouped-offer')];assert.equal(rows.length,2);
 assert.equal(d.querySelector('#all-offers-list [data-action="offer-group"]'),null);assert.equal(rows[0].querySelector('.primary').textContent.trim(),'Выбрать тур');assert.match($('#modal-body .offer-list-context').textContent,/RIA SUITES HOTEL/);
 for(const row of rows){assert.match(row.textContent,/Завтраки/);assert.match(row.textContent,/Standard/);assert.equal(row.hidden,false);}
 const chosen=rows.find(row=>row.getAttribute('aria-label').includes('FUN&SUN')),key=chosen.dataset.offerKey;
 chosen.querySelector('[data-action="offer"]').click();await pause();
 assert.match($('#modal-body').textContent.replace(/\s/g,''),/99938₽/);
 action('modal-back');await pause();
 const retained=$('#all-offers-list .is-selected');assert.equal(retained.dataset.offerKey,key);assert.match(retained.textContent,/✓ Выбран/);assert.equal(retained.querySelector('.primary').textContent.trim(),'Смотреть тур');
 retained.querySelector('.primary').click();await pause();assert.match($('#modal-body').textContent.replace(/\s/g,''),/99938₽/);action('modal-back');await pause();await close();
 assert.match($('#cards [data-action="all-offers"][data-id="3678"]').closest('.hotel-card').textContent.replace(/\s/g,''),/96953₽/);
 action('edit-search','#applied-search');
 assert.equal($('#search-form').hidden,false);assert.equal(d.querySelector('#quick-stars [data-value="2"]'),null);
 assert.deepEqual([...d.querySelectorAll('#quick-stars button')].map(b=>b.textContent),['Любая','3★','4★','5★']);
 const initial={dates:$('#dates-label').textContent,nights:$('#nights-label').textContent,party:$('#guests-label').textContent};
 // Departure is a draft until the explicit footer action; empty query preserves it.
 action('departure','#search-form');input('#departure-query','Несуществующий');assert.match($('#departure-results').textContent,/Нет совпадений/);assert.match($('#departure-summary').textContent,/Москва/);action('clear-departure-query');await close();assert.equal($('#origin-label').textContent,'Москва');
 // Calendar cancellation, maximum inclusive interval, invalid end, month/year crossing.
 action('dates','#search-form');await pause();assert.equal($('.date-choice-tools').classList.contains('scope-expanded'),false,'Calendar context must stay visible without a removed disclosure');assert.match($('#date-selection-price').textContent,/Минимум: 5 окт/,'Async prices preserve the exact minimum footer');assert.match($('.date-source').textContent,/Снимок 23.09/);click('[data-action="day-pick"][data-date="2026-10-05"]');await close();assert.equal($('#dates-label').textContent,initial.dates);
 action('dates','#search-form');click('[data-action="day-pick"][data-date="2026-10-05"]');click('[data-action="day-pick"][data-date="2026-10-28"]');assert.match($('#date-error').textContent,/22 дат/);assert.equal($('[data-action="day-pick"][data-date="2026-10-05"]').getAttribute('aria-pressed'),'true');
 click('[data-action="day-pick"][data-date="2026-10-12"]');action('apply-dates');await pause();assert.match($('#dates-label').textContent,/5–12 октября/);
 action('dates','#search-form');if(width>760){action('month-next');action('month-next');}click('[data-action="day-pick"][data-date="2026-12-30"]');
 click('[data-action="day-pick"][data-date="2027-01-02"]');assert.match($('#date-selection-label').textContent,/2026.*2027/);await close();
 // Nights are exact or an inclusive range. Invalid end preserves the first choice.
 action('nights','#search-form');click('[data-action="night-pick"][data-value="7"]');click('[data-action="night-pick"][data-value="21"]');assert.match($('#night-error').textContent,/диапазон/);click('[data-action="night-pick"][data-value="10"]');action('apply-nights');await pause();assert.equal($('#nights-label').textContent,'7–10 ночей');
 // Child zero differs from unset. Nested Back preserves the old age.
 action('guests','#search-form');action('children-plus');action('children-plus');assert.equal($('[data-action="apply-guests"]').disabled,true);
 click('[data-action="child-age"][data-index="0"]');assert.equal($('[data-action="apply-age"]').disabled,true);click('[data-action="age-pick"][data-value="0"]');action('apply-age');assert.equal($('#modal-title').textContent,'Туристы');assert.match($('.ages').textContent,/До 1 года/);
 click('[data-action="child-age"][data-index="0"]');click('[data-action="age-pick"][data-value="9"]');action('modal-back');assert.match($('.ages').textContent,/До 1 года/);assert.doesNotMatch($('.ages').textContent,/9 лет/);
 click('[data-action="child-age"][data-index="1"]');click('[data-action="age-pick"][data-value="8"]');action('apply-age');assert.equal($('[data-action="apply-guests"]').disabled,false);await close();assert.equal($('#guests-label').textContent,initial.party);
 action('guests','#search-form');action('children-plus');action('children-plus');
 for(const [index,age] of [[0,0],[1,8]]){click('[data-action="child-age"][data-index="'+index+'"]');click('[data-action="age-pick"][data-value="'+age+'"]');action('apply-age');}
 action('apply-guests');await pause();assert.equal($('#guests-label').textContent,'2 взр. · 2 реб.');assert.match($('#guests-detail').textContent,/До 1 года и 8 лет/);
 action('nights','#search-form');click('[data-action="night-preset"][data-value="7"]');action('apply-nights');await pause();assert.equal($('#form-ages-recheck').hidden,false);assert.match($('#guests-detail').textContent,/До 1 года и 8 лет/);action('guests','#search-form');assert.match($('#guest-age-help').textContent,/на возвращение/);action('apply-guests');await pause();assert.equal($('#form-ages-recheck').hidden,true);
 // Budget validates integers and order, keeps zero, and cancels in isolation.
 action('budget','#search-form');input('#budget-max','150,000');assert.equal($('[data-action="apply-budget"]').disabled,true);input('#budget-max','0');assert.equal($('[data-action="apply-budget"]').disabled,false);assert.equal($('#budget-summary').textContent,'До 0 ₽');input('#budget-min','250 000');input('#budget-max','200 000');assert.equal($('[data-action="apply-budget"]').disabled,true);await close();assert.equal($('#budget-label').textContent,'Без ограничений');
 action('budget','#search-form');input('#budget-max','200 000');action('apply-budget');await pause();assert.match($('#budget-label').textContent,/200/);
 // AI and UAI are independent exact choices, Russian names are primary.
 action('meals','#search-form');for(const meal of ['Всё включено','Ультра всё включено']){const box=$('[data-meal-choice][value="'+meal+'"]');box.checked=true;box.dispatchEvent(new w.Event('change',{bubbles:true}));}
 action('apply-meals');await pause();assert.equal($('#meal-label').textContent,'Всё включено · Ультра всё включено');
 // Filter summary and main form share one committed value; nested cancel/reset is local.
 action('form-filters','#search-form');action('stars');click('[data-action="picker-star"][data-value="4"]');click('[data-action="picker-star"][data-value="5"]');action('apply-stars');assert.equal($('#modal-title').textContent,'Все фильтры');assert.match($('#modal-body').textContent,/4★ и 5★/);assert.equal($('#quick-stars [data-action="any-stars"]').getAttribute('aria-pressed'),'true');await close();assert.equal($('#quick-stars [data-action="any-stars"]').getAttribute('aria-pressed'),'true');
 action('form-filters','#search-form');action('stars');click('[data-action="picker-star"][data-value="4"]');click('[data-action="picker-star"][data-value="5"]');action('apply-stars');action('apply-form-filters');await pause();assert.equal($('#quick-stars [data-value="4"]').getAttribute('aria-pressed'),'true');assert.equal($('#quick-stars [data-value="5"]').getAttribute('aria-pressed'),'true');
 // Resorts OR; selecting an exact hotel visibly replaces resorts only after confirmation.
 action('destination','#search-form');input('#destination-query','Стамбул');await lookup();const resort=d.querySelector('[data-action="destination-resort"][data-value="Стамбул"]');assert.ok(resort);resort.click();action('apply-destination');await pause();assert.match($('#destination-detail').textContent,/Стамбул/);
 action('destination','#search-form');input('#destination-query','RIA');await lookup();click('[data-action="destination-hotel"][data-id="3678"]');assert.equal($('#modal-title').textContent,'Изменить направление?');assert.match($('#modal-body').textContent,/Стамбул/);action('keep-destination');assert.equal($('#modal-title').textContent,'Куда поедем?');assert.match($('#destination-summary').textContent,/Стамбул/);click('[data-action="destination-hotel"][data-id="3678"]');action('confirm-destination');assert.match($('#destination-summary').textContent,/RIA SUITES/);action('apply-destination');await pause();assert.equal($('#destination-detail').textContent,'RIA SUITES HOTEL');
 // A search/empty/clear never silently removes the chosen hotel.
 action('destination','#search-form');input('#destination-query','Несуществующий');await lookup();assert.match($('#destination-results').textContent,/Совпадений нет/);assert.match($('#destination-summary').textContent,/RIA SUITES/);action('clear-destination-query');assert.match($('#destination-summary').textContent,/RIA SUITES/);
 input('#destination-query','FOUR-G');await lookup();click('[data-action="destination-hotel"][data-id="5227"]');assert.equal($('#modal-title').textContent,'Куда поедем?');assert.match($('#destination-summary').textContent,/RIA SUITES.*FOUR-G/);action('apply-destination');await pause();assert.match($('#destination-detail').textContent,/RIA SUITES HOTEL · FOUR-G HOTEL/);
 action('destination','#search-form');click('[data-action="destination-remove"][data-id="3678"]');assert.doesNotMatch($('#destination-summary').textContent,/RIA SUITES/);click('[data-action="destination-remove"][data-id="5227"]');assert.equal($('#destination-summary').textContent,'Турция');await close();assert.match($('#destination-detail').textContent,/RIA SUITES HOTEL · FOUR-G HOTEL/);
 // Reset only filters; direction, dates, nights and family survive.
 const kept={direction:$('#destination-detail').textContent,dates:$('#dates-label').textContent,nights:$('#nights-label').textContent,ages:$('#guests-detail').textContent};action('form-filters','#search-form');action('reset-form-filters');action('apply-form-filters');await pause();assert.equal($('#meal-label').textContent,'Любое');assert.equal($('#budget-label').textContent,'Без ограничений');assert.equal($('#quick-stars [data-action="any-stars"]').getAttribute('aria-pressed'),'true');for(const [id,value] of [['destination-detail',kept.direction],['dates-label',kept.dates],['nights-label',kept.nights],['guests-detail',kept.ages]])assert.equal($('#'+id).textContent,value);
 // Return to snapshot matching party/dates, submit both exact hotels, verify URL/result state.
 action('guests','#search-form');action('children-minus');action('children-minus');action('apply-guests');await pause();action('dates','#search-form');click('[data-action="day-pick"][data-date="2026-10-01"]');click('[data-action="day-pick"][data-date="2026-10-07"]');action('apply-dates');await pause();$('.search-submit').click();await pause();await pause();assert.equal($('#search-form').hidden,true);assert.match(w.location.search,/hotels=3678%7C5227/);assert.match($('#results-summary').textContent,/2 отеля/);assert.match($('#cards').textContent,/RIA SUITES HOTEL/);assert.match($('#cards').textContent,/FOUR-G HOTEL/);
 const committed=w.location.search;action('edit-search','#applied-search');click('#quick-stars [data-value="5"]');action('cancel-search-edit','#search-form');await pause();assert.equal(w.location.search,committed);assert.match($('#results-summary').textContent,/2 отеля/);
 // A pending change from an exact hotel set to the country cannot reuse old counts.
 action('edit-search','#applied-search');action('destination','#search-form');action('destination-all');action('confirm-destination');action('apply-destination');await pause();action('meals','#search-form');assert.equal($('#meal-result-preview').textContent,'Любой выбранный вариант');await close();action('budget','#search-form');assert.match($('#budget-preview').textContent,/после поиска с новыми условиями/);await close();action('cancel-search-edit','#search-form');await pause();assert.equal(w.location.search,committed);assert.match($('#results-summary').textContent,/2 отеля/);
 assert.deepEqual(errors,[],'No errors from actual Site scripts');
 const result={width,checks:'PASS: open price rail, exact saved calendar minimum, independent offers and selected-tour return, drafts/cancellation, 22-date bound, cross-year label, nights, child zero/unset/nested Back, return-age recheck, meals, integer/zero/order budgets, canonical filters, direction replacement, multi-hotel OR, submit and return',local_fetches:reads,provider_requests:0,real_leads:0,browser_layout:false};dom.window.close();return result;
}

async function verify(){
 // No fake catalogue enrichment: this snapshot contains one departure/country.
 // Full journeys use the existing saved rows and explicitly labelled fixtures.
 const monthRanges=[];for(const range of [['2026-10-05','2026-10-05','Октябрь 2026'],['2026-09-28','2026-10-04','Сентябрь — октябрь 2026'],['2026-12-30','2027-01-02','Декабрь 2026 — январь 2027']])monthRanges.push(await run(360,range));
 const widths=(process.env.ANYTOUR_QA_WIDTHS||'360,390,430,768,1280').split(',').map(Number),matrix=[];for(const width of widths)matrix.push(await run(width));fs.mkdirSync(evidence,{recursive:true});fs.writeFileSync(path.join(evidence,'behavior.json'),JSON.stringify({kind:'actual NEXT scripts in jsdom; no layout engine',assetRoot:root,matrix,monthRanges},null,2));console.log('PASS: complete form interactions at '+widths.join('/')+'; saved data only; no provider requests or real leads.');
}
verify().catch(error=>{console.error(error.stack);process.exit(1);});
