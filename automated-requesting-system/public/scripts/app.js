/**
 * app.js
 * Global UI behaviour — loaded at the bottom of every page via base.php.
 */

// ── Lucide icons ── //
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
});

// ── Notification panel ── //
function toggleNotif() {
    document.getElementById('notifPanel').classList.toggle('open');
}

// ── Password visibility toggles (global) ── //
// Usage: <button type="button" class="toggle-icon" data-toggle-password="inputId">
//            <i data-lucide="eye" data-toggle-password-icon></i>
//        </button>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;

    const input = document.getElementById(btn.dataset.togglePassword);
    const icon = btn.querySelector('[data-toggle-password-icon]') || btn.querySelector('i');
    if (!input || !icon) return;

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.setAttribute('data-lucide', show ? 'eye-off' : 'eye');
    if (window.lucide) lucide.createIcons();
});

document.addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-record-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, form')) return;
    window.location.href = row.dataset.recordHref;
});

document.addEventListener('keydown', function (e) {
    var row = e.target.closest('tr[data-record-href]');
    if (!row || (e.key !== 'Enter' && e.key !== ' ')) return;
    e.preventDefault();
    window.location.href = row.dataset.recordHref;
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('#notifPanel') && !e.target.closest('#notifBtn')) {
        document.getElementById('notifPanel')?.classList.remove('open');
    }
});

// ── Sidebar toggle (desktop collapse + mobile overlay) ── //
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebar = document.getElementById('sidebar');
var SIDEBAR_KEY = 'sidebar_collapsed';

if (sidebar && window.innerWidth > 900) {
   
    var lsVal = localStorage.getItem(SIDEBAR_KEY);
    if (lsVal === 'true') {
        sidebar.classList.add('collapsed');
    } else if (lsVal === 'false') {
        sidebar.classList.remove('collapsed');
    }
    // lsVal === null means first visit or cleared — DB value (class already set) wins.
}

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        if (window.innerWidth <= 900) {
            sidebar.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem(SIDEBAR_KEY, sidebar.classList.contains('collapsed'));
            updateToggleIcon();
        }
    });
}

// mobile hamburger button in the topbar — the only entry point to open
// the sidebar on mobile, since the in-sidebar toggle is off-screen until opened.
// Delegated on document (not a direct getElementById + addEventListener) so it
// keeps working regardless of script load order or any re-render of the topbar.
document.addEventListener('click', function (e) {
    var btn = e.target.closest('#mobileMenuBtn');
    if (!btn || !sidebar) return;
    e.preventDefault();
    e.stopPropagation();
    sidebar.classList.add('open');
});

// close mobile overlay on outside click (ignore the button that opens it,
// and clicks inside the sidebar itself)
document.addEventListener('click', function (e) {
    if (window.innerWidth <= 900 && sidebar
        && !e.target.closest('#sidebar')
        && !e.target.closest('#mobileMenuBtn')) {
        sidebar.classList.remove('open');
    }
});

// swap toggle icon based on collapsed state
function updateToggleIcon() {
    var icon = document.getElementById('sidebarToggleIcon');
    if (!icon || !sidebar) return;
    icon.className = sidebar.classList.contains('collapsed')
        ? 'ti ti-layout-sidebar-left-expand'
        : 'ti ti-layout-sidebar-left-collapse'
}

// sync icon on page load
updateToggleIcon();

// on resize: remove collapsed when switching to mobile
window.addEventListener('resize', function () {
    if (!sidebar) return;
    if (window.innerWidth <= 900) {
        sidebar.classList.remove('collapsed');
    }
    updateToggleIcon();
});

// ── Sidebar group dropdown ── //
document.addEventListener('DOMContentLoaded', function () {
    var GROUPS_KEY = 'sidebar_groups';
    document.querySelectorAll('.sidebar-group').forEach(function (group) {
        group.classList.remove('collapsed');
    });

    // toggle on label click
    document.querySelectorAll('.sidebar-label--toggle').forEach(function (label) {
        label.addEventListener('click', function () {
            var group = document.querySelector('.sidebar-group[data-group="' + label.dataset.toggle + '"]');
            if (!group) return;
            group.classList.toggle('collapsed');
            try {
                var state = JSON.parse(localStorage.getItem(GROUPS_KEY) || '{}');
                state[label.dataset.toggle] = group.classList.contains('collapsed');
                localStorage.setItem(GROUPS_KEY, JSON.stringify(state));
            } catch (e) {}
        });
    });

    // ── Logout confirmation ── //
    var logoutForm = document.getElementById('logoutForm');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function (e) {
            if (!confirm('Sign out of your account?')) {
                e.preventDefault();
            }
        });
    }
});

