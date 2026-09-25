'use strict';
// Local-only application rehearsal. Contact draft lives only in this open journey; never send or persist it.
(() => {
 const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const childAge=age=>age===0?'до года':`${age} ${age===1?'год':age<5?'года':'лет'}`;
 const displayDay=day=>new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',year:'numeric',timeZone:'UTC'}).format(new Date(day+'T12:00:00Z'));
 const workingSearch='https://anytoour.ru/_preview/search3-local-candidate/prototype-search/';
 // Search3 restores these public search fields without starting a search when searched=1 is absent.
 // Deliberately omit room, meal, operator and flight IDs: this is a hotel search, not an exact-offer deep link.
 function searchLink(o){
  if(!o||o.provider==='fixture')return null;
  const origin=o.origin||o.search?.origin,country=String(o.search?.country||''),day=o.day;
  const first=new Date(Date.now()+86400000).toISOString().slice(0,10);
  const last=new Date(Date.parse(first+'T12:00:00Z')+180*86400000).toISOString().slice(0,10);
  if(typeof origin!=='string'||!origin.trim()||!/^[1-9]\d*$/.test(country)||!Number.isSafeInteger(o.hotelId)||o.hotelId<1)return null;
  const time=Date.parse(day+'T12:00:00Z');
  if(!/^\d{4}-\d{2}-\d{2}$/.test(day||'')||day<first||day>last||!Number.isFinite(time)||new Date(time).toISOString().slice(0,10)!==day)return null;
  if(!Number.isInteger(o.nights)||o.nights<1||o.nights>28||!Number.isInteger(o.adults)||o.adults<1||o.adults>6)return null;
  if(!Array.isArray(o.ages)||o.ages.length>3||o.ages.some(age=>!Number.isInteger(age)||age<0||age>17))return null;
  const url=new URL(workingSearch);
  for(const [key,value] of Object.entries({origin,country,from:day,to:day,minNights:o.nights,maxNights:o.nights,adults:o.adults,ages:o.ages.join(','),hotel:o.hotelId}))url.searchParams.set(key,String(value));
  return url.href;
 }
 function unavailableMarkup(o){
  return `<section class="tour-recording-limit" aria-label="Доступность тура"><h3>Цена и рейсы пока не подтверждены</h3><p>У этого предложения на стенде нет проверки цены и рейсов. Перейти к заявке здесь нельзя.</p><details class="handoff-alternative"><summary>Новый подбор на AnyTour</summary><p>Откроется отдельный поиск с этим отелем и датами. Номер, питание и рейсы нужно выбрать заново.</p><a class="text-button" href="${escape(searchLink(o)||workingSearch)}" target="_blank" rel="noopener" aria-label="Открыть новый поиск AnyTour — откроется в новой вкладке">Открыть новый поиск ↗</a></details></section>`;
 }
 let contactDraft=null;
 const choiceKey=o=>JSON.stringify([o.key,o.room,o.meal,o.day,o.returnDay,o.adults,o.ages,o.total,o.flightChoiceId]);
 const eligible=o=>['fixture','recorded'].includes(o?.provider)&&!o.cached&&!!o.tour&&!o.loading&&!o.quoteError&&!o.flightsLoading&&!o.flightsError&&!o.pricePending&&Array.isArray(o.variants)&&o.variants.length>0;
 function receipt(o){
  if(!eligible(o))throw new Error('Сначала выберите тур и доступный перелёт.');
  const index=o.flightChoiceId===null?o.variants.findIndex(v=>v.isDefault):Number(o.flightChoiceId);
  const flight=Number.isInteger(index)&&index>=0?o.variants[index]:null;
  if(!flight||!Number.isFinite(Number(flight.price))||Number(flight.price)<=0||Number(flight.price)!==Number(o.total))throw new Error('Выбранный перелёт и цена не согласованы. Вернитесь к туру.');
  if(!flight.forward?.length||!flight.backward?.length)throw new Error('Нужны рейсы туда и обратно.');
  return {offerKey:o.key,room:o.room,meal:o.meal,day:o.day,nights:o.nights,adults:o.adults,ages:[...o.ages],flightChoice:index,total:Number(o.total),forward:flight.forward.map(x=>x.number).join(' / '),backward:flight.backward.map(x=>x.number).join(' / ')};
 }
 function markup(o){
  if(!eligible(o))return unavailableMarkup(o);
  return `<section class="tour-handoff" aria-label="Проверка заявки"><h3>Контактные данные</h3><p>Учебная заявка: используйте тестовый телефон. Ничего не отправляется и не сохраняется.</p>
   <form id="prototype-lead-form" autocomplete="off" novalidate>
    <label class="rehearsal-field">Телефон<input class="input" name="phone" type="tel" inputmode="tel" maxlength="40" required placeholder="+7 000 000-00-00" autocomplete="off" aria-describedby="rehearsal-phone-hint rehearsal-phone-error"></label>
    <small id="rehearsal-phone-hint">От 10 до 15 цифр, например +7 000 000-00-00.</small>
    <p id="rehearsal-phone-error" class="rehearsal-error" hidden></p>
    <label class="rehearsal-consent"><input name="consent" type="checkbox" required aria-describedby="rehearsal-consent-error"><span>Проверяю учебную заявку без отправки</span></label>
    <p id="rehearsal-consent-error" class="rehearsal-error" hidden></p>
    <details class="rehearsal-optional"><summary>Добавить имя и пожелания</summary>
    <label class="rehearsal-field">Имя (необязательно)<input class="input" name="name" maxlength="120" placeholder="Тестовый турист" autocomplete="off"></label>
    <label class="rehearsal-field">Комментарий (необязательно)<textarea class="input" name="comment" rows="3" maxlength="1000" placeholder="Тестовое пожелание"></textarea></label>
    </details>
    <p class="handoff-status" role="status" aria-live="polite"></p>
   </form>
  </section>`;
 }
 function bind(o){
  const form=document.getElementById('prototype-lead-form');if(!form)return;
  const phone=form.elements.phone,consent=form.elements.consent,status=form.querySelector('.handoff-status');
  if(contactDraft&&contactDraft.offerKey===o.key){
   for(const name of ['name','phone','comment'])form.elements[name].value=contactDraft[name];
   consent.checked=contactDraft.choice===choiceKey(o)&&contactDraft.consent;
   form.querySelector('.rehearsal-optional').open=contactDraft.optionalOpen;
  }else contactDraft=null;
  const capture=()=>{contactDraft={offerKey:o.key,choice:choiceKey(o),name:form.elements.name.value,phone:phone.value,comment:form.elements.comment.value,consent:consent.checked,optionalOpen:form.querySelector('.rehearsal-optional').open};};
  capture();
  form.querySelector('.rehearsal-optional').addEventListener('toggle',()=>{if(form.isConnected)capture();});
  let attempted=false;
  const fieldError=(field,id,message)=>{const error=form.querySelector('#'+id);error.textContent=message;error.hidden=!message;field.setAttribute('aria-invalid',String(!!message));};
  const validate=()=>{
   const digits=phone.value.replace(/\D/g,'');
   const phoneError=digits.length>=10&&digits.length<=15?'':'Введите телефон: от 10 до 15 цифр.';
   const consentError=consent.checked?'':'Подтвердите, что проверяете учебную заявку без отправки.';
   if(attempted){fieldError(phone,'rehearsal-phone-error',phoneError);fieldError(consent,'rehearsal-consent-error',consentError);}
   return phoneError?phone:consentError?consent:null;
  };
  form.addEventListener('input',()=>{capture();validate();status.textContent='';status.setAttribute('role','status');delete form.dataset.checked;});
  form.addEventListener('submit',event=>{
   event.preventDefault();capture();attempted=true;const invalid=validate();if(invalid){status.textContent='';delete form.dataset.checked;invalid.focus();return;}
   try{const r=receipt(o);form.dataset.checked='1';status.setAttribute('role','status');status.innerHTML=`<strong>Проверка пройдена</strong><span>${escape(displayDay(r.day))} · ${r.nights} ночей · ${r.adults} взр.${r.ages.length?' · дети: '+r.ages.map(childAge).join(', '):''}</span><span>${escape(r.room)} · ${escape(r.meal)}</span><span>Туда: ${escape(r.forward)}. Обратно: ${escape(r.backward)}.</span><strong>Итого: ${r.total.toLocaleString('ru-RU')} ₽</strong><span>Заявка не отправлена. Контакты не сохранены.</span>`;}
   catch(error){status.setAttribute('role','alert');status.textContent=error.message;}
   status.scrollIntoView({block:'nearest'});
  });
 }
 window.AnyTourPrototypeLead=Object.freeze({markup,bind,canApply:eligible,unavailableMarkup,
  action:o=>eligible(o)?'<button type="submit" form="prototype-lead-form" class="primary">Проверить заявку</button>':'<button type="button" class="primary" data-action="close-modal">К результатам</button>',
  reset(){contactDraft=null;const form=document.getElementById('prototype-lead-form');if(form)form.reset();}
 });
})();
