// ── Avatar upload ──
const avatarInput = document.getElementById('avatar-input');
const avatarEditBtn = document.getElementById('avatar-edit-btn');
if (avatarEditBtn && avatarInput) {
    avatarEditBtn.addEventListener('click', () => avatarInput.click());
    avatarInput.addEventListener('change', () => document.getElementById('avatar-form').submit());
}

// ── Nav: click handler + scroll-spy ──
const navItems = document.querySelectorAll('.profile-nav-item');
const sections  = ['account', 'password', 'payslip', 'leave-credits'].map(id => document.getElementById(id)).filter(Boolean);

navItems.forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(link.getAttribute('href'));
        if (!target) return;
        navItems.forEach(n => n.classList.remove('active'));
        link.classList.add('active');
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        navItems.forEach(n => n.classList.remove('active'));
        const active = document.querySelector(`.profile-nav-item[href="#${entry.target.id}"]`);
        if (active) active.classList.add('active');
    });
}, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

sections.forEach(s => observer.observe(s));

// ── Eye toggle ──
document.querySelectorAll('.eye-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = show ? 'ti ti-eye-off' : 'ti ti-eye';
    });
});

// ── Password strength ──
const newPw = document.getElementById('new_password');
const fill = document.getElementById('strength-fill');
const label = document.getElementById('strength-label');
const confirmPw = document.getElementById('confirm_password');
const matchHint = document.getElementById('match-hint');

const levels = [
    { cls: 's1', text: 'Weak' },
    { cls: 's2', text: 'Fair' },
    { cls: 's3', text: 'Good' },
    { cls: 's4', text: 'Strong' },
];

function score(pw) {
    let s = 0;
    if (pw.length >= 8)  s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/\d/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return Math.min(Math.ceil(s / 5 * 4), 4);
}

if (newPw && fill && label) {
    newPw.addEventListener('input', () => {
        const v = newPw.value;
        fill.className  = 'strength-fill';
        label.className = 'strength-label';
        if (!v) { label.textContent = ''; return; }
        const lvl = levels[score(v) - 1] || levels[0];
        fill.classList.add(lvl.cls);
        label.classList.add(lvl.cls);
        label.textContent = lvl.text;
        checkMatch();
    });
}

function checkMatch() {
    if (!confirmPw || !matchHint) return;
    if (!confirmPw.value) { matchHint.textContent = ''; matchHint.className = 'field-hint'; return; }
    const ok = newPw.value === confirmPw.value;
    matchHint.textContent = ok ? '✓ Passwords match' : '✗ Passwords do not match';
    matchHint.className = 'field-hint ' + (ok ? 'match-ok' : 'match-err');
}

if (confirmPw) confirmPw.addEventListener('input', checkMatch);