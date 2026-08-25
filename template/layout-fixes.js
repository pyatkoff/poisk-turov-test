document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[name="searchForm"]');
    var buttonWrap = document.getElementById('sendBtnWrapWrap');

    if (!form) {
        return;
    }

    if (buttonWrap && buttonWrap.parentNode !== form) {
        form.appendChild(buttonWrap);
    }

    var firstExtraColumn = form.querySelector('.searchColLeft');
    var extraColumns = form.querySelectorAll('.searchColLeft, .searchColCenter, .searchColRight');

    if (!firstExtraColumn || !extraColumns.length) {
        return;
    }

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'additionalFiltersToggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.innerHTML = '<span>Дополнительные фильтры</span><span class="additionalFiltersArrow" aria-hidden="true">⌄</span>';
    firstExtraColumn.parentNode.insertBefore(toggle, firstExtraColumn);

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