// ── Notification button wiring ── //
document.addEventListener('DOMContentLoaded', function () {
    var notifBtn = document.getElementById('notifBtn');
    if (notifBtn) notifBtn.addEventListener('click', toggleNotif);

    var markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(window.ARS_BASE + '/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            }).then(function () {
                document.querySelectorAll('.notif-item--unread').forEach(function (el) {
                    el.classList.remove('notif-item--unread');
                });
                document.querySelectorAll('.notif-dot-sm').forEach(function (d) {
                    d.classList.add('notif-dot-sm--read');
                });
                var dot = document.getElementById('notifDot');
                if (dot) dot.classList.add('d-none');
            });
        });
    }

    document.querySelectorAll('.notif-item[data-notif-id]').forEach(function (item) {
        item.addEventListener('click', function () {
            var id = item.dataset.notifId;
            var href = item.dataset.href;
            if (item.classList.contains('notif-item--unread')) {
                item.classList.remove('notif-item--unread');
                fetch(window.ARS_BASE + '/notifications/' + id + '/read', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                });
            }
            if (href) window.location.href = href;
        });
    });

    function pollNotifications() {
        fetch(window.ARS_BASE + '/notifications/unread')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var dot = document.getElementById('notifDot');
                if (dot) dot.classList.toggle('d-none', !(data.unread_count > 0));
                var badge = document.querySelector('.badge-count');
                if (badge && data.pending_count !== undefined) {
                    badge.textContent = data.pending_count;
                    badge.classList.toggle('d-none', !(data.pending_count > 0));
                }
                var dashboardPendingKpi = document.getElementById('dashboardPendingKpi');
                if (dashboardPendingKpi && data.pending_count !== undefined) {
                    dashboardPendingKpi.textContent = data.pending_count;
                }
                var dashboardOverdueKpi = document.getElementById('dashboardOverdueKpi');
                if (dashboardOverdueKpi && data.overdue_count !== undefined) {
                    dashboardOverdueKpi.textContent = data.overdue_count;
                }
                var dashboardAwaitingApprovalKpi = document.getElementById('dashboardAwaitingApprovalKpi');
                if (dashboardAwaitingApprovalKpi && data.awaiting_approval_count !== undefined) {
                    dashboardAwaitingApprovalKpi.textContent = data.awaiting_approval_count;
                }
                var dashboardPostApprovalKpi = document.getElementById('dashboardPostApprovalKpi');
                if (dashboardPostApprovalKpi && data.post_approval_count !== undefined) {
                    dashboardPostApprovalKpi.textContent = data.post_approval_count;
                }
                var dashboardCompletedKpi = document.getElementById('dashboardCompletedSortCount');
                if (dashboardCompletedKpi && data.completed_count !== undefined) {
                    dashboardCompletedKpi.textContent = data.completed_count;
                }
                var dashboardApprovedKpi = document.getElementById('dashboardApprovedSortCount');
                if (dashboardApprovedKpi && data.approved_count !== undefined) {
                    dashboardApprovedKpi.textContent = data.approved_count;
                }
                var dashboardRejectedKpi = document.getElementById('dashboardRejectedKpi');
                if (dashboardRejectedKpi && data.rejected_count !== undefined) {
                    dashboardRejectedKpi.textContent = data.rejected_count;
                }
            })
            .catch(function () {});
    }

    function refreshDashboardPending() {
        var dashboardPendingKpi = document.getElementById('dashboardPendingKpi');
        var dashboardOverdueKpi = document.getElementById('dashboardOverdueKpi');
        var dashboardAwaitingApprovalKpi = document.getElementById('dashboardAwaitingApprovalKpi');
        var dashboardPostApprovalKpi = document.getElementById('dashboardPostApprovalKpi');
        var dashboardCompletedKpi = document.getElementById('dashboardCompletedSortCount');
        var dashboardApprovedKpi = document.getElementById('dashboardApprovedSortCount');
        var dashboardRejectedKpi = document.getElementById('dashboardRejectedKpi');
        if (!dashboardPendingKpi && !dashboardOverdueKpi && !dashboardAwaitingApprovalKpi && !dashboardPostApprovalKpi
            && !dashboardCompletedKpi && !dashboardApprovedKpi && !dashboardRejectedKpi) return;

        fetch(window.ARS_BASE + '/notifications/unread?pending_only=1', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (dashboardPendingKpi && data.pending_count !== undefined) {
                    dashboardPendingKpi.textContent = data.pending_count;
                    var badge = document.querySelector('.badge-count');
                    if (badge) {
                        badge.textContent = data.pending_count;
                        badge.classList.toggle('d-none', !(data.pending_count > 0));
                    }
                }
                if (dashboardOverdueKpi && data.overdue_count !== undefined) {
                    dashboardOverdueKpi.textContent = data.overdue_count;
                }
                if (dashboardAwaitingApprovalKpi && data.awaiting_approval_count !== undefined) {
                    dashboardAwaitingApprovalKpi.textContent = data.awaiting_approval_count;
                }
                if (dashboardPostApprovalKpi && data.post_approval_count !== undefined) {
                    dashboardPostApprovalKpi.textContent = data.post_approval_count;
                }
                if (dashboardCompletedKpi && data.completed_count !== undefined) {
                    dashboardCompletedKpi.textContent = data.completed_count;
                }
                if (dashboardApprovedKpi && data.approved_count !== undefined) {
                    dashboardApprovedKpi.textContent = data.approved_count;
                }
                if (dashboardRejectedKpi && data.rejected_count !== undefined) {
                    dashboardRejectedKpi.textContent = data.rejected_count;
                }
            })
            .catch(function () {});
    }

    var dashboardPendingRefreshTimer = null;
    function scheduleDashboardPendingRefresh() {
        window.clearTimeout(dashboardPendingRefreshTimer);
        dashboardPendingRefreshTimer = window.setTimeout(refreshDashboardPending, 150);
    }

    function connectDashboardRealtime(retryDelay) {
        if (!document.getElementById('dashboardPendingKpi')
            && !document.getElementById('dashboardOverdueKpi')
            && !document.getElementById('dashboardAwaitingApprovalKpi')
            && !document.getElementById('dashboardPostApprovalKpi')
            && !document.getElementById('dashboardCompletedSortCount')
            && !document.getElementById('dashboardApprovedSortCount')
            && !document.getElementById('dashboardRejectedKpi')) return;

        fetch(window.ARS_BASE + '/realtime/token', { cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) throw new Error('Realtime token unavailable.');
                return r.json();
            })
            .then(function (data) {
                var eventUrl = new URL('/events', window.location.origin);
                eventUrl.port = '8443';
                eventUrl.searchParams.set('token', data.token);

                var events = new EventSource(eventUrl);
                events.addEventListener('pending-count-changed', function () {
                    scheduleDashboardPendingRefresh();
                });
                events.onerror = function () {
                    events.close();
                    window.setTimeout(function () {
                        connectDashboardRealtime(Math.min(retryDelay * 2, 60000));
                    }, retryDelay);
                };
            })
            .catch(function () {
                window.setTimeout(function () {
                    connectDashboardRealtime(Math.min(retryDelay * 2, 60000));
                }, retryDelay);
            });
    }

    function connectApprovalTrailRealtime(retryDelay, receivedInitialEvent) {
        if (!document.querySelector('.approval-trail')) return;

        fetch(window.ARS_BASE + '/realtime/token', { cache: 'no-store' })
            .then(function (r) {
                if (!r.ok) throw new Error('Realtime token unavailable.');
                return r.json();
            })
            .then(function (data) {
                var eventUrl = new URL('/events', window.location.origin);
                eventUrl.port = '8443';
                eventUrl.searchParams.set('token', data.token);

                var events = new EventSource(eventUrl);
                var hasReceivedInitialEvent = receivedInitialEvent;
                events.addEventListener('pending-count-changed', function () {
                    if (!hasReceivedInitialEvent) {
                        hasReceivedInitialEvent = true;
                        return;
                    }
                    window.location.reload();
                });
                events.onerror = function () {
                    events.close();
                    window.setTimeout(function () {
                        connectApprovalTrailRealtime(Math.min(retryDelay * 2, 60000), hasReceivedInitialEvent);
                    }, retryDelay);
                };
            })
            .catch(function () {
                window.setTimeout(function () {
                    connectApprovalTrailRealtime(Math.min(retryDelay * 2, 60000), receivedInitialEvent);
                }, retryDelay);
            });
    }

    pollNotifications();
    setInterval(pollNotifications, 60000);
    if (document.getElementById('dashboardPendingKpi')
        || document.getElementById('dashboardOverdueKpi')
        || document.getElementById('dashboardAwaitingApprovalKpi')
        || document.getElementById('dashboardPostApprovalKpi')
        || document.getElementById('dashboardCompletedSortCount')
        || document.getElementById('dashboardApprovedSortCount')
        || document.getElementById('dashboardRejectedKpi')) {
        connectDashboardRealtime(3000);
        window.setInterval(refreshDashboardPending, 15000);
    }
    if (document.querySelector('.approval-trail')) {
        connectApprovalTrailRealtime(3000, false);
    }
});

