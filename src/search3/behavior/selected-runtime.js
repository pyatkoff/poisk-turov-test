/* Load the canonical tour/lead transport and flight-price owners on first selection. */
(function () {
  'use strict';
  const source = document.querySelector('script[data-search3-selected-runtime]');
  if (!source || window.V2TourController) return;
  const url = new URL(source.dataset.search3SelectedRuntime, location.href);
  if (url.origin !== location.origin) return;
  let loading = null, ready = false, generation = 0;
  const proxy = { selectTour: (id, button) => select(id, button, false), get currentTour() { return null; }, version: 4 };

  function load() {
    if (ready) return Promise.resolve();
    if (loading) return loading;
    loading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = url.href;
      script.onload = () => {
        ready = window.V2TourController !== proxy && !!window.V2FlightPriceSync && !!window.V2UnpricedFlightPriceResetV1;
        if (ready) resolve();
        else reject(new Error('Selected tour runtime unavailable'));
      };
      script.onerror = () => { script.remove(); loading = null; reject(new Error('Selected tour runtime unavailable')); };
      document.head.appendChild(script);
    });
    return loading;
  }

  async function select(id, button, replay) {
    if (!id) return;
    const run = ++generation, label = button && button.textContent;
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Загружаем…'; }
    try {
      await load();
    } catch (error) {
      if (button && run === generation) button.textContent = 'Не удалось загрузить. Повторить';
      return;
    } finally {
      if (button) { button.disabled = false; button.removeAttribute('aria-busy'); if (ready || run !== generation) button.textContent = label; }
    }
    if (run !== generation) return;
    // Replay the actual action so the canonical controller remembers return focus.
    if (replay) { if (document.contains(button)) button.click(); return; }
    return window.V2TourController.selectTour(id, button);
  }

  window.V2TourController = proxy;
  window.addEventListener('click', event => {
    if (ready) return;
    const button = event.target && event.target.closest && event.target.closest('.direct-tour');
    if (!button || button.disabled || !button.dataset.tid) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    select(button.dataset.tid, button, true);
  }, true);
  window.addEventListener('v2:search-reset', () => { generation++; });
})();
