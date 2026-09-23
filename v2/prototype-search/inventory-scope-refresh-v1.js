'use strict';
(() => {
  if (window.AnyTourPrototypeInventoryScopeRefreshV1) return;

  const form = document.getElementById('search-form');
  if (!form || typeof form.requestSubmit !== 'function') return;

  const values = (params, key) => [...new Set((params.get(key) || '').split('|').filter(Boolean))].sort();
  const amount = (params, key, fallback) => {
    if (!params.has(key) || params.get(key) === '') return fallback;
    const value = Number(params.get(key));
    return Number.isFinite(value) && value >= 0 ? value : NaN;
  };
  const scope = () => {
    const params = new URLSearchParams(location.search);
    return {
      searched: params.get('searched') === '1',
      hotel: params.get('hotel') || '',
      resorts: values(params, 'resorts'),
      stars: values(params, 'stars'),
      meals: values(params, 'meals'),
      min: amount(params, 'min', 0),
      max: amount(params, 'max', null)
    };
  };
  const contains = (superset, subset) => subset.every(value => superset.includes(value));
  const covered = (previous, next) => {
    if (!previous || !next || !Number.isFinite(previous.min) || !Number.isFinite(next.min)) return false;
    if (previous.max !== null && !Number.isFinite(previous.max)) return false;
    if (next.max !== null && !Number.isFinite(next.max)) return false;

    // Empty hotel/region scope means the upstream request was broad. Otherwise
    // only the same hotel or a subset of already requested OR-regions is safe.
    if (previous.hotel && previous.hotel !== next.hotel) return false;
    if (previous.resorts.length && (!next.resorts.length || !contains(previous.resorts, next.resorts))) return false;

    // Search3 currently sends a supplier category/meal only for a single
    // selected value. Zero or multiple values are broad upstream requests.
    // Keep single-value coverage conservative: changing it requires a search.
    if (previous.stars.length === 1 && (next.stars.length !== 1 || next.stars[0] !== previous.stars[0])) return false;
    if (previous.meals.length === 1 && (next.meals.length !== 1 || next.meals[0] !== previous.meals[0])) return false;

    // A previously fetched budget range may satisfy a narrower range locally,
    // but removing/raising its ceiling or lowering its floor needs new offers.
    if (next.min < previous.min) return false;
    if (previous.max !== null && (next.max === null || next.max > previous.max)) return false;
    return true;
  };

  let searchedScope = scope().searched ? scope() : null;
  let scheduled = false;
  const inspect = () => {
    scheduled = false;
    if (!searchedScope || form.hidden !== true) return;
    const next = scope();
    if (!next.searched || covered(searchedScope, next)) return;
    const submit = document.querySelector('.search-submit');
    if (submit?.disabled) return;
    // Record before requestSubmit(): the submit event is synchronous and this
    // prevents the same UI event from scheduling a duplicate provider search.
    searchedScope = next;
    form.requestSubmit();
  };
  const scheduleInspect = () => {
    if (scheduled) return;
    scheduled = true;
    queueMicrotask(inspect);
  };

  document.addEventListener('click', scheduleInspect);
  document.addEventListener('change', scheduleInspect);
  form.addEventListener('submit', () => queueMicrotask(() => {
    const current = scope();
    if (current.searched) searchedScope = current;
  }));

  window.AnyTourPrototypeInventoryScopeRefreshV1 = true;
})();
