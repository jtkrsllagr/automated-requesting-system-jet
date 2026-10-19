document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();

    // ── Tab switcher ──
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-pane').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('pane-' + tab.dataset.tab).classList.add('active');
        });
    });

    // ── Password toggles ──
    function setupToggle(btnId, inputId, iconId) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', () => {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input || !icon) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
            lucide.createIcons();
        });
    }

    setupToggle('toggleBtn', 'login_password', 'eyeIcon');
});
