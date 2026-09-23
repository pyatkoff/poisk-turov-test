'use strict';
(() => {
  if (window.AnyTourPrototypeResultsDateRefreshV1) return;

  const form = document.getElementById('search-form');
  if (!form || typeof form.requestSubmit !== 'function') return;

  let context = null;
  const range = () => {
    const params = new URLSearchParams(location.search);
    return {from: params.get('from') || '', to: params.get('to') || ''};
  };
  const remember = source => { context = {source, ...range()}; };
  const routeSource = () => {
    const state = history.state;
    if (!state || typeof state !== 'object') return '';
    for (const value of Object.values(state)) {
      if (value && typeof value === 'object' && value.type === 'dates' && ['form','results'].includes(value.source)) return value.source;
    }
    return '';
  };

  document.addEventListener('click', event => {
    const trigger = event.target?.closest?.('[data-action]');
    if (!trigger) return;
    const action = trigger.dataset.action;
    if (action === 'calendar') { remember('results'); return; }
    if (action === 'dates') { remember('form'); return; }
    if (action === 'close-modal') { context = null; return; }
    if (action !== 'apply-dates') return;

    const pending = context;
    context = null;
    if (!pending || pending.source !== 'results') return;
    queueMicrotask(() => {
      const current = range();
      if (!current.from || !current.to || (current.from === pending.from && current.to === pending.to)) return;
      const submit = document.querySelector('.search-submit');
      if (submit?.disabled) return;
      form.requestSubmit();
    });
  });

  addEventListener('popstate', () => queueMicrotask(() => {
    const source = routeSource();
    if (source) remember(source);
  }));

  window.AnyTourPrototypeResultsDateRefreshV1 = true;
})();
