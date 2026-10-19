(function () {
    const navBtns = document.querySelectorAll('.settings-nav-item');
    const panels = document.querySelectorAll('.settings-panel');

    // Restore last active tab from sessionStorage
    const saved = sessionStorage.getItem('ars_settings_tab');
    if (saved) activate(saved);

    navBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            activate(tab);
            sessionStorage.setItem('ars_settings_tab', tab);
        });
    });

    function activate(tab) {
        navBtns.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        panels.forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
    }

    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const inp = btn.closest('.input-password-wrap').querySelector('input');
            const isText = inp.type === 'text';
            inp.type = isText ? 'password' : 'text';
            btn.querySelector('i').className = isText ? 'ti ti-eye' : 'ti ti-eye-off';
        });
    });


    // Disk usage bar — apply --pct CSS custom property from data attribute.
    // Uses ArsStyle (a nonce'd managed stylesheet) instead of element.style,
    // since CSP's style-src-attr blocks style writes from JS just like it
    // blocks style="" in markup.
    document.querySelectorAll('.disk-usage-bar[data-pct]').forEach(el => {
        ArsStyle.setVars(el, { '--pct': el.dataset.pct + '%' });
    });
})();