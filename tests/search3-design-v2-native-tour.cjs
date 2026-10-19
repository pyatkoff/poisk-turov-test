'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM}=require('jsdom');
const builder=require('../scripts/build/search3_design_v2_native.cjs');
const dom=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
const w=dom.window,root=w.document.querySelector('[data-product]');
w.eval(fs.readFileSync(path.resolve(__dirname,'../v2/lead-form-guard-v1.js'),'utf8'));
w.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
const trip={origin:'Москва',country:'4',from:'2026-10-25',to:'2026-10-31',minNights:7,maxNights:7,adults:2,ages:[0,17]};
const offer={key:'canonical:one',hotelId:7,provider:'tourvisor',raw:{id:'offer-one'},cached:false,total:100000,room:'Standard',meal:'Завтраки',operator:'Supplier',day:trip.from,returnDay:'2026-11-01',nights:7,adults:2,ages:trip.ages,search:trip};
const hotel={id:7,name:'Real fixture hotel',location:'Турция · Кемер',photos:[]};
const leg=(number,time)=>({number,company:{name:'Fixture airline'},departure:{date:trip.from,time,port:{name:'SVO'}},arrival:{date:trip.from,time:'15:25',port:{name:'AYT'}},baggage:'20',carryOn:'5 кг'});
const tour={id:'offer-one',price:105000,roomType:'Verified Standard',meal:{name:'BB'}};
const placeholder=leg('FV 000','00:00');placeholder.arrival.time='00:00';
const variants=[{price:103000,forward:[placeholder],backward:[leg('FV 001','16:30')]},{price:112000,forward:[leg('FV 101','10:50')],backward:[leg('FV 102','18:00')]}];
let quoted=0,current=null,payloadCalls=0,selectedIndices=[],accepted=0,leadSent=0,resolveFirst=null;
const bridge={
  getSelection:()=>current,
  quoteOffer(key){quoted++;assert.equal(key,offer.key);return new Promise(resolve=>resolveFirst=()=>{current={...offer,offer,tour,variants,flightChoiceId:null,total:105000,pricePending:true,priceAccepted:false,flightsError:'',flightsLoading:false};resolve(current);});},
  selectFlight(index){selectedIndices.push(index);assert.strictEqual(current.offer,offer);assert.strictEqual(current.variants[index],variants[index]);current={...current,flightChoiceId:String(index),total:variants[index].price,pricePending:false,priceAccepted:false};return current;},
  acceptPrice(){accepted++;current={...current,priceAccepted:true};return current;},
  deferFlight(){current={...current,flightChoiceId:null,total:tour.price,pricePending:false,priceAccepted:false};return current;},
  previewContact(fd){assert.strictEqual(current.tour,tour);assert.strictEqual(current.variants[Number(current.flightChoiceId)],variants[1]);assert(current.priceAccepted);assert.equal(fd.get('phone'),'+7 999 123-45-67');assert.equal(fd.get('consent'),'1');payloadCalls++;return{tourId:tour.id,price:variants[1].price,childAges:trip.ages,delivery:'preview-disabled'};},
  submit(){leadSent++;}
};
const changes=[],owner=w.AnyTourDesignV2Tour.mount({root,bridge,onChanged:event=>changes.push(event)});
const click=selector=>{const node=root.querySelector(selector);assert(node,selector);assert.equal(node.disabled,false,selector+' must be available');node.click();};
const form=root.querySelector('[data-lead-form]'),submit=()=>form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
const tick=()=>new Promise(resolve=>w.setTimeout(resolve,0));
(async()=>{
  owner.render({offer,hotel,screen:'tour'});
  assert.equal(quoted,1);assert(owner.getState().pending);
  assert.equal(root.querySelector('[data-native-flight-status]').textContent,'Проверяем');
  assert([...root.querySelectorAll('[data-native-price-caption]')].every(node=>node.textContent==='Цена из поиска за всех'),'Pending quote keeps the search-price source clear in tour and contact');
  assert(!root.querySelector('[data-screen="tour"]').textContent.includes('08:10'),'donor mock flight removed while pending');
  assert([...root.querySelectorAll('[data-action="lead"]')].every(button=>button.disabled),'no contact before canonical quote');
  resolveFirst();await owner.ready();
  assert.equal(root.querySelector('[data-tour-meal]').textContent,'Завтраки','A checked supplier meal code keeps the readable meal on the tour');
  assert.match(root.querySelector('[data-lead-choice]').textContent,/Завтраки/,'Contact uses the same checked meal label');
  assert.equal(tour.meal.name,'BB','Display normalization never mutates the supplier fact');
  assert.equal(root.querySelectorAll('[data-native-tour-flight]').length,2);
  const firstOption=root.querySelector('[data-native-tour-flight="0"]').closest('.st-native-flight-option');
  assert.match(firstOption.textContent,/Расписание уточняется/);
  assert.match(firstOption.textContent,/Багаж: 20 кг/);
  assert.match(firstOption.textContent,/ручная кладь: 5 кг/);
  const actualOption=root.querySelector('[data-native-tour-flight="1"]').closest('.st-native-flight-option');
  for(const fact of ['Туда','Обратно','10:50','18:00','SVO','AYT','FV 101','FV 102'])assert(actualOption.textContent.includes(fact),'Alternative pair retains '+fact);
  assert(!root.querySelector('[data-native-flight-content]').textContent.includes('00:00'),'provider placeholder is not a real departure time');
  click('[data-native-tour-flight="1"]');await owner.ready();
  assert.deepEqual(selectedIndices,[1],'original supplier index retained');
  assert.equal(root.querySelector('[data-native-tour-flight="1"]'),null,'Selected pair is not repeated as an alternative');
  const alternatives=root.querySelector('[data-native-flight-alternatives]');
  assert(alternatives && !alternatives.open,'After selection alternatives are collapsed');
  assert.match(alternatives.querySelector('summary').textContent,/Другие перелёты · 1/);
  assert.strictEqual(w.document.activeElement,alternatives.querySelector('summary'),'Focus returns to accessible alternatives disclosure');
  alternatives.open=true;alternatives.dispatchEvent(new w.Event('toggle'));
  assert.equal(owner.getState().flightAlternativesOpen,true);assert.equal(quoted,1);assert.deepEqual(selectedIndices,[1],'Disclosure starts no quote or flight selection');
  assert.match(root.querySelector('[data-native-flight-content]').textContent,/SVO/,'Selected full schedule remains visible');
  assert(root.querySelector('[data-screen="tour"]').textContent.replace(/\s+/g,' ').includes('112 000 ₽'),'full selected price shown without added delta/fuel');
  assert.equal(root.querySelector('[data-native-flight-status]').textContent,'Выбранный перелёт');
  assert([...root.querySelectorAll('[data-native-price-caption]')].every(node=>node.textContent==='Проверенная цена за всех'),'Same checked whole-party caption in mobile, desktop and contact');
  assert.match(root.querySelector('[data-native-package-note]').textContent,/Выбранный перелёт учтён в проверенной цене/);
  assert.match(root.querySelector('[data-native-package-note]').textContent,/Трансфер, страховка и обязательные доплаты требуют уточнения/,'Unprovided package facts remain unknown');
  assert.match(root.querySelector('[data-native-cost-note]').textContent,/Цена проверена с выбранным перелётом/,'Desktop price area carries the same checked-price context as mobile');
  assert.equal(owner.getState().ready,false,'changed quoted price needs explicit acceptance');
  assert.match(root.querySelector('[data-native-price-origin]').textContent,/Выбранный номер в поиске: 100 000 ₽/,'The changed full total retains the search baseline without replacing checked authority');
  assert([...root.querySelectorAll('[data-price-change]')].every(node=>!node.hidden));
  click('[data-action="accept-price"]');
  assert.equal(accepted,1);assert.equal(owner.getState().ready,true);
  assert([...root.querySelectorAll('[data-action="lead"]')].every(button=>!button.disabled));
  owner.render({offer,hotel,screen:'lead'});assert.equal(quoted,1,'Back/contact navigation reuses exact quote receipt');
  const contactFacts=root.querySelector('[data-native-contact-facts]');assert(contactFacts,'Contact reviews the complete selected tour before entering details');
  for(const fact of ['10:50','18:00','SVO','AYT','FV 101','FV 102','Supplier','Москва','0 лет','17 лет'])assert(contactFacts.textContent.includes(fact),'Contact retains exact selected fact: '+fact);
  assert.match(root.querySelector('[data-lead-context]').textContent,/25 окт — 1 ноя/,'Contact retains both stay dates');
  assert.match(root.querySelector('[data-native-contact-note]').textContent,/Цена проверена с выбранным перелётом/);
  assert(root.querySelector('[data-native-flight-alternatives]').open,'Return/render retains disclosure');
  assert(!form.elements.phone.disabled && !form.elements.consent.disabled);
  form.elements.phone.value='123';form.elements.consent.checked=true;submit();assert.equal(payloadCalls,0,'invalid contact does not construct payload');
  form.elements.phone.value='+7 999 123-45-67';form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));form.elements.consent.checked=false;submit();assert.equal(payloadCalls,0,'consent required for review');
  form.elements.consent.checked=true;submit();assert.equal(payloadCalls,1);assert.equal(leadSent,0);
  assert.equal(form.dataset.checked,'1');assert.match(form.textContent,/Заявка не отправлена/);assert.equal(root.querySelector('[data-screen="success"]').hidden,true,'never show donor success');
  owner.render({offer,hotel,screen:'tour'});assert.equal(quoted,1);assert.equal(form.elements.phone.value,'+7 999 123-45-67','contact draft preserved on return');
  current={...current,flightsError:'FICTIONAL controlled flight failure'};owner.render({offer,hotel,screen:'tour'});
  assert.equal(owner.getState().ready,false);
  assert.equal(root.querySelector('[data-native-flight-status]').textContent,'Требует проверки');
  assert.match(root.querySelector('[data-native-cost-note]').textContent,/Проверка не завершена/);
  assert.match(root.querySelector('[data-native-contact-note]').textContent,/Проверка не завершена/,'A stale contact screen cannot retain a checked-price claim after failure');
  assert(!root.querySelector('[data-native-package-note]').textContent.includes('учтён в проверенной цене'),'Failed context cannot keep a confirmed-flight claim');
  current={...current,flightsError:''};owner.render({offer,hotel,screen:'tour'});assert.equal(quoted,1,'State copy never requotes the current offer');
  click('[data-native-tour-flight="0"]');await owner.ready();assert.equal(owner.getState().ready,false,'another price revokes acceptance');
  assert.equal(form.dataset.checked,undefined,'review invalidated by flight selection');submit();assert.equal(payloadCalls,1);assert.equal(leadSent,0);
  click('[data-native-defer-flight]');await owner.ready();
  assert.equal(current.flightChoiceId,null);assert.equal(current.total,tour.price);
  assert.equal(root.querySelector('[data-native-flight-status]').textContent,'Уточнит менеджер');
  assert.match(root.querySelector('[data-native-package-note]').textContent,/Рейс уточнит менеджер/);
  assert.match(root.querySelector('[data-native-contact-flight]').textContent,/Рейс уточнит менеджер/);assert(!root.querySelector('[data-native-contact-flight]').textContent.includes('FV 101'),'The manager branch cannot retain the previous selected pair');
  assert(!root.querySelector('[data-native-package-note]').textContent.includes('Выбранный перелёт учтён'),'Manager flight path does not claim a selected pair');
  assert.equal(quoted,1,'Manager flight copy uses the current exact tour');
  owner.destroy();
  const remounted=w.AnyTourDesignV2Tour.mount({root,bridge});remounted.render({offer,hotel,screen:'tour'});await remounted.ready();
  assert.equal(root.querySelectorAll('.st-native-quick-party').length,1,'Reusing the mobile template keeps a single tourist row');
  assert.equal(root.querySelectorAll('.st-quick-price [data-native-price-caption]').length,1,'Responsive return keeps a single mobile price caption');
  assert.equal(root.querySelectorAll('[data-screen="lead"] [data-native-price-caption]').length,1,'Responsive return keeps a single contact price caption');
  assert(root.querySelector('[data-native-cost-details] [data-tour-operator]'),'Preparing the tourist row again cannot remove the operator row');
  assert.equal(quoted,1,'Preparing the retained template never verifies the offer again');
  remounted.destroy();w.close();
  await staleAndExpiry();
  await anexPackageAndGroup();
  await viewportRemount();
  await manyFlightChoices();
  await familyCopyChecks();
  await checkedMealPresentationChecks();
  console.log('PASS native tour: quote/flight indices, full total, exact-selection acceptance, placeholder/missing facts, verified contact review without delivery, return draft, stale response and expiry; 24 family compositions use correct summary/price/contact cases');
})().catch(error=>{console.error(error);owner.destroy();w.close();process.exitCode=1;});

