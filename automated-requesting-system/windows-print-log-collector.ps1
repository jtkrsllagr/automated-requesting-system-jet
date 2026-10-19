param(
    [Parameter(Mandatory = $true)]
    [string]$AppUrl,
    [string]$CursorPath = "$env:ProgramData\ARS\print-log-cursor.txt",
    [string]$SpoolQueuePath = "$env:ProgramData\ARS\print-spool-pending.json",
    [int]$InitialLookbackHours = 0,
    [int]$PollSeconds = 5,
    [switch]$Backfill
)

$ErrorActionPreference = 'Stop'
$Token = $env:ARS_PRINT_LOG_TOKEN
if ([string]::IsNullOrWhiteSpace($Token)) {
    throw 'Set the machine environment variable ARS_PRINT_LOG_TOKEN before starting the collector.'
}
$logName = 'Microsoft-Windows-PrintService/Operational'
$endpoint = $AppUrl.TrimEnd('/') + '/api/print-activities'
$cursorDirectory = Split-Path -Parent $CursorPath
if (-not (Test-Path $cursorDirectory)) {
    New-Item -ItemType Directory -Path $cursorDirectory -Force | Out-Null
}
$spoolQueueDirectory = Split-Path -Parent $SpoolQueuePath
if (-not (Test-Path $spoolQueueDirectory)) {
    New-Item -ItemType Directory -Path $spoolQueueDirectory -Force | Out-Null
}

$lastRecordId = 0L
$pendingSpoolEvents = @()
if (Test-Path -LiteralPath $SpoolQueuePath) {
    try {
        $pendingSpoolEvents = @(Get-Content -LiteralPath $SpoolQueuePath -Raw | ConvertFrom-Json)
    } catch {
        throw "Cannot read the pending spool archive queue at '$SpoolQueuePath': $($_.Exception.Message)"
    }
}
if ($Backfill -and (Test-Path $CursorPath)) {
    Remove-Item -Path $CursorPath -Force
}
if (-not $Backfill -and (Test-Path $CursorPath)) {
    $savedCursor = Get-Content -Path $CursorPath -Raw
    if ($savedCursor -match '^\d+$') { $lastRecordId = [long]$savedCursor }
}

function Convert-PrintEvent($eventRecord) {
    $eventXml = [string]$eventRecord.ToXml()
    $xml = [xml]$eventXml
    $fields = @{}
    foreach ($field in $xml.Event.EventData.Data) {
        if ($field.Name) { $fields[[string]$field.Name] = [string]$field.InnerText }
    }
    $parameters = @{}
    $printedEvent = $xml.SelectSingleNode('//*[local-name()="DocumentPrinted"]')
    if ($printedEvent) {
        foreach ($parameter in $printedEvent.ChildNodes) {
            if ($parameter.LocalName -match '^Param([1-8])$') {
                $parameters[[int]$Matches[1]] = [string]$parameter.InnerText
            }
        }
    }
    $printJobId = $fields['JobId']
    if (-not $printJobId) { $printJobId = $parameters[1] }

    [pscustomobject]@{
        source_computer = [string]$eventRecord.MachineName
        event_record_id = [long]$eventRecord.RecordId
        event_id = [int]$eventRecord.Id
        provider_name = [string]$eventRecord.ProviderName
        level_display_name = [string]$eventRecord.LevelDisplayName
        event_message = [string]$eventRecord.Message
        event_xml = $eventXml
        printed_at = $eventRecord.TimeCreated.ToString('o')
        print_job_id = [long]($printJobId -as [long])
        document_name = [string]$(if ($fields['Document']) { $fields['Document'] } else { $parameters[2] })
        user_name = [string]$(if ($fields['User']) { $fields['User'] } else { $parameters[3] })
        client_computer = [string]$(if ($fields['ClientComputer']) { $fields['ClientComputer'] } else { $parameters[4] })
        printer_name = [string]$(if ($fields['Printer']) { $fields['Printer'] } else { $parameters[5] })
        port_name = [string]$(if ($fields['Port']) { $fields['Port'] } else { $parameters[6] })
        pages = [int]$(if ($fields['Pages']) { $fields['Pages'] -as [int] } else { $parameters[8] -as [int] })
        job_size_bytes = [long]$(if ($fields['Size']) { $fields['Size'] -as [long] } else { $parameters[7] -as [long] })
    }
}

