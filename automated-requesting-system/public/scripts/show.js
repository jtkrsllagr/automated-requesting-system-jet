/**
 * show.php script file
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Back button ──
    const backBtn = document.getElementById('btn-back');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            if (history.length > 1) {
                history.back();
            } else {
                window.location.href = backBtn.dataset.fallbackUrl || window.ARS_BASE + '/approvals';
            }
        });
    }
    
    const rejectBtn = document.getElementById('btn-reject');
    const remarksField = document.getElementById('remarksField');
    const remarksHint = document.getElementById('remarks-hint');
 
    const successAlert = document.querySelector('.alert-success');

    if (rejectBtn && remarksField) {
        // Require remarks and confirm before rejecting
        rejectBtn.addEventListener('click', function (e) {
            if (remarksField.value.trim() === '') {
                e.preventDefault();
                remarksField.classList.add('input-error');
                remarksField.focus();
                remarksField.setCustomValidity('Please enter a rejection reason.');
                remarksField.reportValidity();
                return;
            }
            // If remarks are present, then ask for confirmation
            if (!confirm('Are you sure you want to reject this form? This cannot be undone.')) {
                e.preventDefault();
            }
        });

        // Clear error state once user starts typing
        remarksField.addEventListener('input', function () {
            remarksField.classList.remove('input-error');
            remarksField.setCustomValidity('');
        });

        if (remarksHint) {
            rejectBtn.addEventListener('mouseenter', function () {
                remarksHint.textContent = '(required for rejection)';
            });
            rejectBtn.addEventListener('mouseleave', function () {
                remarksHint.textContent = '(required if rejecting)';
            });
        }
    }

    if (successAlert) {
        setTimeout(() => {
            successAlert.classList.add('ars-fade-out');
            setTimeout(() => successAlert.remove(), 400);
        }, 4000);
    }

    initMultiAttachmentInputs();
    initSignatureCapture();
});

function initMultiAttachmentInputs() {
    document.querySelectorAll('input[type="file"][data-multi-attachment]').forEach((input) => {
        const fileList = document.getElementById(input.dataset.multiAttachment);
        const addButton = document.querySelector(`[data-add-files-for="${input.id}"]`);
        if (!fileList || !addButton) return;

        let selectedFiles = [];

        function syncFileInput() {
            const transfer = new DataTransfer();
            selectedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        }

        function renderFileList() {
            fileList.replaceChildren();
            const hasSelectedFiles = selectedFiles.length > 0;
            input.hidden = hasSelectedFiles;
            addButton.hidden = !hasSelectedFiles;
            selectedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'selected-attachment';

                const name = document.createElement('span');
                name.textContent = file.name;

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'selected-attachment-remove';
                remove.setAttribute('aria-label', `Remove ${file.name}`);
                remove.title = 'Remove file';
                remove.innerHTML = '<i class="ti ti-x"></i>';
                remove.addEventListener('click', () => {
                    selectedFiles.splice(index, 1);
                    syncFileInput();
                    renderFileList();
                });

                item.append(name, remove);
                fileList.append(item);
            });
        }

        input.addEventListener('change', () => {
            selectedFiles.push(...Array.from(input.files));
            syncFileInput();
            renderFileList();
        });

        addButton.addEventListener('click', () => input.click());
        renderFileList();
    });
}

/**
 * Signature capture for the approver action card.
 * Two input methods share a single <input type="file" name="approval_file">
 * so the existing backend upload handling needs no changes:
 *   - "Draw" renders a canvas pad; on submit it's exported to a PNG and
 *     injected into the file input via DataTransfer.
 *   - "Upload" is the original attach-a-file flow, enhanced with drag &
 *     drop, a live preview, a remove button, and inline client validation.
 */
