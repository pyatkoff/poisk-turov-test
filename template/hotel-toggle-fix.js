document.addEventListener('click', function (event) {
    var button = event.target.closest('.hotelNextStep');
    if (!button) return;

    /* Replace the legacy inverted Подробнее/Об отеле toggle only in test UI. */
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') {
        event.stopImmediatePropagation();
    }

    if (button.classList.contains('loading')) return;

    var wrap = button.closest('.tourHotelWrap');
    if (!wrap) return;

    var isOpen = wrap.classList.contains('formshow');

    if (isOpen) {
        wrap.classList.remove('formshow');
        button.classList.remove('orderOpen');
        button.textContent = 'Об отеле';
        button.setAttribute('aria-expanded', 'false');
    } else {
        wrap.classList.add('formshow');
        button.classList.add('orderOpen');
        button.textContent = 'Свернуть';
        button.setAttribute('aria-expanded', 'true');
    }
}, true);