async function checkedMealPresentationChecks(){
  for(const [raw,label] of [['AI','Всё включено'],[{name:'BB'},'Завтраки'],[{fullName:'Питание от поставщика',name:'AI'},'Питание от поставщика'],['AI+','AI+']]){
    const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{runScripts:'outside-only'}),v=doc.window,r=v.document.querySelector('[data-product]');
    v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
    const checkedTour={...tour,meal:raw},receipt={offer,tour:checkedTour,total:112000,variants,flightChoiceId:1,pricePending:false,priceAccepted:true};
    const before=JSON.stringify(checkedTour),view=v.AnyTourDesignV2Tour.mount({root:r,bridge:{getSelection:()=>receipt,quoteOffer(){throw new Error('Retained checked fact cannot requote');}}});
    view.render({offer,hotel,screen:'lead'});await view.ready();
    assert.equal(r.querySelector('[data-tour-meal]').textContent,label);assert(r.querySelector('[data-lead-choice]').textContent.endsWith(label));
    assert.equal(JSON.stringify(checkedTour),before);assert.equal(receipt.total,112000);assert.equal(view.getState().ready,true);
    assert.equal(r.querySelectorAll('[data-native-contact-summary]').length,1,'One complete summary owns identity, price and itinerary');
    assert(r.querySelector('[data-native-contact-summary] [data-tour-price]'));assert(r.querySelector('[data-native-contact-summary] [data-native-contact-flight]'));
    view.destroy();v.close();
  }
}

