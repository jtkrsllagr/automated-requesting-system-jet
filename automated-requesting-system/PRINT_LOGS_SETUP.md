# Print Logs Setup

This feature displays completed print jobs (Event ID 307) from the Windows PrintService Operational log. The PowerShell collector runs on the Windows Server VM at `192.168.100.130`, reads its local Event Viewer log, and can archive retained `.SPL` spool data before sending it to the application over HTTPS. It does not need domain administrator credentials or connect to Active Directory remotely. Print jobs sent directly from a workstation to a printer without passing through this print server will not appear.

## 1. Apply the database migration

Run `migrations/print_activities.sql`, `migrations/print_activities_all_events.sql`, and `migrations/print_spool_archives.sql` against the same database used by the application, in that order.

## 2. Configure the ingestion token

Generate a long random token on a trusted machine, for example with PowerShell:

```powershell
$bytes = New-Object byte[] 32
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
[BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
```

Add it to the environment file used by the app deployment. For Docker Compose in this repository, add it to `.env.docker`; for a non-Docker deployment, add it to the server-side `.env` file:

```dotenv
PRINT_LOG_INGEST_TOKEN=replace-with-the-generated-random-token
```

Keep the token secret and do not commit either environment file. Recreate the application container after changing environment configuration. The collector must use the exact same token.

After applying the code and setting the token, rebuild the Docker app image. The image includes Ghostscript for PostScript-to-PDF conversion; retained spool files are stored in a private Docker volume:

```powershell
docker compose up -d --build app
```

## 3. Verify the server addresses and URL

The supplied addresses are:

- Physical server `3e hitech-server`: wired `192.168.100.129`, Wi-Fi `192.168.100.111`
- Windows Server VM / print server: `192.168.100.130`
- Docker testing host: `192.168.100.108`

For Docker testing from the VM, use `https://192.168.100.108`. The Docker Compose configuration publishes HTTPS on port 443 and serves the app from the web root, so no extra application path is needed. Check reachability from the VM with:

```powershell
Test-NetConnection 192.168.100.108 -Port 443
```

The repository-generated test certificate includes `192.168.100.108` and `localhost` in its subject alternative names, but it is self-signed. Install/trust that certificate on the VM for testing, or configure Docker with a certificate issued by a CA trusted by the VM. Do not disable certificate validation in the collector. For deployment on the physical server instead, use its wired address `192.168.100.129` and install a certificate that includes that address (the existing generated certificate does not). Permit inbound TCP 443 on the Docker host and outbound TCP 443 from the VM.

## 4. Install and run the collector on the Windows print server

On each print queue, enable **Keep printed documents**. The setting preserves completed queue jobs; it does not preserve the original source file. The collector archives the retained `.SPL` data from the Windows spool directory, which is the printer-ready job representation. Notify users that print content is archived and retained for 90 days before enabling collection.

The account running the collector needs read access to `Microsoft-Windows-PrintService/Operational`, the queue jobs, and `C:\Windows\System32\spool\PRINTERS`, plus outbound HTTPS access to the application. Use a dedicated service account with only the required permissions; do not use a domain administrator account. Set the token as a machine environment variable from Windows System Properties > Environment Variables:

```text
ARS_PRINT_LOG_TOKEN=<the same token configured in the app .env>
```

Copy `windows-print-log-collector.ps1` to the VM `192.168.100.130` and run it in PowerShell under the account with read access, replacing the URL with the application URL verified above:

```powershell
.\windows-print-log-collector.ps1 -AppUrl 'https://192.168.100.108' -Backfill
```

The `-Backfill` switch ignores and resets any existing cursor so the collector sends all Event ID 307 records currently retained in the Operational log, then continues polling every 5 seconds for new Event ID 307 records. Repeated submissions are deduplicated in the database. Omit `-Backfill` for normal restarts after the initial backfill. To limit the backfill window, pass `-InitialLookbackHours 24`; to change the polling interval, pass `-PollSeconds`.

The collector associates a retained spool job with Event 307 using the spool job ID. It retries pending spool archives separately so a delayed or unavailable spool file does not stop collection of later print events. Jobs no longer present in the queue cannot be recovered from the event log alone. The application stores received spool files privately for 90 days and allows administrators to download them. PDF input is retained as PDF; PostScript is converted with Ghostscript. Other spool formats are preserved for download and labeled as unsupported for PDF conversion. Conversion failures do not discard the original spool data.

The Print Logs page polls the application database every 5 seconds while the tab is open. This only displays records already received by the application; the Windows collector must also be running on the print server for new Event 307 records to arrive. Restart the collector after updating the script so it uses the 5-second default polling interval.

Spool uploads are limited to 100 MB per job. Larger spool files are reported in the collector log and skipped. Keep queue retention enabled long enough for the collector to process retained jobs. The collector must be able to read both the print queue and the spool files; a queued job without a readable `.SPL` file remains pending for retry.

For continuous operation, create a Task Scheduler task that runs at system startup under an account allowed to read the operational log, with **Run whether user is logged on or not** and restart-on-failure enabled. The machine-level token must be available to that task's account. Do not put the token in the task's command-line arguments.

## 5. View records

Sign in to the application with an administrator account (role ID 1). Open **Extras > Print Logs**. The view includes print time, domain/user name, client computer, document name, printer, port, and pages, with search and pagination.

Only Event ID 307 is displayed and collected; other Event IDs already in the database are left untouched but hidden from the activity list. Event 307 reports completed print jobs. Archive access and downloads are restricted to administrators (role ID 1). Expired spool files are purged during administrator page visits or collector ingestion. Ensure the PrintService Operational log is enabled and has enough retention to cover the collector's availability window.
