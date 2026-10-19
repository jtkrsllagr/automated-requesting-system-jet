# Windows Server 2022 SME Setup Guide
### Web filtering (time-based), USB port blocking, and print tracking via Group Policy

**Assumptions:** Your server is a domain controller (AD DS + DNS role installed), employee workstations are domain-joined, and you're managing everything from Group Policy Management Console (GPMC). Adjust OU names/paths to match your environment.

---

## Part 1 — Restrict Website Access During Working Hours

Windows has no native "block site 9-5, allow after 5" GPO setting, so the cleanest approach for an SME (no extra cost, no third-party firewall) is: **DNS Response Policy Zones (RPZ)** to do the actual blocking, combined with a **Scheduled Task that toggles the block on/off** at your defined hours.

### 1.1 Create the RPZ on your DNS server

Open PowerShell as Administrator on the DC:

```powershell
Add-DnsServerZone -Name "rpz.local" -ZoneFile "rpz.local.dns" -DynamicUpdate "None"
```

This creates an empty Response Policy Zone. Add "poison" records for each site you want blocked — for example, to block facebook.com:

```powershell
Add-DnsServerResourceRecordA -ZoneName "rpz.local" -Name "facebook.com" -IPv4Address 0.0.0.0
Add-DnsServerResourceRecordA -ZoneName "rpz.local" -Name "*.facebook.com" -IPv4Address 0.0.0.0
```

Repeat for every domain you want to control (youtube.com, tiktok.com, instagram.com, etc.). `0.0.0.0` makes the site fail to resolve — effectively unreachable.

> Make sure client PCs use your DC as their DNS server (usually pushed via DHCP scope options — Option 006).

### 1.2 Automate "working hours only" with Scheduled Tasks

Instead of manually adding/removing records, keep two PowerShell scripts:

**`Block-Sites.ps1`** (runs at start of work day, e.g., 8:00 AM):
```powershell
$sites = @("facebook.com","youtube.com","tiktok.com","instagram.com")
foreach ($site in $sites) {
    Add-DnsServerResourceRecordA -ZoneName "rpz.local" -Name $site -IPv4Address 0.0.0.0 -ErrorAction SilentlyContinue
    Add-DnsServerResourceRecordA -ZoneName "rpz.local" -Name "*.$site" -IPv4Address 0.0.0.0 -ErrorAction SilentlyContinue
}
Clear-DnsServerCache
```

**`Unblock-Sites.ps1`** (runs at end of work day, e.g., 6:00 PM):
```powershell
$sites = @("facebook.com","youtube.com","tiktok.com","instagram.com")
foreach ($site in $sites) {
    Remove-DnsServerResourceRecord -ZoneName "rpz.local" -RRType A -Name $site -Force -ErrorAction SilentlyContinue
    Remove-DnsServerResourceRecord -ZoneName "rpz.local" -RRType A -Name "*.$site" -Force -ErrorAction SilentlyContinue
}
Clear-DnsServerCache
```

Register them in Task Scheduler:

```powershell
$action1 = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File C:\Scripts\Block-Sites.ps1"
$trigger1 = New-ScheduledTaskTrigger -Daily -At 8:00AM
Register-ScheduledTask -TaskName "BlockSitesWorkHours" -Action $action1 -Trigger $trigger1 -RunLevel Highest -User "SYSTEM"

$action2 = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File C:\Scripts\Unblock-Sites.ps1"
$trigger2 = New-ScheduledTaskTrigger -Daily -At 6:00PM
Register-ScheduledTask -TaskName "UnblockSitesAfterHours" -Action $action2 -Trigger $trigger2 -RunLevel Highest -User "SYSTEM"
```

Save both scripts to `C:\Scripts\` on the DC first. Add `-DaysOfWeek Monday,Tuesday,Wednesday,Thursday,Friday` on the trigger if you don't want weekend blocking behavior to matter.

### 1.3 Lock down DNS so employees can't bypass it

Tech-savvy employees will switch their DNS to 8.8.8.8 or use a VPN unless you close this off:

1. **Force DNS via GPO:** `Computer Configuration > Policies > Administrative Templates > Network > DNS Client > "Specify DNS servers"` — set to your DC's IP.
2. **Block outbound DNS (port 53) to external resolvers** using Windows Defender Firewall with Advanced Security (or your gateway/router), allowing only your DC.
3. **Block known VPN/proxy apps** via AppLocker or Software Restriction Policies if this is a serious concern.

### 1.4 Alternative (if you want less maintenance)

If ongoing script maintenance feels like overkill, consider a free **DNS filtering service with schedule support** (e.g., Cisco Umbrella free tier, or a pfSense/OPNsense box with pfBlockerNG as your gateway instead of relying purely on Windows DNS). These have built-in category blocking and time-of-day scheduling in a GUI, which is often more sustainable for a small IT team. Since you're already running Windows Server, the RPZ approach above avoids adding another VM.

---

## Part 2 — Block USB Ports via Group Policy

This is the most reliable native Windows method — no third-party tool needed.

### 2.1 Open Group Policy Management

1. On the DC: `Server Manager > Tools > Group Policy Management`
2. Right-click the OU containing your employee computers (e.g., `Employees-PCs`) → **Create a GPO in this domain, and Link it here**
3. Name it something like `USB Restriction Policy`

### 2.2 Configure the removable storage restriction (simplest option)

Edit the GPO:

```
Computer Configuration
  > Policies
    > Administrative Templates
      > System
        > Removable Storage Access