function initSignatureCapture() {
    const form = document.getElementById('approvalForm');
    const tabDraw = document.getElementById('sigTabDraw');
    const tabUpload = document.getElementById('sigTabUpload');
    const panelDraw = document.getElementById('sigPanelDraw');
    const panelUpload = document.getElementById('sigPanelUpload');
    if (!form || !tabDraw || !tabUpload) return;

    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const MAX_BYTES = 20 * 1024 * 1024;
    const SIGNATURE_STORAGE_KEY = 'ars_last_approval_signature';

    function storeLastSignature(method, dataUrl) {
        if (!dataUrl) return;
        try {
            localStorage.setItem(SIGNATURE_STORAGE_KEY, JSON.stringify({ method, dataUrl }));
        } catch (e) {}
    }

    function clearLastSignature() {
        try {
            localStorage.removeItem(SIGNATURE_STORAGE_KEY);
        } catch (e) {}
    }

    function dataUrlToFile(dataUrl, fileName) {
        const arr = dataUrl.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8 = new Uint8Array(n);
        while (n--) u8[n] = bstr.charCodeAt(n);
        return new File([u8], fileName, { type: mime });
    }

    function loadLastSignature() {
        try {
            const saved = JSON.parse(localStorage.getItem(SIGNATURE_STORAGE_KEY) || 'null');
            if (!saved || !saved.dataUrl) return;

            if (saved.method === 'upload') {
                const file = dataUrlToFile(saved.dataUrl, 'saved-signature.png');
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                applyFile(file);
                setActiveTab('upload');
                return;
            }

            if (saved.method === 'draw') {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    hasDrawn = true;
                    wrap.classList.add('has-signature');
                    status.classList.add('is-captured');
                    status.innerHTML = '<i class="ti ti-check"></i> Last signature loaded';
                };
                img.src = saved.dataUrl;
                setActiveTab('draw');
            }
        } catch (e) {}
    }

    // ── Tabs ──
    let activeMethod = 'draw';
    function setActiveTab(method) {
        activeMethod = method;
        const drawing = method === 'draw';
        tabDraw.classList.toggle('active', drawing);
        tabUpload.classList.toggle('active', !drawing);
        tabDraw.setAttribute('aria-selected', String(drawing));
        tabUpload.setAttribute('aria-selected', String(!drawing));
        panelDraw.classList.toggle('sig-hidden', !drawing);
        panelUpload.classList.toggle('sig-hidden', drawing);
    }
    tabDraw.addEventListener('click', () => setActiveTab('draw'));
    tabUpload.addEventListener('click', () => setActiveTab('upload'));

    // ── Draw pad ──
    const wrap = document.getElementById('sigPadWrap');
    const canvas = document.getElementById('sigCanvas');
    const clearBtn = document.getElementById('sigClear');
    const status = document.getElementById('sigStatus');
    const ctx = canvas.getContext('2d');
    let hasDrawn = false;
    let drawing = false;
    let lastX = 0, lastY = 0;

    function resizeCanvas() {
        const ratio = window.devicePixelRatio || 1;
        const prev = hasDrawn ? canvas.toDataURL() : null;
        canvas.width = wrap.clientWidth * ratio;
        canvas.height = wrap.clientHeight * ratio;
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';
        if (prev) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0, wrap.clientWidth, wrap.clientHeight);
            img.src = prev;
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function pointerPos(e) {
        const rect = canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }
    function startDraw(e) {
        drawing = true;
        wrap.classList.add('is-drawing');
        const p = pointerPos(e);
        lastX = p.x; lastY = p.y;
    }
    function moveDraw(e) {
        if (!drawing) return;
        const p = pointerPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        lastX = p.x; lastY = p.y;
        if (!hasDrawn) {
            hasDrawn = true;
            wrap.classList.add('has-signature');
            status.classList.add('is-captured');
            status.innerHTML = '<i class="ti ti-check"></i> Signature captured';
        }
    }
    function endDraw() {
        drawing = false;
        wrap.classList.remove('is-drawing');
    }

    canvas.addEventListener('pointerdown', (e) => { canvas.setPointerCapture(e.pointerId); startDraw(e); });
    canvas.addEventListener('pointermove', moveDraw);
    canvas.addEventListener('pointerup', endDraw);
    canvas.addEventListener('pointerleave', endDraw);

    function resetSignature() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasDrawn = false;
        wrap.classList.remove('has-signature');
        status.classList.remove('is-captured');
        status.innerHTML = '<i class="ti ti-info-circle"></i> Draw with your mouse or finger';
        clearLastSignature();
    }
    clearBtn.addEventListener('click', resetSignature);

    // ── Upload panel: drag & drop + preview ──
    const fileDrop = document.getElementById('fileDrop');
    const fileInput = document.getElementById('approvalFile');
    const fileDropLabel = document.getElementById('fileDropLabel');
    const preview = document.getElementById('filePreview');
    const previewImg = document.getElementById('filePreviewImg');
    const previewPdfIcon = document.getElementById('filePreviewPdfIcon');
    const previewName = document.getElementById('filePreviewName');
    const previewSize = document.getElementById('filePreviewSize');
    const previewRemove = document.getElementById('filePreviewRemove');
    const fileError = document.getElementById('fileError');
    const fileErrorText = document.getElementById('fileErrorText');

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function showFileError(msg) {
        fileErrorText.textContent = msg;
        fileError.classList.remove('sig-hidden');
    }
    function hideFileError() {
        fileError.classList.add('sig-hidden');
    }

    function clearFile() {
        fileInput.value = '';
        preview.classList.add('sig-hidden');
        previewImg.classList.add('sig-hidden');
        previewPdfIcon.classList.add('sig-hidden');
        fileDrop.classList.remove('has-file');
        fileDropLabel.textContent = 'Drag & drop, or click to attach';
        hideFileError();
    }

    function applyFile(file) {
        hideFileError();
        if (!file) return;

        if (!ALLOWED_TYPES.includes(file.type)) {
            showFileError('Only JPG, PNG, GIF, or PDF files are allowed.');
            clearFile();
            return;
        }
        if (file.size > MAX_BYTES) {
            showFileError('File must be under 20 MB.');
            clearFile();
            return;
        }

        fileDrop.classList.add('has-file');
        fileDropLabel.textContent = file.name;
        previewName.textContent = file.name;
        previewSize.textContent = formatSize(file.size);
        preview.classList.remove('sig-hidden');

        if (file.type === 'application/pdf') {
            previewImg.classList.add('sig-hidden');
            previewPdfIcon.classList.remove('sig-hidden');
        } else {
            previewPdfIcon.classList.add('sig-hidden');
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                if (file.type.startsWith('image/')) {
                    storeLastSignature('upload', e.target.result);
                }
            };
            reader.readAsDataURL(file);
            previewImg.classList.remove('sig-hidden');
        }
    }

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        applyFile(file);
    });

    previewRemove.addEventListener('click', (e) => {
        e.preventDefault();
        clearFile();
        clearLastSignature();
    });

    ['dragenter', 'dragover'].forEach((evt) => {
        fileDrop.addEventListener(evt, (e) => {
            e.preventDefault();
            fileDrop.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach((evt) => {
        fileDrop.addEventListener(evt, (e) => {
            e.preventDefault();
            fileDrop.classList.remove('is-dragover');
        });
    });
    fileDrop.addEventListener('drop', (e) => {
        const file = e.dataTransfer.files[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        applyFile(file);
    });

    loadLastSignature();

    // ── Submit: if "Draw" is active and something was drawn, export the
    //    canvas to PNG and inject it into the shared file input. ──
    let readyToSubmit = false;
    form.addEventListener('submit', function (e) {
        if (readyToSubmit) { readyToSubmit = false; return; }
        if (activeMethod !== 'draw' || !hasDrawn) return;

        e.preventDefault();
        const submitter = e.submitter;
        canvas.toBlob(function (blob) {
            if (blob) {
                const file = new File([blob], 'signature.png', { type: 'image/png' });
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                storeLastSignature('draw', canvas.toDataURL('image/png'));
            }
            readyToSubmit = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter);
            } else {
                form.submit();
            }
        }, 'image/png');
    });

    canvas.addEventListener('pointerup', () => {
        if (hasDrawn) {
            storeLastSignature('draw', canvas.toDataURL('image/png'));
        }
    });
}