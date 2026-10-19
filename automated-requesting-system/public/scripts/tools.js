/**
 * tools.js — ARS Tools page
 * World Clock · Calculator · Height & Weight Converter · Notes · File Converter
 */
(function () {
    'use strict';

    var BASE = window.ARS_BASE || '';
    var csrf = function () {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };

    // ── Top-level tab switching ── //
    var navBtns = document.querySelectorAll('.tools-nav-item');
    var panels = document.querySelectorAll('.tools-panel');

    var printActivitiesPanel = document.getElementById('tab-print-activities');
    var printRefreshInProgress = false;

    // Sidebar links point here as /tools#services or /tools#extras — map
    // each group to its first tab. A direct tab hash (e.g. #notes) also
    // works and takes priority over the remembered sessionStorage tab.
    var hashToTab = { services: 'payslip', extras: 'clock' };
    var hash = (location.hash || '').replace('#', '');
    var initialTab = hashToTab[hash] || (document.getElementById('tab-' + hash) ? hash : null)
        || sessionStorage.getItem('ars_tools_tab');
    if (initialTab) activateTab(initialTab);

    navBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.dataset.tab);
            sessionStorage.setItem('ars_tools_tab', btn.dataset.tab);
            if (btn.dataset.tab === 'print-activities') refreshPrintActivities();
        });
    });

    function activateTab(tab) {
        navBtns.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === tab); });
        panels.forEach(function (p) { p.classList.toggle('active', p.id === 'tab-' + tab); });
    }

    function refreshPrintActivities() {
        if (!printActivitiesPanel || printRefreshInProgress || document.visibilityState !== 'visible') return;
        printRefreshInProgress = true;
        var query = new URLSearchParams();
        var search = document.getElementById('printLogSearch');
        var currentUrl = new URL(window.location.href);
        var page = currentUrl.searchParams.get('page') || '1';
        if (search && search.value.trim()) query.set('q', search.value.trim());
        query.set('page', page);

        fetch(BASE + '/api/print-activities?' + query.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Print activity refresh failed.');
            return response.json();
        }).then(function (data) {
            renderPrintActivities(data);
            var status = document.getElementById('printLogLiveStatus');
            if (status) status.textContent = 'Updated ' + new Date().toLocaleTimeString() + ' · refreshes every 10 minutes';
        }).catch(function () {
            var status = document.getElementById('printLogLiveStatus');
            if (status) status.textContent = 'Live update unavailable; retrying.';
        }).finally(function () {
            printRefreshInProgress = false;
        });
    }

    function renderPrintActivities(data) {
        var wrap = printActivitiesPanel.querySelector('.print-log-table-wrap');
        var total = document.getElementById('printLogTotal');
        if (!wrap || !Array.isArray(data.records)) return;
        if (total) total.textContent = Number(data.total).toLocaleString() + ' records';
        wrap.replaceChildren();

        if (!data.records.length) {
            var empty = document.createElement('div');
            empty.className = 'print-log-empty';
            var icon = document.createElement('i');
            icon.className = 'ti ti-printer-off';
            var heading = document.createElement('strong');
            heading.textContent = 'No print activity found';
            var message = document.createElement('p');
            message.textContent = 'Newly collected completed print jobs will appear here.';
            empty.append(icon, heading, message);
            wrap.appendChild(empty);
            return;
        }

        var scroll = document.createElement('div');
        scroll.className = 'print-log-scroll';
        var table = document.createElement('table');
        table.className = 'print-log-table';
        var head = document.createElement('thead');
        var headingRow = document.createElement('tr');
        ['Time', 'Event details'].forEach(function (label) {
            var cell = document.createElement('th');
            cell.scope = 'col';
            cell.textContent = label;
            headingRow.appendChild(cell);
        });
        head.appendChild(headingRow);
        table.appendChild(head);

        var body = document.createElement('tbody');
        data.records.forEach(function (record) {
            var row = document.createElement('tr');
            var timeCell = document.createElement('td');
            timeCell.textContent = record.printed_at || '';
            var detailsCell = document.createElement('td');
            detailsCell.className = 'print-log-document';

            var details = record.job_details || {};
            var labels = Object.keys(details).filter(function (label) { return details[label] !== ''; });
            if (labels.length) {
                var list = document.createElement('dl');
                list.className = 'print-job-details';
                labels.forEach(function (label) {
                    var item = document.createElement('div');
                    item.className = 'print-job-detail';
                    var term = document.createElement('dt');
                    term.textContent = label;
                    var value = document.createElement('dd');
                    value.textContent = details[label];
                    item.append(term, value);
                    list.appendChild(item);
                });
                detailsCell.appendChild(list);
            } else {
                var noDetails = document.createElement('span');
                noDetails.className = 'text-muted';
                noDetails.textContent = 'No structured print-job details available.';
                detailsCell.appendChild(noDetails);
            }

            var archive = document.createElement('div');
            archive.className = 'print-spool-archive';
            if (record.spool_archive_id) {
                var spoolLink = document.createElement('a');
                spoolLink.className = 'btn btn-secondary btn-sm';
                spoolLink.href = BASE + '/tools/print-archives/' + encodeURIComponent(record.spool_archive_id) + '/spool';
                spoolLink.textContent = 'Download spool file';
                archive.appendChild(spoolLink);
                if (record.conversion_status === 'converted') {
                    var pdfLink = document.createElement('a');
                    pdfLink.className = 'btn btn-primary btn-sm';
                    pdfLink.href = BASE + '/tools/print-archives/' + encodeURIComponent(record.spool_archive_id) + '/pdf';
                    pdfLink.textContent = 'Download PDF';
                    archive.appendChild(pdfLink);
                } else {
                    var archiveStatus = document.createElement('span');
                    archiveStatus.className = 'text-muted';
                    archiveStatus.textContent = record.conversion_status === 'unsupported'
                        ? 'PDF unavailable: ' + (record.spool_format || 'unknown') + ' spool format is not supported.'
                        : 'PDF conversion failed; the original spool file is available.';
                    archive.appendChild(archiveStatus);
                }
            } else {
                var missing = document.createElement('span');
                missing.className = 'text-muted';
                missing.textContent = 'No spool file was archived. The job may have expired or exceeded the 100 MB upload limit.';
                archive.appendChild(missing);
            }
            detailsCell.appendChild(archive);
            row.append(timeCell, detailsCell);
            body.appendChild(row);
        });
        table.appendChild(body);
        scroll.appendChild(table);
        wrap.appendChild(scroll);

        if (data.pages > 1) {
            var pagination = document.createElement('nav');
            pagination.className = 'print-log-pagination';
            pagination.setAttribute('aria-label', 'Print activity pages');
            if (data.page > 1) pagination.appendChild(createPrintPageLink(data, data.page - 1, 'Previous'));
            var pageLabel = document.createElement('span');
            pageLabel.textContent = 'Page ' + data.page + ' of ' + data.pages;
            pagination.appendChild(pageLabel);
            if (data.page < data.pages) pagination.appendChild(createPrintPageLink(data, data.page + 1, 'Next'));
            wrap.appendChild(pagination);
        }
    }

    function createPrintPageLink(data, page, label) {
        var link = document.createElement('a');
        var query = new URLSearchParams();
        if (data.search) query.set('q', data.search);
        query.set('page', page);
        link.className = 'btn btn-secondary';
        link.href = BASE + '/tools?' + query.toString() + '#print-activities';
        link.textContent = label;
        return link;
    }

    if (printActivitiesPanel) {
        window.setInterval(refreshPrintActivities, 10 * 60 * 1000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') refreshPrintActivities();
        });
        if (printActivitiesPanel.classList.contains('active')) refreshPrintActivities();
    }

    /* ============================================================
       WORLD CLOCK
       ============================================================ */
    (function initClock() {
        var grid = document.getElementById('clockGrid');
        var select = document.getElementById('clockCitySelect');
        var addBtn = document.getElementById('clockAddBtn');
        if (!grid) return;

        var stored = JSON.parse(localStorage.getItem('ars_clocks') || 'null');
        var zones = stored || ['Asia/Manila', 'America/New_York', 'Europe/London', 'Asia/Tokyo'];

        function labelFor(zone) {
            var opt = select.querySelector('option[value="' + zone + '"]');
            return opt ? opt.textContent : zone;
        }

        function render() {
            grid.innerHTML = '';
            zones.forEach(function (zone) {
                var card = document.createElement('div');
                card.className = 'clock-card';
                card.dataset.zone = zone;
                card.innerHTML =
                    '<button class="clock-remove" title="Remove"><i class="ti ti-x"></i></button>' +
                    '<div class="clock-city">' + labelFor(zone) + '</div>' +
                    '<div class="clock-time">--:--:--</div>' +
                    '<div class="clock-date">—</div>';
                card.querySelector('.clock-remove').addEventListener('click', function () {
                    zones = zones.filter(function (z) { return z !== zone; });
                    persist();
                    render();
                });
                grid.appendChild(card);
            });
            tick();
        }

        function persist() {
            localStorage.setItem('ars_clocks', JSON.stringify(zones));
        }

        function tick() {
            var now = new Date();
            grid.querySelectorAll('.clock-card').forEach(function (card) {
                var zone = card.dataset.zone;
                try {
                    var time = new Intl.DateTimeFormat('en-US', {
                        timeZone: zone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                    }).format(now);
                    var date = new Intl.DateTimeFormat('en-US', {
                        timeZone: zone, weekday: 'short', month: 'short', day: 'numeric'
                    }).format(now);
                    card.querySelector('.clock-time').textContent = time;
                    card.querySelector('.clock-date').textContent = date;
                } catch (e) {
                    card.querySelector('.clock-time').textContent = '—';
                }
            });
        }

        addBtn.addEventListener('click', function () {
            var zone = select.value;
            if (zones.includes(zone)) return;
            zones.push(zone);
            persist();
            render();
        });

        render();
        setInterval(tick, 1000);
    })();

    /* ============================================================
       CALCULATOR (Standard / Scientific)
       ============================================================ */
    (function initCalculator() {
        var grid = document.getElementById('calcGrid');
        var exprEl = document.getElementById('calcExpr');
        var valueEl = document.getElementById('calcValue');
        var modeBtns = document.querySelectorAll('[data-calc-mode]');
        if (!grid) return;

        var expr = '';
        var mode = 'standard';

        var standardKeys = [
            ['C', '±', '%', '÷'],
            ['7', '8', '9', '×'],
            ['4', '5', '6', '−'],
            ['1', '2', '3', '+'],
            ['0', '.', '⌫', '=']
        ];

        var scientificKeys = [
            ['sin', 'cos', 'tan', 'π', 'C'],
            ['ln', 'log', '√', '^', '⌫'],
            ['7', '8', '9', '÷', '%'],
            ['4', '5', '6', '×', '±'],
            ['1', '2', '3', '−', '('],
            ['0', '.', 'e', '+', ')'],
            ['=', '=', '=', '=', '=']
        ];

        function opClass(key) {
            return ['÷', '×', '−', '+', '^', '%'].includes(key) ? ' calc-op' : '';
        }

        function buildGrid() {
            grid.className = 'calc-grid' + (mode === 'scientific' ? ' calc-sci' : '');
            grid.innerHTML = '';
            var rows = mode === 'scientific' ? scientificKeys : standardKeys;
            var seen = {};
            rows.forEach(function (row) {
                row.forEach(function (key) {
                    // Collapse the 5x "=" filler row in scientific mode into a single button.
                    if (key === '=' ) {
                        if (seen['=']) return;
                        seen['='] = true;
                    }
                    var btn = document.createElement('button');
                    btn.textContent = key;
                    var cls = '';
                    if (key === '=') cls = ' calc-eq';
                    else if (['sin', 'cos', 'tan', 'ln', 'log', '√'].includes(key)) cls = ' calc-fn';
                    else cls = opClass(key);
                    btn.className = cls.trim();
                    if (key === '=') btn.classList.toggle('calc-eq-scientific', mode === 'scientific');
                    btn.addEventListener('click', function () { press(key); });
                    grid.appendChild(btn);
                });
            });
        }

        // Display-friendly aliases for the internal function-call tokens
        // inserted into `expr`. Keeps the on-screen expression readable
        // (e.g. "sin(45)") while the real token used for evaluation stays
        // a plain, unambiguous identifier (e.g. "sinDeg(45)").
        var prettyMap = [
            [/sinDeg\(/g, 'sin('],
            [/cosDeg\(/g, 'cos('],
            [/tanDeg\(/g, 'tan('],
            [/Math\.log10\(/g, 'log('],
            [/Math\.log\(/g, 'ln('],
            [/Math\.sqrt\(/g, '√('],
            [/Math\.PI/g, 'π'],
            [/Math\.E/g, 'e'],
            [/\*\*/g, '^']
        ];

        function toPretty(str) {
            prettyMap.forEach(function (pair) { str = str.replace(pair[0], pair[1]); });
            return str;
        }

        function press(key) {
            switch (key) {
                case 'C': expr = ''; break;
                case '⌫': expr = expr.slice(0, -1); break;
                case '=': evaluate(); return;
                case '±':
                    // Negate only the trailing number, not the whole expression.
                    if (/(-?\d+\.?\d*)$/.test(expr)) {
                        expr = expr.replace(/(-?\d+\.?\d*)$/, function (m) {
                            return m.charAt(0) === '-' ? m.slice(1) : '-' + m;
                        });
                    } else {
                        expr += '-';
                    }
                    break;
                case 'π': expr += 'Math.PI'; break;
                case 'e': expr += 'Math.E'; break;
                case '√': expr += 'Math.sqrt('; break;
                // Trig buttons use *Deg helpers (defined at evaluate-time) so
                // input is in degrees, matching what a Standard/Scientific
                // calculator user expects — Math.sin() etc. take radians.
                case 'sin': expr += 'sinDeg('; break;
                case 'cos': expr += 'cosDeg('; break;
                case 'tan': expr += 'tanDeg('; break;
                case 'ln': expr += 'Math.log('; break;
                case 'log': expr += 'Math.log10('; break;
                case '^': expr += '**'; break;
                case '÷': expr += '/'; break;
                case '×': expr += '*'; break;
                case '−': expr += '-'; break;
                default: expr += key;
            }
            updateDisplay();
        }

        function updateDisplay() {
            exprEl.textContent = expr ? toPretty(expr) : '\u00A0';
        }

        // ── Safe expression evaluator ──
        // Parses and evaluates the calculator's expression string directly
        // (recursive descent), instead of building and invoking dynamic code
        // via Function()/eval(). Function() is treated the same as eval() by
        // CSP's script-src and requires 'unsafe-eval' to run — which we don't
        // want to enable app-wide just for this. This parser never turns a
        // string into executable code, so no CSP exception is needed at all.
        var CALC_FUNCS = {
            'Math.log10': Math.log10,
            'Math.sqrt': Math.sqrt,
            'Math.log': Math.log,
            'sinDeg': function (x) { return Math.sin(x * Math.PI / 180); },
            'cosDeg': function (x) { return Math.cos(x * Math.PI / 180); },
            'tanDeg': function (x) { return Math.tan(x * Math.PI / 180); },
        };
        // Longest-name-first so e.g. "Math.log10" isn't cut short at "Math.log".
        var CALC_FUNC_NAMES = ['Math.log10', 'Math.sqrt', 'Math.log', 'sinDeg', 'cosDeg', 'tanDeg'];
        var CALC_CONSTS = { 'Math.PI': Math.PI, 'Math.E': Math.E };
        var CALC_CONST_NAMES = ['Math.PI', 'Math.E'];

        function evaluateExpr(src) {
            var i = 0;
            var len = src.length;

            function at(str) { return src.startsWith(str, i); }
            function skipWs() { while (i < len && /\s/.test(src[i])) i++; }

            function parseExpr() { return parseAddSub(); }

            function parseAddSub() {
                var left = parseMulDiv();
                for (;;) {
                    skipWs();
                    if (at('+')) { i++; left += parseMulDiv(); }
                    else if (at('-')) { i++; left -= parseMulDiv(); }
                    else break;
                }
                return left;
            }

            function parseMulDiv() {
                var left = parseUnary();
                for (;;) {
                    skipWs();
                    if (at('*') && !at('**')) { i++; left *= parseUnary(); }
                    else if (at('/')) { i++; left /= parseUnary(); }
                    else break;
                }
                return left;
            }

            function parseUnary() {
                skipWs();
                if (at('-')) { i++; return -parseUnary(); }
                return parsePower();
            }

            function parsePower() {
                var base = parsePrimary();
                skipWs();
                if (at('**')) { i += 2; return Math.pow(base, parseUnary()); }
                return base;
            }

            function parsePrimary() {
                skipWs();
                if (at('(')) {
                    i++;
                    var v = parseExpr();
                    skipWs();
                    if (!at(')')) throw new Error('expected )');
                    i++;
                    return v;
                }
                for (var f = 0; f < CALC_FUNC_NAMES.length; f++) {
                    var fname = CALC_FUNC_NAMES[f];
                    if (at(fname)) {
                        i += fname.length;
                        skipWs();
                        if (!at('(')) throw new Error('expected (');
                        i++;
                        var arg = parseExpr();
                        skipWs();
                        if (!at(')')) throw new Error('expected )');
                        i++;
                        return CALC_FUNCS[fname](arg);
                    }
                }
                for (var c = 0; c < CALC_CONST_NAMES.length; c++) {
                    var cname = CALC_CONST_NAMES[c];
                    if (at(cname)) { i += cname.length; return CALC_CONSTS[cname]; }
                }
                var m = /^[0-9]*\.?[0-9]+/.exec(src.slice(i));
                if (m) { i += m[0].length; return parseFloat(m[0]); }
                throw new Error('unexpected token at ' + i);
            }

            var result = parseExpr();
            skipWs();
            if (i !== len) throw new Error('trailing input');
            return result;
        }

        function evaluate() {
            if (!expr) return;
            try {
                // Belt-and-suspenders: only digits, operators, parentheses,
                // dots, whitespace, and the whitelisted Math.*/sinDeg/cosDeg/
                // tanDeg tokens inserted by press() are accepted; anything
                // else is rejected before it ever reaches the parser above.
                if (!/^[0-9+\-*/().\sA-Za-z%^]*$/.test(expr)) throw new Error('invalid');

                var safe = expr.replace(/%/g, '/100');

                // Auto-close any parentheses the user forgot (e.g. "sinDeg(45"
                // without pressing the closing bracket) instead of erroring.
                var open = (safe.match(/\(/g) || []).length;
                var close = (safe.match(/\)/g) || []).length;
                if (open > close) safe += ')'.repeat(open - close);

                var result = evaluateExpr(safe);

                if (typeof result !== 'number' || !isFinite(result)) throw new Error('bad result');
                exprEl.textContent = toPretty(expr) + ' =';
                valueEl.textContent = Number(result.toPrecision(12)).toString();
                expr = Number(result.toPrecision(12)).toString();
            } catch (e) {
                valueEl.textContent = 'Error';
                expr = '';
            }
        }

        modeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.dataset.calcMode;
                modeBtns.forEach(function (b) { b.classList.toggle('active', b === btn); });
                buildGrid();
            });
        });

        buildGrid();
        updateDisplay();
    })();

    /* ============================================================
       HEIGHT & WEIGHT CONVERTER (+ BMI)
       ============================================================ */
    (function initConverter() {
        var heightResult = document.getElementById('heightResult');
        var weightResult = document.getElementById('weightResult');
        var bmiResult = document.getElementById('bmiResult');
        if (!heightResult) return;

        var heightUnit = 'cm';
        var weightUnit = 'kg';

        var cmField = document.getElementById('heightCmFields');
        var ftInField = document.getElementById('heightFtInFields');
        var heightCm = document.getElementById('heightCm');
        var heightFt = document.getElementById('heightFt');
        var heightIn = document.getElementById('heightIn');
        var weightInput = document.getElementById('weightInput');
        var weightLabel = document.getElementById('weightInputLabel');

        document.querySelectorAll('[data-height-unit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                heightUnit = btn.dataset.heightUnit;
                document.querySelectorAll('[data-height-unit]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                cmField.classList.toggle('is-hidden', heightUnit !== 'cm');
                ftInField.classList.toggle('is-hidden', heightUnit !== 'ftin');
                calc();
            });
        });

        document.querySelectorAll('[data-weight-unit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                weightUnit = btn.dataset.weightUnit;
                document.querySelectorAll('[data-weight-unit]').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                weightLabel.textContent = weightUnit === 'kg' ? 'Kilograms' : 'Pounds';
                calc();
            });
        });

        [heightCm, heightFt, heightIn, weightInput].forEach(function (el) {
            el.addEventListener('input', calc);
        });

        function getHeightCm() {
            if (heightUnit === 'cm') {
                return parseFloat(heightCm.value) || 0;
            }
            var ft = parseFloat(heightFt.value) || 0;
            var inch = parseFloat(heightIn.value) || 0;
            return (ft * 12 + inch) * 2.54;
        }

        function getWeightKg() {
            var v = parseFloat(weightInput.value) || 0;
            return weightUnit === 'kg' ? v : v * 0.45359237;
        }

        function calc() {
            var cm = getHeightCm();
            if (cm > 0) {
                var totalIn = cm / 2.54;
                var ft = Math.floor(totalIn / 12);
                var inch = (totalIn % 12).toFixed(1);
                if (heightUnit === 'cm') {
                    heightResult.innerHTML = 'Result: <strong>' + ft + ' ft ' + inch + ' in</strong>';
                } else {
                    heightResult.innerHTML = 'Result: <strong>' + cm.toFixed(1) + ' cm</strong>';
                }
            } else {
                heightResult.innerHTML = 'Result: <strong>—</strong>';
            }

            var kg = getWeightKg();
            if (kg > 0) {
                if (weightUnit === 'kg') {
                    weightResult.innerHTML = 'Result: <strong>' + (kg / 0.45359237).toFixed(1) + ' lb</strong>';
                } else {
                    weightResult.innerHTML = 'Result: <strong>' + kg.toFixed(1) + ' kg</strong>';
                }
            } else {
                weightResult.innerHTML = 'Result: <strong>—</strong>';
            }

            if (cm > 0 && kg > 0) {
                var meters = cm / 100;
                var bmi = kg / (meters * meters);
                var category = 'Normal';
                var badgeClass = 'conv-bmi-badge--normal';
                if (bmi < 18.5) { category = 'Underweight'; badgeClass = 'conv-bmi-badge--underweight'; }
                else if (bmi >= 25 && bmi < 30) { category = 'Overweight'; badgeClass = 'conv-bmi-badge--overweight'; }
                else if (bmi >= 30) { category = 'Obese'; badgeClass = 'conv-bmi-badge--obese'; }
                bmiResult.innerHTML = 'BMI: <strong>' + bmi.toFixed(1) + '</strong> &nbsp; ' +
                    '<span class="conv-bmi-badge ' + badgeClass + '">' + category + '</span>';
            } else {
                bmiResult.textContent = 'Enter height and weight to calculate BMI.';
            }
        }

        calc();
    })();

    /* ============================================================
       NOTES (persisted via /tools/notes JSON endpoints)
       ============================================================ */
    (function initNotes() {
        var grid = document.getElementById('notesGrid');
        var addBtn = document.getElementById('notesAddBtn');
        if (!grid) return;

        var notes = [];
        try { notes = JSON.parse(grid.dataset.notes || '[]'); } catch (e) { notes = []; }

        var colors = ['yellow', 'pink', 'blue', 'green', 'purple'];

        function render() {
            grid.innerHTML = '';
            if (!notes.length) {
                grid.innerHTML = '<div class="notes-empty">No notes yet — click "New note" to add one.</div>';
                return;
            }
            notes.forEach(renderCard);
        }

        function renderCard(note) {
            var card = document.createElement('div');
            card.className = 'note-card note-' + (note.color || 'yellow');
            card.dataset.id = note.id;

            var dotsHtml = colors.map(function (c) {
                return '<span data-color="' + c + '" class="note-' + c + (c === note.color ? ' active' : '') +
                    '"></span>';
            }).join('');

            card.innerHTML =
                '<input type="text" class="note-title" placeholder="Title" value="' + escapeAttr(note.title || '') + '">' +
                '<textarea class="note-content" placeholder="Write a note…">' + escapeHtml(note.content || '') + '</textarea>' +
                '<div class="note-footer">' +
                    '<div class="note-color-dots">' + dotsHtml + '</div>' +
                    '<button class="note-delete" title="Delete"><i class="ti ti-trash"></i></button>' +
                '</div>';

            var titleInput = card.querySelector('.note-title');
            var contentInput = card.querySelector('.note-content');
            var saveTimer = null;

            function scheduleSave() {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function () { saveNote(note, card); }, 600);
            }

            titleInput.addEventListener('input', function () { note.title = titleInput.value; scheduleSave(); });
            contentInput.addEventListener('input', function () { note.content = contentInput.value; scheduleSave(); });

            card.querySelectorAll('.note-color-dots span').forEach(function (dot) {
                dot.addEventListener('click', function () {
                    note.color = dot.dataset.color;
                    card.className = 'note-card note-' + note.color;
                    card.querySelectorAll('.note-color-dots span').forEach(function (d) {
                        d.classList.toggle('active', d === dot);
                    });
                    saveNote(note, card);
                });
            });

            card.querySelector('.note-delete').addEventListener('click', function () {
                if (!confirm('Delete this note?')) return;
                deleteNote(note, card);
            });

            grid.appendChild(card);
        }

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
        function escapeAttr(s) {
            return String(s).replace(/"/g, '&quot;');
        }

        addBtn.addEventListener('click', function () {
            var draft = { id: null, title: '', content: '', color: 'yellow' };
            var fd = new FormData();
            fd.append('title', '');
            fd.append('content', '');
            fd.append('color', 'yellow');
            fd.append('csrf_token', csrf());

            fetch(BASE + '/tools/notes', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.note) {
                        notes.unshift(data.note);
                        render();
                        var firstTitle = grid.querySelector('.note-card .note-title');
                        if (firstTitle) firstTitle.focus();
                    }
                });
        });

        function saveNote(note, card) {
            if (!note.id) return; // still being created
            var fd = new FormData();
            fd.append('title', note.title || '');
            fd.append('content', note.content || '');
            fd.append('color', note.color || 'yellow');
            fd.append('csrf_token', csrf());
            fetch(BASE + '/tools/notes/' + note.id + '/update', { method: 'POST', body: fd, credentials: 'same-origin' })
                .catch(function () {});
        }

        function deleteNote(note, card) {
            if (!note.id) {
                notes = notes.filter(function (n) { return n !== note; });
                render();
                return;
            }
            var fd = new FormData();
            fd.append('csrf_token', csrf());
            fetch(BASE + '/tools/notes/' + note.id + '/delete', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function () {
                    notes = notes.filter(function (n) { return n.id !== note.id; });
                    render();
                });
        }

        render();
    })();
})();