function Send-PrintSpoolArchive($event) {
    if ([long]$event.print_job_id -le 0 -or [string]::IsNullOrWhiteSpace([string]$event.printer_name)) {
        return $true
    }

    try {
        $queuedJob = Get-PrintJob -PrinterName ([string]$event.printer_name) -ErrorAction Stop |
            Where-Object { [long]$_.ID -eq [long]$event.print_job_id } |
            Select-Object -First 1
    } catch {
        Write-Warning "Could not inspect retained print job $($event.print_job_id) on $($event.printer_name): $($_.Exception.Message)"
        return $false
    }

    if (-not $queuedJob) { return $true }
    $spoolPath = Join-Path $env:windir ("System32\spool\PRINTERS\{0:D5}.SPL" -f $event.print_job_id)
    if (-not (Test-Path -LiteralPath $spoolPath -PathType Leaf)) {
        $spoolPath = Join-Path $env:windir ("System32\spool\PRINTERS\{0}.SPL" -f $event.print_job_id)
    }
    if (-not (Test-Path -LiteralPath $spoolPath -PathType Leaf)) {
        Write-Warning "Retained print job $($event.print_job_id) is queued, but its spool file is not available yet."
        return $false
    }
    $spoolSize = (Get-Item -LiteralPath $spoolPath).Length
    if ($spoolSize -gt 104857600) {
        Write-Warning "Spool file for event $($event.event_record_id) exceeds 100 MB and was not uploaded."
        return $true
    }

    $request = [System.Net.HttpWebRequest]::Create($endpoint.Replace('/api/print-activities', '/api/print-spool-archives'))
    $request.Method = 'POST'
    $request.ContentType = 'application/octet-stream'
    $request.Timeout = 600000
    $request.ReadWriteTimeout = 600000
    $request.Headers.Add('X-Print-Log-Token', $Token)
    $request.Headers.Add('X-Print-Source-Computer', [string]$event.source_computer)
    $request.Headers.Add('X-Print-Event-Record-Id', [string]$event.event_record_id)
    $request.Headers.Add('X-Print-Job-Id', [string]$event.print_job_id)
    $request.ContentLength = $spoolSize
    $fileStream = [System.IO.File]::OpenRead($spoolPath)
    $requestStream = $null
    try {
        $requestStream = $request.GetRequestStream()
        $fileStream.CopyTo($requestStream)
        $requestStream.Close()
        $requestStream = $null
        $response = $request.GetResponse()
        try {
            $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
            $result = $reader.ReadToEnd() | ConvertFrom-Json
            Write-Host "Archived spool data for print event $($event.event_record_id): $($result.conversion_status)."
        } finally {
            $response.Close()
        }
    } finally {
        $fileStream.Close()
        if ($requestStream) { $requestStream.Close() }
    }
    return $true
}

function Save-PendingSpoolEvents {
    $temporaryQueuePath = $SpoolQueuePath + '.tmp'
    ConvertTo-Json -InputObject @($script:pendingSpoolEvents) -Depth 4 |
        Set-Content -LiteralPath $temporaryQueuePath -Encoding UTF8
    Move-Item -LiteralPath $temporaryQueuePath -Destination $SpoolQueuePath -Force
}

function Process-PendingSpoolEvents {
    $remaining = @()
    foreach ($pendingEvent in $script:pendingSpoolEvents) {
        try {
            if (-not (Send-PrintSpoolArchive $pendingEvent)) {
                $remaining += $pendingEvent
            }
        } catch {
            Write-Warning "Spool archive for event $($pendingEvent.event_record_id) will be retried: $($_.Exception.Message)"
            $remaining += $pendingEvent
        }
    }
    $script:pendingSpoolEvents = $remaining
    Save-PendingSpoolEvents
}

while ($true) {
    try {
        $latestEvent = Get-WinEvent -LogName $logName -MaxEvents 1 -ErrorAction SilentlyContinue
        if ($latestEvent -and $lastRecordId -gt [long]$latestEvent.RecordId) {
            Write-Warning 'PrintService log record IDs reset; restarting from the configured lookback window.'
            $lastRecordId = 0L
            Remove-Item -Path $CursorPath -Force -ErrorAction SilentlyContinue
        }

        if ($lastRecordId -gt 0) {
            $xpath = "*[System[(EventID=307) and (EventRecordID > $lastRecordId)]]"
            $events = @(Get-WinEvent -LogName $logName -FilterXPath $xpath -ErrorAction SilentlyContinue)
        } elseif ($InitialLookbackHours -gt 0) {
            $events = @(Get-WinEvent -FilterHashtable @{
                LogName = $logName
                Id = 307
                StartTime = (Get-Date).AddHours(-$InitialLookbackHours)
            } -ErrorAction SilentlyContinue)
        } else {
            $events = @(Get-WinEvent -FilterHashtable @{
                LogName = $logName
                Id = 307
            } -ErrorAction SilentlyContinue | Sort-Object RecordId)
        }

        $events = @($events | Sort-Object RecordId)
        for ($offset = 0; $offset -lt $events.Count; $offset += 10) {
            $end = [Math]::Min($offset + 9, $events.Count - 1)
            $batch = @($events[$offset..$end] | ForEach-Object { Convert-PrintEvent $_ })
            $body = @{ events = $batch } | ConvertTo-Json -Depth 5
            $response = Invoke-RestMethod -Method Post -Uri $endpoint -Headers @{
                'X-Print-Log-Token' = $Token
            } -ContentType 'application/json; charset=utf-8' -Body $body
            foreach ($event in $batch) {
                if ([long]$event.print_job_id -gt 0 -and -not [string]::IsNullOrWhiteSpace([string]$event.printer_name)) {
                    $alreadyQueued = @($pendingSpoolEvents | Where-Object {
                        [string]$_.source_computer -eq [string]$event.source_computer -and
                        [long]$_.event_record_id -eq [long]$event.event_record_id
                    }).Count -gt 0
                    if (-not $alreadyQueued) {
                        $pendingSpoolEvents += [pscustomobject]@{
                            source_computer = [string]$event.source_computer
                            event_record_id = [long]$event.event_record_id
                            print_job_id = [long]$event.print_job_id
                            printer_name = [string]$event.printer_name
                        }
                    }
                }
            }
            Save-PendingSpoolEvents
            $lastRecordId = [long]$events[$end].RecordId
            Set-Content -Path $CursorPath -Value $lastRecordId -NoNewline
            Write-Host "Uploaded $($response.accepted) new print event(s); cursor $lastRecordId."
        }
        Process-PendingSpoolEvents
    } catch {
        Write-Warning "Print log collection failed: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds $PollSeconds
}
