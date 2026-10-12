'use strict';
(() => {
  const wrapper = document.getElementById('b4');
  const mobileStage = wrapper.querySelector('[data-variant="Мобильная версия"]');
  const desktopStage = wrapper.querySelector('[data-variant="На компьютере"]');
  const mobile = mobileStage.querySelector('[data-product]');
  const desktop = mobile.cloneNode(true);
  desktop.classList.remove('st-mobile');
  desktop.classList.add('st-desktop');
  desktopStage.querySelector('[data-desktop-mount]').append(desktop);
  const media = matchMedia('(max-width:760px)');
  const bridge = window.AnyTourDesignV2Bridge.create();
  let mounted = null, mountGeneration = 0;
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
    if (state.screen === 'results') mounted.render();
  }, {replay: false});
  function selectVariant() {
    const epoch = ++mountGeneration;
    const isMobile = media.matches;
    mobileStage.hidden = !isMobile;
    desktopStage.hidden = isMobile;
    const product = isMobile ? mobile : desktop;
    const previous = mounted?.getState?.();
    mounted?.destroy?.();
    mounted = window.AnyTourDesignV2Native.mount({root: product,
      bridge, initialState: previous});
    const ownMount = mounted;
    Promise.resolve(ownMount.ready).then(() => {
      if (epoch !== mountGeneration || mounted !== ownMount) return;
      product.hidden = false;
      document.getElementById('design-v2-native-root').setAttribute('aria-busy', 'false');
    }).catch(error => {
      if (epoch !== mountGeneration || mounted !== ownMount) return;
      product.hidden = false;
      const main = product.querySelector('.st-main');
      main.textContent = 'Не удалось подготовить поиск. Обновите страницу. ' + String(error.message || '');
      document.getElementById('design-v2-native-root').setAttribute('aria-busy', 'false');
    });
  }
  media.addEventListener('change', selectVariant);
  selectVariant();
})();
