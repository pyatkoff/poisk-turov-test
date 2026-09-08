/* Search3-owned search progress and recovery presentation. */
(function () {
  'use strict';

  var status = document.getElementById('status');
  if (!status) return;
  var renderedCount = 0;
  var lastProgress = 0;

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
    });
  }

  function render(progress, title, note, done) {
    var value = Math.max(0, Math.min(100, Number(progress) || 0));
    status.hidden = false;
    status.innerHTML = '<div class="search-progress-ux' + (done ? ' search-progress-done' : '') + '"><div class="search-progress-head"><strong>' + escapeHtml(title) + '</strong><span>' + (done ? 'Готово' : value + '%') + '</span></div><div class="search-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + value + '"><div class="search-progress-bar" style="width:' + value + '%"></div></div>' + (note ? '<div class="search-progress-note">' + escapeHtml(note) + '</div>' : '') + '</div>';
  }

  function renderEmpty() {
    status.hidden = false;
    status.innerHTML = '<div class="search-progress-empty" role="status"><div class="search-progress-empty-copy"><strong>По этим условиям туров не нашли</strong><span>Можно немного расширить даты или длительность — мы сначала покажем новые значения в форме и ничего не запустим без вас.</span></div><div class="search-progress-empty-actions"><button type="button" class="search-progress-relax-dates">Расширить даты ±2 дня</button><button type="button" class="search-progress-relax-nights">Расширить ночи ±1</button><button type="button" class="search-progress-edit">Изменить условия</button><button type="button" class="search-progress-filters">Открыть фильтры</button></div></div>';
  }

  function finalResultsFailure(detail) {
    return String(detail && detail.phase || '') === 'status' && lastProgress >= 100;
  }

  function renderError(detail) {
    var error = detail && detail.error || {};
    var phase = String(detail && detail.phase || '');
    var resultsOnly = finalResultsFailure(detail);
    var message = resultsOnly
      ? 'Поиск уже завершён, но итоговые результаты временно не загрузились. Повторно запускать поиск не нужно.'
      : error.code === 'TIMEOUT'
        ? 'Tourvisor отвечает дольше обычного. Повторите поиск — выбранные параметры сохранятся.'
        : phase === 'start'
          ? 'Не удалось запустить поиск. Проверьте соединение и попробуйте ещё раз.'
          : 'Не удалось обновить результаты. Попробуйте повторить поиск.';
    status.hidden = false;
    status.innerHTML = '<div class="search-progress-error" role="alert"><div class="search-progress-error-copy"><strong>' + (resultsOnly ? 'Поиск завершён — результаты не загрузились' : 'Не получилось завершить поиск') + '</strong><span>' + escapeHtml(message) + '</span></div><button type="button" class="' + (resultsOnly ? 'search-progress-retry-results' : 'search-progress-retry') + '">' + (resultsOnly ? 'Загрузить результаты ещё раз' : 'Повторить поиск') + '</button></div>';
  }

  function renderContinueError(detail) {
    var resultsOnly = !!(detail && detail.retryResultsOnly);
    var message = detail && detail.error && detail.error.message
      ? String(detail.error.message)
      : 'Не удалось загрузить дополнительные предложения.';
    status.hidden = false;
    status.innerHTML = '<div class="search-progress-error" role="alert"><div class="search-progress-error-copy"><strong>' + (resultsOnly ? 'Дополнительный поиск завершён — результаты не загрузились' : 'Не удалось найти ещё варианты') + '</strong><span>' + escapeHtml(resultsOnly ? 'Найденные варианты сохранены в Tourvisor. Нажмите кнопку под результатами, чтобы загрузить их без повторного дополнительного поиска.' : message) + '</span></div><span class="search-progress-note">Уже найденные предложения остаются на месте.</span></div>';
  }

  function goToSearch(openFilters) {
    var form = document.getElementById('tourSearch');
    if (!form) return null;
    window.dispatchEvent(new CustomEvent('v2:search-dirty', { detail: { source: 'recovery' } }));
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var extras = openFilters && form.querySelector('details.extras');
    if (extras) extras.open = true;
    return form;
  }

  function shiftDate(value, days) {
    var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return '';
    var date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (Number.isNaN(date.getTime())) return '';
    date.setDate(date.getDate() + days);
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  }

  function today() {
    var date = new Date();
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
  }

  function changeFields(fields) {
    fields.forEach(function (field) {
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function focusSearchSubmit(form) {
    var submit = form && form.querySelector('.search-submit');
    var sticky = document.querySelector('.mobile-search-sticky-submit');
    var stickyRoot = sticky && sticky.closest('.mobile-search-sticky');
    var stickyVisible = !!(sticky && stickyRoot && !stickyRoot.hidden
      && getComputedStyle(sticky).display !== 'none'
      && getComputedStyle(sticky).visibility !== 'hidden');
    var target = stickyVisible ? sticky : submit;
    if (target) target.focus({ preventScroll: true });
  }

  function renderAdjusted(title, note) {
    status.innerHTML = '<div class="search-progress-empty search-progress-empty--adjusted" role="status"><div class="search-progress-empty-copy"><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(note) + '</span></div><div class="search-progress-empty-actions"><button type="button" class="search-progress-edit">Проверить параметры</button></div></div>';
  }

  function finishAdjustment(form, fields, title, note) {
    changeFields(fields);
    goToSearch(false);
    focusSearchSubmit(form);
    renderAdjusted(title, note);
    return true;
  }

  function relaxDates() {
    var form = document.getElementById('tourSearch');
    var from = form && form.querySelector('[name="dateFrom"]');
    var to = form && form.querySelector('[name="dateTo"]');
    if (!from || !to) return false;
    var nextFrom = shiftDate(from.value, -2);
    var nextTo = shiftDate(to.value, 2);
    if (!nextFrom || !nextTo) return false;
    nextFrom = nextFrom < today() ? today() : nextFrom;
    if (nextTo < nextFrom) return false;
    from.value = nextFrom;
    to.value = nextTo;
    return finishAdjustment(form, [from, to], 'Диапазон дат расширен', 'Новые даты ' + nextFrom + ' — ' + nextTo + ' уже стоят в форме. Проверьте их и нажмите «Найти туры», когда будете готовы.');
  }

  function relaxNights() {
    var form = document.getElementById('tourSearch');
    var from = form && form.querySelector('[name="daysFrom"]');
    var to = form && form.querySelector('[name="daysTill"]');
    if (!from || !to) return false;
    var currentFrom = Number.parseInt(from.value, 10);
    var currentTo = Number.parseInt(to.value, 10);
    if (!Number.isFinite(currentFrom) || !Number.isFinite(currentTo)) return false;
    var minimum = Math.max(1, Number.parseInt(from.min || '1', 10) || 1);
    var maximum = Math.max(minimum, Number.parseInt(to.max || '28', 10) || 28);
    var nextFrom = Math.max(minimum, currentFrom - 1);
    var nextTo = Math.max(nextFrom, Math.min(maximum, currentTo + 1));
    if (nextFrom === currentFrom && nextTo === currentTo) return false;
    from.value = String(nextFrom);
    to.value = String(nextTo);
    return finishAdjustment(form, [from, to], 'Диапазон ночей расширен', nextFrom + '–' + nextTo + ' ночей уже стоят в форме. Проверьте длительность и нажмите «Найти туры», когда будете готовы.');
  }

  async function retryFinalResults(button) {
    var lifecycle = window.V2SearchLifecycle;
    var runtime = window.V2Runtime;
    var renderer = window.V2Results;
    var id = Number(lifecycle && lifecycle.searchId || 0);
    var generation = Number(lifecycle && lifecycle.generation || 0);
    if (!id || !runtime || !renderer || typeof renderer.render !== 'function') return false;
    function current() {
      return Number(lifecycle.searchId || 0) === id && Number(lifecycle.generation || 0) === generation && !lifecycle.dirty;
    }
    if (button) {
      button.disabled = true;
      button.textContent = 'Загружаем результаты…';
    }
    try {
      var response = await runtime.api('search_results', { searchId: id, limit: 100 });
      if (!current()) return false;
      var items = Array.isArray(response) ? response : [];
      renderer.render(items);
      window.dispatchEvent(new CustomEvent('v2:search-complete', { detail: { searchId: id, progress: 100, items: items, recoveredResults: true } }));
      return true;
    } catch (error) {
      if (!current()) return false;
      renderError({ phase: 'status', error: error });
      return false;
    }
  }

  window.addEventListener('v2:results-rendered', function (event) {
    var items = event.detail && Array.isArray(event.detail.items) ? event.detail.items : [];
    renderedCount = items.length;
  });
  window.addEventListener('v2:search-started', function () {
    renderedCount = 0;
    lastProgress = 0;
    render(6, 'Ищем лучшие варианты', 'Первые предложения появятся прямо во время поиска.', false);
  });
  window.addEventListener('v2:search-progress', function (event) {
    var detail = event.detail || {};
    var progress = Number(detail.progress || 0);
    var note = renderedCount
      ? 'Уже можно смотреть ' + renderedCount + ' ' + (renderedCount === 1 ? 'отель' : 'отелей') + ' — поиск продолжается.'
      : 'Сравниваем предложения туроператоров.';
    lastProgress = Math.max(lastProgress, progress);
    render(progress, 'Ищем лучшие варианты', note, false);
  });
  window.addEventListener('v2:search-complete', function (event) {
    var items = event.detail && Array.isArray(event.detail.items) ? event.detail.items : [];
    renderedCount = items.length;
    lastProgress = 100;
    if (!renderedCount) return renderEmpty();
    render(100, 'Поиск завершён', 'Найдено отелей: ' + renderedCount + '. Предложения актуальны на сейчас.', true);
  });
  window.addEventListener('v2:search-dirty', function () {
    renderedCount = 0;
    lastProgress = 0;
  });
  window.addEventListener('v2:search-error', function (event) {
    var detail = event.detail || {};
    if (detail.phase !== 'validation') renderError(detail);
  });
  window.addEventListener('v2:search-continue-started', function (event) {
    var count = Number(event.detail && event.detail.previousResultsCount || renderedCount || 0);
    render(4, 'Ищем ещё варианты', count ? 'Найденные ' + count + ' ' + (count === 1 ? 'отель остаётся' : 'отелей остаются') + ' на месте.' : 'Запрашиваем дополнительные предложения.', false);
  });
  window.addEventListener('v2:search-continue-requested', function (event) {
    var count = Number(event.detail && event.detail.requestCount || 0);
    render(10, 'Ищем ещё варианты', count ? 'Дополнительных запросов к туроператорам: ' + count + '.' : 'Запросили дополнительные предложения у туроператоров.', false);
  });
  window.addEventListener('v2:search-continue-progress', function (event) {
    render(Math.max(10, Number(event.detail && event.detail.progress || 0)), 'Ищем ещё варианты', 'Уже найденные предложения можно продолжать смотреть.', false);
  });
  window.addEventListener('v2:search-continued', function (event) {
    var detail = event.detail || {};
    var added = Number(detail.addedResultsCount || 0);
    var items = Array.isArray(detail.items) ? detail.items : [];
    renderedCount = items.length;
    render(100, 'Дополнительный поиск завершён', added ? 'Добавлено отелей: ' + added + '.' : 'Новых вариантов больше не найдено.', true);
  });
  window.addEventListener('v2:search-continue-error', function (event) {
    renderContinueError(event.detail || {});
  });

  status.addEventListener('click', function (event) {
    var target = event.target;
    var retryResults = target && target.closest('.search-progress-retry-results');
    if (retryResults) return void retryFinalResults(retryResults);
    var retry = target && target.closest('.search-progress-retry');
    if (retry) {
      var lifecycle = window.V2SearchLifecycle;
      if (lifecycle && typeof lifecycle.submit === 'function') {
        retry.disabled = true;
        lifecycle.submit();
      }
      return;
    }
    if (target && target.closest('.search-progress-relax-dates')) return void relaxDates();
    if (target && target.closest('.search-progress-relax-nights')) return void relaxNights();
    if (target && target.closest('.search-progress-edit')) return void goToSearch(false);
    if (target && target.closest('.search-progress-filters')) goToSearch(true);
  });
})();
