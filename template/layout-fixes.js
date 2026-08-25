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
    var lastExtraColumn = extraColumns[extraColumns.length - 1];
    var originalSubmit = form.querySelector('#search_start');

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'additionalFiltersToggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.innerHTML = '<span>Дополнительные фильтры</span><span class="additionalFiltersArrow" aria-hidden="true">⌄</span>';
    extrasParent.insertBefore(toggle, firstExtraColumn);

    var mobileCta = document.getElementById('mobileSearchCta');
    if (!mobileCta && originalSubmit) {
        mobileCta = document.createElement('button');
        mobileCta.type = 'submit';
        mobileCta.id = 'mobileSearchCta';
        mobileCta.className = 'mobileSearchCta';
        mobileCta.name = originalSubmit.name || 'search_start';
        mobileCta.value = originalSubmit.value || 'Искать туры';
        mobileCta.textContent = originalSubmit.value || 'Искать туры';
    }

    var mobileQuery = window.matchMedia('(max-width: 620px)');

    function positionMobileCta(collapsed) {
        if (!mobileCta) return;
        if (collapsed) {
            toggle.insertAdjacentElement('afterend', mobileCta);
        } else {
            lastExtraColumn.insertAdjacentElement('afterend', mobileCta);
        }
    }

    function setCollapsed(collapsed) {
        form.classList.toggle('additional-filters-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.classList.toggle('is-open', !collapsed);
        positionMobileCta(collapsed);
    }

    function applyViewportState() {
        if (mobileQuery.matches) {
            if (!form.dataset.additionalFiltersTouched) {
                setCollapsed(true);
            } else {
                positionMobileCta(form.classList.contains('additional-filters-collapsed'));
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
