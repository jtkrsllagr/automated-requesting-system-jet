const search = document.getElementById('compSearch');
const subTitle = document.querySelector('.comp-panel-sub');
const allItems = () => document.querySelectorAll('.comp-item');
const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

// ── Add dialog ──
const addDialog = document.getElementById('addDialog');
document.getElementById('openAddModalBtn').addEventListener('click', () => { addDialog.showModal(); document.getElementById('addName').focus(); });
document.getElementById('emptyCreateBtn').addEventListener('click', () => { addDialog.showModal(); document.getElementById('addName').focus(); });
document.getElementById('addCancelBtn').addEventListener('click', () => addDialog.close());
addDialog.addEventListener('click', e => { if (e.target === addDialog) addDialog.close(); });

document.getElementById('addLogo')?.addEventListener('change', e => {
    const label = document.getElementById('addLogoFileName');
    label.textContent = e.target.files[0]?.name || 'Add a logo (optional)';
});

// ── Detail panel: change logo ──
document.getElementById('logoInput')?.addEventListener('change', () => {
    document.getElementById('logoForm').submit();
});

// ── List click ──
document.getElementById('compList').addEventListener('click', e => {
    const li = e.target.closest('.comp-item');
    if (!li) return;
    selectCompany(li, li.dataset.id, li.dataset.label, li.dataset.count);
});

// ── Keyboard navigation ──
document.getElementById('compList').addEventListener('keydown', e => {
    if (!['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) return;
    e.preventDefault();
    const items = [...allItems()].filter(li => !li.classList.contains('d-none'));
    if (!items.length) return;
    const active = document.querySelector('.comp-item--active');
    const idx = items.indexOf(active);
    if (e.key === 'ArrowDown') { const n = items[idx + 1] ?? items[0]; n.focus(); n.click(); }
    else if (e.key === 'ArrowUp') { const p = items[idx - 1] ?? items[items.length - 1]; p.focus(); p.click(); }
    else if ((e.key === 'Enter' || e.key === ' ') && active) active.click();
});

// ── ⋯ Overflow menu ──
const moreBtn = document.getElementById('detailMoreBtn');
const overflowMenu = document.getElementById('compOverflowMenu');

moreBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = !overflowMenu.classList.contains('comp-hidden');
    overflowMenu.classList.toggle('comp-hidden', open);
    moreBtn.setAttribute('aria-expanded', String(!open));
});
document.addEventListener('click', () => {
    overflowMenu.classList.add('comp-hidden');
    moreBtn.setAttribute('aria-expanded', 'false');
});

// ── Search ──
search?.addEventListener('input', () => {
    const q = search.value.toLowerCase();
    let visible = 0;
    allItems().forEach(li => {
        const match = li.dataset.name.includes(q);
        li.classList.toggle('d-none', !match);
        if (match) visible++;
        if (li.classList.contains('comp-item--active') && !match) {
            li.classList.remove('comp-item--active');
            document.getElementById('compDetailContent').classList.add('comp-hidden');
            document.getElementById('compDetailEmpty').classList.remove('comp-hidden');
            closeEdit();
        }
    });
    subTitle.textContent = visible + ' total';
});

// ── Flash auto-dismiss ──
document.querySelectorAll('.comp-flash').forEach(el => {
    setTimeout(() => el.classList.add('ars-fade-out'), 3000);
    setTimeout(() => el.remove(), 3400);
});

