<div class="tools-panel" id="tab-print-activities">
    <div class="tools-section-header">
        <h2 class="tools-section-title"><i class="ti ti-printer"></i> Print Logs</h2>
        <p class="tools-section-sub">Completed print jobs from the Windows PrintService Operational log. The list checks for new records every 10 minutes while this page is open.</p>
        <p class="tools-section-sub">Retained spool data is available to administrators for 90 days. A spool file is the printer-ready job representation, not necessarily the original source document.</p>
    </div>

    <?php if (!$printActivities['available']): ?>
        <div class="tools-card print-log-empty">
            <i class="ti ti-database-off"></i>
            <strong>Print activity storage is not ready</strong>
            <p>Run <code>migrations/print_activities.sql</code>, <code>migrations/print_activities_all_events.sql</code>, and <code>migrations/print_spool_archives.sql</code> on the application database, then configure the Windows collector.</p>
        </div>
    <?php else: ?> 
        <form class="print-log-filters" method="get" action="<?= url('tools') ?>#print-activities">
            <label class="sr-only" for="printLogSearch">Search print activity</label>
            <input id="printLogSearch" class="form-control" type="search" name="q"
                   value="<?= htmlspecialchars($printActivities['search'], ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Search user, computer, printer, or job details">
            <button class="btn btn-primary" type="submit"><i class="ti ti-search"></i> Search</button>
            <?php if ($printActivities['search'] !== ''): ?>
                <a class="btn btn-secondary" href="<?= url('tools#print-activities') ?>">Clear</a>
            <?php endif; ?>
            <span class="print-log-total" id="printLogTotal"><?= number_format($printActivities['total']) ?> records</span>
            <span class="print-log-live-status" id="printLogLiveStatus" aria-live="polite">Live updates every 5 seconds</span>
        </form>

        <div class="tools-card print-log-table-wrap">
            <?php if (!$printActivities['records']): ?>
                <div class="print-log-empty">
                    <i class="ti ti-printer-off"></i> 
                    <strong>No print activity found</strong>
                    <p>Newly collected completed print jobs will appear here.</p>
                </div>
            <?php else: ?>
                <div class="print-log-scroll">
                    <table class="print-log-table">
                        <thead>
                            <tr>
                                <th scope="col">Time</th>
                                <th scope="col">Event details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($printActivities['records'] as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$record['display_time'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="print-log-document">
                                        <?php if ($record['job_details']): ?>
                                            <dl class="print-job-details">
                                                <?php foreach ($record['job_details'] as $label => $value): ?>
                                                    <div class="print-job-detail">
                                                        <dt><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></dt>
                                                        <dd><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></dd>
                                                    </div>
                                                <?php endforeach; ?>
                                            </dl>
                                        <?php else: ?>
                                            <span class="text-muted">No structured print-job details available.</span>
                                        <?php endif; ?>
                                        <div class="print-spool-archive">
                                            <?php if ($record['spool_archive_id']): ?>
                                                <a class="btn btn-secondary btn-sm" href="<?= url('tools/print-archives/' . (int)$record['spool_archive_id'] . '/spool') ?>">
                                                    <i class="ti ti-download"></i> Download spool file
                                                </a>
                                                <?php if ($record['conversion_status'] === 'converted'): ?>
                                                    <a class="btn btn-primary btn-sm" href="<?= url('tools/print-archives/' . (int)$record['spool_archive_id'] . '/pdf') ?>">
                                                        <i class="ti ti-file-type-pdf"></i> Download PDF
                                                    </a>
                                                <?php elseif ($record['conversion_status'] === 'unsupported'): ?>
                                                    <span class="text-muted">PDF unavailable: <?= htmlspecialchars((string)$record['spool_format'], ENT_QUOTES, 'UTF-8') ?> spool format is not supported.</span>
                                                <?php else: ?>
                                                    <span class="text-muted">PDF conversion failed; the original spool file is available.</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No spool file was archived. The job may have expired or exceeded the 100 MB upload limit.</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($printActivities['pages'] > 1): ?>
                    <nav class="print-log-pagination" aria-label="Print activity pages">
                        <?php if ($printActivities['page'] > 1): ?>
                            <a class="btn btn-secondary" href="<?= url('tools') ?>?<?= htmlspecialchars(http_build_query(['q' => $printActivities['search'], 'page' => $printActivities['page'] - 1]), ENT_QUOTES, 'UTF-8') ?>#print-activities"><i class="ti ti-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <span>Page <?= $printActivities['page'] ?> of <?= $printActivities['pages'] ?></span>
                        <?php if ($printActivities['page'] < $printActivities['pages']): ?>
                            <a class="btn btn-secondary" href="<?= url('tools') ?>?<?= htmlspecialchars(http_build_query(['q' => $printActivities['search'], 'page' => $printActivities['page'] + 1]), ENT_QUOTES, 'UTF-8') ?>#print-activities">Next <i class="ti ti-chevron-right"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
