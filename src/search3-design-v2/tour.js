'use strict';
// Native final Sites v2 tour/contact owner. All quote, price and receipt
// authority stays in the existing headless adapter. No lead is sent here.
(function (window) {
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const textFact = value => typeof value === 'string' ? value : value && typeof value === 'object' ? value.russianName || value.nameRu || value.name || '' : '';
  const money = value => Number.isFinite(Number(value)) && Number(value) > 0 ? new Intl.NumberFormat('ru-RU').format(Number(value)).replace(/[\u00a0\u202f]/g,' ') + ' ₽' : 'Цена требует проверки';
  const dateText = value => /^\d{4}-\d{2}-\d{2}$/.test(value || '') ? new Date(value + 'T12:00:00Z').toLocaleDateString('ru-RU',{day:'numeric',month:'short',timeZone:'UTC'}).replace('.', '') : value || 'Дата уточняется';
  const nights = value => value+' '+(value%10===1 && value%100!==11 ? 'ночь' : value%10>=2 && value%10<=4 && (value%100<12 || value%100>14) ? 'ночи' : 'ночей');
  const party = offer => offer.adults + (offer.adults===1 ? ' взрослого' : ' взрослых') + (offer.ages?.length ? ' + ' + offer.ages.length + (offer.ages.length===1 ? ' ребёнка' : ' детей') : '');
  const icon = name => '<i data-lucide="' + name + '" aria-hidden="true"></i>';
  function mount({root, bridge, onChanged = () => {}, initialState = null}) {
    if (!root || !bridge) throw new Error('Не удалось подключить условия тура.');
    const $ = selector => root.querySelector(selector), $$ = selector => [...root.querySelectorAll(selector)];
    const controller = new window.AbortController();
    const form = $('[data-lead-form]'), tourScreen = $('[data-screen="tour"]');
    let alive = true, context = null, selection = null, epoch = 0, pending = Promise.resolve(), busy = false, error = '', review = null, expiryTimer = null;
    let flightAlternativesOpen = false;
    const text = (selector, value) => $$(selector).forEach(node => { node.textContent = value; });
    const sameOffer = (value, offer = context?.offer) => Boolean(value && offer && (value.offer === offer || value.key === offer.key && value.raw === offer.raw));
    function current() {
      if (!context?.offer) return null;
      try {
        const value = typeof bridge.getSelection === 'function' ? bridge.getSelection() : selection;
        return sameOffer(value) ? value : null;
      } catch { return null; }
    }
    function binding(value = current()) {
      return value && context?.offer ? {offer:context.offer,tour:value.tour,choice:value.flightChoiceId ?? value.quoteResult?.choice?.choiceRef ?? null,total:Number(value.total)} : null;
    }
    function sameBinding(a,b) {return Boolean(a && b && a.offer===b.offer && a.tour===b.tour && a.choice===b.choice && a.total===b.total);}
    function emit(extra = {}) { if (alive) onChanged({selection:current(),ready:canContinue(),pending:busy,error,...extra}); }
    function canContinue() {
      const value = current();
      return Boolean(value && !busy && !error && !value.quoteError && !value.pricePending && !value.flightsLoading && !value.flightsError
        && Number.isFinite(Number(value.total)) && Number(value.total) > 0 && value.priceAccepted === true
        && (!value.expiresAt || value.expiresAt * 1000 > Date.now())
        && (!value.quoteResult?.expiresAt || value.quoteResult.expiresAt * 1000 > Date.now()));
    }
    function photo(selector) {
      const hotel = context?.hotel, source = hotel?.photos?.[0];
      $$(selector).forEach(node => {
        node.style.backgroundImage = source && /^(https?:\/\/|\/)/.test(source) ? 'url(' + JSON.stringify(source) + ')' : 'none';
        node.classList.toggle('st-no-photo', !source);
        node.setAttribute('aria-label',source ? 'Фото отеля ' + hotel.name : 'Фото отеля пока нет');
      });
    }
    function setStatus(message, failure = false) {
      const node = $('[data-native-tour-status]');
      if (!node) return;
      node.hidden = !message; node.textContent = message;
      node.setAttribute('role',failure ? 'alert' : 'status');
    }
    function prepare() {
      tourScreen.querySelector('[data-stay-section-marker]')?.closest('.st-tour-section')?.classList.add('st-stay-details');
      const sections = [...tourScreen.querySelectorAll('.st-tour-layout > div > .st-tour-section')];
      if (sections[1]) {
        sections[1].dataset.nativeFlightSection = '';
        sections[1].innerHTML = '<div class="st-step-label">ПЕРЕЛЁТ</div><div class="st-section-top"><h3>Туда и обратно</h3><span class="st-status-pill" data-native-flight-status>Требует проверки</span></div><div data-native-flight-content></div>';
      }
      if (sections[2]) sections[2].innerHTML = '<div class="st-step-label">СОСТАВ ТУРА</div><div class="st-included-list" data-native-inclusions></div>';
      let status = $('[data-native-tour-status]');
      if (!status) {status = window.document.createElement('p');status.className = 'st-small st-muted';status.dataset.nativeTourStatus='';status.setAttribute('aria-live','polite');tourScreen.querySelector('.st-screen-title').insertAdjacentElement('afterend',status);}
      const consent = form.querySelector('input[type="checkbox"]');
      consent.name='consent';consent.value='1';consent.required=true;
      form.elements.name.maxLength=120;form.elements.phone.maxLength=40;form.elements.phone.setAttribute('inputmode','tel');
      const submit = form.querySelector('button[type="submit"]');submit.textContent='Проверить заявку';
      let message = form.querySelector('[data-native-contact-status]');
      if (!message) {message = window.document.createElement('p');message.className='st-small st-muted';message.dataset.nativeContactStatus='';message.setAttribute('aria-live','polite');submit.insertAdjacentElement('afterend',message);}
      const note = [...form.querySelectorAll('p')].find(node => !node.hasAttribute('data-native-contact-status'));
      if (note) note.textContent='В этой версии можно проверить контакты и выбранный тур. Заявка не отправляется.';
      form.dataset.delivery='preview-disabled';
      for(const name of ['name','phone'])if(typeof initialState?.draft?.[name]==='string')form.elements[name].value=initialState.draft[name].slice(0,name==='name'?120:40);
    }
    function legHTML(segments,label) {
      if (!Array.isArray(segments) || !segments.length) return '<div class="st-flight"><div class="st-flight-label"><span>' + esc(label) + '</span></div><p class="st-small st-muted">Расписание не предоставлено.</p></div>';
      return segments.map((flight,index) => {
        const departure=flight?.departure || {},arrival=flight?.arrival || {};
        const placeholder=/000$/.test(String(flight?.number || '').replace(/\s/g,'')) && departure.time==='00:00' && arrival.time==='00:00';
        const company=textFact(flight?.company),number=placeholder ? 'Номер рейса уточняется' : flight?.number || '';
        const allowance=field => {
          const raw=flight?.[field];
          if(raw===null || raw===undefined || String(raw).trim()==='' || placeholder && Number(raw)===0)return 'Уточняется';
          if(field==='carryOn')return String(raw)==='0' ? 'Без ручной клади' : typeof raw==='string' || typeof raw==='number' ? String(raw) : 'Уточняется';
          const n=Number(raw);return !Number.isFinite(n) || n<0 ? 'Уточняется' : n===0 ? 'Без багажа' : n+' кг';
        };
        return '<div class="st-flight"><div class="st-flight-label"><span>'+esc((index ? 'Пересадка · ' : '')+label)+(departure.date ? ' · '+esc(dateText(departure.date)) : '')+'</span><span>'+esc([company,number].filter(Boolean).join(' '))+'</span></div><div class="st-flight-row"><div><b>'+esc(placeholder ? '—' : departure.time || '—')+'</b><span>'+esc(textFact(departure.port) || 'Аэропорт уточняется')+'</span></div><div class="st-flight-middle">'+icon('plane')+'<hr>'+esc(flight?.plane || '')+'</div><div><b>'+esc(placeholder ? '—' : arrival.time || '—')+'</b><span>'+esc(textFact(arrival.port) || 'Аэропорт уточняется')+'</span></div></div><div class="st-baggage">'+icon('luggage')+'Багаж: '+esc(allowance('baggage'))+' · ручная кладь: '+esc(allowance('carryOn'))+'</div></div>';
      }).join('');
    }
    function flightChoiceHTML(value) {
      const variants=Array.isArray(value?.variants) ? value.variants : [];
      const alternatives=(html,count,hasSelection)=>!html?'':hasSelection
        ? '<details class="st-native-flight-alternatives" data-native-flight-alternatives '+(flightAlternativesOpen?'open':'')+'><summary>Другие перелёты · '+count+'</summary><div class="st-native-flight-options">'+html+'</div></details>'
        : '<div class="st-native-flight-options">'+html+'</div>';
      const option=(facts,amount,button)=>'<div class="st-offer-choice st-native-flight-option"><div class="st-native-flight-facts">'+facts+'</div><div class="st-native-flight-option-footer">'+(amount===null?'':'<div class="st-offer-price">'+money(amount)+'</div>')+button+'</div></div>';
      if (context.offer.provider==='anex') {
        const chosen=value?.quoteResult?.choice;
        const otherVariants=variants.filter(variant=>variant.choiceRef!==chosen?.choiceRef);
        return (chosen ? chosen.legs.map((leg,index)=>'<div class="st-flight"><div class="st-flight-label"><span>'+(index ? 'Обратно' : 'Туда')+'</span></div><p>'+esc(leg.label)+'</p></div>').join('') : '')
          + alternatives(otherVariants.map(variant=>{
            const facts=variant.legs.map((leg,index)=>'<div class="st-native-flight-leg"><span class="st-native-flight-direction">'+(index?'Обратно':'Туда')+'</span><div class="st-native-flight-route">'+esc(leg.label)+'</div></div>').join('');
            const button='<button type="button" class="st-secondary st-offer-select" data-native-provider-flight="'+esc(variant.choiceRef)+'" '+(busy ? 'disabled' : '')+'>Проверить цену</button>';
            return option(facts,null,button);
          }).join(''),otherVariants.length,Boolean(chosen));
      }
      const index=value?.flightChoiceId===null || value?.flightChoiceId===undefined ? null : Number(value.flightChoiceId),selected=index===null ? null : variants[index];
      let content=selected ? legHTML(selected.forward,'Туда')+legHTML(selected.backward,'Обратно') : '<p class="st-small st-muted">Выберите перелёт или оставьте уточнение рейсов менеджеру.</p>';
      const directionHTML=(segments,label)=>{
        if(!Array.isArray(segments)||!segments.length)return '<div class="st-native-flight-leg"><span class="st-native-flight-direction">'+label+'</span><div class="st-native-flight-route">Расписание уточняется</div></div>';
        return segments.map((segment,connection)=>{
          const departure=segment?.departure||{},arrival=segment?.arrival||{},placeholder=/000$/.test(String(segment?.number||'').replace(/\s/g,''))&&departure.time==='00:00'&&arrival.time==='00:00';
          const allowance=field=>{const raw=segment?.[field];if(raw===null||raw===undefined||String(raw).trim()===''||placeholder&&Number(raw)===0)return 'Уточняется';if(field==='carryOn')return String(raw)==='0'?'Без ручной клади':typeof raw==='string'||typeof raw==='number'?String(raw):'Уточняется';const n=Number(raw);return !Number.isFinite(n)||n<0?'Уточняется':n===0?'Без багажа':n+' кг';};
          const route=[textFact(departure.port)||'Аэропорт уточняется',textFact(arrival.port)||'Аэропорт уточняется'].join(' → ');
          const dates=[departure.date?dateText(departure.date):'',arrival.date&&arrival.date!==departure.date?dateText(arrival.date):''].filter(Boolean).join(' → ');
          const flight=[textFact(segment?.company),placeholder?'Номер рейса уточняется':segment?.number||'',dates,segment?.plane||''].filter(Boolean).join(' · ');
          return '<div class="st-native-flight-leg"><span class="st-native-flight-direction">'+esc((connection?'Пересадка · ':'')+label)+'</span><div class="st-native-flight-time">'+(placeholder?'Расписание уточняется':'<b>'+esc(departure.time||'—')+'</b><span aria-hidden="true"> → </span><b>'+esc(arrival.time||'—')+'</b>')+'</div><div class="st-native-flight-route">'+esc(route)+'</div>'+(flight?'<div class="st-small st-muted">'+esc(flight)+'</div>':'')+'<div class="st-small st-muted">Багаж: '+esc(allowance('baggage'))+' · ручная кладь: '+esc(allowance('carryOn'))+'</div></div>';
        }).join('');
      };
      const otherVariants=variants.map((variant,originalIndex)=>({variant,originalIndex})).filter(row=>row.originalIndex!==index);
      content+=alternatives(otherVariants.map(({variant,originalIndex})=>{
        const amount=typeof variant?.price==='object' ? Number(variant.price.value ?? variant.price.amount) : Number(variant?.price);
        const facts=directionHTML(variant?.forward,'Туда')+directionHTML(variant?.backward,'Обратно');
        const button='<button type="button" class="st-secondary st-offer-select" data-native-tour-flight="'+originalIndex+'" '+(!Number.isFinite(amount) || amount<=0 || busy ? 'disabled' : '')+'>Выбрать</button>';
        return option(facts,amount,button);
      }).join(''),otherVariants.length,Boolean(selected));
      if (value?.tour && bridge.deferFlight) content+='<button type="button" class="st-link" data-native-defer-flight '+(busy ? 'disabled' : '')+'>Рейс уточнит менеджер</button>';
      if (value?.tour && bridge.retryFlights && value.flightsError) content+='<button type="button" class="st-secondary" data-native-retry-flights '+(busy ? 'disabled' : '')+'>Повторить загрузку рейсов</button>';
      return content;
    }
    function renderDetails() {
      if (!context?.offer) return;
      const offer=context.offer,hotel=context.hotel,value=current(),tour=value?.tour;
      selection=value;
      const total=value && !value.pricePending && Number.isFinite(Number(value.total)) && Number(value.total)>0 ? Number(value.total) : Number(offer.total);
      const room=textFact(tour?.roomType) || offer.room || 'Номер уточняется',meal=textFact(tour?.meal) || offer.meal || 'Питание уточняется';
      text('[data-tour-hotel],[data-quick-hotel],[data-lead-hotel]',hotel?.name || 'Отель уточняется');
      text('[data-tour-location]',hotel?.location || 'Расположение уточняется');
      text('[data-tour-room],[data-quick-room]',room);text('[data-tour-meal],[data-quick-meal]',meal);
      const stay=dateText(offer.day)+(offer.returnDay ? ' — '+dateText(offer.returnDay) : '')+' · '+nights(offer.nights);
      text('[data-tour-stay],[data-quick-dates]',stay);text('[data-tour-party],[data-cost-party]',party(offer));text('[data-quick-party]','За '+party(offer));
      text('[data-tour-price]',money(total));text('[data-tour-operator]',offer.operator || 'Туроператор уточняется');
      text('[data-lead-choice]',room+' · '+meal);text('[data-lead-context]',dateText(offer.day)+' · '+nights(offer.nights)+' · '+party(offer));
      photo('[data-tour-thumb],[data-quick-thumb],[data-lead-thumb]');
      const changed=value && !value.pricePending && Number(value.total)!==Number(offer.total);
      $$('[data-price-change]').forEach(node=>{node.hidden=!changed || value.priceAccepted===true;});
      text('[data-price-change-text]',changed ? 'Было '+money(offer.total)+' → стало '+money(value.total)+' за всех туристов.' : '');
      $$('[data-action="accept-price"]').forEach(node=>node.disabled=busy || !changed);
      $$('[data-action="lead"]').forEach(node=>{node.disabled=!canContinue();node.setAttribute('aria-disabled',String(node.disabled));});
      text('.st-quick-price-note',canContinue() ? value.flightChoiceId!==null && value.flightChoiceId!==undefined || value.quoteResult?.state==='quote_verified' ? 'Стоимость проверена для выбранного перелёта. Другие возможные доплаты уточнит менеджер.' : 'Цена предложения проверена. Перелёт уточнит менеджер.' : busy ? 'Проверяем цену и рейсы выбранного тура…' : 'Цена из поиска. Проверьте точный тур и выбранный перелёт.');
      const flightContent=$('[data-native-flight-content]');
      if (flightContent) {
        if (context.offer.raw?.anexKind==='group_minimum') flightContent.innerHTML='<p class="st-small st-muted">Это минимальная цена группы предложений. Для выбора нужны точные варианты.</p><button type="button" class="st-secondary" data-native-expand-offer '+(busy?'disabled':'')+'>Показать точные варианты</button>';
        else if (busy && !value) flightContent.innerHTML='<p class="st-small st-muted" role="status">Проверяем предложение и загружаем варианты рейсов…</p>';
        else if (value?.quoteError && !value.tour && !value.quoteResult) flightContent.innerHTML='<p class="st-small st-muted">'+esc(value.quoteError)+'</p>'+(offer.provider==='tourvisor' && !offer.cached ? '<button type="button" class="st-secondary" data-native-check-tour>Повторить проверку тура</button>' : '');
        else if (value) flightContent.innerHTML=flightChoiceHTML(value);
        else flightContent.innerHTML='<p class="st-small st-muted">'+esc(error || 'Рейсы не подтверждены.')+'</p>'+(offer.provider==='tourvisor' && !offer.cached ? '<button type="button" class="st-secondary" data-native-check-tour>Проверить тур</button>' : '');
      }
      text('[data-native-flight-status]',value?.quoteResult?.state==='quote_verified' || value?.flightChoiceId!==null && value?.flightChoiceId!==undefined ? 'Выбранный перелёт' : 'Требует уточнения');
      text('[data-native-inclusions]','Проживание: '+room+' · Питание: '+meal+'. Перелёт, трансфер, страховку и обязательные доплаты следует сверить по условиям выбранного пакета.');
      const supplements=tourScreen.querySelector('.st-cost-list > div:last-child > b');if(supplements)supplements.textContent='Уточняются';
      const failure=error || value?.quoteError || value?.flightsError || '';
      setStatus(busy ? 'Проверяем точное предложение…' : failure, Boolean(failure));
      renderContact();scheduleExpiry();window.lucide?.createIcons({attrs:{width:18,height:18}});
    }
    function renderContact() {
      const ready=canContinue();
      for (const input of form.querySelectorAll('input,textarea')) input.disabled=!ready;
      const button=form.querySelector('button[type="submit"]');button.disabled=!ready;
      const message=form.querySelector('[data-native-contact-status]');
      if (!ready) {review=null;delete form.dataset.checked;const failure=error || current()?.quoteError || current()?.flightsError;message.textContent=failure || 'Сначала проверьте тур, выберите перелёт или уточнение менеджером и подтвердите изменившуюся цену.';message.setAttribute('role',failure?'alert':'status');}
      else if (!review) {message.textContent='';message.setAttribute('role','status');}
    }
    function scheduleExpiry() {
      if(expiryTimer!==null)window.clearTimeout(expiryTimer);expiryTimer=null;
      const value=current(),expires=value?.expiresAt || value?.quoteResult?.expiresAt;
      if(expires && expires*1000>Date.now())expiryTimer=window.setTimeout(()=>{if(!alive)return;error='Срок подтверждённой цены истёк. Выполните новый поиск.';review=null;renderDetails();emit();},Math.min(2147483647,expires*1000-Date.now()+1));
    }
    function settlePrice() {
      const value=current();
      if(value && !value.pricePending && Number(value.total)===Number(context.offer.total) && value.priceAccepted!==true && bridge.acceptPrice)selection=bridge.acceptPrice();
    }
    async function operation(action) {
      if(busy || !context?.offer)return;
      const operationEpoch=epoch,offer=context.offer;busy=true;error='';review=null;form.elements.consent.checked=false;renderDetails();emit();
      pending=(async()=>{
        try {
          const result=await action(offer);
          if(!alive || operationEpoch!==epoch || context.offer!==offer)return;
          if(result?.navigate){busy=false;emit(result);return;}
          selection=result;if(current()?.quoteError)throw new Error(current().quoteError);settlePrice();
        } catch(failure) {if(alive && operationEpoch===epoch){error=failure.message || 'Не удалось проверить условия тура.';}}
        finally {if(alive && operationEpoch===epoch){busy=false;renderDetails();emit();}}
      })();
      return pending;
    }
    function render(next) {
      if(!alive)return;
      if(!next?.offer){++epoch;context=null;selection=null;busy=false;error='';review=null;if(expiryTimer!==null)window.clearTimeout(expiryTimer);return;}
      const replaced=!context || context.offer!==next.offer;
      context=next;
      if(replaced){
        flightAlternativesOpen=initialState?.flightAlternativesOffer===next.offer && initialState.flightAlternativesOpen===true;
        ++epoch;selection=null;error='';review=null;busy=false;delete form.dataset.checked;form.elements.consent.checked=false;
        const cached=current();
        if((cached?.loading || cached?.flightsLoading) && bridge.waitSelection)operation(()=>bridge.waitSelection());
        else if(initialState?.pendingOffer===next.offer && initialState.pendingPromise){const prior=initialState.pendingPromise;initialState={...initialState,pendingPromise:null};operation(()=>Promise.resolve(prior).then(()=>bridge.getSelection()));}
        else if(cached?.loading || cached?.flightsLoading)operation(offer=>bridge.quoteOffer(offer.key,{expectedOffer:offer}));
        else if(cached){selection=cached;error=cached.quoteError || '';settlePrice();if(canContinue() && sameBinding(initialState?.draft?.binding,binding()))form.elements.consent.checked=initialState.draft.consent===true;}
        else if(next.offer.raw?.anexKind!=='group_minimum' && !next.offer.cached)operation(offer=>bridge.quoteOffer(offer.key,{expectedOffer:offer}));
      }
      renderDetails();
    }
    root.addEventListener('click',event=>{
      const button=event.target.closest('button');if(!button || !root.contains(button) || button.disabled)return;
      const chooseFlight=action=>{const choiceEpoch=epoch;flightAlternativesOpen=false;operation(action)?.then(()=>{
        if(!alive || epoch!==choiceEpoch || !current())return;
        const target=$('[data-native-flight-alternatives] summary') || $('[data-native-flight-status]');
        if(target){if(target.tagName!=='SUMMARY')target.tabIndex=-1;target.focus({preventScroll:true});}
      });};
      if(button.hasAttribute('data-native-tour-flight'))chooseFlight(()=>bridge.selectFlight(Number(button.dataset.nativeTourFlight)));
      if(button.hasAttribute('data-native-provider-flight'))chooseFlight(()=>bridge.selectProviderFlight(button.dataset.nativeProviderFlight));
      if(button.hasAttribute('data-native-defer-flight'))operation(()=>bridge.deferFlight());
      if(button.hasAttribute('data-native-retry-flights'))operation(()=>bridge.retryFlights());
      if(button.hasAttribute('data-native-check-tour'))operation(offer=>bridge.quoteOffer(offer.key,{force:true,expectedOffer:offer}));
      if(button.hasAttribute('data-native-expand-offer'))operation(async offer=>({navigate:'hotel',expandedOffers:await bridge.expandOfferGroup(offer.key,{expectedOffer:offer})}));
      if(button.dataset.action==='accept-price'){
        try{selection=bridge.acceptPrice();error='';review=null;renderDetails();emit();}catch(failure){error=failure.message;renderDetails();emit();}
      }
    },{signal:controller.signal});
    root.addEventListener('toggle',event=>{
      if(event.target.matches?.('[data-native-flight-alternatives]'))flightAlternativesOpen=event.target.open;
    },{capture:true,signal:controller.signal});
    form.addEventListener('input',()=>{review=null;delete form.dataset.checked;window.V2LeadFormGuard?.validatePhone(form.elements.phone);renderContact();},{signal:controller.signal});
    form.addEventListener('change',()=>{review=null;delete form.dataset.checked;renderContact();},{signal:controller.signal});
    form.addEventListener('submit',event=>{
      event.preventDefault();if(!canContinue())return;
      const phone=form.elements.phone;
      if(!window.V2LeadFormGuard?.validatePhone){const message=form.querySelector('[data-native-contact-status]');message.setAttribute('role','alert');message.textContent='Проверка контактов временно недоступна.';return;}
      if(!window.V2LeadFormGuard.validatePhone(phone) || !form.reportValidity())return;
      try {
        review=bridge.previewContact(new window.FormData(form));form.dataset.checked='1';
        const message=form.querySelector('[data-native-contact-status]');message.setAttribute('role','status');
        message.textContent='Контакты и выбранные условия проверены. '+(context.hotel?.name || '')+' · '+money(current().total)+'. Заявка не отправлена.';
        emit({contactChecked:true});
      } catch(failure) {review=null;const message=form.querySelector('[data-native-contact-status]');message.setAttribute('role','alert');message.textContent=failure.message || 'Не удалось проверить выбранные условия.';}
    },{signal:controller.signal});
    prepare();renderContact();
    return Object.freeze({render,ready:()=>pending,getState:()=>({selection:current(),ready:canContinue(),pending:busy,pendingPromise:busy?pending:null,pendingOffer:busy?context?.offer:null,error,contactChecked:Boolean(review),flightAlternativesOpen,flightAlternativesOffer:context?.offer,draft:{name:form.elements.name.value,phone:form.elements.phone.value,consent:canContinue()&&form.elements.consent.checked,binding:binding()}}),destroy(){alive=false;++epoch;controller.abort();if(expiryTimer!==null)window.clearTimeout(expiryTimer);context=null;selection=null;review=null;}});
  }
  window.AnyTourDesignV2Tour=Object.freeze({mount});
})(window);
