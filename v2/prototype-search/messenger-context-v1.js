'use strict';
// Preparation only: browser observations, never a trusted fare or bot capability.
// No transport, storage, DOM, analytics or supplier calls. Host controls activation.
(function(root){
  const fail=code=>{throw new Error(code);};
  function text(value,max=500){
    if(value===undefined||value===null)return '';
    if(!['string','number'].includes(typeof value))fail('invalid_context_text');
    const result=String(value).trim();
    if(result.length>max||/[\u0000-\u001f\u007f]/.test(result))fail('invalid_context_text');
    return result;
  }
  function day(value,optional=false){
    if(optional&&(value===undefined||value===null||value===''))return null;
    if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))fail('invalid_trip_date');
    const stamp=Date.parse(value+'T12:00:00Z');
    if(!Number.isFinite(stamp)||new Date(stamp).toISOString().slice(0,10)!==value)fail('invalid_trip_date');
    return value;
  }
  function money(value){
    const raw=value&&typeof value==='object'?value.value:value;
    if(raw===undefined||raw===null||raw===''||raw===0||raw==='0')return null;
    if(!['number','string'].includes(typeof raw)||!Number.isFinite(Number(raw))||Number(raw)<=0)fail('invalid_observed_amount');
    return String(raw);
  }
  function freeze(value){
    if(value&&typeof value==='object'){Object.values(value).forEach(freeze);Object.freeze(value);}
    return value;
  }
  function label(value,max,helpers){return text(helpers.text(value),max);}
  function point(value,helpers,unknownTime){
    const p=value||{},port=p.port;
    return {date:text(p.date,80),time:unknownTime?null:text(p.time,80)||null,town:label(p.town,160,helpers),
      port:label(port,160,helpers),portCode:port&&typeof port==='object'?text(port.id||port.shortName,80):''};
  }
  function leg(value,helpers,unknownTime){
    if(!value||typeof value!=='object')fail('invalid_flight_leg');
    const allowance=field=>{const shown=helpers.allowanceValue(value,field);return shown==='уточняется'?null:text(shown,160);};
    return {number:text(value.number,120),carrier:label(value.company,180,helpers),
      departure:point(value.departure,helpers,unknownTime),arrival:point(value.arrival,helpers,unknownTime),
      baggage:allowance('baggage'),carryOn:allowance('carryOn')};
  }
  function pair(offer,helpers){
    const id=offer.flightChoiceId;
    if(id===null||id===undefined){
      return {ref:null,value:{state:offer.hotelFlightDeferred===true?'manager-deferred':'not-selected',outward:[],inward:[]},amount:null};
    }
    if(!['number','string'].includes(typeof id)||!/^(?:0|[1-9][0-9]*)$/.test(String(id))||!Number.isSafeInteger(Number(id)))fail('invalid_selected_pair');
    const selected=Array.isArray(offer.variants)?offer.variants[Number(id)]:null;
    if(!selected||!Array.isArray(selected.forward)||!selected.forward.length||!Array.isArray(selected.backward)||!selected.backward.length)fail('invalid_selected_pair');
    if(offer.hotelFlightDeferred===true)fail('ambiguous_flight_selection');
    const direction=segments=>{const unknownTime=helpers.departureMinute(segments)===null;return segments.map(value=>leg(value,helpers,unknownTime));};
    return {ref:selected,amount:money(selected.price),value:{state:'selected',outward:direction(selected.forward),inward:direction(selected.backward)}};
  }
  function readApplicationContext(input,capturedAt,now,helpers){
    const o=input.offer,h=input.hotel,r=input.application,raw=o?.raw;
    const invalid=()=>fail('invalid_application_context');
    if(!r||o?.provider!=='anex'||r.provider!=='anex'||raw?.anexKind!=='concrete'||raw.anexSessionCurrent!==true
      ||r.offerRef!==raw.offerRef||r.searchRef!==raw.searchRef||r.generation!==raw.anexGeneration
      ||r.localHotelId!==Number(raw.anexLocalHotelId)||!Number.isSafeInteger(r.localHotelId)||r.localHotelId<1
      ||!/^anex_online:[a-f0-9]{64}$/.test(r.offerRef)||!/^[a-f0-9]{32}$/.test(r.searchRef)
      ||!Number.isSafeInteger(r.generation)||r.generation<1||r.hotel!==h?.name||r.day!==o.day||r.nights!==o.nights
      ||r.adults!==o.adults||!Array.isArray(r.ages)||JSON.stringify(r.ages)!==JSON.stringify(o.ages)
      ||r.room!==o.room||r.currency!=='RUB'||typeof r.price!=='number'||!Number.isFinite(r.price)||r.price<=0
      ||o.flightChoiceId!==undefined&&o.flightChoiceId!==null)invalid();
    const verified=r.priceKind==='verified'&&r.finalPriceVerified===true;
    const estimated=r.priceKind==='estimate'&&r.finalPriceVerified===false;
    if(!verified&&!estimated||!Array.isArray(r.flights))invalid();
    if(verified){
      if(typeof r.choiceRef!=='string'||!r.choiceRef||r.choiceRef.length>4096||!Number.isSafeInteger(r.expiresAt)||r.expiresAt<1
        ||r.departure!==(o.origin||o.search?.origin)||r.flights.length!==2
        ||r.flights.some((f,i)=>f?.direction!==String(i)||typeof f.name!=='string'||!f.name.trim()||f.name.length>1000))invalid();
    }else if(r.flights.length||r.choiceRef!==undefined||r.expiresAt!==undefined)invalid();
    // Reuse the original selected-row/party/date/namespace validation. The
    // original terms also bind pending results when receipt labels are unchanged.
    const base=readContext({...input,application:undefined,price:{state:'unknown',currency:'RUB',description:''}},capturedAt,now,helpers);
    const binding=JSON.stringify([base.value,money(o.total),r.offerRef,r.searchRef,r.generation,r.localHotelId,r.choiceRef]);
    const value=base.value;
    value.stay={room:text(r.room,500),meal:text(r.meal,300),placement:text(o.placement,500),operator:text(r.operator,300)};
    if(!value.stay.meal||!value.stay.operator)invalid();
    value.flights=verified?{state:'selected',outward:[{label:text(r.flights[0].name,1000)}],inward:[{label:text(r.flights[1].name,1000)}]}
      :{state:'manager-deferred',outward:[],inward:[]};
    value.price={amount:money(r.price),currency:'RUB',state:verified?(r.expiresAt*1000<=now?'expired':'checked'):'estimated',
      description:verified?'Подтверждённая цена выбранного тура':'Расчётная стоимость · требует подтверждения',
      expiresAt:verified?r.expiresAt:null,basis:verified?'selected-pair':'shown-offer',finalPriceVerified:false,authority:'browser-observation-only'};
    return {...base,value,pair:r,binding,application:r};
  }
  function readTourvisorContext(input,capturedAt,now,helpers){
    const r=input.selector,o=input.offer,h=input.hotel;
    if(!r||Object.keys(r).sort().join(',')!=='choice_ref,generation,local_hotel_id,offer_ref,provider,search_ref'
      ||o?.provider!=='tourvisor'||r.provider!=='tourvisor'||!Number.isSafeInteger(r.generation)||r.generation<1||r.generation>2147483647
      ||typeof r.search_ref!=='string'||!/^[a-f0-9]{32}$/.test(r.search_ref)||typeof r.offer_ref!=='string'||!/^tourvisor:[a-f0-9]{64}$/.test(r.offer_ref)
      ||!Number.isSafeInteger(r.local_hotel_id)||r.local_hotel_id<1||r.local_hotel_id>2147483647
      ||h?.id!==r.local_hotel_id&&!(h?.legacyIds||[]).map(String).includes(String(r.local_hotel_id))
      ||input.price?.state!=='checked'||input.price.currency!=='RUB')fail('invalid_tourvisor_context');
    const base=readContext({...input,selector:undefined},capturedAt,now,helpers),selected=base.pair;
    if(r.choice_ref==='manager-deferred'?!!selected:!selected||typeof r.choice_ref!=='string'||!/^tourvisor_pair:[a-f0-9]{64}$/.test(r.choice_ref))fail('invalid_tourvisor_context');
    // Compare the accepted server's retained TV terms. Keep the entire original
    // UI observation in the binding so a changed label, party or price is stale.
    const binding=JSON.stringify([base.value,r]);
    const contractLegs=values=>values.map(value=>{
      const from=value.departure?.port,to=value.arrival?.port;
      const number=label(value.number,80,helpers),a=label(from?.shortName??from?.id,80,helpers),b=label(to?.shortName??to?.id,80,helpers),date=label(value.departure?.date,80,helpers);
      if(!number||!a||!b||!date)fail('invalid_tourvisor_flight');
      return {label:number+' · '+a+' → '+b+' · '+date};
    });
    base.value.hotel.uiId=r.local_hotel_id;
    base.value.stay.room=label(o.tour?.roomType,500,helpers);
    base.value.stay.meal=label(o.tour?.meal,300,helpers);
    if(!base.value.stay.room||!base.value.stay.meal)fail('invalid_tourvisor_context');
    base.value.flights=selected?{state:'selected',outward:contractLegs(selected.forward),inward:contractLegs(selected.backward)}:{state:'manager-deferred',outward:[],inward:[]};
    return {...base,binding,selector:Object.freeze({...r})};
  }
  function readContext(input,capturedAt,now,helpers){
    if(input?.application!==undefined)return readApplicationContext(input,capturedAt,now,helpers);
    if(input?.selector!==undefined)return readTourvisorContext(input,capturedAt,now,helpers);
    const o=input?.offer,h=input?.hotel,p=input?.price;
    if(!o||!h||!p||!Number.isSafeInteger(input.revision)||input.revision<0)fail('incomplete_selection_context');
    if(typeof o.key!=='string'||!o.key||o.key.length>4096)fail('invalid_selection_key');
    if(!o.raw||typeof o.raw!=='object'||Array.isArray(o.raw))fail('missing_selected_row');
    if(!Number.isSafeInteger(o.hotelId)||o.hotelId<1||o.hotelId!==h.id)fail('selected_hotel_mismatch');
    if(!['tourvisor','anex','andromeda','fixture','recorded'].includes(o.provider))fail('invalid_context_provider');
    if(!Number.isInteger(o.adults)||o.adults<1||o.adults>6||!Array.isArray(o.ages)||o.ages.length>3||o.ages.some(age=>!Number.isInteger(age)||age<0||age>17))fail('invalid_trip_party');
    if(!Number.isInteger(o.nights)||o.nights<1||o.nights>365)fail('invalid_trip_nights');
    const hotelName=text(h.name,500),room=text(o.room,500),meal=text(o.meal,300),origin=text(o.origin||o.search?.origin,180);
    if(!hotelName||!room||!meal||!origin)fail('incomplete_trip_terms');
    if(!['listing','checked','estimated','unknown','expired','demo'].includes(p.state)||typeof p.currency!=='string'||!/^[A-Z]{3}$/.test(p.currency))fail('invalid_price_qualification');
    const expires=p.expiresAt===undefined||p.expiresAt===null?null:p.expiresAt;
    if(expires!==null&&(!Number.isSafeInteger(expires)||expires<1))fail('invalid_quote_expiry');
    const selected=pair(o,helpers),shown=money(o.total);
    const pairCurrency=selected.ref?.price?.currency||selected.ref?.currency;
    if(pairCurrency&&pairCurrency!==p.currency)fail('selected_pair_currency_mismatch');
    if(selected.ref&&selected.amount!==null&&shown!==null&&Number(selected.amount)!==Number(shown))fail('selected_pair_amount_mismatch');
    const amount=p.state==='unknown'||o.pricePending===true||selected.ref&&selected.amount===null?null:shown;
    const state=expires!==null&&expires*1000<=now?'expired':amount===null?'unknown':p.state;
    const value={schema:1,kind:'search3_browser_observation',capturedAt,source:o.provider,
      hotel:{uiId:h.id,namespace:'search3-ui',name:hotelName,resort:text(h.resort,300)},
      trip:{origin,countryUiId:text(o.search?.country||h.country,80),departure:day(o.day),nights:o.nights,
        stayEnd:day(o.returnDay,true),stayEndBasis:o.returnDay?'shown-stay-end':'unknown',adults:o.adults,childAges:[...o.ages]},
      stay:{room,meal,placement:text(o.placement,500),operator:text(o.operator,300)},
      flights:selected.value,
      price:{amount,currency:p.currency,state,description:text(p.description,1000),expiresAt:expires,
        basis:selected.ref?'selected-pair':'shown-offer',finalPriceVerified:false,authority:'browser-observation-only'}};
    return {value,key:o.key,raw:o.raw,pair:selected.ref,revision:input.revision};
  }
  function createSession(read,clock=Date.now,helpers={text:root.AnyTourPrototypeData?.text,
    departureMinute:root.AnyTourFlightDisplayV1?.departureMinute,allowanceValue:root.AnyTourFlightPickerV18?.allowanceValue}){
    if(typeof read!=='function'||typeof clock!=='function')fail('invalid_context_reader');
    if(!helpers||['text','departureMinute','allowanceValue'].some(key=>typeof helpers[key]!=='function'))fail('missing_flight_presentation_owner');
    const presentation={text:helpers.text,departureMinute:helpers.departureMinute,allowanceValue:helpers.allowanceValue};
    const captures=new WeakMap();
    function now(){const value=clock();if(!Number.isSafeInteger(value)||value<0)fail('invalid_context_clock');return value;}
    function currentCapture(value){
      const saved=value&&typeof value==='object'?captures.get(value):null;if(!saved)return null;
      try{
        const current=readContext(read(),value.capturedAt,now(),presentation);
        return saved.key===current.key&&saved.raw===current.raw&&saved.pair===current.pair&&saved.revision===current.revision&&saved.binding===current.binding&&saved.signature===JSON.stringify(current.value)?current:null;
      }catch(error){return null;}
    }
    return Object.freeze({
      capture(){
        const time=now(),snapshot=readContext(read(),time,time,presentation),value=freeze(snapshot.value);
        captures.set(value,{...snapshot,signature:JSON.stringify(value)});return value;
      },
      isCurrent(value){return currentCapture(value)!==null;},
      selector(value){
        const current=currentCapture(value);
        if(current?.selector&&current.value.price.state==='checked'&&current.value.price.amount!==null)return current.selector;
        return this.anexSelector(value);
      },
      // Same-origin producer input only. The server resolves retained facts;
      // these private selectors are never display context or bot URL payloads.
      anexSelector(value){
        const current=currentCapture(value),r=current?.application;
        if(!r||current.value.source!=='anex'||current.value.flights.state!=='selected'||current.value.price.state!=='checked'
          ||r.generation>2147483647||r.localHotelId>2147483647||!/^anex_quote:[a-f0-9]{64}$/.test(r.choiceRef))return null;
        return Object.freeze({generation:r.generation,search_ref:r.searchRef,offer_ref:r.offerRef,
          local_hotel_id:r.localHotelId,choice_ref:r.choiceRef});
      }
    });
  }
  root.AnyTourSearch3MessengerContextV1=Object.freeze({createSession,version:1});
})(typeof window==='object'?window:globalThis);
