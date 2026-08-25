(function () {
    'use strict';

    function syncButton(button) {
        var wrap = button.closest('.tourHotelWrap');
        if (!wrap) return;
        var formOpen = wrap.classList.contains('formshow');
        var label = formOpen ? 'Вернуться к туру' : 'Оставить заявку';
        var expanded = formOpen ? 'true' : 'false';

        button.classList.toggle('orderOpen', formOpen);
        if (button.textContent !== label) button.textContent = label;
        if (button.getAttribute('aria-expanded') !== expanded) {
            button.setAttribute('aria-expanded', expanded);
        }
    }

    function refreshOrderDetails(wrap) {
        if (typeof window.jQuery !== 'function') return;
        if (typeof window.requestDetail === 'undefined' || window.requestDetail) return;

        var $ = window.jQuery;
        var $wrap = $(wrap);
        var $online = $wrap.find('.hotelOrderOnline').first();
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
            refreshOrderDetails(wrap);
        }

        syncButton(button);
    }, true);

    function syncAll(root) {
        root = root || document;
        if (root.nodeType === 1 && root.matches && root.matches('.hotelNextStep')) {
            syncButton(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll('.hotelNextStep').forEach(syncButton);
        }
    }

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) syncAll(node);
            });
        });
    });

    observer.observe(document.documentElement, {childList: true, subtree: true});
    document.addEventListener('DOMContentLoaded', function () { syncAll(document); });
})();
