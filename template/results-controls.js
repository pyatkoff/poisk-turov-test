(function () {
    'use strict';

    var lastTours = [];
    var lastPager = '';
    var nativeConstruct = window.constructHotelsResult;

    if (typeof nativeConstruct !== 'function') {
        return;
    }

    function cloneTour(tour) {
        return Object.assign({}, tour || {});
    }

    function numberPrice(value) {
        return parseInt(String(value || '').replace(/[^0-9]/g, ''), 10) || 0;
    }

    function rating(value) {
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function stars(value) {
        var n = parseInt(value, 10);
        return isNaN(n) ? 0 : n;
    }

    function sortedTours(mode) {
        var copy = lastTours.map(cloneTour);
        if (mode === 'price-asc') {
            copy.sort(function (a, b) { return numberPrice(a.price) - numberPrice(b.price); });
        } else if (mode === 'price-desc') {
            copy.sort(function (a, b) { return numberPrice(b.price) - numberPrice(a.price); });
        } else if (mode === 'rating') {
            copy.sort(function (a, b) {
                return (rating(b.hotel_rate) - rating(a.hotel_rate)) ||
                    (stars(b.hotel_star) - stars(a.hotel_star)) ||
                    (numberPrice(a.price) - numberPrice(b.price));
            });
        }
        return copy;
    }

    function ensureControls() {
        var table = document.getElementById('searchResultTable');
        if (!table) return null;

        var controls = document.getElementById('testResultsControls');
        if (!controls) {
            controls = document.createElement('div');
            controls.id = 'testResultsControls';
            controls.className = 'testResultsControls';
            controls.innerHTML = '<div class="testResultsFound"><strong class="testResultsCount">0</strong> отелей на этой странице</div>' +
                '<label class="testResultsSortLabel">Сортировка <select class="testResultsSort">' +
                '<option value="default">Как найдено</option>' +
                '<option value="price-asc">Сначала дешевле</option>' +
                '<option value="price-desc">Сначала дороже</option>' +
                '<option value="rating">По рейтингу</option>' +
                '</select></label>';
            table.parentNode.insertBefore(controls, table);

            controls.querySelector('.testResultsSort').addEventListener('change', function () {
                nativeConstruct({tours: sortedTours(this.value), pager: lastPager});
                updateCount();
            });
        }
        return controls;
    }

    function updateCount() {
        var controls = ensureControls();
        if (!controls) return;
        var count = controls.querySelector('.testResultsCount');
        if (count) count.textContent = String(lastTours.length);
    }

    window.constructHotelsResult = function (data) {
        lastTours = data && Array.isArray(data.tours) ? data.tours.map(cloneTour) : [];
        lastPager = data && data.pager ? data.pager : '';
        var controls = ensureControls();
        var mode = controls ? controls.querySelector('.testResultsSort').value : 'default';
        var renderData = Object.assign({}, data || {}, {tours: sortedTours(mode)});
        nativeConstruct(renderData);
        updateCount();
    };
})();
