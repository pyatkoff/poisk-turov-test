(function () {
    'use strict';

    /*
     * Keep the legacy click handler and all of its existing form/AJAX logic.
     * We only normalize the label after it has toggled .formshow.
     * Details visible:  Продолжить
     * Order form visible: Об отеле
     */
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.hotelNextStep');
        if (!button || button.classList.contains('loading')) return;

        window.setTimeout(function () {
            var wrap = button.closest('.tourHotelWrap');
            if (!wrap) return;

            if (wrap.classList.contains('formshow')) {
                button.classList.add('orderOpen');
                button.textContent = 'Об отеле';
                button.setAttribute('aria-expanded', 'true');
            } else {
                button.classList.remove('orderOpen');
                button.textContent = 'Продолжить';
                button.setAttribute('aria-expanded', 'false');
            }
        }, 0);
    });
})();
