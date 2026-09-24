'use strict';
(() => {
  if (window.AnyTourPrototypeSearchLifecycleV1) return;

  const unionReceipt = value => {
    if (!value || typeof value !== 'object' || Array.isArray(value)
      || !Number.isSafeInteger(value.hotels) || value.hotels < 0
      || !Number.isSafeInteger(value.offers) || value.offers < 0) return null;
    const counts = key => {
      const source=value[key];
      if (!source || typeof source !== 'object' || Array.isArray(source)) return null;
      const result={};
      for (const [name,count] of Object.entries(source)) {
        if (!name || name.length > 96 || !Number.isSafeInteger(count) || count < 0) return null;
        result[name]=count;
      }
      return result;
    };
    const hotelsByProvider=counts('hotelsByProvider'),offersByProvider=counts('offersByProvider'),providerSets=counts('providerSets');
    if (!hotelsByProvider || !offersByProvider || !providerSets) return null;
    const providerOfferTotal=Object.values(offersByProvider).reduce((sum,count)=>sum+count,0);
    if (providerOfferTotal !== value.offers || Object.values(hotelsByProvider).some(count=>count>value.hotels)
      || Object.values(providerSets).reduce((sum,count)=>sum+count,0)!==value.hotels) return null;
    return Object.freeze({hotels:value.hotels,offers:value.offers,
      hotelsByProvider:Object.freeze(hotelsByProvider),offersByProvider:Object.freeze(offersByProvider),providerSets:Object.freeze(providerSets)});
  };

  const reduce = (response, event) => {
    if (!response || !event || typeof event !== 'object') return;
    if (event.type === 'loading') {
      response.pending = true;
      response.phase = 'loading';
      response.continued = event.continued === true;
      response.cachedResume = event.cachedResume === true;
      response.canContinue = false;
      response.union = null;
      response.message = event.cachedResume
        ? 'Восстанавливаем сохранённые предложения без нового запроса к туроператорам.'
        : event.retryRead
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
      response.cachedResume = event.cachedResume === true;
      response.sources = event.sources || response.sources || {};
      response.union = unionReceipt(event.union);
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

    let generation = 0, bound = false, submitScheduledGeneration = null, scopeScheduledGeneration = null;
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
        const resumeOnly=runOptions.resumeOnly===true;
        const runner=resumeOnly?data.resumeCached:data.search;
        if(typeof runner!=='function')throw new Error(resumeOnly?'Prototype cached resume is unavailable.':'Prototype search is unavailable.');
        pending = runner.call(data,prepared.search, receive, prepared.hotelIds || [], prepared.filters || {});
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
      if (!canSubmit()) return false;
      const runOptions = typeof options.commit === 'function' ? options.commit(event) : {};
      if (runOptions === false) return false;
      const started = run(runOptions && typeof runOptions === 'object' ? runOptions : {});
      if (typeof options.afterSubmit === 'function') options.afterSubmit(started);
      return started;
    };

    const requestSubmit = () => {
      const scheduledGeneration = generation;
      if (submitScheduledGeneration === scheduledGeneration) return true;
      submitScheduledGeneration = scheduledGeneration;
      queueMicrotask(() => {
        if (submitScheduledGeneration === scheduledGeneration) submitScheduledGeneration = null;
        if (scheduledGeneration !== generation || !canSubmit() || typeof form.requestSubmit !== 'function') return;
        form.requestSubmit();
      });
      return true;
    };

    const inspectSupplierScope = scheduledGeneration => {
      if (scopeScheduledGeneration === scheduledGeneration) scopeScheduledGeneration = null;
      if (scheduledGeneration !== generation
        || form.hidden !== true
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
      if (form.hidden !== true) return;
      const scheduledGeneration = generation;
      if (scopeScheduledGeneration === scheduledGeneration) return;
      scopeScheduledGeneration = scheduledGeneration;
      queueMicrotask(() => inspectSupplierScope(scheduledGeneration));
    };

    const click = event => {
      const action = event?.target?.closest?.('[data-action]')?.dataset?.action;
      if (action === 'stop-search' || action === 'edit-search') {
        generation++;
        return;
      }
      scheduleSupplierScope();
    };

    const bind = () => {
      if (bound) return false;
      bound = true;
      form.addEventListener('submit', submit);
      if (events && typeof events.addEventListener === 'function'
        && typeof options.supplierFilters === 'function'
        && typeof data.supplierScope === 'function'
        && typeof data.supplierScopeCovered === 'function') {
        events.addEventListener('click', click);
        events.addEventListener('change', scheduleSupplierScope);
      }
      return true;
    };

    const invalidate = () => { generation++; };

    return Object.freeze({run, bind, invalidate, requestSubmit});
  }

  window.AnyTourPrototypeSearchLifecycleV1 = Object.freeze({create});
})();