```

Enable these settings:
- **"Removable Disks: Deny read access"** → Enabled
- **"Removable Disks: Deny write access"** → Enabled
- (Optional) **"All Removable Storage classes: Deny all access"** → Enabled — blanket block, simplest option

### 2.3 More precise: block only USB storage, not all USB devices

If you want to keep USB mice/keyboards working but block flash drives specifically, use **Device Installation Restrictions** instead — it's more surgical:

```
Computer Configuration
  > Policies
    > Administrative Templates
      > System
        > Device Installation
          > Device Installation Restrictions
```

1. Enable **"Prevent installation of devices using drivers that match these device setup classes"**
2. Add the GUID for USB Mass Storage devices: `{4d36e967-e325-11ce-bfc1-08002be10318}`
3. This blocks USB storage drivers specifically while leaving HID devices (keyboard/mouse) and printers unaffected.

> Also enable **"Prevent installation of removable devices"** combined with the class GUID above, to cover devices that were already installed once before the policy applied.

### 2.4 Apply and test

```powershell
gpupdate /force
```

Run this on a test client, then plug in a USB flash drive — it should either fail to install or be inaccessible depending on which method you used. Check `Event Viewer > Windows Logs > System` for policy-related events confirming enforcement.

---

## Part 3 — Track Printing Activity

### 3.1 Set up a centralized print server (recommended for tracking)

If not already done, install the **Print Server role**:

```powershell
Install-WindowsFeature Print-Server -IncludeManagementTools
```

Route all office printers through the server (rather than each PC printing directly) — this is what makes centralized tracking possible.

1. `Server Manager > Tools > Print Management`
2. Add your printers under **Print Servers > [Your Server] > Printers**
3. Deploy shared printers to users via GPO: `User Configuration > Preferences > Control Panel Settings > Printers`

### 3.2 Enable detailed print logging

By default, Windows logs print jobs but with minimal detail. Turn on the full operational log:

1. Open **Event Viewer** → `Applications and Services Logs > Microsoft > Windows > PrintService`
2. Right-click **Operational** log → **Enable Log**
3. Right-click **Admin** log → **Enable Log** (usually already on)

Once enabled, every print job appears here with: user name, document name, printer, page count, and timestamp. Event ID **307** is the key one — it logs job completion details including pages printed and byte size.

### 3.3 Query print logs with PowerShell (for reports)

```powershell
Get-WinEvent -LogName "Microsoft-Windows-PrintService/Operational" |
  Where-Object { $_.Id -eq 307 } |
  Select-Object TimeCreated, @{N="User";E={$_.Properties[2].Value}}, @{N="Document";E={$_.Properties[1].Value}}, @{N="Printer";E={$_.Properties[3].Value}}, @{N="Pages";E={$_.Properties[8].Value}} |
  Format-Table -AutoSize
```

Export to CSV for monthly reporting:

```powershell
Get-WinEvent -LogName "Microsoft-Windows-PrintService/Operational" |
  Where-Object { $_.Id -eq 307 } |
  Select-Object TimeCreated, @{N="User";E={$_.Properties[2].Value}}, @{N="Document";E={$_.Properties[1].Value}}, @{N="Pages";E={$_.Properties[8].Value}} |
  Export-Csv "C:\Reports\PrintActivity.csv" -NoTypeInformation
```

Schedule this as a weekly Scheduled Task the same way as Part 1.2 if you want automated reports.

### 3.4 Optional: per-user quotas / cost tracking

Native Windows doesn't do print quotas or cost-per-page out of the box. If you need that level of control (e.g., capping pages per employee per month), consider a third-party tool like **PaperCut NG** (has a free tier for small deployments) sitting on top of your print server — it reads the same print queue and adds quota enforcement, cost reporting, and a web dashboard.

---

## Quick Verification Checklist

| Task | How to verify |
|---|---|
| Site blocking active during hours | `nslookup facebook.com` on a client during work hours → should return `0.0.0.0` |
| Site unblocked after hours | Same command after 6 PM → should resolve normally |
| USB blocked | Insert flash drive on domain PC → access denied / driver install blocked |
| Print tracking working | Print a test doc → check Event Viewer PrintService Operational log for Event ID 307 |

---

## A Few Practical Notes

- **Communicate the policy to staff first.** In most jurisdictions, employees should be informed in writing (via an Acceptable Use Policy or similar) that browsing, USB use, and printing are monitored/restricted on company equipment. This matters both legally and for staff trust.
- Test everything in your VirtualBox lab with a second VM as a domain-joined "client" before rolling out to real employee machines.
- Keep a documented exceptions list (e.g., an OU for management or IT staff exempted from USB blocking) rather than hard-coding exceptions into scripts.
