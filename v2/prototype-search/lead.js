(function(root){
  'use strict';
  let draft={name:'',phone:'',comment:''},draftRevision=0;
  const preview=new URL(root.V2_CONFIG.leadApi,root.location.href).pathname.endsWith('/preview-lead-disabled.php');
  function markup(){
    return `<form id="prototype-lead-form">
      <p class="modal-intro">${preview?'Проверьте контакты и выбранные условия. В версии для проверки заявка не отправляется.':'Менеджер получит выбранный тур и рейс и свяжется с вами. Оплата на этом шаге не требуется.'}</p>
      <div class="form-row"><label>Имя (необязательно)<input class="input" name="name" autocomplete="name" maxlength="120" placeholder="Как к вам обращаться"></label><label>Телефон<input class="input" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="40" required placeholder="+7 999 123-45-67" aria-describedby="prototype-phone-hint"></label></div>
      <p class="modal-intro" id="prototype-phone-hint">Введите номер с кодом страны или города.</p>
      <div class="form-row"><label>Комментарий (необязательно)<textarea class="input" name="comment" rows="3" maxlength="1000" placeholder="Ваши пожелания к поездке"></textarea></label></div>
      <label class="meal-option"><input type="checkbox" name="consent" value="1" required><span>Согласен на обработку персональных данных</span></label>
      <p class="lead-message modal-intro" role="status" aria-live="polite"></p>
    </form>`;
  }
  function action(){return `<button class="primary" type="submit" form="prototype-lead-form">${preview?'Проверить заявку':'Отправить заявку'}</button>`;}
  function bind(offer){
    const form=document.getElementById('prototype-lead-form');if(!form)return;
    draftRevision++;
    const phone=form.elements.phone,button=document.querySelector('[type="submit"][form="prototype-lead-form"]'),message=form.querySelector('.lead-message');
    for(const name of ['name','phone','comment'])form.elements[name].value=draft[name];
    let session;
    try{session=root.AnyTourPrototypeData.leadSession(offer);}
    catch(error){message.textContent=error.message;message.setAttribute('role','alert');button.disabled=true;message.scrollIntoView({block:'nearest'});}
    form.addEventListener('input',()=>{
      draftRevision++;
      for(const name of ['name','phone','comment'])draft[name]=form.elements[name].value;
      root.V2LeadFormGuard.validatePhone(phone);
      if(preview&&session){message.textContent='';delete form.dataset.checked;}
    });
    form.addEventListener('submit',async event=>{
      event.preventDefault();
      if(!session||form.dataset.sent==='1'||button.disabled)return;
      if(!root.V2LeadFormGuard.validatePhone(phone)||!form.reportValidity()){phone.reportValidity();return;}
      try{
        // Construct through the same owner even in preview; no preview request is sent.
        session.payload(new FormData(form));
        if(preview){form.dataset.checked='1';message.setAttribute('role','status');message.textContent='Данные проверены. Тур, выбранный рейс и контакты готовы к передаче. В этой версии заявка не отправлена.';message.scrollIntoView({block:'nearest'});return;}
        const submittedRevision=draftRevision;
        if(await session.submit(form,{button})&&draftRevision===submittedRevision)reset();
      }catch(error){message.textContent=error.message;message.setAttribute('role','alert');}
    });
  }
  const providerText=(value,max=500)=>String(value??'').replace(/\s+/g,' ').trim().slice(0,max);
  function providerPreviewReceipt(value){
    if(!preview)throw new Error('Проверка заявки поставщика доступна только в изолированной preview-версии.');
    const price=Number(value?.price),nights=Number(value?.nights),adults=Number(value?.adults),ages=value?.ages;
    if(!value||value.provider!=='andromeda'||!(/^offer_[a-f0-9]{64}$/).test(String(value.offerRef||''))
      ||value.currency!=='RUB'||!Number.isFinite(price)||price<=0||!(/^\d{4}-\d{2}-\d{2}$/).test(String(value.day||''))
      ||!Number.isInteger(nights)||nights<1||!Number.isInteger(adults)||adults<1||adults>6
      ||!Array.isArray(ages)||ages.length>3||ages.some(age=>!Number.isInteger(age)||age<0||age>17)
      ||!Array.isArray(value.flights)||value.flights.length>100)throw new Error('Подтверждённые условия тура неполные. Повторите проверку предложения.');
    const flights=value.flights.map(f=>Object.freeze({direction:String(f?.direction||''),name:providerText(f?.name,160),
      datebeg:providerText(f?.datebeg,40),dateend:providerText(f?.dateend,40),class:providerText(f?.class,80),
      departure:f?.departure&&typeof f.departure==='object'?structuredClone(f.departure):null,
      arrival:f?.arrival&&typeof f.arrival==='object'?structuredClone(f.arrival):null}));
    if(flights.some(f=>!['0','1'].includes(f.direction)))throw new Error('Подтверждённые рейсы повреждены. Повторите проверку предложения.');
    return Object.freeze({provider:'andromeda',offerRef:String(value.offerRef),hotel:providerText(value.hotel,240),
      country:providerText(value.country,120),resort:providerText(value.resort,160),day:String(value.day),nights,adults,
      ages:Object.freeze([...ages]),room:providerText(value.room,300),meal:providerText(value.meal,160),
      operator:providerText(value.operator,180),price,currency:'RUB',flights:Object.freeze(flights)});
  }
  function providerFlightText(f){
    const label=f.direction==='0'?'Туда':'Обратно',point=p=>[providerText(p?.town,120),providerText(p?.port,20)].filter(Boolean).join(' ');
    return [label,providerText(f.name,160),providerText(f.datebeg,40),point(f.departure),point(f.arrival)].filter(Boolean).join(' · ');
  }
  function providerPreviewPayload(receipt,fd){
    const r=providerPreviewReceipt(receipt);
    return Object.freeze({provider:r.provider,providerOfferRef:r.offerRef,name:providerText(fd.get('name'),120),
      phone:providerText(fd.get('phone'),40),comment:providerText(fd.get('comment'),1000),consent:fd.get('consent')==='1',
      hotel:r.hotel,country:r.country,region:r.resort,date:r.day,nights:r.nights,adults:r.adults,childAges:[...r.ages],
      meal:r.meal,roomType:r.room,operator:r.operator,price:r.price,currency:r.currency,
      flight:r.flights.map(providerFlightText).filter(Boolean).join(' | '),delivery:'preview-disabled'});
  }
  function bindProviderPreview(receipt){
    const form=document.getElementById('prototype-lead-form');if(!form)return;
    draftRevision++;
    const phone=form.elements.phone,button=document.querySelector('[type="submit"][form="prototype-lead-form"]'),message=form.querySelector('.lead-message');
    for(const name of ['name','phone','comment'])form.elements[name].value=draft[name];
    let accepted;
    try{accepted=providerPreviewReceipt(receipt);}
    catch(error){message.textContent=error.message;message.setAttribute('role','alert');button.disabled=true;message.scrollIntoView({block:'nearest'});return;}
    form.addEventListener('input',()=>{
      draftRevision++;for(const name of ['name','phone','comment'])draft[name]=form.elements[name].value;
      root.V2LeadFormGuard.validatePhone(phone);message.textContent='';delete form.dataset.checked;
    });
    form.addEventListener('submit',event=>{
      event.preventDefault();if(form.dataset.checked==='1'||button.disabled)return;
      if(!root.V2LeadFormGuard.validatePhone(phone)||!form.reportValidity()){phone.reportValidity();return;}
      try{
        const payload=providerPreviewPayload(accepted,new FormData(form));
        if(!payload.consent)throw new Error('Подтвердите согласие на обработку персональных данных.');
        form.dataset.checked='1';message.setAttribute('role','status');
        message.textContent='Данные проверены. '+accepted.operator+' · '+accepted.hotel+' · '+new Intl.NumberFormat('ru-RU').format(accepted.price)+' ₽. Заявка не отправлена.';
        message.scrollIntoView({block:'nearest'});
      }catch(error){message.textContent=error.message;message.setAttribute('role','alert');}
    });
  }
  function reset(){draftRevision++;draft={name:'',phone:'',comment:''};}
  root.AnyTourPrototypeLead=Object.freeze({markup,action,bind,bindProviderPreview,providerPreviewPayload,reset});
})(window);