// ── Default today's date on date inputs ── //
document.querySelectorAll('input[type="date"]:not([value])').forEach(function (el) {
    if (!el.closest('[data-no-default]')) {
        el.value = new Date().toISOString().split('T')[0];
    }
});

// ── Dynamic CSS ── //
document.querySelectorAll('.activity-icon-dynamic[data-bg]').forEach(function (el) {
    ArsStyle.setVars(el, { '--icon-bg': el.dataset.bg, '--icon-color': el.dataset.color });
});

document.querySelectorAll('.qf-icon[data-color]').forEach(function (el) {
    ArsStyle.setVars(el, { '--qf-color': el.dataset.color });
});

document.querySelectorAll('.vol-fill[data-pct]').forEach(function (el) {
    ArsStyle.setVars(el, { width: el.dataset.pct + '%', background: el.dataset.color });
});

// ── Status filter ── //
document.addEventListener('DOMContentLoaded', function () {
    var inApproval = [ 'submitted', 'checker_approved', 'process_approved', 'department_reviewed', 'finance_reviewed', 'final_approved' ];

    function applyStatusFilter(val) {
        document.querySelectorAll('table[data-filterable] tbody tr').forEach(function (row) {
            if (!val) { row.classList.remove('d-none'); return; }
            var s = row.dataset.status || '';
            var show;
            if (val === 'in_approval') {
                show = inApproval.includes(s);
            } else if (val === 'approved') {
                show = (s === 'final_approved' || s === 'completed');
            } else {
                show = s === val;
            }
            row.classList.toggle('d-none', !show);
        });
    }

    var sel = document.getElementById('statusFilter');
    if (sel) {
        sel.addEventListener('change', function () { applyStatusFilter(sel.value); });
        if (sel.value) applyStatusFilter(sel.value);
    }
});

