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
    var originalSubmit = form.querySelector('#search_start');

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'additionalFiltersToggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.innerHTML = '<span>Дополнительные фильтры</span><span class="additionalFiltersArrow" aria-hidden="true">⌄</span>';
    extrasParent.insertBefore(toggle, firstExtraColumn);

    /* Mobile CTA is outside the collapsible columns, directly below the filters toggle. */
    var mobileCta = document.getElementById('mobileSearchCta');
    if (!mobileCta && originalSubmit) {
        mobileCta = document.createElement('button');
        mobileCta.type = 'submit';
        mobileCta.id = 'mobileSearchCta';
        mobileCta.className = 'mobileSearchCta';
        mobileCta.name = originalSubmit.name || 'search_start';
        mobileCta.value = originalSubmit.value || 'Искать туры';
        mobileCta.textContent = originalSubmit.value || 'Искать туры';
        toggle.insertAdjacentElement('afterend', mobileCta);
    }

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