async function manyFlightChoices(){
  for(const provider of ['tourvisor','anex']){
    const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
    const v=other.window,r=v.document.querySelector('[data-product]');
    v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
    const exact={...offer,provider,raw:{...offer.raw,anexKind:'concrete'}};
    const pairs=Array.from({length:40},(_,index)=>provider==='anex'
      ? {choiceRef:'canonical-many-'+index,legs:[{label:'Туда: рейс '+index},{label:'Обратно: рейс '+index}]}
      : {price:100000+index*100,forward:[leg('FV '+index,'10:50')],backward:[leg('FB '+index,'18:00')]});
    let present={...exact,offer:exact,tour:provider==='tourvisor'?{...tour,price:100000}:null,variants:pairs,flightChoiceId:null,total:100000,pricePending:true,priceAccepted:false};
    let quotes=0;const selected=[];
    const adapter={getSelection:()=>present,
      quoteOffer(){quotes++;throw new Error('No new quote while browsing retained choices');},
      selectFlight(index){assert.strictEqual(present.variants[index],pairs[index]);selected.push(index);present={...present,flightChoiceId:index,total:pairs[index].price,pricePending:false,priceAccepted:false};return present;},
      selectProviderFlight(ref){const choice=pairs.find(pair=>pair.choiceRef===ref);assert.strictEqual(choice,pairs[39]);selected.push(ref);present={...present,total:103900,pricePending:false,priceAccepted:false,quoteResult:{state:'quote_verified',choice}};return present;},
      acceptPrice(){present={...present,priceAccepted:true};return present;},
      deferFlight(){present={...present,flightChoiceId:null,total:100000,pricePending:false,priceAccepted:false};return present;}};
    let view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter});view.render({offer:exact,hotel,screen:'tour'});await view.ready();
    const selector=provider==='anex'?'[data-native-provider-flight]':'[data-native-tour-flight]';
    const visible=()=>[...r.querySelectorAll(selector)].filter(node=>!node.closest('details:not([open])'));
    assert.equal(r.querySelectorAll(selector).length,40,'All exact supplier choices are retained');
    assert.equal(visible().length,3,'Only three unselected pairs are initially expanded');
    const more=r.querySelector('[data-native-flight-alternatives]');
    assert.equal(more.open,false);assert.equal(more.querySelector('summary').textContent,'Ещё перелёты · 37');
    if(provider==='tourvisor')assert(r.querySelector('[data-native-defer-flight]').compareDocumentPosition(visible()[0])&v.Node.DOCUMENT_POSITION_FOLLOWING,'Manager action precedes all40 options');
    more.open=true;more.dispatchEvent(new v.Event('toggle'));assert.equal(visible().length,40);
    const retained=view.getState();view.destroy();
    view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter,initialState:retained});view.render({offer:exact,hotel,screen:'tour'});await view.ready();
    assert.equal(r.querySelector('[data-native-flight-alternatives]').open,true,'Responsive remount retains the expanded remainder');assert.equal(visible().length,40);assert.equal(quotes,0);assert.deepEqual(selected,[]);
    r.querySelector(provider==='anex'?'[data-native-provider-flight="canonical-many-39"]':'[data-native-tour-flight="39"]').click();await view.ready();
    assert.deepEqual(selected,[provider==='anex'?'canonical-many-39':39],'Last initially hidden pair retains its exact canonical reference');
    assert.equal(present.total,103900);assert.equal(view.getState().ready,false,'New total still needs explicit acceptance');
    assert.equal(r.querySelector('[data-native-flight-alternatives]').open,false);assert.equal(r.querySelector('[data-native-flight-alternatives] summary').textContent,'Другие перелёты · 39');
    assert.equal(visible().length,0,'Selected pair is shown separately with other39 collapsed');
    r.querySelector('[data-action="accept-price"]').click();assert.equal(view.getState().ready,true);
    view.render({offer:exact,hotel,screen:'lead'});view.render({offer:exact,hotel,screen:'tour'});
    assert.equal(present.total,103900);assert.equal(quotes,0);assert.equal(selected.length,1,'Return never repeats selection or quote');
    view.destroy();other.window.close();
  }
}