// ── Back button ── //
document.querySelectorAll('.js-go-back').forEach(btn => {
    btn.addEventListener('click', function () { history.back(); });
});

// New Request dropdown toggle
(function () {
    var newReqBtn = document.getElementById('newReqBtn');
    var newReqDropdown = document.getElementById('newReqDropdown');
    if (!newReqBtn || !newReqDropdown) return;
 
    newReqBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isHidden = newReqDropdown.classList.contains('dept-hidden');
        newReqDropdown.classList.toggle('dept-hidden', !isHidden);
        newReqBtn.setAttribute('aria-expanded', String(isHidden));
    });
 
    document.addEventListener('click', function () {
        newReqDropdown.classList.add('dept-hidden');
        newReqBtn.setAttribute('aria-expanded', 'false');
    });
})();

// ── User block / profile menu ── //
(function () {
    var block = document.getElementById('userBlock');
    var menu = document.getElementById('userMenu');
    if (!block || !menu) return;
 
    block.addEventListener('click', function () {
        var isOpen = menu.classList.toggle('open');
        block.classList.toggle('open', isOpen);
    });
 
    document.addEventListener('click', function (e) {
        if (!block.contains(e.target)) {
            menu.classList.remove('open');
            block.classList.remove('open');
        }
    });
})();

