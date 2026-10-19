<?php
    /**
     * ToolsController
     *
     * Renders the internal utilities page (World Clock, Calculator,
    * Height & Weight Converter, Notes, File Converter, and print activity
    * viewer. Notes and print activities are the server-backed tools.
     */
    class ToolsController {

        // ── GET /tools ── //
        public function index(): void {
            \App\Middleware\AuthMiddleware::require();

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $notes = $this->fetchNotes($userId);
            $leaveCredits = $this->leaveCreditsSummary($userId);
            if ($this->canViewPrintActivities()) {
                $this->purgeExpiredPrintArchives();
            }
            $printActivities = $this->canViewPrintActivities()
                ? $this->fetchPrintActivities()
                : null;

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/tools/index.php';
            $content = ob_get_clean();
            $pageTitle = 'Tools';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        public function ingestPrintActivities(): void {
            $configuredToken = (string)(($_ENV['PRINT_LOG_INGEST_TOKEN'] ?? getenv('PRINT_LOG_INGEST_TOKEN')) ?: '');
            $providedToken = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
            if (preg_match('/^Bearer\s+(.+)$/i', $providedToken, $matches)) {
                $providedToken = $matches[1];
            } else {
                $providedToken = (string)($_SERVER['HTTP_X_PRINT_LOG_TOKEN'] ?? '');
            }

            if ($configuredToken === '' || !hash_equals($configuredToken, $providedToken)) {
                $this->json(['error' => 'Unauthorized.'], 401);
            }

            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload) || !isset($payload['events']) || !is_array($payload['events'])) {
                $this->json(['error' => 'Expected an events array.'], 400);
            }
            if (count($payload['events']) > 250) {
                $this->json(['error' => 'A maximum of 250 events can be sent per request.'], 413);
            }

            try {
                $stmt = db()->prepare(
                    'INSERT IGNORE INTO print_activities
                     (source_computer, event_record_id, event_id, provider_name, level_display_name,
                      event_message, event_xml, printed_at, document_name, print_job_id, user_name, client_computer,
                      printer_name, port_name, pages, job_size_bytes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $updateJobId = db()->prepare(
                    'UPDATE print_activities SET print_job_id = ?
                     WHERE source_computer = ? AND event_record_id = ? AND printed_at = ?
                       AND print_job_id = 0'
                );
                $accepted = 0;
                foreach ($payload['events'] as $event) {
                    if (!is_array($event)
                        || !is_numeric($event['event_record_id'] ?? null)
                        || !is_numeric($event['event_id'] ?? null)
                        || trim((string)($event['source_computer'] ?? '')) === '') {
                        continue;
                    }

                    try {
                        $timestamp = trim((string)($event['printed_at'] ?? ''));
                        if ($timestamp === '') continue;
                        $printedAt = new \DateTimeImmutable($timestamp);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    $sourceComputer = $this->boundedText($event['source_computer'], 255);
                    $eventRecordId = (int)$event['event_record_id'];
                    $printJobId = max(0, (int)($event['print_job_id'] ?? 0));
                    $printedAt = $printedAt->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                        ->format('Y-m-d H:i:s');
                    $stmt->execute([
                        $sourceComputer,
                        $eventRecordId,
                        max(0, (int)$event['event_id']),
                        $this->boundedText($event['provider_name'] ?? '', 255),
                        $this->boundedText($event['level_display_name'] ?? '', 64),
                        $this->boundedText($event['event_message'] ?? '', 12000),
                        $this->boundedText($event['event_xml'] ?? '', 1048576),
                        $printedAt,
                        $this->boundedText($event['document_name'] ?? '', 512),
                        $printJobId,
                        $this->boundedText($event['user_name'] ?? '', 255),
                        $this->boundedText($event['client_computer'] ?? '', 255),
                        $this->boundedText($event['printer_name'] ?? '', 255),
                        $this->boundedText($event['port_name'] ?? '', 255),
                        max(0, (int)($event['pages'] ?? 0)),
                        max(0, (int)($event['job_size_bytes'] ?? 0)),
                    ]);
                    $accepted += $stmt->rowCount();
                    if ($printJobId > 0) {
                        $updateJobId->execute([$printJobId, $sourceComputer, $eventRecordId, $printedAt]);
                    }
                }

                $this->purgeExpiredPrintArchives();
                $this->json(['accepted' => $accepted]);
            } catch (\Throwable $e) {
                error_log('Print activity ingestion failed: ' . $e->getMessage());
                $this->json(['error' => 'Print activity storage is unavailable.'], 503);
            }
        }

        public function listPrintActivities(): void {
            \App\Middleware\AuthMiddleware::require();
            if (!$this->canViewPrintActivities()) {
                $this->json(['error' => 'Forbidden.'], 403);
            }

            $result = $this->fetchPrintActivities();
            if (!$result['available']) {
                $this->json(['error' => 'Print activity storage is unavailable.'], 503);
            }

            $records = array_map(static function (array $record): array {
                return [
                    'printed_at' => (string)$record['display_time'],
                    'job_details' => $record['job_details'],
                    'spool_archive_id' => $record['spool_archive_id'] !== null
                        ? (int)$record['spool_archive_id']
                        : null,
                    'spool_format' => $record['spool_format'],
                    'conversion_status' => $record['conversion_status'],
                ];
            }, $result['records']);

            $this->json([
                'records' => $records,
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'search' => $result['search'],
            ]);
        }

        public function ingestPrintSpoolArchive(): void {
            if (!$this->validPrintCollectorToken()) {
                $this->json(['error' => 'Unauthorized.'], 401);
            }

            $sourceComputer = $this->boundedText($_SERVER['HTTP_X_PRINT_SOURCE_COMPUTER'] ?? '', 255);
            $eventRecordId = filter_var($_SERVER['HTTP_X_PRINT_EVENT_RECORD_ID'] ?? null, FILTER_VALIDATE_INT);
            $jobId = filter_var($_SERVER['HTTP_X_PRINT_JOB_ID'] ?? null, FILTER_VALIDATE_INT);
            $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($sourceComputer === '' || $eventRecordId === false || $eventRecordId < 1
                || $jobId === false || $jobId < 1 || $contentLength < 1 || $contentLength > 104857600) {
                $this->json(['error' => 'Invalid spool archive metadata or file size.'], 400);
            }

            $directory = $this->printArchiveDirectory();
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                $this->json(['error' => 'Private spool archive storage is unavailable.'], 503);
            }

            try {
                $activity = db()->prepare(
                    'SELECT id, print_job_id FROM print_activities
                     WHERE source_computer = ? AND event_record_id = ? AND event_id = 307 LIMIT 1'
                );
                $activity->execute([$sourceComputer, $eventRecordId]);
                $printActivity = $activity->fetch(\PDO::FETCH_ASSOC);
                if (!$printActivity || (int)$printActivity['print_job_id'] !== $jobId) {
                    $this->json(['error' => 'No matching completed print event was found.'], 409);
                }

                $existing = db()->prepare(
                    'SELECT id FROM print_spool_archives WHERE print_activity_id = ? LIMIT 1'
                );
                $existing->execute([(int)$printActivity['id']]);
                if ($existing->fetchColumn()) {
                    $this->json(['accepted' => true, 'duplicate' => true]);
                }

                $key = bin2hex(random_bytes(16));
                $temporaryPath = $directory . DIRECTORY_SEPARATOR . $key . '.upload';
                $rawPath = $directory . DIRECTORY_SEPARATOR . $key . '.spl';
                $pdfPath = $directory . DIRECTORY_SEPARATOR . $key . '.pdf';
                $input = fopen('php://input', 'rb');
                $output = fopen($temporaryPath, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    $this->json(['error' => 'Unable to stage the spool archive.'], 503);
                }
                $copied = stream_copy_to_stream($input, $output, 104857601);
                fclose($input);
                fclose($output);
                if ($copied === false || $copied < 1 || $copied > 104857600) {
                    $this->removePrintArchiveFile($temporaryPath);
                    $this->json(['error' => 'Spool archive is empty or exceeds the 100 MB limit.'], 413);
                }
                chmod($temporaryPath, 0640);

                $sha256 = hash_file('sha256', $temporaryPath);
                $prefix = file_get_contents($temporaryPath, false, null, 0, 4096);
                $format = 'unsupported';
                $conversionStatus = 'unsupported';
                $pdfStorageKey = null;
                if (is_string($prefix) && str_starts_with($prefix, '%PDF-')) {
                    $format = 'pdf';
                    if (!rename($temporaryPath, $rawPath) || !copy($rawPath, $pdfPath)) {
                        $this->removePrintArchiveFile($temporaryPath);
                        $this->removePrintArchiveFile($rawPath);
                        $this->removePrintArchiveFile($pdfPath);
                        $this->json(['error' => 'Unable to store the spool archive.'], 503);
                    }
                    chmod($rawPath, 0640);
                    chmod($pdfPath, 0640);
                    $conversionStatus = 'converted';
                    $pdfStorageKey = $key;
                } elseif (is_string($prefix) && str_starts_with(ltrim($prefix), '%!PS')) {
                    $format = 'postscript';
                    if (!rename($temporaryPath, $rawPath)) {
                        $this->removePrintArchiveFile($temporaryPath);
                        $this->json(['error' => 'Unable to store the spool archive.'], 503);
                    }
                    chmod($rawPath, 0640);
                    $conversionStatus = $this->convertPostScriptToPdf($rawPath, $pdfPath)
                        ? 'converted'
                        : 'failed';
                    if ($conversionStatus === 'converted') {
                        chmod($pdfPath, 0640);
                        $pdfStorageKey = $key;
                    } else {
                        $this->removePrintArchiveFile($pdfPath);
                    }
                } else {
                    $format = 'unknown';
                    if (!rename($temporaryPath, $rawPath)) {
                        $this->removePrintArchiveFile($temporaryPath);
                        $this->json(['error' => 'Unable to store the spool archive.'], 503);
                    }
                    chmod($rawPath, 0640);
                }

                $insert = db()->prepare(
                    'INSERT INTO print_spool_archives
                     (print_activity_id, storage_key, pdf_storage_key, spool_format,
                      conversion_status, spool_size_bytes, sha256)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    (int)$printActivity['id'],
                    $key,
                    $pdfStorageKey,
                    $format,
                    $conversionStatus,
                    $copied,
                    $sha256,
                ]);
                $this->json([
                    'accepted' => true,
                    'conversion_status' => $conversionStatus,
                    'format' => $format,
                ]);
            } catch (\Throwable $e) {
                if (isset($temporaryPath)) $this->removePrintArchiveFile($temporaryPath);
                if (isset($rawPath)) $this->removePrintArchiveFile($rawPath);
                if (isset($pdfPath)) $this->removePrintArchiveFile($pdfPath);
                error_log('Print spool archive ingestion failed: ' . $e->getMessage());
                $this->json(['error' => 'Unable to save the spool archive.'], 503);
            }
        }

        public function downloadPrintSpoolArchive(int $id, bool $asPdf = false): void {
            \App\Middleware\AuthMiddleware::require();
            if (!$this->canViewPrintActivities()) {
                http_response_code(403);
                exit('Forbidden.');
            }

            try {
                $stmt = db()->prepare(
                    'SELECT a.storage_key, a.pdf_storage_key, a.conversion_status, p.document_name
                     FROM print_spool_archives a
                     JOIN print_activities p ON p.id = a.print_activity_id
                     WHERE a.id = ? LIMIT 1'
                );
                $stmt->execute([$id]);
                $archive = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('Print spool archive lookup failed: ' . $e->getMessage());
                http_response_code(503);
                exit('Print archive storage is unavailable.');
            }

            if (!$archive || ($asPdf && ($archive['conversion_status'] !== 'converted'
                || !$archive['pdf_storage_key']))) {
                http_response_code(404);
                exit('The requested print archive is not available.');
            }

            $key = $asPdf ? $archive['pdf_storage_key'] : $archive['storage_key'];
            $extension = $asPdf ? 'pdf' : 'spl';
            if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key)) {
                http_response_code(404);
                exit('The requested print archive file is unavailable.');
            }
            $path = $this->printArchiveDirectory() . DIRECTORY_SEPARATOR . $key . '.' . $extension;
            if (!is_file($path)) {
                http_response_code(404);
                exit('The requested print archive file is unavailable.');
            }

            $baseName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string)$archive['document_name']);
            $baseName = trim((string)$baseName, ' .');
            if ($baseName === '') $baseName = 'print-job';
            header('Content-Type: ' . ($asPdf ? 'application/pdf' : 'application/octet-stream'));
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: attachment; filename="' . $baseName . '.' . $extension . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            readfile($path);
            exit;
        }

        // ── GET /attendance ── //
        public function attendance(): void {
            \App\Middleware\AuthMiddleware::require();

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/tools/attendance.php';
            $content = ob_get_clean();
            $pageTitle = 'Attendance';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        // ── POST /tools/payslip/request ── //
        // Demo "Services" action — logs a self-notification so the
        // request shows up in the bell icon. Swap the body of this
        // method for real payroll logic once that data source exists;
        // the CSRF handling around it does not need to change.
        public function requestPayslip(): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $period = trim($_POST['period'] ?? 'the selected period');

            try {
                $stmt = db()->prepare(
                    'INSERT INTO notifications (user_id, type, message, link)
                     VALUES (?, "info", ?, ?)'
                );
                $stmt->execute([
                    $userId,
                    "Payslip request received for {$period}. HR will process it shortly.",
                    url('tools'),
                ]);
            } catch (\Throwable $e) {
                // notifications table may not exist in older installs — non-fatal.
            }

            $_SESSION['success'] = 'Payslip request submitted for ' . $period . '.';
            header('Location: ' . url('tools#services'));
            exit;
        }

        // ── GET /tools/notes ── //
        // Returns the current user's notes as JSON (used to refresh the list).
        public function listNotes(): void {
            \App\Middleware\AuthMiddleware::require();
            $userId = (int)($_SESSION['user_id'] ?? 0);

            $this->json(['notes' => $this->fetchNotes($userId)]);
        }

        // ── POST /tools/notes ── //
        // "New note" always creates a blank note the user can then type
        // into — an empty title/content here is the expected starting
        // state, not an error condition.
        public function createNote(): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $color = $this->safeColor($_POST['color'] ?? 'yellow');

            $stmt = db()->prepare(
                'INSERT INTO notes (user_id, title, content, color) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $title, $content, $color]);

            $this->json(['note' => $this->fetchNote((int)db()->lastInsertId(), $userId)], 201);
        }

        // ── POST /tools/notes/{id}/update ── //
        public function updateNote(int $id): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $color = $this->safeColor($_POST['color'] ?? 'yellow');

            $stmt = db()->prepare(
                'UPDATE notes SET title = ?, content = ?, color = ?
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$title, $content, $color, $id, $userId]);

            if ($stmt->rowCount() === 0) {
                $this->json(['error' => 'Note not found.'], 404);
                return;
            }

            $this->json(['note' => $this->fetchNote($id, $userId)]);
        }

        // ── POST /tools/notes/{id}/delete ── //
        public function deleteNote(int $id): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);

            $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);

            $this->json(['deleted' => $stmt->rowCount() > 0]);
        }

        /**
         * Derives leave-credit usage from approved/completed leave_application
         * forms already in the `forms` table (form_type = 'leave_application').
         * Allotments are a placeholder policy default — adjust to match your
         * actual HR policy, or swap this for a real leave_credits table later.
         */
        private function leaveCreditsSummary(int $userId): array {
            $allotment = ['vacation' => 15, 'sick' => 15, 'parental' => 7, 'other' => 5];
            $used = ['vacation' => 0, 'sick' => 0, 'parental' => 0, 'other' => 0];

            if ($userId === 0) return $this->buildLeaveCredits($allotment, $used);

            try {
                $stmt = db()->prepare(
                    "SELECT data FROM forms
                     WHERE submitted_by = ? AND form_type = 'leave_application'
                     AND status IN ('completed', 'final_approved')"
                );
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $json) {
                    $data = json_decode((string)$json, true) ?: [];
                    $type = $data['leave_type'] ?? 'other';
                    if (!isset($used[$type])) $type = 'other';
                    $used[$type] += (float)($data['num_of_leave'] ?? 0);
                }
            } catch (\Throwable $e) {
                // forms table not reachable — fall back to zeroed usage.
            }

            return $this->buildLeaveCredits($allotment, $used);
        }

        private function buildLeaveCredits(array $allotment, array $used): array {
            $out = [];
            foreach ($allotment as $type => $total) {
                $out[$type] = [
                    'label' => ucfirst($type) . ' Leave',
                    'total' => $total,
                    'used' => min($used[$type], $total),
                    'remaining' => max(0, $total - $used[$type]),
                ];
            }
            return $out;
        }

        // ── Private helpers ── //

        private function fetchNotes(int $userId): array {
            if ($userId === 0) return [];
            try {
                $stmt = db()->prepare(
                    'SELECT id, title, content, color, created_at, updated_at
                     FROM notes WHERE user_id = ? ORDER BY updated_at DESC'
                );
                $stmt->execute([$userId]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // Table may not exist yet (migration not run).
                return [];
            }
        }

        private function fetchNote(int $id, int $userId): ?array {
            $stmt = db()->prepare(
                'SELECT id, title, content, color, created_at, updated_at
                 FROM notes WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$id, $userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        private function fetchPrintActivities(): array {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 50;
            $search = trim((string)($_GET['q'] ?? ''));
            $search = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);

            try {
                $where = ' WHERE p.event_id = 307';
                $params = [];
                if ($search !== '') {
                    $where .= ' AND (p.document_name LIKE ? OR p.user_name LIKE ? OR p.client_computer LIKE ?
                                   OR p.printer_name LIKE ? OR p.port_name LIKE ? OR p.event_message LIKE ?)';
                    $needle = '%' . $search . '%';
                    $params = array_fill(0, 6, $needle);
                }

                $count = db()->prepare('SELECT COUNT(*) FROM print_activities p' . $where);
                $count->execute($params);
                $total = (int)$count->fetchColumn();
                $pages = max(1, (int)ceil($total / $perPage));
                $page = min($page, $pages);
                $offset = ($page - 1) * $perPage;
                $rows = db()->prepare(
                    'SELECT p.id, p.printed_at, p.event_xml, p.document_name, p.user_name, p.printer_name,
                            p.port_name, p.pages, p.job_size_bytes, a.id AS spool_archive_id,
                            a.spool_format, a.conversion_status
                     FROM print_activities p
                     LEFT JOIN print_spool_archives a ON a.print_activity_id = p.id' . $where . '
                     ORDER BY p.printed_at DESC, p.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
                );
                $rows->execute($params);
                $records = $rows->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($records as &$record) {
                    $record['display_time'] = $this->formatPrintActivityTime((string)$record['printed_at']);
                    $record['job_details'] = $this->printJobDetails($record);
                }
                unset($record);

                return [
                    'available' => true,
                    'records' => $records,
                    'page' => $page,
                    'pages' => $pages,
                    'total' => $total,
                    'search' => $search,
                ];
            } catch (\Throwable $e) {
                error_log('Print activity storage query failed: ' . $e->getMessage());
                return ['available' => false, 'records' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'search' => $search];
            }
        }

        private function printJobDetails(array $record): array {
            $details = [
                'Printed by' => (string)($record['user_name'] ?? ''),
                'Printer' => (string)($record['printer_name'] ?? ''),
                'Pages printed' => (int)($record['pages'] ?? 0) > 0
                    ? number_format((int)$record['pages'])
                    : '',
                'Size' => (int)($record['job_size_bytes'] ?? 0) > 0
                    ? $this->formatPrintJobSize((int)$record['job_size_bytes'])
                    : '',
            ];

            $eventXml = (string)($record['event_xml'] ?? '');
            if ($eventXml === '') {
                return array_filter($details, static fn(string $value): bool => $value !== '');
            }

            $document = new \DOMDocument();
            $previousErrorMode = libxml_use_internal_errors(true);
            $loaded = $document->loadXML($eventXml, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
            if (!$loaded) {
                return array_filter($details, static fn(string $value): bool => $value !== '');
            }

            $xpath = new \DOMXPath($document);
            $printedEvent = $xpath->query('//*[local-name()="DocumentPrinted"]')->item(0);
            if ($printedEvent instanceof \DOMElement) {
                $params = [];
                foreach ($printedEvent->childNodes as $child) {
                    if ($child instanceof \DOMElement && preg_match('/^Param([1-8])$/', $child->localName, $matches)) {
                        $params[(int)$matches[1]] = trim($child->textContent);
                    }
                }

                $details['Printed by'] = $details['Printed by'] ?: ($params[3] ?? '');
                $details['Printer'] = $details['Printer'] ?: ($params[5] ?? '');
                $details['Size'] = $details['Size'] ?: (
                    isset($params[7]) && ctype_digit($params[7])
                        ? $this->formatPrintJobSize((int)$params[7])
                        : ''
                );
                $details['Pages printed'] = $details['Pages printed'] ?: (
                    isset($params[8]) && ctype_digit($params[8])
                        ? number_format((int)$params[8])
                        : ''
                );
            }

            return array_filter($details, static fn(string $value): bool => $value !== '');
        }

        private function validPrintCollectorToken(): bool {
            $configuredToken = (string)(($_ENV['PRINT_LOG_INGEST_TOKEN'] ?? getenv('PRINT_LOG_INGEST_TOKEN')) ?: '');
            $providedToken = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
            if (preg_match('/^Bearer\s+(.+)$/i', $providedToken, $matches)) {
                $providedToken = $matches[1];
            } else {
                $providedToken = (string)($_SERVER['HTTP_X_PRINT_LOG_TOKEN'] ?? '');
            }
            return $configuredToken !== '' && hash_equals($configuredToken, $providedToken);
        }

        private function canViewPrintActivities(): bool {
            return in_array((int)($_SESSION['role_id'] ?? 0), [1, 6, 7], true);
        }

        private function convertPostScriptToPdf(string $inputPath, string $outputPath): bool {
            $logPath = $outputPath . '.log';
            $command = [
                '/usr/bin/gs',
                '-q',
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=pdfwrite',
                '-sOutputFile=' . $outputPath,
                $inputPath,
            ];
            $process = proc_open($command, [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', $logPath, 'w'],
            ], $pipes, $this->printArchiveDirectory());
            if (!is_resource($process)) {
                error_log('Ghostscript could not be started for a print spool conversion.');
                $this->removePrintArchiveFile($logPath);
                return false;
            }

            $deadline = microtime(true) + 30;
            $status = proc_get_status($process);
            while ($status['running'] && microtime(true) < $deadline) {
                usleep(100000);
                $status = proc_get_status($process);
            }
            if ($status['running']) {
                proc_terminate($process, 9);
                error_log('Ghostscript timed out converting a PostScript print spool file.');
                proc_close($process);
                $this->removePrintArchiveFile($logPath);
                return false;
            }
            $exitCode = (int)$status['exitcode'];
            $closeCode = proc_close($process);
            if ($exitCode < 0) $exitCode = $closeCode;
            if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) < 5) {
                $stderr = is_file($logPath) ? file_get_contents($logPath, false, null, 0, 2048) : '';
                error_log('Ghostscript failed to convert a PostScript print spool file: ' . trim((string)$stderr));
                $this->removePrintArchiveFile($logPath);
                return false;
            }
            $this->removePrintArchiveFile($logPath);
            return true;
        }

        private function printArchiveDirectory(): string {
            return __DIR__ . '/../../storage/print-archives';
        }

        private function removePrintArchiveFile(string $path): void {
            if (is_file($path) && !unlink($path)) {
                error_log('Unable to remove print archive file: ' . basename($path));
            }
        }

        private function purgeExpiredPrintArchives(): void {
            $directory = $this->printArchiveDirectory();
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                error_log('Unable to create private print archive storage for retention cleanup.');
                return;
            }

            $lockPath = $directory . DIRECTORY_SEPARATOR . 'cleanup.lock';
            $lock = fopen($lockPath, 'c');
            if ($lock === false || !flock($lock, LOCK_EX)) {
                if (is_resource($lock)) fclose($lock);
                error_log('Unable to lock private print archive storage for retention cleanup.');
                return;
            }

            if (filemtime($lockPath) !== false && filemtime($lockPath) > time() - 86400) {
                flock($lock, LOCK_UN);
                fclose($lock);
                return;
            }

            try {
                $stmt = db()->query(
                    "SELECT id, storage_key, pdf_storage_key
                     FROM print_spool_archives
                     WHERE archived_at < NOW() - INTERVAL 90 DAY"
                );
                $expired = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $delete = db()->prepare('DELETE FROM print_spool_archives WHERE id = ?');
                foreach ($expired as $archive) {
                    $delete->execute([(int)$archive['id']]);
                    if ($delete->rowCount() !== 1) continue;
                    $this->removePrintArchiveFile($directory . DIRECTORY_SEPARATOR . $archive['storage_key'] . '.spl');
                    if ($archive['pdf_storage_key']) {
                        $this->removePrintArchiveFile($directory . DIRECTORY_SEPARATOR . $archive['pdf_storage_key'] . '.pdf');
                    }
                }
                touch($lockPath);
            } catch (\Throwable $e) {
                error_log('Print spool archive retention cleanup failed: ' . $e->getMessage());
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }

        private function formatPrintJobSize(int $bytes): string {
            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2) . ' GB';
            }
            if ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2) . ' MB';
            }
            if ($bytes >= 1024) {
                return number_format($bytes / 1024, 2) . ' KB';
            }
            return number_format($bytes) . ' bytes';
        }

        private function formatPrintActivityTime(string $timestamp): string {
            try {
                return (new \DateTimeImmutable($timestamp))
                    ->format('M j, Y | g:i:s A');
            } catch (\Throwable $e) {
                error_log('Unable to format print activity timestamp: ' . $e->getMessage());
                return $timestamp;
            }
        }

        private function boundedText(mixed $value, int $limit): string {
            $text = trim((string)$value);
            return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
        }

        private function safeColor(string $color): string {
            $allowed = ['yellow', 'pink', 'blue', 'green', 'purple'];
            return in_array($color, $allowed, true) ? $color : 'yellow';
        }

        private function json(array $data, int $status = 200): void {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
    }