async function staleAndExpiry(){
  const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
  const v=other.window,r=v.document.querySelector('[data-product]');v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
  // Advance the fixture clock and the product's armed expiry callback together;
  // wall-clock second boundaries must not expire the receipt before ready.
  let now=Date.UTC(2026,9,19,12),expiryTimer=null;
  const nextTurn=v.setTimeout.bind(v);v.Date.now=()=>now;
  v.setTimeout=(callback,delay)=>{expiryTimer={callback,delay};return expiryTimer;};
  v.clearTimeout=timer=>{if(timer===expiryTimer)expiryTimer=null;};
  let present=null,resolvers=[];
  const second={...offer,key:'canonical:two',raw:{id:'offer-two'},total:90000};
  const adapter={getSelection:()=>present,quoteOffer(key){return new Promise(resolve=>resolvers.push({key,resolve}));},acceptPrice(){present={...present,priceAccepted:true};return present;},previewContact(){throw new Error('Expired canonical receipt');}};
  const view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter});
  view.render({offer,hotel,screen:'tour'});view.render({offer:second,hotel:{...hotel,name:'Second hotel'},screen:'tour'});
  present={...second,offer:second,tour:{id:'offer-two',price:90000},variants:[],total:90000,flightChoiceId:null,pricePending:false,priceAccepted:true,expiresAt:now/1000+60};
  resolvers[1].resolve(present);await view.ready();assert.equal(view.getState().ready,true);
  resolvers[0].resolve({...offer,offer,tour,variants,pricePending:false,total:999999});await new Promise(resolve=>nextTurn(resolve,0));
  assert.equal(r.querySelector('[data-tour-hotel]').textContent,'Second hotel','stale quote cannot repaint current offer');
  assert.equal(r.querySelector('[data-tour-price]').textContent,'90 000 ₽');
  assert(expiryTimer&&expiryTimer.delay===60001,'The current receipt arms its own expiry timer');
  now=present.expiresAt*1000+1;expiryTimer.callback();
  assert.equal(view.getState().ready,false,'expiry blocks contact even after acceptance');
  assert.match(r.querySelector('[data-native-tour-status]').textContent,/истёк/);assert(r.querySelector('[data-lead-form]').elements.phone.disabled);
  assert.equal(r.querySelector('[data-native-flight-status]').textContent,'Нужна новая проверка');
  assert([...r.querySelectorAll('[data-native-price-caption]')].every(node=>node.textContent==='Последняя цена за всех · нужна проверка'));
  assert.match(r.querySelector('[data-native-cost-note]').textContent,/Срок цены истёк/);
  view.destroy();other.window.close();
}

