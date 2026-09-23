'use strict';
(() => {
  if (window.AnyTourPrototypeSearchLifecycleV1) return;

  const reduce = (response, event) => {
    if (!response || !event || typeof event !== 'object') return;
    if (event.type === 'loading') {
      response.pending = true;
      response.phase = 'loading';
      response.continued = event.continued === true;
      response.canContinue = false;
      response.message = event.retryRead
        ? 'Проверяем результат предыдущего запроса без повторного запуска.'
        : event.continued
          ? 'Запрашиваем дополнительные варианты. Найденные предложения сохраняются.'
          : '';
    }
    if (event.type === 'provider') {
      response.providers ??= {};
      response.providers[event.provider] = event.status;
    }
    if (event.type === 'database-error') response.databaseError = true;
    if (event.type === 'database') response.databaseError = false;
    if (event.type === 'progress') response.message = 'Получаем предложения · ' + event.progress + '%';
    if (event.type === 'complete') {
      response.pending = false;
      response.phase = 'complete';
      response.canContinue = event.canContinue === true;
      response.retryRead = event.retryRead === true;
      response.resultLimitReached = event.resultLimitReached === true;
      response.sources = event.sources || response.sources || {};
    }
    if (event.type === 'error') {
      response.pending = false;
      response.phase = 'error';
      response.message = event.message;
      response.canContinue = event.canContinue === true;
      response.retryRead = event.retryRead === true;
    }
  };

  function create(options = {}) {
    const form = options.form, data = options.data, events = options.events;
    if (!form || typeof form.addEventListener !== 'function'
      || !data || typeof data.search !== 'function'
      || typeof options.prepare !== 'function'
      || typeof options.currentKey !== 'function') throw new Error('Prototype search lifecycle dependencies are unavailable.');

    let generation = 0, bound = false, submitScheduled = false, scopeScheduled = false;
    const canSubmit = () => typeof options.canSubmit !== 'function' || options.canSubmit() !== false;

    const run = (runOptions = {}) => {
      const prepared = options.prepare(runOptions);
      if (!prepared) return false;
      const response = prepared.response;
      if (!response || typeof response !== 'object' || typeof response.key !== 'string') throw new Error('Prototype search lifecycle response is unavailable.');
      const key = response.key, runGeneration = ++generation;
      const current = () => runGeneration === generation && key === options.currentKey();
      const receive = event => {
        if (!current()) return;
        if (event?.type === 'results' && typeof options.onResults === 'function') options.onResults(event, response);
        reduce(response, event);
        if (typeof options.afterEvent === 'function') options.afterEvent(event, response);
      };
      const fail = error => {
        if (!current()) return;
        response.pending = false;
        response.phase = 'error';
        response.message = error?.message || String(error || '');
        if (typeof options.afterFailure === 'function') options.afterFailure(error, response);
      };

      let pending;
      try {
        pending = data.search(prepared.search, receive, prepared.hotelIds || [], prepared.filters || {});
      } catch (error) {
        fail(error);
        return false;
      }
      if (typeof options.afterStart === 'function') options.afterStart(response);
      Promise.resolve(pending).catch(fail);
      return true;
    };

    const submit = event => {
      event?.preventDefault?.();
      const runOptions = typeof options.commit === 'function' ? options.commit(event) : {};
      if (runOptions === false) return false;
      const started = run(runOptions && typeof runOptions === 'object' ? runOptions : {});
      if (typeof options.afterSubmit === 'function') options.afterSubmit(started);
      return started;
    };

    const requestSubmit = () => {
      if (submitScheduled) return true;
      submitScheduled = true;
      queueMicrotask(() => {
        submitScheduled = false;
        if (!canSubmit() || typeof form.requestSubmit !== 'function') return;
        form.requestSubmit();
      });
      return true;
    };

    const inspectSupplierScope = () => {
      scopeScheduled = false;
      if (form.hidden !== true
        || typeof options.supplierFilters !== 'function'
        || typeof data.supplierScope !== 'function'
        || typeof data.supplierScopeCovered !== 'function') return;
      const previous = data.currentSupplierScope;
      if (!previous) return;
      let next;
      try { next = data.supplierScope(options.supplierFilters()); } catch { return; }
      if (!next || data.supplierScopeCovered(previous, next)) return;
      requestSubmit();
    };

    const scheduleSupplierScope = () => {
      if (form.hidden !== true || scopeScheduled) return;
      scopeScheduled = true;
      queueMicrotask(inspectSupplierScope);
    };

    const bind = () => {
      if (bound) return false;
      bound = true;
      form.addEventListener('submit', submit);
      if (events && typeof events.addEventListener === 'function'
        && typeof options.supplierFilters === 'function'
        && typeof data.supplierScope === 'function'
        && typeof data.supplierScopeCovered === 'function') {
        events.addEventListener('click', scheduleSupplierScope);
        events.addEventListener('change', scheduleSupplierScope);
      }
      return true;
    };

    const invalidate = () => { generation++; };

    return Object.freeze({run, bind, invalidate, requestSubmit});
  }

  window.AnyTourPrototypeSearchLifecycleV1 = Object.freeze({create});
})();
