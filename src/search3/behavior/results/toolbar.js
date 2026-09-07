  // Progressive results and breakpoint changes share the existing deferred mount.
  function scheduleMobileToolbar() {
    if (!body.classList.contains('search3-results-active') || mobileToolbarTimer !== null) return;
    mobileToolbarTimer = window.setTimeout(function () {
      mobileToolbarTimer = null;
      mountMobileToolbar();
    }, 0);
  }

  function cancelMobileToolbar() {
    if (mobileToolbarTimer !== null) window.clearTimeout(mobileToolbarTimer);
    mobileToolbarTimer = null;
  }

  function mountMobileToolbar() {
    if (!body.classList.contains('search3-results-active')) return;
    var filterBar = document.querySelector('.mrf-bar');
    if (!filterBar || !sort) return;
    var toolbar = document.querySelector('.search3-mobile-toolbar');
    if (!toolbar) {
      toolbar = document.createElement('div');
      toolbar.className = 'search3-mobile-toolbar';
      toolbar.innerHTML = '<div class="search3-mobile-filter-slot"></div>'
        + '<label class="search3-mobile-sort"><span>Сортировка</span><select aria-label="Сортировка результатов"></select></label>';
      tools.insertAdjacentElement('afterend', toolbar);
      var proxy = toolbar.querySelector('select');
      proxy.innerHTML = sort.innerHTML;
      proxy.value = sort.value;
      proxy.addEventListener('change', function () {
        sort.value = proxy.value;
        sort.dispatchEvent(new Event('change', { bubbles: true }));
      });
      sort.addEventListener('change', function () { proxy.value = sort.value; });
    }
    var slot = toolbar.querySelector('.search3-mobile-filter-slot');
    if (slot && filterBar.parentElement !== slot) slot.appendChild(filterBar);
  }
