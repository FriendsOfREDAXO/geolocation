// Geolocation Dashboard JS
(function () {
    'use strict';

    // Copy-Buttons
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.geo-copy-btn');
        if (!btn) return;
        var url = btn.getAttribute('data-url');
        if (!url) return;
        navigator.clipboard.writeText(url).then(function () {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fa fa-check';
                setTimeout(function () { icon.className = 'fa fa-copy'; }, 1500);
            }
        }).catch(function (err) {
            console.error('Copy failed', err);
        });
    });
})();
