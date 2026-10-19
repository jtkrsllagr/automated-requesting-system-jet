## Running the Automated Requesting System in Docker

### Prerequisites

Install **Docker Desktop** on the machine that will host the system:
- Download from [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop)
- Make sure it's **running** before proceeding (whale icon in the taskbar/menu bar)

---

### Step 1 — Get the project files

Unzip the project and open a terminal inside the project folder:

```bash
cd C:\automated-requesting-system
```

---

### Step 2 — First-time build & start

```bash
docker compose up --build
```

This single command:
- Builds the PHP 8.2 + Apache image
- Installs all Composer dependencies
- Starts MariaDB and waits for it to be healthy
- Seeds the entire database (tables, roles, default admin account)

First run takes **2–3 minutes**. You'll know it's ready when you see:

```
ars_app  | AH00558: apache2: ... httpd started
```

---

### Step 3 — Access the system

| What | URL |
|------|-----|
| **App** | `https://localhost/automated-requesting-system/public` |
| **Realtime stream** (dashboard only) | `https://localhost:8443/events` |
| **phpMyAdmin** (DB admin) | `http://localhost:8080` |

> Your browser will show a **certificate warning** — click **Advanced → Proceed to localhost**. This is expected because the SSL certificate is self-signed for local use.

---

### Default login credentials

The database seeds one admin account out of the box:

| Field | Value |
|-------|-------|
| Email | `it@3ehitech.com` |
| Employee Code | `EMP-0001` |
| Role | **SysAdmin** |

Log in as SysAdmin first to create accounts for your staff.

---

### Staff roles available

| Role | What they can do |
|------|-----------------|
| **SysAdmin** | Full system access |
| **Staff** | Submit forms only |
| **Approver** | Approve / reject forms |
| **DepartmentHead** | Department-level approvals |
| **Checker** | Verify / check submissions |
| **FinalApprover** | Final sign-off authority |

---

### Giving staff access (network setup)

By default the system is only reachable at `localhost` (the machine running Docker). To let other staff on the same network access it:

1. Find the **host machine's local IP address** (e.g. `192.168.1.50`)
2. Staff open: `https://192.168.1.50/automated-requesting-system/public`
3. They'll get the same certificate warning — click **Advanced → Proceed**

> If you want the URL to use the IP instead of localhost, update `APP_URL` in `.env.docker` before running:
> ```
> APP_URL=https://192.168.1.50
> ```
> Then rebuild: `docker compose up --build`

> **Need staff to access it off the office network too (mobile data, home, etc.)?**
> See [TAILSCALE.md](./TAILSCALE.md) — a private-network setup that doesn't require opening
> any ports on your router or exposing the app to the public internet.

---

### Day-to-day commands

```bash
# Normal start (after first build)
docker compose up

# Stop (keeps all data)
docker compose down

# View live logs
docker compose logs -f app

# Full reset — wipes DB and starts fresh
docker compose down -v
docker compose up --build

# Shell into the app container
docker exec -it ars_app bash

# Access the database directly
docker exec -it ars_db mariadb -u root -proot arsdb
```

---

### What runs inside Docker

| Container | Purpose | Port |
|-----------|---------|------|
| `ars_app` | PHP 8.2 + Apache (the app) | 80, 443 |
| `ars_realtime` | Node.js pending-count event stream | 8443 |
| `ars_db` | MariaDB 11 (database) | 3307 |
| `ars_phpmyadmin` | phpMyAdmin (DB GUI) | 8080 |

The dashboard's pending KPI uses a persistent event stream and fetches a new count only when an approval changes. Allow TCP `8443` from staff devices to the Docker host; do not expose it to the public internet. Data persists across restarts in Docker volumes (`db_data`, `uploads_data`, `storage_data`, and `tls_certs`). Only `docker compose down -v` wipes them.