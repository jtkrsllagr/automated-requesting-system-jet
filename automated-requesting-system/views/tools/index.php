<?php
/**
 * views/tools/index.php
 *
 * $notes         – this user's saved notes (array of rows)
 * $leaveCredits  – ['vacation' => [...], 'sick' => [...], ...] usage summary
 *                  both passed in by ToolsController::index()
 */
?>

<link rel="stylesheet" href="<?= url('stylesheets/tools.css') ?>">

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="ti ti-circle-check"></i>
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="tools-layout">

    <!-- ── Left nav ── -->
    <nav class="tools-nav">
        <div class="tools-nav-caption">Services</div>
        <button class="tools-nav-item active" data-tab="payslip">
            <i class="ti ti-file-invoice"></i> <span>Payslip</span>
        </button>
        <button class="tools-nav-item" data-tab="leavecredits">
            <i class="ti ti-calendar-stats"></i> <span>Leave Credits</span>
        </button>

        <div class="tools-nav-caption">Extras</div>
        <?php if ($printActivities !== null): ?>
        <button class="tools-nav-item" data-tab="print-activities">
            <i class="ti ti-printer"></i> <span>Print Logs</span>
        </button>
        <?php endif; ?>
        <button class="tools-nav-item" data-tab="clock">
            <i class="ti ti-clock"></i> <span>World Clock</span>
        </button>
        <button class="tools-nav-item" data-tab="calculator">
            <i class="ti ti-calculator"></i> <span>Calculator</span>
        </button>
        <button class="tools-nav-item" data-tab="notes">
            <i class="ti ti-notes"></i> <span>Notes</span>
        </button>
        <button class="tools-nav-item" data-tab="converter">
            <i class="ti ti-ruler-2"></i> <span>Height &amp; Weight</span>
        </button>
    </nav>

    <!-- ── Panels ── -->
    <div class="tools-panels">

        <!-- ── PAYSLIP (Services) ── -->
        <div class="tools-panel active" id="tab-payslip">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-file-invoice"></i> Payslip</h2>
                <p class="tools-section-sub">Request a copy of your payslip.</p>
            </div>
            <div class="tools-card coming-soon-card">
                <i class="ti ti-clock-hour-4 coming-soon-icon"></i>
                <p class="coming-soon-label">Coming Soon</p>
                <p class="tools-section-sub">Payslip requests will be available once HRIS integration is complete.</p>
            </div>
        </div>

        <!-- ── LEAVE CREDITS (Services) ── -->
        <div class="tools-panel" id="tab-leavecredits">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-calendar-stats"></i> Leave Credits</h2>
                <p class="tools-section-sub">Based on your completed Leave Application forms.</p>
            </div>
            <div class="tools-card coming-soon-card">
                <i class="ti ti-clock-hour-4 coming-soon-icon"></i>
                <p class="coming-soon-label">Coming Soon</p>
                <p class="tools-section-sub">Leave credit tracking will be available in a future update.</p>
            </div>
        </div>

        <?php if ($printActivities !== null) require __DIR__ . '/print_activities_panel.php'; ?>

        <!-- ── WORLD CLOCK (Extras) ── -->
        <div class="tools-panel" id="tab-clock">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-clock"></i> World Clock</h2>
                <p class="tools-section-sub">Track the current time across offices, branches, or clients.</p>
            </div>

            <div class="tools-card">
                <div class="clock-add-row">
                    <select id="clockCitySelect" class="form-control">
                        <option value="Asia/Manila">Manila (PH)</option>
                        <option value="America/New_York">New York (US)</option>
                        <option value="America/Los_Angeles">Los Angeles (US)</option>
                        <option value="Europe/London">London (UK)</option>
                        <option value="Europe/Paris">Paris (FR)</option>
                        <option value="Asia/Tokyo">Tokyo (JP)</option>
                        <option value="Asia/Shanghai">Shanghai (CN)</option>
                        <option value="Asia/Hong_Kong">Hong Kong (HK)</option>
                        <option value="Asia/Singapore">Singapore (SG)</option>
                        <option value="Asia/Dubai">Dubai (UAE)</option>
                        <option value="Asia/Kolkata">Mumbai/Delhi (IN)</option>
                        <option value="Australia/Sydney">Sydney (AU)</option>
                        <option value="America/Chicago">Chicago (US)</option>
                        <option value="America/Sao_Paulo">Sao Paulo (BR)</option>
                        <option value="UTC">UTC</option>
                    </select>
                    <button class="btn btn-primary" id="clockAddBtn"><i class="ti ti-plus"></i> Add</button>
                </div>

                <div class="clock-grid" id="clockGrid"></div>
            </div>
        </div>

        <!-- ── CALCULATOR (Extras) ── -->
        <div class="tools-panel" id="tab-calculator">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-calculator"></i> Calculator</h2>
                <p class="tools-section-sub">Standard arithmetic or scientific functions.</p>
            </div>

            <div class="tools-card calc-wrap">
                <div class="calc-mode-toggle">
                    <button class="active" data-calc-mode="standard">Standard</button>
                    <button data-calc-mode="scientific">Scientific</button>
                </div>

                <div class="calc-display">
                    <div class="calc-expr" id="calcExpr">&nbsp;</div>
                    <div class="calc-value" id="calcValue">0</div>
                </div>

                <div class="calc-grid" id="calcGrid"></div>
            </div>
        </div>

        <!-- ── NOTES (Extras) ── -->
        <div class="tools-panel" id="tab-notes">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-notes"></i> Notes</h2>
                <p class="tools-section-sub">Quick sticky notes, saved to your account.</p>
            </div>

            <div class="notes-toolbar">
                <button class="btn btn-primary" id="notesAddBtn"><i class="ti ti-plus"></i> New note</button>
            </div>

            <div class="notes-grid" id="notesGrid" data-notes='<?= htmlspecialchars(json_encode($notes ?? []), ENT_QUOTES) ?>'></div>
        </div>

        <!-- ── HEIGHT & WEIGHT CONVERTER (Extras) ── -->
        <div class="tools-panel" id="tab-converter">
            <div class="tools-section-header">
                <h2 class="tools-section-title"><i class="ti ti-ruler-2"></i> Height &amp; Weight Converter</h2>
                <p class="tools-section-sub">Convert between metric and imperial units, with a quick BMI check.</p>
            </div>

            <div class="tools-card conv-grid">
                <!-- Height -->
                <div>
                    <h4 class="mt-0">Height</h4>
                    <div class="conv-unit-toggle">
                        <button class="active" data-height-unit="cm">Centimeters</button>
                        <button data-height-unit="ftin">Feet / Inches</button>
                    </div>

                    <div id="heightCmFields">
                        <div class="conv-field">
                            <label for="heightCm">Centimeters</label>
                            <input type="number" id="heightCm" class="form-control" placeholder="e.g. 170" min="0" step="0.1">
                        </div>
                    </div>

                    <div id="heightFtInFields" class="is-hidden">
                        <div class="conv-field">
                            <label for="heightFt">Feet</label>
                            <input type="number" id="heightFt" class="form-control" placeholder="e.g. 5" min="0" step="1">
                        </div>
                        <div class="conv-field">
                            <label for="heightIn">Inches</label>
                            <input type="number" id="heightIn" class="form-control" placeholder="e.g. 7" min="0" max="11.99" step="0.1">
                        </div>
                    </div>

                    <div class="conv-result" id="heightResult">Result: <strong>—</strong></div>
                </div>

                <!-- Weight -->
                <div>
                    <h4 class="mt-0">Weight</h4>
                    <div class="conv-unit-toggle">
                        <button class="active" data-weight-unit="kg">Kilograms</button>
                        <button data-weight-unit="lb">Pounds</button>
                    </div>

                    <div class="conv-field">
                        <label for="weightInput" id="weightInputLabel">Kilograms</label>
                        <input type="number" id="weightInput" class="form-control" placeholder="e.g. 65" min="0" step="0.1">
                    </div>

                    <div class="conv-result" id="weightResult">Result: <strong>—</strong></div>
                </div>
            </div>

            <div class="tools-card conv-bmi">
                <h4 class="mt-0"><i class="ti ti-heart-rate-monitor"></i> BMI (uses the values above)</h4>
                <div class="conv-result" id="bmiResult">Enter height and weight to calculate BMI.</div>
            </div>
        </div>

    </div><!-- /.tools-panels -->
</div><!-- /.tools-layout -->

<script src="<?= url('scripts/tools.js?v=20261016-print-live-10m') ?>"></script>
