// Geolocation Dashboard JS
(function () {
    'use strict';

    // Copy-Buttons
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.geo-copy-btn');
        if (!btn) return;

        var text = '';
        var blockWrapper = btn.closest('.geo-code-wrapper');
        if (blockWrapper) {
            var codeEl = blockWrapper.querySelector('code');
            text = codeEl ? codeEl.textContent : '';
        } else {
            text = btn.getAttribute('data-url') || '';
        }

        if (!text) return;
        
        navigator.clipboard.writeText(text.trim()).then(function () {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fa fa-check text-success';
                setTimeout(function () { icon.className = 'fa fa-copy'; }, 1500);
            }
        }).catch(function (err) {
            console.error('Copy failed', err);
        });
    });
})();
