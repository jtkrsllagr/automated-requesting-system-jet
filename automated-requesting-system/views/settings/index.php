<?php
/**
 * views/settings/index.php
 *
 * $settings – associative array of all settings rows (key => value)
 * $isSysAdmin – bool, true when role_id === 1
 */
$s = fn(string $key, string $default = '') => htmlspecialchars($settings[$key] ?? $default);
$checked = fn(string $key, string $on = '1') => ($settings[$key] ?? '0') === $on ? 'checked' : '';
$selected = fn(string $key, string $match) => ($settings[$key] ?? '') === $match ? 'selected' : '';
?>

<link rel="stylesheet" href="<?= url('stylesheets/settings.css') ?>">

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="ti ti-circle-check"></i>
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="ti ti-alert-circle"></i>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="settings-layout">

    <!-- ── Left nav ── -->
    <nav class="settings-nav">

        <!-- Tabs available to ALL authenticated users -->
        <button class="settings-nav-item active" data-tab="appearance">
            <i class="ti ti-palette"></i> Appearance
        </button>
        <button class="settings-nav-item" data-tab="notifications">
            <i class="ti ti-bell"></i> Notifications
        </button>
        <button class="settings-nav-item" data-tab="sysinfo">
            <i class="ti ti-info-circle"></i> System Info
        </button>

        <!-- SysAdmin-only tabs -->
        <?php if ($isSysAdmin): ?>
            <hr class="settings-nav-divider">
            <button class="settings-nav-item" data-tab="general">
                <i class="ti ti-settings"></i> General
            </button>
            <button class="settings-nav-item" data-tab="mail">
                <i class="ti ti-mail"></i> Mail
            </button>
            <button class="settings-nav-item" data-tab="storage">
                <i class="ti ti-folder"></i> Storage
            </button>
        <?php endif; ?>
    </nav>

    <!-- ── Panels ── -->
    <div class="settings-panels">

        <!-- ════════════════════════════════════════════
             APPEARANCE  (all users)
             ════════════════════════════════════════════ -->
        <div class="settings-panel active" id="tab-appearance">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-palette"></i> Appearance
                </h2>
                <p class="settings-section-sub">Personalise the look of the application.</p>
            </div>

            <form method="POST" action="<?= url('settings/appearance') ?>">
                <?= \App\Helpers\Csrf::field() ?>

                <div class="settings-card">
                    <div class="settings-card-header">Theme</div>
                    <div class="settings-card-body">

                        <!-- Accent colour -->
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="theme_color">Accent colour</label>
                                <span class="settings-row-hint">Highlights buttons and active nav items.</span>
                            </div>
                            <div class="settings-row-control">
                                <select id="theme_color" name="theme_color" class="form-control">
                                    <option value="blue"   <?= $selected('theme_color', 'blue')   ?>>🔵 Blue (default)</option>
                                    <option value="purple" <?= $selected('theme_color', 'purple') ?>>🟣 Purple</option>
                                    <option value="green" <?= $selected('theme_color', 'green')  ?>>🟢 Green</option>
                                    <option value="orange" <?= $selected('theme_color', 'orange') ?>>🟠 Orange</option>
                                </select>
                            </div>
                        </div>

                        <!-- Dark mode -->
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="theme_mode">Colour mode</label>
                                <span class="settings-row-hint">Light or dark interface.</span>
                            </div>
                            <div class="settings-row-control">
                                <select id="theme_mode" name="theme_mode" class="form-control">
                                    <option value="light" <?= $selected('theme_mode', 'light') ?>>☀️ Light</option>
                                    <option value="dark" <?= $selected('theme_mode', 'dark')  ?>>🌙 Dark</option>
                                </select>
                            </div>
                        </div>

                        <!-- Sidebar collapsed -->
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="sidebar_collapsed">Compact sidebar</label>
                                <span class="settings-row-hint">Collapse sidebar to icon-only by default.</span>
                            </div>
                            <div class="settings-row-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="sidebar_collapsed" name="sidebar_collapsed" value="1"
                                           <?= $checked('sidebar_collapsed', '1') ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save appearance
                    </button>
                </div>
            </form>
        </div><!-- /tab-appearance -->


        <!-- ── NOTIFICATIONS  (all users) ── -->
        <div class="settings-panel" id="tab-notifications">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-bell"></i> Notifications
                </h2>
                <p class="settings-section-sub">Choose which events trigger an email notification.</p>
            </div>

            <form method="POST" action="<?= url('settings/notifications') ?>">
                <?= \App\Helpers\Csrf::field() ?>

                <div class="settings-card">
                    <div class="settings-card-header">Email events</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="notify_on_submit">Form submitted</label>
                                <span class="settings-row-hint">Notify when a new form is submitted.</span>
                            </div>
                            <div class="settings-row-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="notify_on_submit" name="notify_on_submit" value="1"
                                           <?= $checked('notify_on_submit') ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="notify_on_approval">Step approved</label>
                                <span class="settings-row-hint">Notify on each approval step.</span>
                            </div>
                            <div class="settings-row-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="notify_on_approval" name="notify_on_approval" value="1"
                                           <?= $checked('notify_on_approval') ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="notify_on_rejection">Form rejected</label>
                                <span class="settings-row-hint">Notify when a form is rejected.</span>
                            </div>
                            <div class="settings-row-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="notify_on_rejection" name="notify_on_rejection" value="1"
                                           <?= $checked('notify_on_rejection') ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="notify_on_completion">Form completed</label>
                                <span class="settings-row-hint">Notify on final sign-off.</span>
                            </div>
                            <div class="settings-row-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="notify_on_completion" name="notify_on_completion" value="1"
                                           <?= $checked('notify_on_completion') ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save notifications
                    </button>
                </div>
            </form>
        </div><!-- /tab-notifications -->


        <!-- ── SYSTEM INFO  (all users — read-only) ── -->
        <div class="settings-panel" id="tab-sysinfo">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-info-circle"></i> System Info
                </h2>
                <p class="settings-section-sub">Read-only snapshot of the current environment.</p>
            </div>

            <?php
                $diskTotal = disk_total_space('/');
                $diskFree = disk_free_space('/');
                $diskUsed = $diskTotal - $diskFree;
                $diskPct = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100) : 0;
                $barClass = $diskPct >= 90 ? 'disk-usage-bar--danger'
                           : ($diskPct >= 70 ? 'disk-usage-bar--warning' : '');

                $fmtBytes = fn(int $b): string => match(true) {
                    $b >= 1_073_741_824 => round($b / 1_073_741_824, 1) . ' GB',
                    $b >= 1_048_576 => round($b / 1_048_576, 1) . ' MB',
                    default => round($b / 1_024, 1) . ' KB',
                };
            ?>

            <!-- Application -->
            <div class="settings-card settings-card--mb">
                <div class="settings-card-header">Application</div>
                <div class="settings-card-body settings-card-body--info">
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-tag"></i> App name</span>
                        <span class="info-row-value"><?= $s('app_name', 'AutomatedRequestingSystem') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-link"></i> App URL</span>
                        <span class="info-row-value"><?= $s('app_url', '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-world"></i> Timezone</span>
                        <span class="info-row-value"><?= $s('app_timezone', 'Asia/Manila') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-code"></i> Environment</span>
                        <span class="info-row-value"><?= $s('app_env', 'local') ?></span>
                    </div>
                </div>
            </div>

            <!-- Server -->
            <div class="settings-card settings-card--mb">
                <div class="settings-card-header">Server</div>
                <div class="settings-card-body settings-card-body--info">
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-brand-php"></i> PHP version</span>
                        <span class="info-row-value"><?= htmlspecialchars(PHP_VERSION) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-server"></i> Server software</span>
                        <span class="info-row-value"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-cpu"></i> OS</span>
                        <span class="info-row-value"><?= htmlspecialchars(PHP_OS_FAMILY) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-clock"></i> Server time</span>
                        <span class="info-row-value"><?= htmlspecialchars(date('Y-m-d H:i:s T')) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-clock-bolt"></i> Max execution</span>
                        <span class="info-row-value"><?= ini_get('max_execution_time') ?>s</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-upload"></i> Upload limit</span>
                        <span class="info-row-value"><?= htmlspecialchars(ini_get('upload_max_filesize')) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-database"></i> Memory limit</span>
                        <span class="info-row-value"><?= htmlspecialchars(ini_get('memory_limit')) ?></span>
                    </div>
                </div>
            </div>

            <!-- Disk -->
            <div class="settings-card">
                <div class="settings-card-header">Disk usage</div>
                <div class="settings-card-body settings-card-body--info">
                    <div class="disk-usage-bar-wrap">
                        <div class="disk-usage-bar <?= $barClass ?>" data-pct="<?= $diskPct ?>"></div>
                        <span class="disk-usage-label"><?= $diskPct ?>% used</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-circle-filled icon-danger"></i> Used</span>
                        <span class="info-row-value"><?= $fmtBytes($diskUsed) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-circle-filled icon-success"></i> Free</span>
                        <span class="info-row-value"><?= $fmtBytes($diskFree) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-circle"></i> Total</span>
                        <span class="info-row-value"><?= $fmtBytes($diskTotal) ?></span>
                    </div>
                </div>
            </div>
            <!-- Developer -->
            <div class="settings-card">
                <div class="settings-card-header">Developer</div>
                <div class="settings-card-body settings-card-body--info">
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-code"></i> Developer</span>
                        <span class="info-row-value">Jet-Ly</span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label"><i class="ti ti-code"></i> Contributor</span>
                        <span class="info-row-value">JHN PHL</span>
                    </div>
                </div>
            </div>
        </div><!-- /tab-sysinfo -->


        <!-- ── GENERAL  (SysAdmin only) ── -->
        <?php if ($isSysAdmin): ?>
        <div class="settings-panel" id="tab-general">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-settings"></i> General
                </h2>
                <p class="settings-section-sub">Core application configuration. SysAdmin only.</p>
            </div>

            <form method="POST" action="<?= url('settings/general') ?>">
                <?= \App\Helpers\Csrf::field() ?>

                <div class="settings-card">
                    <div class="settings-card-header">Application</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="app_name">App name</label>
                            </div>
                            <div class="settings-row-control">
                                <input type="text" id="app_name" name="app_name"
                                       value="<?= $s('app_name') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="app_url">App URL</label>
                                <span class="settings-row-hint">No trailing slash.</span>
                            </div>
                            <div class="settings-row-control">
                                <input type="url" id="app_url" name="app_url"
                                       value="<?= $s('app_url') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="app_timezone">Timezone</label>
                            </div>
                            <div class="settings-row-control">
                                <input type="text" id="app_timezone" name="app_timezone"
                                       value="<?= $s('app_timezone', 'Asia/Manila') ?>" class="form-control"
                                       placeholder="Asia/Manila">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="app_env">Environment</label>
                            </div>
                            <div class="settings-row-control">
                                <select id="app_env" name="app_env" class="form-control">
                                    <option value="local" <?= $selected('app_env', 'local') ?>>Local</option>
                                    <option value="staging" <?= $selected('app_env', 'staging') ?>>Staging</option>
                                    <option value="production" <?= $selected('app_env', 'production') ?>>Production</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">Session &amp; pagination</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="items_per_page">Items per page</label>
                            </div>
                            <div class="settings-row-control settings-row-control--short">
                                <div class="input-addon">
                                    <input type="number" id="items_per_page" name="items_per_page" min="5" max="200"
                                           value="<?= $s('items_per_page', '20') ?>" class="form-control">
                                    <span class="input-addon-text">rows</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="session_lifetime">Session lifetime</label>
                            </div>
                            <div class="settings-row-control settings-row-control--short">
                                <div class="input-addon">
                                    <input type="number" id="session_lifetime" name="session_lifetime" min="5"
                                           value="<?= $s('session_lifetime', '120') ?>" class="form-control">
                                    <span class="input-addon-text">min</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save general
                    </button>
                </div>
            </form>
        </div><!-- /tab-general -->


        <!-- ── MAIL  (SysAdmin only) ── -->
        <div class="settings-panel" id="tab-mail">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-mail"></i> Mail
                </h2>
                <p class="settings-section-sub">SMTP configuration for outgoing emails. SysAdmin only.</p>
            </div>

            <form method="POST" action="<?= url('settings/mail') ?>">
                <?= \App\Helpers\Csrf::field() ?>

                <div class="settings-card">
                    <div class="settings-card-header">SMTP server</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_host">Host</label></div>
                            <div class="settings-row-control">
                                <input type="text" id="mail_host" name="mail_host"
                                       value="<?= $s('mail_host', 'smtp.gmail.com') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_port">Port</label></div>
                            <div class="settings-row-control settings-row-control--short">
                                <input type="number" id="mail_port" name="mail_port"
                                       value="<?= $s('mail_port', '587') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_encryption">Encryption</label></div>
                            <div class="settings-row-control settings-row-control--short">
                                <select id="mail_encryption" name="mail_encryption" class="form-control">
                                    <option value="tls" <?= $selected('mail_encryption', 'tls') ?>>TLS</option>
                                    <option value="ssl" <?= $selected('mail_encryption', 'ssl') ?>>SSL</option>
                                    <option value="" <?= $selected('mail_encryption', '') ?>>None</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-card">
                    <div class="settings-card-header">Credentials</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_username">Username</label></div>
                            <div class="settings-row-control">
                                <input type="email" id="mail_username" name="mail_username"
                                       value="<?= $s('mail_username') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="mail_password">Password</label>
                                <span class="settings-row-hint">Leave blank to keep current.</span>
                            </div>
                            <div class="settings-row-control">
                                <div class="input-password-wrap">
                                    <input type="password" id="mail_password" name="mail_password"
                                           placeholder="••••••••" class="form-control">
                                    <button type="button" class="toggle-password">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_from_address">From address</label></div>
                            <div class="settings-row-control">
                                <input type="email" id="mail_from_address" name="mail_from_address"
                                       value="<?= $s('mail_from_address') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label"><label for="mail_from_name">From name</label></div>
                            <div class="settings-row-control">
                                <input type="text" id="mail_from_name" name="mail_from_name"
                                       value="<?= $s('mail_from_name', 'Automated Requesting System') ?>" class="form-control">
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save mail
                    </button>
                </div>
            </form>
        </div><!-- /tab-mail -->


        <!-- ── STORAGE  (SysAdmin only) ── -->
        <div class="settings-panel" id="tab-storage">
            <div class="settings-section-header">
                <h2 class="settings-section-title">
                    <i class="ti ti-folder"></i> Storage
                </h2>
                <p class="settings-section-sub">Upload path and file-type restrictions. SysAdmin only.</p>
            </div>

            <form method="POST" action="<?= url('settings/storage') ?>">
                <?= \App\Helpers\Csrf::field() ?>

                <div class="settings-card">
                    <div class="settings-card-header">Upload configuration</div>
                    <div class="settings-card-body">

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="upload_path">Upload path</label>
                                <span class="settings-row-hint">Relative to project root. No leading slash.</span>
                            </div>
                            <div class="settings-row-control">
                                <input type="text" id="upload_path" name="upload_path"
                                       value="<?= $s('upload_path', 'public/uploads') ?>" class="form-control"
                                       placeholder="public/uploads">
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="max_file_size_mb">Max file size</label>
                            </div>
                            <div class="settings-row-control settings-row-control--short">
                                <div class="input-addon">
                                    <input type="number" id="max_file_size_mb" name="max_file_size_mb"
                                           min="1" max="100"
                                           value="<?= $s('max_file_size_mb', '10') ?>" class="form-control">
                                    <span class="input-addon-text">MB</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-row-label">
                                <label for="allowed_file_types">Allowed types</label>
                                <span class="settings-row-hint">Comma-separated extensions, e.g. pdf,jpg,png,docx</span>
                            </div>
                            <div class="settings-row-control">
                                <input type="text" id="allowed_file_types" name="allowed_file_types"
                                       value="<?= $s('allowed_file_types', 'pdf,jpg,png,docx') ?>" class="form-control"
                                       placeholder="pdf,jpg,png,docx">
                            </div>
                        </div>

                    </div>
                </div>

                <div class="settings-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save storage
                    </button>
                </div>
            </form>
        </div><!-- /tab-storage -->
        <?php endif; ?>

    </div><!-- /.settings-panels -->
</div><!-- /.settings-layout -->

<script src="<?= url('scripts/settings.js') ?>"></script>