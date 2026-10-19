# Windows Server 2022 SME Setup Guide
### Web filtering (time-based), USB port blocking, and print tracking via Group Policy

**Assumptions:** Your server is a domain controller (AD DS + DNS role installed), employee workstations are domain-joined, and you're managing everything from Group Policy Management Console (GPMC). Adjust OU names/paths to match your environment.

---

## Part 0 — Initial Windows Server 2022 Setup (Foundation)

Do this before Parts 1–3. It gets you from a fresh VirtualBox install to a working Domain Controller with DNS, ready for the GPOs and scripts later in this guide.

### 0.1 First boot configuration

1. On first boot, Windows prompts you to set the **local Administrator password**. Set a strong one and record it somewhere safe (password manager, not a sticky note).
2. Log in as `Administrator`. **Server Manager** opens automatically — this is your main control panel for the rest of setup.

### 0.2 VirtualBox networking (important — do this first)

For the server to talk to client VMs and eventually your real network, set the VM's network adapter appropriately:

- **Lab/testing only (server + client VMs talking to each other, no internet needed):** Use an **Internal Network** or **Host-only Adapter** in VirtualBox settings for both the server and client VMs, on the same network name.
- **If you want internet access too (Windows Updates, etc.):** Add a second adapter set to **NAT** or **Bridged**, so you have one adapter for the internal lab network and one for internet.

Restart the VM after changing adapter settings if Windows doesn't pick them up automatically.

### 0.3 Set a static IP address

Your DC needs a fixed IP so DNS/DHCP don't break on reboot.

1. `Server Manager > Local Server` → click the network adapter link (e.g., "IPv4 address assigned by DHCP, IPv6 enabled")
2. Right-click the adapter → **Properties** → **Internet Protocol Version 4 (TCP/IPv4)** → **Properties**
3. Set:
   - IP address: e.g. `192.168.56.10` (match your VirtualBox internal network range)
   - Subnet mask: `255.255.255.0`
   - Default gateway: leave blank for internal-only lab, or set your router IP if bridged
   - Preferred DNS server: `127.0.0.1` (the server will be its own DNS server once AD DS is installed)

### 0.4 Rename the server

Give it a proper hostname before promoting to a DC (renaming after is more disruptive):

1. `Server Manager > Local Server` → click the current computer name
2. **Computer Name** tab → **Change...** → enter e.g. `SME-DC01`
3. Restart when prompted.

### 0.5 Install Active Directory Domain Services (AD DS)

1. `Server Manager > Manage > Add Roles and Features`
2. **Role-based or feature-based installation** → select your server
3. Check **Active Directory Domain Services** → Add Features when prompted → Next through the wizard → **Install**
4. When installation finishes, click the notification flag (top of Server Manager) → **Promote this server to a domain controller**

### 0.6 Promote to a new forest

Since this is a new SME setup with no existing domain:

1. Select **Add a new forest**
2. Root domain name — pick something like `smecorp.local` (using `.local` is common for internal-only labs; for a real production domain matching a public one, plan this carefully as it's hard to change later)
3. Set **Forest/Domain functional level** to `Windows Server 2016` or higher (2022 is fine if all future DCs will also be 2022)
4. Leave **DNS Server** checked — this installs DNS automatically alongside AD DS, which Part 1's RPZ setup depends on
5. Set a **DSRM password** (Directory Services Restore Mode — separate from the Administrator password, used for AD recovery scenarios)
6. Click through NetBIOS name confirmation, paths (defaults are fine for a lab), and **Install**. The server will reboot automatically.

### 0.7 Verify AD DS + DNS are working

After reboot, log in as `smecorp\Administrator` (domain account now, not local):

```powershell
Get-ADDomain
Get-Service DNS
Resolve-DnsName smecorp.local
```

All three should return clean results with no errors.

### 0.8 Create your OU structure

Organize computers/users now so GPOs in Parts 1–3 have somewhere to link:

```powershell
New-ADOrganizationalUnit -Name "Employees-PCs" -Path "DC=smecorp,DC=local"
New-ADOrganizationalUnit -Name "Employees-Users" -Path "DC=smecorp,DC=local"
New-ADOrganizationalUnit -Name "IT-Admins" -Path "DC=smecorp,DC=local"
```

### 0.9 (Optional but recommended) Install DHCP

If you want client PCs to auto-join the domain network and get IPs (rather than static-configuring each one), add DHCP now:

```powershell
Install-WindowsFeature DHCP -IncludeManagementTools
```

Then in `Server Manager > Tools > DHCP`, create a scope matching your VirtualBox network range, and set **Option 006 (DNS Servers)** to your DC's IP — this is what makes Part 1's DNS filtering apply automatically to every client that gets an address.

### 0.10 Join a client VM to the domain (for testing)

On a second VirtualBox VM (Windows 10/11), same internal network:

1. Set its DNS server to point to your DC's IP (`192.168.56.10`), either statically or via DHCP if you set that up
2. `Settings > System > About > Domain or workgroup` → **Change** → enter `smecorp.local`
3. Enter domain admin credentials when prompted → restart

Once this client is domain-joined and shows up under `Computers` in Active Directory Users and Computers, move it into the `Employees-PCs` OU you created — this is what makes it a target for the GPOs in the rest of this guide.

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
