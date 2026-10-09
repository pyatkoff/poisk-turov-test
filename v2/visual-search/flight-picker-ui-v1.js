'use strict';
// Cold picker presentation. The eager summary owns shared route/allowance facts.
(() => {
 const {departureMinute,pairAllowance,pairRoutes}=window.AnyTourFlightDisplayV1;
function departurePeriod(segments) {
    const minutes = departureMinute(segments);
    return minutes === null ? 'unknown' : minutes < 360 ? 'night' : minutes < 720 ? 'morning' : minutes < 1080 ? 'day' : 'evening';
  }
function priceDifference(total, selectedTotal, selected, money) {
    if (!(total > 0)) return 'Выбор после уточнения цены';
    if (selected) return 'Выбрано';
    if (!(selectedTotal > 0)) return '';
    const delta = total - selectedTotal;
    return delta === 0 ? 'Такая же цена' : `На ${money(Math.abs(delta))} ${delta > 0 ? 'дороже' : 'дешевле'} выбранного`;
  }
function timeFilters() {
    return `<details class="flight-time-filters"><summary>Время вылета и сортировка <span data-flight-time-count></span></summary><div class="flight-time-fields">${[['forward','Туда'],['backward','Обратно']].map(([direction,label])=>`<label>${label}<select data-flight-time="${direction}"><option value="">Любое время</option><option value="night">Ночью · 00:00–05:59</option><option value="morning">Утром · 06:00–11:59</option><option value="day">Днём · 12:00–17:59</option><option value="evening">Вечером · 18:00–23:59</option><option value="unknown">Время уточняется</option></select></label>`).join('')}</div><label class="flight-sort-label">Порядок<select data-flight-sort><option value="price">Сначала дешевле</option><option value="original">Исходный порядок</option></select></label><p>Время местное. Для перелёта с пересадкой учитывается вылет первого рейса.</p></details>`;
  }
function render(o, selected, helpers) {
    const {esc, money, price, legHTML, fuelText} = helpers;
    const selectedVariant = selected == null ? null : o.variants?.[Number(selected)];
    const selectedTotal = selectedVariant ? price(o.tour, selectedVariant) : null;
    return `<details class="flight-filter-panel"><summary>Фильтры и сортировка <span data-flight-filter-count></span></summary><div class="flight-filter-panel-body"><div class="flight-search-row"><label><span class="sr-only">Найти авиакомпанию, рейс или аэропорт</span><input type="search" data-flight-query placeholder="Авиакомпания, рейс или аэропорт" autocomplete="off"></label></div><div class="flight-picker-tools"><div class="flight-quick-filters"><label><input type="checkbox" data-flight-filter="direct"> Без пересадок</label><label><input type="checkbox" data-flight-filter="baggage"> С багажом</label></div></div>${timeFilters()}<button type="button" class="primary flight-filter-apply" data-flight-show-results>Показать варианты</button></div></details><div class="flight-result-summary"><p class="flight-filter-state" aria-live="polite"></p><button type="button" class="text-button" data-flight-selected>К выбранному</button></div><fieldset class="flight-options flight-options-compact"><legend class="sr-only">Пары рейсов туда и обратно. Цена всего тура за всех туристов.</legend>${o.variants.map((v, i) => {
      const total = price(o.tour, v);
      const bags = [...(v.forward || []), ...(v.backward || [])].map(f => f.baggage);
      const bag = 'Багаж: ' + pairAllowance(v, 'baggage'), carryOn = 'Ручная кладь: ' + pairAllowance(v, 'carryOn');
      return `<article class="flight-option flight-option-compact" data-flight-index="${i}" data-flight-forward-time="${departurePeriod(v.forward)}" data-flight-backward-time="${departurePeriod(v.backward)}" data-flight-search="${esc([...(v.forward||[]),...(v.backward||[])].map(f=>[helpers.text(f.company),f.number,helpers.text(f.departure?.port),helpers.text(f.arrival?.port)].filter(Boolean).join(' ')).join(' '))}" data-flight-price="${total || ''}" data-flight-direct="${v.forward?.length === 1 && v.backward?.length === 1}" data-flight-baggage="${!!v.forward?.length && !!v.backward?.length && bags.every(n => Number(n) > 0)}"><label class="flight-choice-row"><input type="radio" name="flight-pair" value="${i}" ${String(i) === String(selected) ? 'checked' : ''}><span class="sr-only">Вариант ${i + 1}.</span>${pairRoutes(v, helpers)}<div class="flight-price"><strong>${total ? money(total) : 'Цена уточняется'}</strong><small>весь тур за всех</small><em data-flight-difference>${esc(priceDifference(total, selectedTotal, String(i) === String(selected), money))}</em></div></label><details><summary aria-label="Подробнее о варианте ${i + 1}: ${esc(bag)}. ${esc(carryOn)}"><span class="flight-allowances"><span>${esc(bag)}</span><span>${esc(carryOn)}</span></span><span class="flight-details-label">Подробнее</span></summary><div class="flight-expanded">${legHTML(v.forward, 'Туда') + legHTML(v.backward, 'Обратно')}<p>Топливный сбор: ${fuelText({...o, flightChoiceId: String(i)})}</p></div></details></article>`;
    }).join('')}</fieldset><button type="button" class="secondary flight-load-more" data-flight-load-more hidden></button>`;
  }
function bind(container, money = value => value.toLocaleString('ru-RU') + ' ₽', restored = null, changed = null) {
    const list=container.querySelector('.flight-options'),rows=[...list.querySelectorAll('.flight-option')];
    container.querySelector('.flight-filter-panel').open=false;
    const status=container.querySelector('.flight-filter-state'),query=container.querySelector('[data-flight-query]'),selectedButton=container.querySelector('[data-flight-selected]'),loadMore=container.querySelector('[data-flight-load-more]');
    const pageSize=matchMedia('(max-width:760px)').matches?4:6;let visibleLimit=Number.isSafeInteger(restored?.limit)?Math.max(pageSize,Math.min(rows.length,restored.limit)):pageSize;
    if(restored){
      query.value=typeof restored.query==='string'?restored.query.slice(0,1024):'';
      for(const name of ['direct','baggage'])container.querySelector('[data-flight-filter="'+name+'"]').checked=restored[name]===true;
      for(const name of ['forward','backward']){const control=container.querySelector('[data-flight-time="'+name+'"]');control.value=[...control.options].some(option=>option.value===restored[name])?restored[name]:'';}
      container.querySelector('[data-flight-sort]').value=restored.sort==='original'?'original':'price';
      container.querySelector('.flight-filter-panel').open=restored.panel===true;
      container.querySelector('.flight-time-filters').open=restored.timePanel===true;
      for(const row of rows)row.querySelector('details').open=Array.isArray(restored.expanded)&&restored.expanded.includes(Number(row.dataset.flightIndex));
    }
    const normalize=value=>String(value).toLocaleLowerCase('ru').replace(/ё/g,'е').replace(/\s+/g,' ').trim();
    const resetFilters=()=>{container.querySelectorAll('[data-flight-filter]').forEach(x=>x.checked=false);container.querySelectorAll('[data-flight-time]').forEach(x=>x.value='');query.value='';};
    const orderKeys=rows.map(row=>({row}));let ordered=[],lastSort;
    function orderRows(sort){
      let changed=sort!==lastSort;
      for(const key of orderKeys){
        const index=key.row.dataset.flightIndex,price=sort==='price'?key.row.dataset.flightPrice:null;
        if(index!==key.index||price!==key.price){key.index=index;key.price=price;changed=true;}
      }
      if(changed){
        // Start from the bind inventory to retain stable ties after a key change.
        ordered=[...orderKeys].sort((a,b)=>sort==='price'?((Number(a.price)||Infinity)-(Number(b.price)||Infinity))||Number(a.index)-Number(b.index):Number(a.index)-Number(b.index)).map(key=>key.row);lastSort=sort;
      }
      return ordered;
    }
    function update(resetLimit=false){
      if(resetLimit)visibleLimit=pageSize;
      const direct=container.querySelector('[data-flight-filter="direct"]').checked,baggage=container.querySelector('[data-flight-filter="baggage"]').checked;
      const sort=container.querySelector('[data-flight-sort]').value,terms=normalize(query.value).split(' ').filter(Boolean);
      const forward=container.querySelector('[data-flight-time=forward]').value,backward=container.querySelector('[data-flight-time=backward]').value;
      const timeCount=Number(!!forward)+Number(!!backward);container.querySelector('[data-flight-time-count]').textContent=timeCount?'· '+timeCount+(timeCount===1?' условие':' условия'):'';
      const filterCount=Number(direct)+Number(baggage)+Number(!!terms.length)+timeCount;
      const filterBadge=container.querySelector('[data-flight-filter-count]');filterBadge.textContent=filterCount?'· '+filterCount:'';filterBadge.setAttribute('aria-label',filterCount?'Активных фильтров: '+filterCount:'');
      const ordered=orderRows(sort);
      const matching=[];let hiddenSelection=false,selectedBeyondLimit=false;
      for(const row of ordered){const search=terms.length?normalize(row.dataset.flightSearch):'';const match=terms.every(term=>search.includes(term))&&(!direct||row.dataset.flightDirect==='true')&&(!baggage||row.dataset.flightBaggage==='true')&&(!forward||row.dataset.flightForwardTime===forward)&&(!backward||row.dataset.flightBackwardTime===backward);row.dataset.flightMatch=String(match);if(match)matching.push(row);else if(row.querySelector('input:checked'))hiddenSelection=true;}
      // Inspect actual child nodes, including text and unowned nodes, before reuse.
      const nodes=list.childNodes,offset=nodes.length-ordered.length;
      if(ordered.some((row,index)=>nodes[offset+index]!==row))ordered.forEach(row=>list.append(row));
      matching.forEach((row,index)=>{row.hidden=index>=visibleLimit;if(row.hidden&&row.querySelector('input:checked'))selectedBeyondLimit=true;});
      ordered.filter(row=>row.dataset.flightMatch!=='true').forEach(row=>row.hidden=true);
      const shown=Math.min(visibleLimit,matching.length),remaining=Math.max(0,matching.length-shown);
      container.querySelector('[data-flight-show-results]').textContent='Показать варианты · '+matching.length;
      status.replaceChildren(document.createTextNode(matching.length?`Показано ${shown} из ${matching.length}`:'Нет вариантов с такими условиями'));
      if(hiddenSelection)status.append(document.createTextNode(' · Выбранный перелёт скрыт фильтрами.'));
      else if(selectedBeyondLimit)status.append(document.createTextNode(' · Выбранный перелёт ниже в списке.'));
      loadMore.hidden=!remaining;loadMore.textContent=remaining?`Показать ещё ${Math.min(pageSize,remaining)} · останется ${Math.max(0,remaining-pageSize)}`:'';
      const selectedRow=list.querySelector('input:checked')?.closest('.flight-option');
      const selectedTotal=Number(selectedRow?.dataset.flightPrice);
      rows.forEach(row=>{row.querySelector('[data-flight-difference]').textContent=priceDifference(Number(row.dataset.flightPrice),selectedTotal,row===selectedRow,money);});
      const firstVisible=matching.find(row=>!row.hidden);
      selectedButton.hidden=!selectedRow||(!hiddenSelection&&!selectedBeyondLimit&&selectedRow===firstVisible);
      selectedButton.textContent=hiddenSelection?'Сбросить фильтры и показать выбранный':'К выбранному';
      if(selectedBeyondLimit)selectedButton.textContent='Показать выбранный';
      if(direct||baggage||terms.length||timeCount){const reset=document.createElement('button');reset.type='button';reset.className='text-button';reset.textContent='Сбросить';reset.onclick=()=>{const focused=document.activeElement===reset;resetFilters();update(true);if(focused)container.querySelector('.flight-filter-panel>summary').focus({preventScroll:true});};status.append(reset);}
      if(changed)queueMicrotask(changed);
    }
    query.addEventListener('input',()=>update(true));
    container.querySelector('[data-flight-show-results]').addEventListener('click',()=>{const panel=container.querySelector('.flight-filter-panel');panel.open=false;panel.querySelector('summary').focus({preventScroll:true});container.scrollTop=0;});
    loadMore.addEventListener('click',()=>{visibleLimit+=pageSize;update();const firstNew=[...list.querySelectorAll('.flight-option:not([hidden])')][visibleLimit-pageSize];firstNew?.querySelector('input')?.focus({preventScroll:true});firstNew?.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'nearest'});});
    selectedButton.addEventListener('click',()=>{const chosen=list.querySelector('input:checked');if(!chosen)return;const row=chosen.closest('.flight-option');if(row.dataset.flightMatch!=='true'){resetFilters();visibleLimit=pageSize;update();}if(row.hidden){const position=[...list.querySelectorAll('.flight-option[data-flight-match="true"]')].indexOf(row);visibleLimit=Math.max(visibleLimit,position+1);update();}chosen.focus({preventScroll:true});row.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'nearest'});});
    container.addEventListener('change',event=>{if(event.target.name==='flight-pair'){const focused=event.target;update();focused.focus({preventScroll:true});}});
    container.querySelectorAll('[data-flight-filter],[data-flight-sort],[data-flight-time]').forEach(control=>control.addEventListener('change',()=>update(true)));
    if(changed)container.addEventListener('toggle',changed,true);update();
    return Object.freeze({snapshot:()=>({query:query.value,direct:container.querySelector('[data-flight-filter="direct"]').checked,baggage:container.querySelector('[data-flight-filter="baggage"]').checked,forward:container.querySelector('[data-flight-time="forward"]').value,backward:container.querySelector('[data-flight-time="backward"]').value,sort:container.querySelector('[data-flight-sort]').value,panel:container.querySelector('.flight-filter-panel').open,timePanel:container.querySelector('.flight-time-filters').open,limit:visibleLimit,expanded:rows.filter(row=>row.querySelector('details').open).map(row=>Number(row.dataset.flightIndex))})});
  }
 window.AnyTourFlightPickerUIV1=Object.freeze({render,bind});
})();

