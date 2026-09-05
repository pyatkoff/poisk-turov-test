/* Shared presentation of supplier flight details. Match tour-controller-v4's
   placeholder semantics without rewriting flight data or lead fields. */
(function () {
  'use strict';
  function text(value) {
    if (value == null) return '';
    if (typeof value !== 'object') return String(value).trim();
    for (const key of ['russianName', 'fullRussianName', 'name', 'title', 'value', 'text']) {
      const label = text(value[key]);
      if (label) return label;
    }
    return '';
  }
  function placeholder(segment) {
    if (!segment || typeof segment !== 'object') return false;
    const number = String(segment.number || '').replace(/\s+/g, '').toUpperCase();
    return /000$/.test(number)
      && String((segment.departure || {}).time || '') === '00:00'
      && String((segment.arrival || {}).time || '') === '00:00';
  }
  function segments(variant) {
    return variant ? [].concat(Array.isArray(variant.forward) ? variant.forward : [], Array.isArray(variant.backward) ? variant.backward : []).filter(Boolean) : [];
  }
  function flightLabel(variant, emptyLabel) {
    if (!variant) return emptyLabel || 'Рейс уточняется';
    const labels = segments(variant).map(segment => [text(segment.company), placeholder(segment) ? 'рейс уточняется' : text(segment.number)].filter(Boolean).join(' '));
    return Array.from(new Set(labels.filter(Boolean))).join(' · ') || 'Рейс уточняется';
  }
  function baggage(variant) {
    return Array.from(new Set(segments(variant).map(segment => {
      const raw = segment.baggage;
      const missing = raw === null || raw === undefined || raw === '';
      const zero = !missing && Number(raw) === 0;
      const bag = missing || (placeholder(segment) && zero) ? 'багаж уточняется' : zero ? 'без багажа' : 'багаж ' + text(raw) + ' кг';
      const carry = text(segment.carryOn);
      return bag + ' · ручная кладь ' + (carry && carry !== '0' ? carry : 'уточняется');
    }))).join('; ');
  }
  window.Search3FlightPresentation = Object.freeze({ placeholder, flightLabel, baggage });
})();


