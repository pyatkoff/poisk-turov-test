document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[name="searchForm"]');
    var buttonWrap = document.getElementById('sendBtnWrapWrap');

    if (form && buttonWrap && buttonWrap.parentNode !== form) {
        form.appendChild(buttonWrap);
    }
});
