## Accessing ARS Over Mobile Data (Tailscale)

By default, ARS is only reachable at a private LAN address (e.g. `https://192.168.100.108`) —
see [DOCKER.md](./DOCKER.md#giving-staff-access-network-setup). That address only exists inside
the office network, so a phone on mobile data has no route to it at all. This isn't a Docker
setting — it's fixed by putting the phone and the Docker host on the same private network,
which is exactly what [Tailscale](https://tailscale.com) does (a mesh VPN, free for small teams).

No app code changes are needed for this — `url()` in `config/app.php` only generates
root-relative links, so the app works identically no matter which address (LAN IP, Tailscale
IP, or MagicDNS name) it's reached through.

---

### Step 1 — Install Tailscale on the Docker host

1. Sign up free at [tailscale.com](https://tailscale.com) (Google/Microsoft/GitHub login all work).
2. Download and install the **Windows client** on the machine running Docker, and sign in.
3. That's it for the host side — a sidecar container isn't needed. `docker-compose.yml` already
   publishes ports to all interfaces (`"80:80"`, `"443:443"`, `"8443:8443"`), so once the *Windows host* joins
   the tailnet, everything it exposes is reachable through it too.

You'll see a `100.x.y.z` Tailscale IP assigned to this machine — visible in the system tray icon,
or the [admin console](https://login.tailscale.com/admin/machines).

---

### Step 2 — Turn on MagicDNS (recommended)

Admin console → **DNS** → enable **MagicDNS**. This gives the host a stable name like
`ars-server.your-tailnet.ts.net` instead of a numeric IP you'd otherwise have to remember and
re-share every time it changes.

---

### Step 3 — Install Tailscale on staff phones

1. App Store / Google Play → **Tailscale** → sign in with the same account/organization.
2. If **device approval** is turned on (Settings → Device management — recommended for a
   business tailnet, so a random device signed into the same email can't silently join), approve
   each phone once in the admin console.

---

### Step 4 — Access the app over mobile data

With the Tailscale toggle **on** in the phone app, open a browser and go to:

```
https://100.x.y.z
```

or, once MagicDNS is set up:

```
https://ars-server.your-tailnet.ts.net
```

Same login page, same app — reachable from anywhere with a data connection, and never exposed
to the public internet.

---

### About the certificate warning

The Docker image bakes a **self-signed certificate** at build time (`docker/Dockerfile`),
scoped for the LAN IP. Browsers will show a "Not Secure" warning when hitting the Tailscale
address too — this is cosmetic (Tailscale's own WireGuard tunnel is already end-to-end
encrypted independently of this), but it looks unprofessional for staff logging in.

**Option A — do nothing.** Click through the one-time browser warning. Zero extra setup, and
the connection is still genuinely private via Tailscale.

**Option B — get a real trusted certificate.** Tailscale can auto-issue a free Let's Encrypt
cert for your MagicDNS name. `docker-compose.yml` already has the mount wired up (commented
out) for exactly this — see `docker/certs/README.md` for the full walkthrough. Short version:

1. Admin console → **HTTPS Certificates** → enable.
2. On the Docker host, from inside `docker/certs/`:
   `tailscale cert --cert-file=ars-selfsigned.crt --key-file=ars-selfsigned.key ars-server.your-tailnet.ts.net`
3. Replace the `tls_certs` volume under both `app` and `realtime` in `docker-compose.yml` with the matching certificate and key bind mounts shown there.
4. `docker compose up -d --force-recreate app realtime` — no image rebuild needed.

Tailscale certs are Let's Encrypt-backed and last ~90 days; re-running step 2 and
`docker compose restart app realtime` before expiry renews it (see `docker/certs/README.md` for a
suggested scheduled-task approach).

---

### Optional hardening

- **ACLs** — restrict which tailnet devices/roles can actually reach the Docker host's ports,
  rather than every device on the tailnet having access by default.
- **Device approval** — keep this on so new devices need explicit sign-off before joining.

---

### Quick reference

| Item | Value |
|------|-------|
| Docker host ports published | `80`, `443`, `8443` (see `docker-compose.yml`; restrict with tailnet ACLs) |
| App code changes required | None |
| `.env.docker` changes required | None |
| Where to find your Tailscale IP | System tray icon, or [admin console → Machines](https://login.tailscale.com/admin/machines) |
| Where to enable MagicDNS | Admin console → DNS |
| Where to enable trusted HTTPS certs | Admin console → HTTPS Certificates |