async function anexPackageAndGroup(){
  const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
  const v=other.window,r=v.document.querySelector('[data-product]');v.eval(fs.readFileSync(path.resolve(__dirname,'../v2/lead-form-guard-v1.js'),'utf8'));v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
  const concrete={...offer,key:'anex:concrete',provider:'anex',raw:{anexKind:'concrete'},total:100000};
  const choices=[{choiceRef:'canonical-choice-a',legs:[{label:'Туда: рейс 201'}, {label:'Обратно: рейс 202'}]},{choiceRef:'canonical-choice-b',legs:[{label:'Туда: рейс 301'}, {label:'Обратно: рейс 302'}]}];
  let present=null,quoteCalls=0,choiceCalls=[],payloadCalls=0;const notices=[];
  const adapter={getSelection:()=>present,
    async quoteOffer(key,options){quoteCalls++;assert.equal(key,concrete.key);assert.strictEqual(options.expectedOffer,concrete);present={...concrete,offer:concrete,variants:choices,quoteResult:{state:'quote_choices',choices},pricePending:true,priceAccepted:false};return present;},
    async selectProviderFlight(choiceRef){choiceCalls.push(choiceRef);assert.equal(choiceRef,choices[1].choiceRef);present={...present,total:109900,pricePending:false,priceAccepted:false,quoteResult:{state:'quote_verified',choices,choice:choices[1],expiresAt:Math.floor(Date.now()/1000)+60,finalPrice:{amount:109900,currency:'RUB'}}};return present;},
    acceptPrice(){present={...present,priceAccepted:true};return present;},
    previewContact(fd){payloadCalls++;assert.equal(fd.get('consent'),'1');assert.strictEqual(present.quoteResult.choice,choices[1]);return{price:109900,delivery:'preview-disabled'};},
    async expandOfferGroup(key){assert.equal(key,'anex:group');return[concrete];}}
  const view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter,onChanged:event=>notices.push(event)});
  view.render({offer:concrete,hotel,screen:'tour'});await view.ready();
  assert.equal(quoteCalls,1);assert.equal(r.querySelectorAll('[data-native-provider-flight]').length,2);assert.equal(view.getState().ready,false);
  r.querySelector('[data-native-provider-flight="canonical-choice-b"]').click();await view.ready();
  assert.deepEqual(choiceCalls,[choices[1].choiceRef]);assert.match(r.querySelector('[data-native-flight-content]').textContent,/Туда: рейс 301/);assert.equal(r.querySelector('[data-tour-price]').textContent,'109 900 ₽');assert.equal(view.getState().ready,false);
  assert.equal(r.querySelector('[data-native-flight-status]').textContent,'Выбранный перелёт');
  assert.match(r.querySelector('[data-native-package-note]').textContent,/Выбранный перелёт учтён/,'ANEX selected package and displayed inclusion copy agree');
  assert.match(r.querySelector('[data-native-contact-flight]').textContent,/Туда: рейс 301/);assert.match(r.querySelector('[data-native-contact-flight]').textContent,/Обратно: рейс 302/,'ANEX contact uses the exact chosen supplier labels');
  assert.equal(r.querySelector('[data-native-provider-flight="canonical-choice-b"]'),null);assert.equal(r.querySelector('[data-native-flight-alternatives]').open,false,'ANEX alternatives also collapse without changing exact choiceRef');
  r.querySelector('[data-action="accept-price"]').click();assert.equal(view.getState().ready,true);
  const f=r.querySelector('[data-lead-form]');f.elements.phone.value='+7 999 123-45-67';f.elements.consent.checked=true;f.dispatchEvent(new v.Event('submit',{bubbles:true,cancelable:true}));assert.equal(payloadCalls,1);
  const group={...concrete,key:'anex:group',raw:{anexKind:'group_minimum'}};
  present=null;view.render({offer:group,hotel,screen:'tour'});assert.equal(quoteCalls,1,'group minimum is not a concrete quote');r.querySelector('[data-native-expand-offer]').click();await view.ready();
  assert.strictEqual(notices.find(event=>event.navigate==='hotel').expandedOffers[0],concrete,'expanded exact supplier object handed back to hotel owner');
  view.destroy();other.window.close();
}