// ── Generic "data-confirm" guard ── //
// Any <form data-confirm="..."> shows a native confirm dialog before
// submitting. Used by row-level delete buttons (e.g. my_submissions.php)
// that don't have a dedicated modal of their own.
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches && form.matches('[data-confirm]')) {
        var msg = form.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    }
});

// ── Dashboard record search ── //
(function () {
    var searchTimer;

    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('[data-dashboard-kpi-search]')) return;

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            if (input.form) input.form.requestSubmit();
        }, 300);
    });
})();

// ── Delete confirmation modal ── //
// Works for a single page-level form (forms/show.php just has #deleteForm)
// AND for tables with one delete-form per row (my_submissions.php): the
// trigger button carries data-target-form="<form id>" so we know which
// row's form to submit when "Yes, Delete" is clicked.
(function () {
    var targetFormId = 'deleteForm'; // default, matches forms/show.php

    document.addEventListener('click', function (e) {
        // Open modal
        var trigger = e.target.closest('[data-modal]');
        if (trigger) {
            var modalId = trigger.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (!modal) return;

            targetFormId = trigger.getAttribute('data-target-form') || 'deleteForm';

            var nameEl = document.getElementById('deleteModalFormName');
            if (nameEl) {
                nameEl.textContent = trigger.getAttribute('data-form-title') || 'this form';
            }

            modal.removeAttribute('hidden');
            return;
        }

        // Confirm — submit the real form with CSRF
        if (e.target.closest('#btn-delete-confirm')) {
            var form = document.getElementById(targetFormId);
            if (form) form.submit();
            return;
        }

        // Close modal via cancel button
        var closeBtn = e.target.closest('[data-modal-close]');
        if (closeBtn) {
            var m = document.getElementById(closeBtn.getAttribute('data-modal-close'));
            if (m) m.setAttribute('hidden', '');
            return;
        }

        // Close modal on backdrop click
        if (e.target.classList.contains('ars-modal-backdrop')) {
            e.target.setAttribute('hidden', '');
            return;
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.ars-modal-backdrop:not([hidden])').forEach(function (m) {
                m.setAttribute('hidden', '');
            });
        }
    });
})();

// ── Approval attachment preview modal ── //
(function () {
    var modal = document.getElementById('approvalAttachmentPreview');
    var title = document.getElementById('approvalAttachmentPreviewTitle');
    var image = document.getElementById('approvalAttachmentPreviewImage');
    var documentPreview = document.getElementById('approvalAttachmentPreviewDocument');
    if (!modal || !title || !image || !documentPreview) return;

    function clearPreview() {
        image.removeAttribute('src');
        image.hidden = true;
        documentPreview.removeAttribute('src');
        documentPreview.hidden = true;
    }

    document.addEventListener('click', function (e) {
        var target = e.target instanceof Element ? e.target : null;
        var attachment = target ? target.closest('[data-approval-preview]') : null;
        if (attachment) {
            e.preventDefault();
            e.stopPropagation();
            clearPreview();
            title.textContent = attachment.getAttribute('data-preview-title')
                || attachment.textContent.trim()
                || 'Attachment Preview';

            if (attachment.getAttribute('data-preview-type') === 'image') {
                image.src = attachment.href;
                image.alt = title.textContent;
                image.hidden = false;
            } else {
                documentPreview.src = attachment.href;
                documentPreview.title = title.textContent;
                documentPreview.hidden = false;
            }

            modal.removeAttribute('hidden');
            return;
        }

        if (target && (target.closest('[data-modal-close="approvalAttachmentPreview"]')
            || target === modal)) {
            clearPreview();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
            clearPreview();
        }
    });
})();


// ── Approval file-drop zone ── //
(function () {
    var input = document.getElementById('approvalFile');
    var zone = document.getElementById('fileDrop');
    var label = document.getElementById('fileDropLabel');
    if (!input || !zone || !label) return;

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) {
            label.textContent = input.files[0].name;
            zone.classList.add('has-file');
        } else {
            label.textContent = 'Click to attach a file';
            zone.classList.remove('has-file');
        }
    });
})();

