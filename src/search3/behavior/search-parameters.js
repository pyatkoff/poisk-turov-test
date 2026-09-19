/* Mobile drafts project the canonical form. Only the existing lifecycle submits. */
(function(){'use strict';
const form=document.getElementById('tourSearch');
if(!form||!document.body.classList.contains('search3-candidate')||typeof HTMLDialogElement==='undefined'||!HTMLDialogElement.prototype.showModal)return;
const triggers=Array.from(form.querySelectorAll('[data-search3-parameter]'));
if(triggers.length!==3)return;
const media=window.matchMedia('(max-width:700px)');
const dialog=document.createElement('dialog');dialog.className='search-parameter-dialog';dialog.setAttribute('aria-labelledby','searchParameterTitle');
dialog.innerHTML='<header><h2 id="searchParameterTitle"></h2><button type="button" data-close aria-label="Закрыть без изменений">×</button></header><div class="search-parameter-body"></div><footer><p class="search-parameter-error" role="alert" hidden></p><button type="button" class="search-parameter-apply">Выбрать</button><button type="button" data-close>Отмена</button></footer>';
document.body.appendChild(dialog);
const body=dialog.querySelector('.search-parameter-body'),title=dialog.querySelector('h2'),apply=dialog.querySelector('.search-parameter-apply'),error=dialog.querySelector('[role=alert]');
let active='',draft=null,opener=null,month=null,rangeEnd=false,frame=0;
const field=name=>form.elements[name],value=name=>String(field(name)?.value||''),ages=()=>Array.from(form.querySelectorAll('[name="child_age[]"]')).map(node=>node.value);
const day=value=>/^\d{4}-\d{2}-\d{2}$/.test(value)?new Date(value+'T12:00:00'):new Date(NaN);
const iso=date=>date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');
const today=()=>iso(new Date()),shift=(value,days)=>{const d=day(value);d.setDate(d.getDate()+days);return iso(d)};
const dateText=value=>Number.isFinite(day(value).getTime())?day(value).toLocaleDateString('ru-RU',{day:'numeric',month:'short'}):'Укажите дату';
const span=(a,b)=>Math.round((day(b)-day(a))/86400000);
const word=(n,one,few,many)=>n%100>=11&&n%100<=14?many:n%10===1?one:n%10>=2&&n%10<=4?few:many;
const nightsText=(from,to)=>(from===to?from:from+'–'+to)+' '+word(Number(to),'ночь','ночи','ночей');
function setText(node,text){if(node.textContent!==text)node.textContent=text}
function summaries(){
 const texts={dates:dateText(value('dateFrom'))+(value('dateTo')!==value('dateFrom')?' — '+dateText(value('dateTo')):''),nights:nightsText(value('daysFrom'),value('daysTill')),party:value('count_people')+' '+word(Number(value('count_people')),'взрослый','взрослых','взрослых')};
 const count=Number(value('child_count'));if(count)texts.party+=' · '+count+' '+word(count,'ребёнок','ребёнка','детей');
 triggers.forEach(button=>{button.hidden=!media.matches;setText(button.querySelector('strong'),texts[button.dataset.search3Parameter]);const small=button.querySelector('small');setText(small,button.dataset.search3Parameter==='party'&&count?'Возраст: '+ages().map(age=>age+' '+word(Number(age),'год','года','лет')).join(', '):button.dataset.search3Parameter==='dates'?'Можно выбрать диапазон':'Изменить');});
 form.dataset.search3Parameters=media.matches?'mobile':'native';
}
function schedule(){if(!frame)frame=requestAnimationFrame(()=>{frame=0;summaries()})}
function showError(message){error.textContent=message;error.hidden=!message}
function close(){if(dialog.open)dialog.close();draft=null;active='';showError('');opener?.focus({preventScroll:true})}
function valid(){
 if(active==='dates')return !draft.from||!draft.to||!Number.isFinite(span(draft.from,draft.to))?'Укажите обе даты вылета.':draft.from<today()?'Дата вылета не может быть в прошлом.':draft.to<draft.from?'Последняя дата не может быть раньше первой.':span(draft.from,draft.to)>21?'Выберите диапазон не больше 21 дня.':'';
 if(active==='nights')return ![draft.from,draft.to].every(n=>Number.isInteger(n)&&n>=1&&n<=28)?'Выберите от 1 до 28 ночей.':draft.to<draft.from?'Последнее число ночей не может быть меньше первого.':draft.to-draft.from>10?'Выберите диапазон не больше 10 ночей.':'';
 if(active==='party')return draft.ages.some(age=>age===''||!Number.isInteger(Number(age))||Number(age)<0||Number(age)>17)?'Укажите возраст каждого ребёнка на момент окончания поездки.':'';
 return '';
}
function calendar(){
 const grid=body.querySelector('[data-calendar]');if(!grid)return;grid.replaceChildren();
 setText(body.querySelector('[data-month]'),month.toLocaleDateString('ru-RU',{month:'long',year:'numeric'}));
 body.querySelector('[data-prev]').disabled=month.getFullYear()*12+month.getMonth()<=new Date().getFullYear()*12+new Date().getMonth();
 const gap=(month.getDay()+6)%7,total=new Date(month.getFullYear(),month.getMonth()+1,0).getDate();
 for(let i=0;i<gap;i++)grid.appendChild(document.createElement('span'));
 for(let n=1;n<=total;n++){
  const date=new Date(month.getFullYear(),month.getMonth(),n,12),key=iso(date),button=document.createElement('button');button.type='button';button.dataset.day=key;button.textContent=String(n);button.disabled=key<today();button.setAttribute('aria-label',date.toLocaleDateString('ru-RU',{day:'numeric',month:'long',year:'numeric'}));
  const edge=key===draft.from||key===draft.to;button.setAttribute('aria-pressed',String(key>=draft.from&&key<=draft.to));if(edge)button.className='is-edge';grid.appendChild(button);
 }
 setText(body.querySelector('[data-range-hint]'),rangeEnd?'Теперь выберите последнюю дату вылета.':'Выберите день вылета или начало диапазона.');
 setText(apply,'Выбрать '+dateText(draft.from)+(draft.from!==draft.to?' — '+dateText(draft.to):''));
}
function syncDates(){body.querySelector('[data-from]').value=draft.from;body.querySelector('[data-to]').value=draft.to;calendar();showError('')}
function datesPanel(){
 body.innerHTML='<p>Ищем туры с вылетом в выбранные даты. Дату возвращения определяет число ночей.</p><div class="search-parameter-range"><label>Вылет с<input type="date" data-from></label><label>Вылет до<input type="date" data-to></label></div><div class="search-parameter-month"><button type="button" data-prev aria-label="Предыдущий месяц">‹</button><strong data-month></strong><button type="button" data-next aria-label="Следующий месяц">›</button></div><div class="search-parameter-week" aria-hidden="true"><span>пн</span><span>вт</span><span>ср</span><span>чт</span><span>пт</span><span>сб</span><span>вс</span></div><div class="search-parameter-calendar" data-calendar role="group" aria-label="Дни вылета"></div><p data-range-hint aria-live="polite"></p><div class="search-parameter-presets" role="group" aria-label="Гибкие даты"><button type="button" data-flex="0">Один день</button><button type="button" data-flex="1">±1 день</button><button type="button" data-flex="3">±3 дня</button></div>';
 for(const input of body.querySelectorAll('input'))input.min=today();syncDates();
}
function nightsPanel(){
 body.innerHTML='<p>Выберите одно число или диапазон ночей.</p><div class="search-parameter-presets" role="group" aria-label="Популярная длительность"><button type="button" data-nights="3,5">3–5 ночей</button><button type="button" data-nights="7,10">7–10 ночей</button><button type="button" data-nights="10,14">10–14 ночей</button></div><div class="search-parameter-calendar" data-nights-grid role="group" aria-label="Количество ночей"></div><p data-range-hint aria-live="polite"></p>';
 const grid=body.querySelector('[data-nights-grid]');for(let n=1;n<=28;n++){const button=document.createElement('button');button.type='button';button.dataset.night=String(n);button.textContent=String(n);button.setAttribute('aria-label',nightsText(String(n),String(n)));grid.appendChild(button)}syncNights();
}
function syncNights(){
 body.querySelectorAll('[data-night]').forEach(button=>{const n=Number(button.dataset.night);button.setAttribute('aria-pressed',String(n>=draft.from&&n<=draft.to));button.classList.toggle('is-edge',n===draft.from||n===draft.to)});
 setText(apply,'Выбрать '+nightsText(String(draft.from),String(draft.to)));setText(body.querySelector('[data-range-hint]'),rangeEnd?'Выберите последнее число ночей или подтвердите одно.':'Можно выбрать от 1 до 28 ночей.');showError('');
}
function partyPanel(){
 body.innerHTML='<div class="search-parameter-adults"><div><strong>Взрослые</strong></div><div class="search-parameter-stepper"><button type="button" data-adults="-1" aria-label="Уменьшить число взрослых">−</button><output aria-label="Число взрослых"></output><button type="button" data-adults="1" aria-label="Увеличить число взрослых">+</button></div></div><h3>Дети</h3><p>Укажите возраст каждого ребёнка на момент окончания поездки.</p><div data-children></div><button type="button" class="search-parameter-add" data-add>Добавить ребёнка +</button>';
 syncParty();
}
function syncParty(){
 setText(body.querySelector('output'),String(draft.adults));body.querySelector('[data-adults="-1"]').disabled=draft.adults<=1;body.querySelector('[data-adults="1"]').disabled=draft.adults>=6;
 const children=body.querySelector('[data-children]');children.replaceChildren();draft.ages.forEach((age,index)=>{
  const row=document.createElement('div');row.className='search-parameter-child';const label=document.createElement('label');label.textContent='Возраст ребёнка '+(index+1);const select=document.createElement('select');select.dataset.age=String(index);select.add(new Option('Выберите возраст',''));for(let n=0;n<=17;n++)select.add(new Option(n+' '+word(n,'год','года','лет'),String(n)));select.value=age;label.appendChild(select);row.appendChild(label);
  const remove=document.createElement('button');remove.type='button';remove.dataset.remove=String(index);remove.setAttribute('aria-label','Убрать ребёнка '+(index+1));remove.textContent='×';row.appendChild(remove);children.appendChild(row);
 });body.querySelector('[data-add]').disabled=draft.ages.length>=3;setText(apply,'Выбрать');showError('');
}
function open(kind,trigger){
 if(!media.matches)return;active=kind;opener=trigger;rangeEnd=false;showError('');
 if(kind==='dates'){draft={from:value('dateFrom'),to:value('dateTo'),center:value('dateFrom')};const initial=day(draft.from);month=Number.isFinite(initial.getTime())?initial:new Date();month=new Date(month.getFullYear(),month.getMonth(),1,12);title.textContent='Даты вылета';datesPanel()}
 else if(kind==='nights'){draft={from:Number(value('daysFrom'))||7,to:Number(value('daysTill'))||10};title.textContent='На сколько ночей';nightsPanel()}
 else{draft={adults:Math.max(1,Math.min(6,Number(value('count_people'))||2)),ages:ages().slice(0,3)};title.textContent='Кто едет';partyPanel()}
 dialog.showModal();dialog.querySelector('[data-close]').focus({preventScroll:true});
}
function commit(){
 const issue=valid();if(issue){showError(issue);if(active==='party')body.querySelector('select[data-age]:has(option:checked[value=""])')?.focus();return}
 const set=(name,next)=>{const node=field(name);if(node&&node.value!==String(next)){node.value=String(next);node.dispatchEvent(new Event('change',{bubbles:true}))}};
 if(active==='dates'){set('dateFrom',draft.from);set('dateTo',draft.to)}
 else if(active==='nights'){set('daysFrom',draft.from);set('daysTill',draft.to)}
 else{set('count_people',draft.adults);set('child_count',draft.ages.length);Array.from(form.querySelectorAll('[name="child_age[]"]')).forEach((node,i)=>{if(node.value!==draft.ages[i]){node.value=draft.ages[i];node.dispatchEvent(new Event('change',{bubbles:true}))}})}
 summaries();close();
}
triggers.forEach(button=>button.addEventListener('click',()=>open(button.dataset.search3Parameter,button)));
dialog.addEventListener('cancel',event=>{event.preventDefault();close()});
dialog.addEventListener('click',event=>{
 if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)close();return}
 const button=event.target.closest('button');if(!button)return;
 if(button.hasAttribute('data-close')){close();return}if(button===apply){commit();return}
 if(active==='dates'){
  if(button.hasAttribute('data-prev')||button.hasAttribute('data-next')){month.setMonth(month.getMonth()+(button.hasAttribute('data-prev')?-1:1));calendar();return}
  if(button.dataset.day){const selected=button.dataset.day;if(rangeEnd&&selected>=draft.from){if(span(draft.from,selected)>21){showError('Выберите диапазон не больше 21 дня.');return}draft.to=selected;rangeEnd=false}else{draft.from=selected;draft.to=selected;draft.center=selected;rangeEnd=true}syncDates();body.querySelector('[data-day="'+selected+'"]').focus({preventScroll:true})}
  if(button.hasAttribute('data-flex')){const flex=Number(button.dataset.flex),center=draft.center||today();draft.from=shift(center,-flex)<today()?today():shift(center,-flex);draft.to=shift(center,flex);rangeEnd=false;syncDates()}
 }else if(active==='nights'){
  if(button.dataset.nights){[draft.from,draft.to]=button.dataset.nights.split(',').map(Number);rangeEnd=false;syncNights()}
  if(button.dataset.night){const n=Number(button.dataset.night);if(rangeEnd&&n>=draft.from){if(n-draft.from>10){showError('Выберите диапазон не больше 10 ночей.');return}draft.to=n;rangeEnd=false}else{draft.from=n;draft.to=n;rangeEnd=true}syncNights()}
 }else{
  if(button.dataset.adults){draft.adults=Math.max(1,Math.min(6,draft.adults+Number(button.dataset.adults)));syncParty()}
  if(button.hasAttribute('data-add')&&draft.ages.length<3){draft.ages.push('');syncParty();body.querySelector('[data-age="'+(draft.ages.length-1)+'"]').focus()}
  if(button.hasAttribute('data-remove')){draft.ages.splice(Number(button.dataset.remove),1);syncParty();body.querySelector('[data-add]').focus()}
 }
});
dialog.addEventListener('change',event=>{if(!draft)return;const input=event.target;if(active==='dates'&&input.matches('input')){draft[input.hasAttribute('data-from')?'from':'to']=input.value;if(input.hasAttribute('data-from')){draft.center=input.value;const selected=day(input.value);if(Number.isFinite(selected.getTime()))month=new Date(selected.getFullYear(),selected.getMonth(),1,12)}rangeEnd=false;calendar();showError('')}if(active==='party'&&input.hasAttribute('data-age')){draft.ages[Number(input.dataset.age)]=input.value;showError('')}});
form.addEventListener('change',schedule);form.addEventListener('input',schedule);
new MutationObserver(schedule).observe(form,{subtree:true,childList:true});
media.addEventListener('change',()=>{const kind=active;if(dialog.open)close();summaries();if(kind&&!media.matches)field(kind==='dates'?'dateFrom':kind==='nights'?'daysFrom':'count_people')?.focus({preventScroll:true})});
window.addEventListener('v2:search-error',event=>{const name=event.detail?.phase==='validation'&&event.detail.error?.field;const kind=['dateFrom','dateTo'].includes(name)?'dates':['daysFrom','daysTill'].includes(name)?'nights':['count_people','child_count','child_age[]'].includes(name)?'party':'';if(kind&&media.matches){open(kind,triggers.find(button=>button.dataset.search3Parameter===kind));showError(event.detail.error.message)}});
['v2:search-reset','v2:search-resumed','v2:search-started'].forEach(name=>window.addEventListener(name,()=>{if(dialog.open)close();schedule()}));
summaries();
})();
