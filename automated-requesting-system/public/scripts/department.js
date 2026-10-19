const search = document.getElementById('deptSearch');
const subTitle = document.querySelector('.dept-panel-sub');
const allItems = () => document.querySelectorAll('.dept-item');
const esc = s => String(s).replace(/&/g,'&').replace(/</g,'<').replace(/>/g,'>').replace(/"/g,'"');

// ── Add dialog ──
const addDialog = document.getElementById('addDialog');
document.getElementById('openAddModalBtn').addEventListener('click', () => { addDialog.showModal(); document.getElementById('addName').focus(); });
document.getElementById('emptyCreateBtn').addEventListener('click', () => { addDialog.showModal(); document.getElementById('addName').focus(); });
document.getElementById('addCancelBtn').addEventListener('click', () => addDialog.close());
addDialog.addEventListener('click', e => { if (e.target === addDialog) addDialog.close(); });

// ── List click ──
document.getElementById('deptList').addEventListener('click', e => {
    const li = e.target.closest('.dept-item');
    if (!li) return;
    selectDept(li, li.dataset.id, li.dataset.label, li.dataset.count);
});

// ── Keyboard navigation ──
document.getElementById('deptList').addEventListener('keydown', e => {
    if (!['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) return;
    e.preventDefault();
    const items = [...allItems()].filter(li => !li.classList.contains('d-none'));
    if (!items.length) return;
    const active = document.querySelector('.dept-item--active');
    const idx = items.indexOf(active);
    if (e.key === 'ArrowDown') { const n = items[idx + 1] ?? items[0]; n.focus(); n.click(); }
    else if (e.key === 'ArrowUp') { const p = items[idx - 1] ?? items[items.length - 1]; p.focus(); p.click(); }
    else if ((e.key === 'Enter' || e.key === ' ') && active) active.click();
});

// ── ⋯ Overflow menu ──
const moreBtn = document.getElementById('detailMoreBtn');
const overflowMenu = document.getElementById('deptOverflowMenu');

moreBtn.addEventListener('click', e => {
    e.stopPropagation();
    const open = !overflowMenu.classList.contains('dept-hidden');
    overflowMenu.classList.toggle('dept-hidden', open);
    moreBtn.setAttribute('aria-expanded', String(!open));
});
document.addEventListener('click', () => {
    overflowMenu.classList.add('dept-hidden');
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
        if (li.classList.contains('dept-item--active') && !match) {
            li.classList.remove('dept-item--active');
            document.getElementById('deptDetailContent').classList.add('dept-hidden');
            document.getElementById('deptDetailEmpty').classList.remove('dept-hidden');
            closeEdit();
        }
    });
    subTitle.textContent = visible + ' total';
});

// ── Flash auto-dismiss ──
document.querySelectorAll('.dept-flash').forEach(el => {
    setTimeout(() => el.classList.add('ars-fade-out'), 3000);
    setTimeout(() => el.remove(), 3400);
});

// ── Right panel select ──
function selectDept(el, id, name, count = 0) {
    allItems().forEach(li => li.classList.remove('dept-item--active'));
    el.classList.add('dept-item--active');

    document.querySelector('.dept-detail-icon').textContent = el.dataset.initial;
    document.getElementById('deptDetailEmpty').classList.add('dept-hidden');
    document.getElementById('deptDetailContent').classList.remove('dept-hidden');
    document.getElementById('detailName').textContent = name;
    document.getElementById('detailId').textContent = 'Since ' + (el.dataset.created ?? '—');
    document.getElementById('detailCount').textContent = count;
    document.getElementById('detailForms').textContent = el.dataset.forms ?? 0;

    document.getElementById('detailDeleteForm').action = window.ARS_BASE + '/departments/' + parseInt(id, 10) + '/delete';
    document.getElementById('detailEditBtn').onclick = () => openEdit(id, name);
    document.getElementById('detailEditBtn').disabled = false;
    document.getElementById('detailDeleteBtn').disabled = false;

    closeEdit();

    const list = document.getElementById('deptMembersList');
    const badge = document.getElementById('deptMembersCount');
    list.innerHTML = '<li class="dept-members-loading"><i class="ti ti-loader-2 spin"></i> Loading…</li>';

    fetch(window.ARS_BASE + '/departments/' + parseInt(id, 10) + '/members')
        .then(r => r.json())
        .then(members => {
            badge.textContent = members.length;
            if (!members.length) {
                list.innerHTML = '<li class="dept-members-empty">No members yet.</li>';
                return;
            }
            const roleLabels = {1:'Admin',2:'Approver',3:'Staff',4:'Dept. Head',5:'Checker',6:'Final Approver'};
            list.innerHTML = members.map(m => `
                <li class="dept-member-item">
                    <div class="dept-member-avatar">${esc(m.full_name.charAt(0).toUpperCase())}</div>
                    <div class="dept-member-body">
                        <span class="dept-member-name">${esc(m.full_name)}</span>
                        <span class="dept-member-meta">${esc(m.employee_code)} · ${esc(roleLabels[m.role_id] ?? 'User')}</span>
                    </div>
                </li>`).join('');
        })
        .catch(() => { list.innerHTML = '<li class="dept-members-empty">Failed to load.</li>'; });
}

// ── Delete confirm dialog ──
const confirmDialog = document.getElementById('confirmDeleteDialog');
const confirmDeptName = document.getElementById('confirmDeptName');
const deleteForm = document.getElementById('detailDeleteForm');

document.getElementById('detailDeleteBtn').addEventListener('click', () => {
    confirmDeptName.textContent = document.getElementById('detailName').textContent;
    confirmDialog.showModal();
});
document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
    confirmDialog.close();
    deleteForm.submit();
});
document.getElementById('confirmCancelBtn').addEventListener('click', () => confirmDialog.close());
confirmDialog.addEventListener('click', e => { if (e.target === confirmDialog) confirmDialog.close(); });

// ── Inline rename bar ──
const renameBar = document.querySelector('.dept-rename-bar');
const detailName = document.getElementById('detailName');
const detailId = document.getElementById('detailId');
const renameInput = document.getElementById('editName');
const renameCancelBtn = document.getElementById('renameCancelBtn');

function openEdit(id, name) {
    document.getElementById('editForm').action =
        window.ARS_BASE + '/departments/' + parseInt(id, 10) + '/update';
    renameInput.value = name;
    renameBar.classList.remove('dept-hidden');
    detailName.classList.add('dept-hidden');
    detailId.classList.add('dept-hidden');
    renameInput.select();
    renameInput.focus();
}

function closeEdit() {
    renameBar.classList.add('dept-hidden');
    detailName.classList.remove('dept-hidden');
    detailId.classList.remove('dept-hidden');
}

renameCancelBtn.addEventListener('click', closeEdit);
renameInput.addEventListener('keydown', e => { if (e.key === 'Escape') closeEdit(); });

// ── Auto-select after reload ──
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('deptList');
    const autoId = list?.dataset.autoSelect;
    if (autoId) {
        const li = document.querySelector(`.dept-item[data-id="${parseInt(autoId, 10)}"]`);
        if (li) { li.click(); li.scrollIntoView({ block: 'nearest' }); }
    }
});