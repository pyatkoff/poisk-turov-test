document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[name="searchForm"]');

    if (!form) {
        return;
    }

    var firstExtraColumn = form.querySelector('.searchColLeft');
    var extraColumns = form.querySelectorAll('.searchColLeft, .searchColCenter, .searchColRight');

    if (!firstExtraColumn || !extraColumns.length) {
        return;
    }

    var extrasParent = firstExtraColumn.parentNode;
    var searchTop = form.querySelector('.searchTop');
    var originalSubmit = form.querySelector('#search_start');

    /*
     * Do not depend on moving the legacy submit out of .searchColRight:
     * that column is one of the optional filters and is hidden on mobile.
     * Create a dedicated submit immediately after the main fields instead.
     */
    var mobileCta = document.getElementById('mobileSearchCta');
    if (!mobileCta && originalSubmit && searchTop) {
        mobileCta = document.createElement('button');
        mobileCta.type = 'submit';
        mobileCta.id = 'mobileSearchCta';
        mobileCta.className = 'mobileSearchCta';
        mobileCta.name = originalSubmit.name || 'search_start';
        mobileCta.value = originalSubmit.value || 'Искать туры';
        mobileCta.textContent = originalSubmit.value || 'Искать туры';
        searchTop.insertAdjacentElement('afterend', mobileCta);
    }

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'additionalFiltersToggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.innerHTML = '<span>Дополнительные фильтры</span><span class="additionalFiltersArrow" aria-hidden="true">⌄</span>';
    extrasParent.insertBefore(toggle, firstExtraColumn);

    var mobileQuery = window.matchMedia('(max-width: 620px)');

    function setCollapsed(collapsed) {
        form.classList.toggle('additional-filters-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.classList.toggle('is-open', !collapsed);
    }

    function applyViewportState() {
        if (mobileQuery.matches) {
            if (!form.dataset.additionalFiltersTouched) {
                setCollapsed(true);
            }
        } else {
            setCollapsed(false);
        }
    }

    toggle.addEventListener('click', function () {
        form.dataset.additionalFiltersTouched = '1';
        setCollapsed(!form.classList.contains('additional-filters-collapsed'));
    });

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', applyViewportState);
    } else if (typeof mobileQuery.addListener === 'function') {
        mobileQuery.addListener(applyViewportState);
    }

    applyViewportState();
});