async function viewportRemount(){
  const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
  const v=other.window;v.eval(fs.readFileSync(path.resolve(__dirname,'../v2/lead-form-guard-v1.js'),'utf8'));v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
  const freshRoot=()=>{const holder=v.document.createElement('div');holder.innerHTML=builder.extract().markup;const product=holder.querySelector('[data-product]');v.document.body.appendChild(product);return product;};
  let present=null,resolveQuote=null,calls=0;
  const adapter={getSelection:()=>present,
    quoteOffer(){calls++;present={...offer,offer,loading:true,pricePending:true,priceAccepted:false,variants:[]};return new Promise(resolve=>resolveQuote=()=>{present={...present,tour,loading:false,pricePending:false,total:offer.total,priceAccepted:true,flightChoiceId:'1',variants};resolve(present);});},
    acceptPrice(){present={...present,priceAccepted:true};return present;},
    selectFlight(index){present={...present,flightChoiceId:String(index),total:variants[index].price,priceAccepted:false,pricePending:false};return present;}}
  let view=v.AnyTourDesignV2Tour.mount({root:freshRoot(),bridge:adapter});view.render({offer,hotel,screen:'tour'});
  const pendingState=view.getState();assert(pendingState.pendingPromise);view.destroy();
  let product=freshRoot();view=v.AnyTourDesignV2Tour.mount({root:product,bridge:adapter,initialState:pendingState});view.render({offer,hotel,screen:'tour'});
  assert.equal(calls,1,'viewport remount must join pending quote without duplicate supplier start');resolveQuote();await view.ready();assert.equal(view.getState().ready,true);
  const f=product.querySelector('[data-lead-form]');f.elements.name.value='Name';f.elements.phone.value='+7 999 123-45-67';f.elements.consent.checked=true;
  const disclosure=product.querySelector('[data-native-flight-alternatives]');disclosure.open=true;disclosure.dispatchEvent(new v.Event('toggle'));
  const draft=view.getState();view.destroy();product=freshRoot();view=v.AnyTourDesignV2Tour.mount({root:product,bridge:adapter,initialState:draft});view.render({offer,hotel,screen:'lead'});
  const restored=product.querySelector('[data-lead-form]');assert.equal(restored.elements.name.value,'Name');assert.equal(restored.elements.phone.value,'+7 999 123-45-67');assert(restored.elements.consent.checked,'same exact quote/flight/total may carry consent during viewport remount');
  assert(product.querySelector('[data-native-flight-alternatives]').open,'Responsive remount retains opened alternatives for the same exact offer');
  product.querySelector('[data-native-tour-flight="0"]').click();await view.ready();assert.equal(restored.elements.consent.checked,false,'changed flight revokes prior consent');assert.equal(calls,1);
  present={...present,variants:[variants[0]],flightChoiceId:null,pricePending:true,priceAccepted:false};view.render({offer,hotel,screen:'tour'});
  product.querySelector('[data-native-tour-flight="0"]').click();await view.ready();
  assert.equal(product.querySelector('[data-native-flight-alternatives]'),null,'Single selected variant adds no empty disclosure');
  assert.strictEqual(v.document.activeElement,product.querySelector('[data-native-flight-status]'),'Single variant restores focus to selected flight status');
  view.destroy();other.window.close();
}

