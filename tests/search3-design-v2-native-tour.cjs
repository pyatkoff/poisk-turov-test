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
const tour={id:'offer-one',price:105000,roomType:'Verified Standard',meal:{name:'Verified breakfast'}};
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
  assert(!root.querySelector('[data-screen="tour"]').textContent.includes('08:10'),'donor mock flight removed while pending');
  assert([...root.querySelectorAll('[data-action="lead"]')].every(button=>button.disabled),'no contact before canonical quote');
  resolveFirst();await owner.ready();
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
  const chosenOption=root.querySelector('[data-native-tour-flight="1"]').closest('.st-native-flight-option');
  assert.match(chosenOption.textContent,/Выбранный перелёт/);
  assert(!chosenOption.textContent.includes('SVO'),'Selected alternative does not repeat the full schedule');
  assert.match(root.querySelector('[data-native-flight-content]').textContent,/SVO/,'Selected full schedule remains visible');
  assert(root.querySelector('[data-screen="tour"]').textContent.replace(/\s+/g,' ').includes('112 000 ₽'),'full selected price shown without added delta/fuel');
  assert.equal(owner.getState().ready,false,'changed quoted price needs explicit acceptance');
  assert([...root.querySelectorAll('[data-price-change]')].every(node=>!node.hidden));
  click('[data-action="accept-price"]');
  assert.equal(accepted,1);assert.equal(owner.getState().ready,true);
  assert([...root.querySelectorAll('[data-action="lead"]')].every(button=>!button.disabled));
  owner.render({offer,hotel,screen:'lead'});assert.equal(quoted,1,'Back/contact navigation reuses exact quote receipt');
  assert(!form.elements.phone.disabled && !form.elements.consent.disabled);
  form.elements.phone.value='123';form.elements.consent.checked=true;submit();assert.equal(payloadCalls,0,'invalid contact does not construct payload');
  form.elements.phone.value='+7 999 123-45-67';form.elements.phone.dispatchEvent(new w.Event('input',{bubbles:true}));form.elements.consent.checked=false;submit();assert.equal(payloadCalls,0,'consent required for review');
  form.elements.consent.checked=true;submit();assert.equal(payloadCalls,1);assert.equal(leadSent,0);
  assert.equal(form.dataset.checked,'1');assert.match(form.textContent,/Заявка не отправлена/);assert.equal(root.querySelector('[data-screen="success"]').hidden,true,'never show donor success');
  owner.render({offer,hotel,screen:'tour'});assert.equal(quoted,1);assert.equal(form.elements.phone.value,'+7 999 123-45-67','contact draft preserved on return');
  click('[data-native-tour-flight="0"]');await owner.ready();assert.equal(owner.getState().ready,false,'another price revokes acceptance');
  assert.equal(form.dataset.checked,undefined,'review invalidated by flight selection');submit();assert.equal(payloadCalls,1);assert.equal(leadSent,0);
  owner.destroy();w.close();
  await staleAndExpiry();
  await anexPackageAndGroup();
  await viewportRemount();
  console.log('PASS native tour: quote/flight indices, full total, exact-selection acceptance, placeholder/missing facts, verified contact review without delivery, return draft, stale response and expiry');
})().catch(error=>{console.error(error);owner.destroy();w.close();process.exitCode=1;});

async function staleAndExpiry(){
  const other=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only'});
  const v=other.window,r=v.document.querySelector('[data-product]');v.eval(fs.readFileSync(path.resolve(__dirname,'../src/search3-design-v2/tour.js'),'utf8'));
  let present=null,resolvers=[];
  const second={...offer,key:'canonical:two',raw:{id:'offer-two'},total:90000};
  const adapter={getSelection:()=>present,quoteOffer(key){return new Promise(resolve=>resolvers.push({key,resolve}));},acceptPrice(){present={...present,priceAccepted:true};return present;},previewContact(){throw new Error('Expired canonical receipt');}};
  const view=v.AnyTourDesignV2Tour.mount({root:r,bridge:adapter});
  view.render({offer,hotel,screen:'tour'});view.render({offer:second,hotel:{...hotel,name:'Second hotel'},screen:'tour'});
  present={...second,offer:second,tour:{id:'offer-two',price:90000},variants:[],total:90000,flightChoiceId:null,pricePending:false,priceAccepted:true,expiresAt:Math.floor(Date.now()/1000)+1};
  resolvers[1].resolve(present);await view.ready();assert.equal(view.getState().ready,true);
  resolvers[0].resolve({...offer,offer,tour,variants,pricePending:false,total:999999});await new Promise(resolve=>v.setTimeout(resolve,0));
  assert.equal(r.querySelector('[data-tour-hotel]').textContent,'Second hotel','stale quote cannot repaint current offer');
  assert.equal(r.querySelector('[data-tour-price]').textContent,'90 000 ₽');
  await new Promise(resolve=>v.setTimeout(resolve,1100));
  assert.equal(view.getState().ready,false,'expiry blocks contact even after acceptance');
  assert.match(r.querySelector('[data-native-tour-status]').textContent,/истёк/);assert(r.querySelector('[data-lead-form]').elements.phone.disabled);
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
  const draft=view.getState();view.destroy();product=freshRoot();view=v.AnyTourDesignV2Tour.mount({root:product,bridge:adapter,initialState:draft});view.render({offer,hotel,screen:'lead'});
  const restored=product.querySelector('[data-lead-form]');assert.equal(restored.elements.name.value,'Name');assert.equal(restored.elements.phone.value,'+7 999 123-45-67');assert(restored.elements.consent.checked,'same exact quote/flight/total may carry consent during viewport remount');
  product.querySelector('[data-native-tour-flight="0"]').click();await view.ready();assert.equal(restored.elements.consent.checked,false,'changed flight revokes prior consent');assert.equal(calls,1);
  view.destroy();other.window.close();
}
