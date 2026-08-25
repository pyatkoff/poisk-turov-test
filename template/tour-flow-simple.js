(function () {
    'use strict';

    function syncButton(button) {
        var wrap = button.closest('.tourHotelWrap');
        if (!wrap) return;
        var formOpen = wrap.classList.contains('formshow');
        button.classList.toggle('orderOpen', formOpen);
        button.textContent = formOpen ? 'Вернуться к туру' : 'Оставить заявку';
        button.setAttribute('aria-expanded', formOpen ? 'true' : 'false');
    }

    function refreshOrderDetails() {
        if (typeof window.jQuery !== 'function') return;
        if (typeof window.requestDetail === 'undefined' || window.requestDetail) return;

        var $ = window.jQuery;
        var $online = $('.hotelOrderOnline').first();
        if (!$online.length) return;

        var req = $online.find('input[name=ordReq]').val();
        var hid = $online.find('input[name=ordHid]').val();
        var tid = $online.find('input[name=ordTid]').val();
        if (!req || !hid || !tid) return;

        window.requestDetail = true;
        $.ajax($('form[name=searchForm]').attr('action'), {
            cache: false,
            data: {req: req, hid: hid, tid: tid, update_detail: 'y'},
            dataType: 'json',
            error: typeof window.errorHandler === 'function' ? window.errorHandler : undefined,
            success: typeof window.successTourInfoUpdate === 'function' ? window.successTourInfoUpdate : undefined,
            type: 'POST'
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.hotelNextStep');
        if (!button) return;

        /* Own the interaction before the legacy delegated jQuery handler sees it. */
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        if (button.classList.contains('loading')) return;

        var wrap = button.closest('.tourHotelWrap');
        if (!wrap) return;

        if (wrap.classList.contains('formshow')) {
            wrap.classList.remove('formshow');
        } else {
            wrap.classList.add('formshow');
            refreshOrderDetails();
        }

        syncButton(button);
    }, true);

    function syncAll() {
        document.querySelectorAll('.hotelNextStep').forEach(syncButton);
    }

    var observer = new MutationObserver(function () {
        syncAll();
    });

    observer.observe(document.documentElement, {childList: true, subtree: true});
    document.addEventListener('DOMContentLoaded', syncAll);
})();