// ── Right panel select ──
function selectCompany(el, id, name, count = 0) {
    allItems().forEach(li => li.classList.remove('comp-item--active'));
    el.classList.add('comp-item--active');

    const logoUrl = el.dataset.logo;
    const iconEl = document.getElementById('detailIcon');
    const logoEl = document.getElementById('detailLogo');
    if (logoUrl) {
        logoEl.src = logoUrl;
        logoEl.classList.remove('comp-hidden');
        iconEl.classList.add('comp-hidden');
    } else {
        logoEl.classList.add('comp-hidden');
        iconEl.classList.remove('comp-hidden');
        iconEl.textContent = el.dataset.initial;
    }

    document.getElementById('compDetailEmpty').classList.add('comp-hidden');
    document.getElementById('compDetailContent').classList.remove('comp-hidden');
    document.getElementById('detailName').textContent = name;
    document.getElementById('detailId').textContent = 'Since ' + (el.dataset.created ?? '—');
    document.getElementById('detailCount').textContent = count;

    document.getElementById('detailDeleteForm').action = window.ARS_BASE + '/companies/' + parseInt(id, 10) + '/delete';
    document.getElementById('logoForm').action = window.ARS_BASE + '/companies/' + parseInt(id, 10) + '/logo';
    document.getElementById('detailEditBtn').onclick = () => openEdit(id, name);
    document.getElementById('detailEditBtn').disabled = false;
    document.getElementById('detailDeleteBtn').disabled = false;

    closeEdit();

    const list = document.getElementById('compMembersList');
    const badge = document.getElementById('compMembersCount');
    list.innerHTML = '<li class="comp-members-loading"><i class="ti ti-loader-2 spin"></i> Loading…</li>';

    fetch(window.ARS_BASE + '/companies/' + parseInt(id, 10) + '/members')
        .then(r => r.json())
        .then(members => {
            badge.textContent = members.length;
            if (!members.length) {
                list.innerHTML = '<li class="comp-members-empty">No members yet.</li>';
                return;
            }
            const roleLabels = {1:'Admin',2:'Approver',3:'Staff',4:'Dept. Head',5:'Checker',6:'Final Approver'};
            list.innerHTML = members.map(m => `
                <li class="comp-member-item">
                    <div class="comp-member-avatar">${esc(m.full_name.charAt(0).toUpperCase())}</div>
                    <div class="comp-member-body">
                        <span class="comp-member-name">${esc(m.full_name)}</span>
                        <span class="comp-member-meta">${esc(m.employee_code)} · ${esc(roleLabels[m.role_id] ?? 'User')}</span>
                    </div>
                </li>`).join('');
        })
        .catch(() => { list.innerHTML = '<li class="comp-members-empty">Failed to load.</li>'; });
}

// ── Delete confirm dialog ──
const confirmDialog = document.getElementById('confirmDeleteDialog');
const confirmCompName = document.getElementById('confirmCompName');
const deleteForm = document.getElementById('detailDeleteForm');

document.getElementById('detailDeleteBtn').addEventListener('click', () => {
    confirmCompName.textContent = document.getElementById('detailName').textContent;
    confirmDialog.showModal();
});
document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
    confirmDialog.close();
    deleteForm.submit();
});
document.getElementById('confirmCancelBtn').addEventListener('click', () => confirmDialog.close());
confirmDialog.addEventListener('click', e => { if (e.target === confirmDialog) confirmDialog.close(); });

// ── Inline rename bar ──
const renameBar = document.querySelector('.comp-rename-bar');
const detailName = document.getElementById('detailName');
const detailId = document.getElementById('detailId');
const renameInput = document.getElementById('editName');
const renameCancelBtn = document.getElementById('renameCancelBtn');

function openEdit(id, name) {
    document.getElementById('editForm').action =
        window.ARS_BASE + '/companies/' + parseInt(id, 10) + '/update';
    renameInput.value = name;
    renameBar.classList.remove('comp-hidden');
    detailName.classList.add('comp-hidden');
    detailId.classList.add('comp-hidden');
    renameInput.select();
    renameInput.focus();
}

function closeEdit() {
    renameBar.classList.add('comp-hidden');
    detailName.classList.remove('comp-hidden');
    detailId.classList.remove('comp-hidden');
}

renameCancelBtn.addEventListener('click', closeEdit);
renameInput.addEventListener('keydown', e => { if (e.key === 'Escape') closeEdit(); });

// ── Auto-select after reload ──
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('compList');
    const autoId = list?.dataset.autoSelect;
    if (autoId) {
        const li = document.querySelector(`.comp-item[data-id="${parseInt(autoId, 10)}"]`);
        if (li) { li.click(); li.scrollIntoView({ block: 'nearest' }); }
    }
});