// ── Auto-submit selects (employment status) ── //
document.addEventListener('change', function (e) {
    var sel = e.target.closest('select.auto-submit');
    if (!sel) return;
    // Update visual class before submitting
    sel.classList.remove('emp-sel--employed', 'emp-sel--resigned', 'emp-sel--floating');
    var map = { employed: 'emp-sel--employed', resigned: 'emp-sel--resigned', floating: 'emp-sel--floating' };
    if (map[sel.value]) sel.classList.add(map[sel.value]);
    sel.closest('form').submit();
});

// ── Employee deactivate modal ── //
(function () {
    var modal = document.getElementById('deactivateEmpModal');
    var nameEl = document.getElementById('deactivateEmpName');
    var warnEl = document.getElementById('deactivateEmpPendingWarn');
    var countEl = document.getElementById('deactivateEmpPendingCount');
    var confirmBtn = document.getElementById('deactivateEmpConfirmBtn');
    var cancelBtn = document.getElementById('deactivateEmpCancelBtn');
    if (!modal) return;

    var targetFormId = null;

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-emp-delete]');
        if (trigger) {
            var empId = trigger.getAttribute('data-emp-delete');
            var empName = trigger.getAttribute('data-emp-name');
            var pending = parseInt(trigger.getAttribute('data-emp-pending') || '0', 10);

            targetFormId = 'deleteEmpForm-' + empId;
            nameEl.textContent = empName;

            if (pending > 0) {
                countEl.textContent = pending;
                warnEl.removeAttribute('hidden');
            } else {
                warnEl.setAttribute('hidden', '');
            }

            modal.removeAttribute('hidden');
        }
    });

    confirmBtn.addEventListener('click', function () {
        var form = document.getElementById(targetFormId);
        if (form) form.submit();
    });

    function closeModal() {
        modal.setAttribute('hidden', '');
        targetFormId = null;
    }

    cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();

// ── Form attachment drop zone ── //
(function () {
    var drop = document.getElementById('attachDrop');
    var input = document.getElementById('attachInput');
    var newList = document.getElementById('attachNewList');
    if (!drop || !input || !newList) return;

    var pickedFiles = [];

    function fmt(bytes) {
        return bytes < 1024 * 1024
            ? (bytes / 1024).toFixed(1) + ' KB'
            : (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderNew() {
        newList.innerHTML = '';
        pickedFiles.forEach(function (file, i) {
            var isImg = file.type.startsWith('image/');
            var iconHtml = isImg
                ? '<img class="attach-thumb" alt="">'
                : '<span class="attach-icon"><i class="ti ti-file-type-pdf"></i></span>';

            var item = document.createElement('div');
            item.className = 'attach-item';
            item.innerHTML =
                iconHtml +
                '<div class="attach-info">' +
                    '<span class="attach-name">' + file.name + '</span>' +
                    '<span class="attach-size">' + fmt(file.size) + '</span>' +
                '</div>' +
                '<div class="attach-actions">' +
                    '<button type="button" class="attach-btn attach-btn--danger" title="Remove">' +
                        '<i class="ti ti-trash"></i>' +
                    '</button>' +
                '</div>';

            if (isImg) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    item.querySelector('img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            item.querySelector('button').addEventListener('click', function () {
                pickedFiles.splice(i, 1);
                syncInput();
                renderNew();
            });

            newList.appendChild(item);
        });
    }

    function syncInput() {
        var dt = new DataTransfer();
        pickedFiles.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
    }

    function addFiles(files) {
        Array.from(files).forEach(function (f) {
            if (f.size > 20 * 1024 * 1024) { alert(f.name + ' exceeds 20 MB.'); return; }
            pickedFiles.push(f);
        });
        syncInput();
        renderNew();
    }

    input.addEventListener('change', function () {
        addFiles(input.files);
    });

    // Drag-and-drop
    drop.addEventListener('dragover', function (e) {
        e.preventDefault();
        drop.classList.add('is-dragover');
    });
    drop.addEventListener('dragleave', function () {
        drop.classList.remove('is-dragover');
    });
    drop.addEventListener('drop', function (e) {
        e.preventDefault();
        drop.classList.remove('is-dragover');
        addFiles(e.dataTransfer.files);
    });

    // Remove saved attachments
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-saved]');
        if (!btn) return;
        var item = document.getElementById('saved-' + btn.dataset.removeSaved);
        if (item) {
            item.querySelector('input[type="hidden"]').disabled = true;
            item.remove();
        }
    });
})();
