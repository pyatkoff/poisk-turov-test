'use strict';
(() => {
  const nativeRoot = document.getElementById('design-v2-native-root');
  const wrapper = document.getElementById('b4');
  const startup = wrapper.querySelector('[data-native-startup]');
  const startupStatus = startup.querySelector('[data-native-startup-status]');
  const startupHeading = startup.querySelector('[data-native-startup-heading]');
  const startupMessage = startup.querySelector('[data-native-startup-message]');
  const startupRetry = startup.querySelector('[data-native-startup-retry]');
  const mobileStage = wrapper.querySelector('[data-variant="Мобильная версия"]');
  const desktopStage = wrapper.querySelector('[data-variant="На компьютере"]');
  const mobile = mobileStage.querySelector('[data-product]');
  const desktop = mobile.cloneNode(true);
  desktop.classList.remove('st-mobile');
  desktop.classList.add('st-desktop');
  desktopStage.querySelector('[data-desktop-mount]').append(desktop);
  const media = matchMedia('(max-width:760px)');
  let mounted = null, mountGeneration = 0;
  startupRetry.addEventListener('click', () => window.location.reload());
  function pendingStartup() {
    mobile.hidden = desktop.hidden = true;
    startup.hidden = false;
    startup.classList.toggle('st-mobile', media.matches);
    startup.classList.toggle('st-desktop', !media.matches);
    startupStatus.setAttribute('role', 'status');
    startupStatus.setAttribute('aria-live', 'polite');
    startupHeading.textContent = 'Готовим поиск туров';
    startupMessage.textContent = 'Загружаем города вылета и направления…';
    startupRetry.hidden = true;
    nativeRoot.setAttribute('aria-busy', 'true');
  }
  function failedStartup() {
    mobile.hidden = desktop.hidden = true;
    startup.hidden = false;
    startupStatus.setAttribute('role', 'alert');
    startupStatus.setAttribute('aria-live', 'assertive');
    startupHeading.textContent = 'Не удалось загрузить поиск';
    startupMessage.textContent = 'Обновите страницу, чтобы попробовать снова.';
    startupRetry.hidden = false;
    nativeRoot.setAttribute('aria-busy', 'false');
  }
  pendingStartup();
  let bridge;
  try { bridge = window.AnyTourDesignV2Bridge.create(); }
  catch { failedStartup(); return; }
  const sameTrip = (a, b) => a && b && ['origin','country','from','to','minNights','maxNights','adults'].every(key => String(a[key]) === String(b[key]))
    && JSON.stringify(a.ages) === JSON.stringify(b.ages);
  // The bridge outlives responsive mounts. Its single stream delivers the
  // current supplier snapshot to whichever original template is now visible.
  bridge.subscribe(snapshot => {
    if (snapshot.dictionaryEvent) mounted?.applyDictionarySnapshot?.(snapshot.dictionaryEvent);
    const state = mounted?.getState?.();
    if (!state?.hasSearched || !sameTrip(state.search, snapshot.search)) return;
    if (state.searchGeneration && state.searchGeneration !== snapshot.searchGeneration
      && !(state.status === 'loading' && snapshot.status === 'loading' && !state.restoredHistory)) return;
    state.searchGeneration = snapshot.searchGeneration;
    state.hotels = snapshot.hotels;
    state.status = snapshot.status;
    state.message = snapshot.message || '';
    if (state.screen === 'results' || state.screen === 'filters') mounted.render();
  }, {replay: false});
  function selectVariant() {
    const epoch = ++mountGeneration;
    const isMobile = media.matches;
    mobileStage.hidden = !isMobile;
    desktopStage.hidden = isMobile;
    const product = isMobile ? mobile : desktop;
    const previous = mounted?.getState?.();
    mounted?.destroy?.();
    mounted = null;
    pendingStartup();
    let ownMount = null;
    const readyStartup = () => {
      if (epoch !== mountGeneration || !ownMount || mounted !== ownMount) return;
      startup.hidden = true;
      product.hidden = false;
      nativeRoot.setAttribute('aria-busy', 'false');
    };
    try {
      mounted = window.AnyTourDesignV2Native.mount({root: product,
        bridge, initialState: previous, onReady: readyStartup});
      ownMount = mounted;
    } catch { failedStartup(); return; }
    Promise.resolve(ownMount.ready).then(readyStartup).catch(() => {
      if (epoch !== mountGeneration || mounted !== ownMount) return;
      failedStartup();
    });
  }
  media.addEventListener('change', selectVariant);
  selectVariant();
})();
