document.addEventListener('DOMContentLoaded', function () {
    function enhanceVariant(variant) {
        if (!variant || variant.dataset.choiceEnhanced === '1') return;
        variant.dataset.choiceEnhanced = '1';

        var price = variant.querySelector('.airTourPrice');
        if (!price) return;

        var action = document.createElement('button');
        action.type = 'button';
        action.className = 'testChooseTourBtn';
        action.textContent = 'Выбрать тур';
        action.setAttribute('aria-label', 'Выбрать этот вариант тура');
        price.appendChild(action);

        action.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            /* Reuse the existing legacy selection flow unchanged. */
            if (window.jQuery) {
                window.jQuery(variant).trigger('click');
            } else {
                variant.dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
            }
        });
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('.hotel_tours .airTourVar').forEach(enhanceVariant);
    }

    enhanceAll(document);

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                if (node.matches && node.matches('.hotel_tours .airTourVar')) enhanceVariant(node);
                enhanceAll(node);
            });
        });
    });

    observer.observe(document.body, {childList: true, subtree: true});
});
