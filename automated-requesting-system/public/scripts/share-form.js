(function () {
    var modal = document.getElementById('shareModal');
    if (!modal) return;

    var FORM_ID = window.SHARE_FORM_ID;
    var BASE = window.ARS_BASE || '';
    var csrf = function () {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };

    var listEl = document.getElementById('shareRecipientList');
    var searchEl = document.getElementById('shareRecipientSearch');
    var noteEl = document.getElementById('shareNote');
    var confirmBtn = document.getElementById('btn-share-confirm');
    var errorEl = document.getElementById('shareModalError');

    var users = [];
    var loaded = false;
    var selectedId = null;

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }
    function clearError() {
        errorEl.hidden = true;
        errorEl.textContent = '';
    }

    function renderList(filter) {
        var term = (filter || '').trim().toLowerCase();
        var filtered = users.filter(function (u) {
            return !term
                || u.full_name.toLowerCase().indexOf(term) !== -1
                || (u.department || '').toLowerCase().indexOf(term) !== -1;
        });

        listEl.innerHTML = '';
        if (!filtered.length) {
            var empty = document.createElement('div');
            empty.className = 'share-recipient-empty';
            empty.textContent = 'No matching people.';
            listEl.appendChild(empty);
            return;
        }

        filtered.forEach(function (u) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'share-recipient-row' + (selectedId === u.id ? ' share-recipient-row--selected' : '');
            row.innerHTML =
                '<img class="share-recipient-avatar" src="' + u.avatar + '" alt="">' +
                '<span class="share-recipient-name">' + escapeHtml(u.full_name) +
                (u.department ? ' <span class="share-recipient-dept">· ' + escapeHtml(u.department) + '</span>' : '') +
                '</span>';
            row.addEventListener('click', function () {
                selectedId = u.id;
                confirmBtn.disabled = false;
                renderList(searchEl.value);
            });
            listEl.appendChild(row);
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function loadUsers() {
        if (loaded) return;
        listEl.innerHTML = '<div class="share-recipient-empty">Loading…</div>';
        fetch(BASE + '/chat/users', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                users = Array.isArray(res) ? res : [];
                loaded = true;
                renderList('');
            })
            .catch(function () {
                listEl.innerHTML = '<div class="share-recipient-empty">Could not load people. Try again.</div>';
            });
    }

    // Reset + load whenever the modal is opened
    document.addEventListener('click', function (e) {
        if (e.target.closest('#btn-share-trigger')) {
            selectedId = null;
            confirmBtn.disabled = true;
            clearError();
            noteEl.value = '';
            searchEl.value = '';
            loadUsers();
        }
    });

    searchEl.addEventListener('input', function () {
        renderList(searchEl.value);
    });

    confirmBtn.addEventListener('click', function () {
        if (!selectedId || !FORM_ID) return;
        clearError();
        confirmBtn.disabled = true;

        var fd = new FormData();
        fd.append('csrf_token', csrf());
        fd.append('receiver_id', selectedId);
        fd.append('form_id', FORM_ID);
        fd.append('message', noteEl.value.trim());

        var base = BASE;
        fetch(base + '/chat/share-form', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (!res.ok) {
                    showError(res.data.error || 'Could not share this form. Please try again.');
                    confirmBtn.disabled = false;
                    return;
                }
                window.location.href = base + '/chat?with=' + selectedId;
            })
            .catch(function () {
                showError('Network error. Please try again.');
                confirmBtn.disabled = false;
            });
    });
})();

// ── Attachment drop zone ──
(function () {
    const drop = document.getElementById('attachDrop');
    const input = document.getElementById('attachInput');
    const list = document.getElementById('attachNewList');
    if (!drop || !input || !list) return;

    const MAX = 20 * 1024 * 1024;
    const ALLOWED = ['image/jpeg','image/png','application/pdf'];

    function renderFile(file, idx) {
        const isImg = file.type.startsWith('image/');
        const size  = (file.size / 1024).toFixed(0) + ' KB';
        const item  = document.createElement('div');
        item.className = 'attach-item';
        item.id = 'new-' + idx;

        const thumb = isImg
            ? `<img src="${URL.createObjectURL(file)}" class="attach-thumb" alt="">`
            : `<span class="attach-icon"><i class="ti ti-file-type-pdf"></i></span>`;

        item.innerHTML = `
            ${thumb}
            <div class="attach-info">
                <span class="attach-name">${file.name}</span>
                <span class="attach-size">${size}</span>
            </div>
            <div class="attach-actions">
                <button type="button" class="attach-btn attach-btn--danger"
                        title="Remove" data-remove-new="${idx}">
                    <i class="ti ti-trash"></i>
                </button>
            </div>`;
        list.appendChild(item);
    }

    // Drag events
    drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('is-dragover'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-dragover'));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        drop.classList.remove('is-dragover');
        handleFiles(e.dataTransfer.files);
    });

    input.addEventListener('change', () => handleFiles(input.files));

    function handleFiles(files) {
        Array.from(files).forEach((f, i) => {
            if (!ALLOWED.includes(f.type) || f.size > MAX) {
                alert(`"${f.name}" is invalid or exceeds 20 MB.`);
                return;
            }
            renderFile(f, Date.now() + i);
        });
    }

    // Remove newly picked file
    list.addEventListener('click', e => {
        const btn = e.target.closest('[data-remove-new]');
        if (btn) btn.closest('.attach-item').remove();
    });

    // Remove already-saved file
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-remove-saved]');
        if (!btn) return;
        const idx = btn.dataset.removeSaved;
        document.getElementById('saved-' + idx)?.remove();
        document.getElementById('saved-input-' + idx)?.remove();
    });
})();