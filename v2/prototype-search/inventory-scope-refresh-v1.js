'use strict';
(() => {
  if (window.AnyTourPrototypeInventoryScopeRefreshV1) return;

  const form = document.getElementById('search-form');
  const data = window.AnyTourPrototypeData;
  if (!form || typeof form.requestSubmit !== 'function'
    || !data || typeof data.supplierScope !== 'function' || typeof data.supplierScopeCovered !== 'function') return;

  const values = (params, key) => [...new Set((params.get(key) || '').split('|').filter(Boolean))].sort();
  const amount = (params, key, fallback) => {
    if (!params.has(key) || params.get(key) === '') return fallback;
    const value = Number(params.get(key));
    return Number.isFinite(value) && value >= 0 ? value : NaN;
  };
  const scope = () => {
    const params = new URLSearchParams(location.search);
    if (params.get('searched') !== '1') return null;
    const min = amount(params, 'min', 0), max = amount(params, 'max', null);
    if (!Number.isFinite(min) || max !== null && !Number.isFinite(max)) return null;
    const hotel = params.get('hotel') || '', hotelId = /^[1-9]\d*$/.test(hotel) && Number.isSafeInteger(Number(hotel)) ? Number(hotel) : 0;
    const filters = {
      hotelId,
      resorts: values(params, 'resorts'),
      stars: values(params, 'stars').map(Number).filter(value => [3,4,5].includes(value)),
      meals: values(params, 'meals'),
      min,
      max
    };
    try { return data.supplierScope(filters); } catch { return null; }
  };

  let scheduled = false;
  const inspect = () => {
    scheduled = false;
    if (form.hidden !== true) return;
    const previous = data.currentSupplierScope, next = scope();
    if (!previous || !next || data.supplierScopeCovered(previous, next)) return;
    const submit = document.querySelector('.search-submit');
    if (submit?.disabled) return;
    form.requestSubmit();
  };
  const scheduleInspect = () => {
    // Result-side filters are edited while the main search form is collapsed.
    // The actual previous supplier scope comes from data.search(); do not keep
    // a second URL-derived lifecycle state here.
    if (form.hidden !== true || scheduled) return;
    scheduled = true;
    queueMicrotask(inspect);
  };

  document.addEventListener('click', scheduleInspect);
  document.addEventListener('change', scheduleInspect);

  window.AnyTourPrototypeInventoryScopeRefreshV1 = true;
})();