async function familyCopyChecks(){
  const adultSummary=['','1 взрослый','2 взрослых','3 взрослых','4 взрослых','5 взрослых','6 взрослых'];
  const adultPrice=['','1 взрослого','2 взрослых','3 взрослых','4 взрослых','5 взрослых','6 взрослых'];
  const childSummary=['',' + 1 ребёнок',' + 2 ребёнка',' + 3 ребёнка'];
  const childPrice=['',' + 1 ребёнка',' + 2 детей',' + 3 детей'];
  const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
  const v=other.window,r=v.document.querySelector('[data-product]');v.eval(fs.readFileSync(path.resolve(__dirname,'../v2/lead-form-guard-v1.js'),'utf8'));v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
  let present=null,quoteCalls=0;const adapter={getSelection:()=>present,quoteOffer(){quoteCalls++;throw new Error('Existing exact selection must not be requoted for copy');}};
  const view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter});const el=s=>{const n=r.querySelector(s);assert(n,s);return n;};
  try{
    let checked=0;
    for(let adults=1;adults<=6;adults++)for(let children=0;children<=3;children++){
      const summary=adultSummary[adults]+childSummary[children],price=adultPrice[adults]+childPrice[children],ages=[0,7,17].slice(0,children);
      const familyOffer={...offer,adults,ages,search:{...trip,adults,ages}};
      present={...familyOffer,offer:familyOffer,tour,variants,flightChoiceId:1,total:112000,pricePending:false,priceAccepted:true};
      view.render({offer:familyOffer,hotel,screen:'tour'});
      assert.equal(el('[data-tour-party]').textContent,summary,'tour summary: '+summary);
      assert.equal(el('[data-cost-party]').textContent,summary,'tour cost table uses a free summary');
      assert.equal(el('[data-quick-party]').textContent,'За '+price,'mobile tour price uses accusative');
      assert.equal(el('[data-tour-price]').textContent,'112 000 ₽','copy preserves the canonical full total');
      view.render({offer:familyOffer,hotel,screen:'lead'});
      assert(el('[data-lead-context]').textContent.endsWith(' · '+summary),'contact context uses a free summary');
      assert.strictEqual(view.getState().selection,present);assert.strictEqual(present.offer,familyOffer);assert.strictEqual(present.tour,tour);assert.strictEqual(present.variants[1],variants[1]);
      assert.equal(present.adults,adults);assert.deepEqual(present.ages,ages);assert.equal(present.flightChoiceId,1);assert.equal(view.getState().ready,true);
      checked++;
    }
    assert.equal(checked,24,'all adult 1–6 × child 0–3 compositions reach tour/contact owners');
    assert.equal(quoteCalls,0,'rendering family copy reuses exact quote authority');
  }finally{view.destroy();other.window.close();}
}
