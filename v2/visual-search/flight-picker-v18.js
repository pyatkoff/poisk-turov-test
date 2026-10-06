'use strict';
// Pure, data-agnostic UI renderer. Variant indices always remain supplier indices.
(() => {
  const hasPlaceholder = segments => (segments || []).some(f => /000$/.test(String(f.number || '').replace(/\s/g, '')) && f.departure?.time === '00:00' && f.arrival?.time === '00:00');
  function allowanceValue(f, field) {
    const raw = f[field];
    if (raw == null || String(raw).trim() === '' || hasPlaceholder([f]) && Number(raw) === 0) return 'уточняется';
    if (field === 'carryOn') return String(raw) === '0' ? 'без ручной клади' : String(raw);
    const n = Number(raw);return !Number.isFinite(n) || n < 0 ? 'уточняется' : n === 0 ? 'без багажа' : n + ' кг';
  }
  function directionAllowance(segments, field) {
    const values = (segments || []).map(f => allowanceValue(f, field));
    return !values.length ? 'уточняется' : new Set(values).size === 1 ? values[0] : values.map((value, i) => `${i + 1}-й рейс — ${value}`).join('; ');
  }
  function pairAllowance(pair, field) {
    const forward = directionAllowance(pair.forward, field), backward = directionAllowance(pair.backward, field);
    return forward === backward ? forward : `туда: ${forward} · обратно: ${backward}`;
  }
  const carriersFor = (segments, helpers) => [...new Set((segments || []).map(f => helpers.text(f.company)).filter(Boolean))].join(' / ');
  function departureMinute(segments) {
    const time = segments?.[0]?.departure?.time;
    if (hasPlaceholder(segments) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(time || '')) return null;
    const [hours, minutes] = time.split(':').map(Number);return hours * 60 + minutes;
  }

  function selectionSummary(o, id) {
    const pair = id == null ? null : o.variants?.[Number(id)];if (!pair) return 'Перелёт пока не выбран';
    const time = segments => departureMinute(segments) === null ? 'время уточняется' : segments[0].departure.time;
    return `Вылет туда ${time(pair.forward)} · обратно ${time(pair.backward)}`;
  }

  function shortDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return value || 'Дата уточняется';
    const date = new Date(value + 'T12:00:00Z');
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('ru-RU', {day:'numeric',month:'short',timeZone:'UTC'}).replace('.', '');
  }
  function route(segments, label, helpers, showCarrier) {
    const {esc, text} = helpers;
    if (!segments?.length) return `<div class="flight-compact-leg"><span>${label}</span><strong>Расписание уточняется</strong></div>`;
    const first = segments[0], last = segments.at(-1), d = first.departure || {}, a = last.arrival || {};
    const placeholder = hasPlaceholder(segments);
    const stops = segments.length === 1 ? 'Без пересадок' : `${segments.length - 1} ${segments.length === 2 ? 'пересадка' : 'пересадки'}`;
    const connections = segments.slice(0,-1).map((segment, i) => {const arrival = text(segment.arrival?.port), departure = text(segments[i + 1].departure?.port);return arrival && departure && arrival === departure ? arrival : [arrival || 'Аэропорт прилёта уточняется', departure || 'Аэропорт вылета уточняется'].join(' / ');});
    return `<div class="flight-compact-leg"><div class="flight-leg-meta"><span class="flight-direction">${label}</span><span class="flight-route-date">${esc(shortDate(d.date))}${a.date && a.date !== d.date ? ' → ' + esc(shortDate(a.date)) : ''}</span><span class="flight-route-stops">${esc(stops)}</span></div><strong>${esc(placeholder ? 'Время уточняется' : (d.time || '—') + ' → ' + (a.time || '—'))}</strong><small class="flight-route-airports">${esc(text(d.port) || 'Аэропорт уточняется')} → ${esc(text(a.port) || 'Аэропорт уточняется')}</small>${connections.length?`<small class="flight-route-connection">${connections.length===1?'Пересадка':'Пересадки'}: ${esc(connections.join('; '))}</small>`:''}${showCarrier?`<small class="flight-route-carrier">${esc(carriersFor(segments, helpers) || 'Авиакомпания уточняется')}</small>`:''}</div>`;
  }
  // The picker and the selected summary share exactly the same route markup.
  function pairRoutes(pair, helpers) {
    const {esc} = helpers;
    const forwardCarriers=carriersFor(pair.forward,helpers),backwardCarriers=carriersFor(pair.backward,helpers);
    const sharedCarrier=forwardCarriers&&forwardCarriers===backwardCarriers;
    return `<div class="flight-compact-routes">${route(pair.forward,'Туда',helpers,!sharedCarrier)}${route(pair.backward,'Обратно',helpers,!sharedCarrier)}${sharedCarrier?`<small class="flight-pair-carriers">${esc(forwardCarriers)}</small>`:''}</div>`;
  }
  function pairSummary(pair, helpers) {
    const {esc} = helpers;
    return `<div class="chosen-flight-pair">${pairRoutes(pair, helpers)}<div class="chosen-flight-allowances"><span>Багаж: ${esc(pairAllowance(pair,'baggage'))}</span><span>Ручная кладь: ${esc(pairAllowance(pair,'carryOn'))}</span></div><p class="flight-demo-note">Время местное</p></div>`;
  }

  // Compatibility entrypoints become usable after the UI owner is loaded.
  function render(o,selected,helpers){return window.AnyTourFlightPickerUIV1.render(o,selected,helpers);}
  function bind(container,money=value=>value.toLocaleString('ru-RU')+' ₽'){return window.AnyTourFlightPickerUIV1.bind(container,money);}
  window.AnyTourFlightDisplayV1=Object.freeze({departureMinute,pairAllowance,pairRoutes});
  window.AnyTourFlightPickerV18 = Object.freeze({render,bind,pairSummary,selectionSummary,allowanceValue,directionAllowance});
})();
