(function () {
    'use strict';

    function normalizeButton(wrap) {
        if (!wrap) return;

        var button = wrap.querySelector('.hotelNextStep');
        if (!button) return;

        if (wrap.classList.contains('formshow')) {
            button.classList.add('orderOpen');
            if (button.textContent !== 'Об отеле') {
                button.textContent = 'Об отеле';
            }
            button.setAttribute('aria-expanded', 'true');
        } else {
            button.classList.remove('orderOpen');
            if (button.textContent !== 'Продолжить') {
                button.textContent = 'Продолжить';
            }
            button.setAttribute('aria-expanded', 'false');
        }
    }

    function normalizeAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var wraps = scope.querySelectorAll('.tourHotelWrap');
        for (var i = 0; i < wraps.length; i++) {
            normalizeButton(wraps[i]);
        }

        if (root && root.classList && root.classList.contains('tourHotelWrap')) {
            normalizeButton(root);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        normalizeAll(document);

        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];

                if (mutation.type === 'attributes') {
                    var target = mutation.target;
                    if (target.classList && target.classList.contains('tourHotelWrap')) {
                        normalizeButton(target);
                    }
                    continue;
                }

                if (mutation.type === 'childList') {
                    var node = mutation.target;
                    var wrap = node.closest ? node.closest('.tourHotelWrap') : null;
                    if (wrap) normalizeButton(wrap);

                    for (var j = 0; j < mutation.addedNodes.length; j++) {
                        var added = mutation.addedNodes[j];
                        if (added.nodeType === 1) normalizeAll(added);
                    }
                }
            }
        });

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class']
        });
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.hotelNextStep');
        if (!button) return;

        var wrap = button.closest('.tourHotelWrap');
        if (!wrap) return;

        window.setTimeout(function () { normalizeButton(wrap); }, 0);
        window.setTimeout(function () { normalizeButton(wrap); }, 50);
        window.setTimeout(function () { normalizeButton(wrap); }, 250);
        window.setTimeout(function () { normalizeButton(wrap); }, 800);
    });
})();
