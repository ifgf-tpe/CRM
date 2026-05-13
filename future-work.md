# Future Work

This document tracks planned features and exploratory ideas for ChurchCRM that are not yet scheduled for development.

---

## Face Recognition for Attendance Tracking

**Status:** Exploratory / Not Scheduled  
**Related system:** Event Check-in (`src/event/routes/checkin.php`, `src/event/views/checkin.php`) and Kiosk (`src/kiosk/`)

### Motivation

Currently, attendance is recorded manually via the event check-in page or kiosk device — staff must find a person's name in the list and tap/click to mark them present. Face recognition would allow a camera-equipped kiosk to automatically identify members as they arrive, eliminating manual lookup and reducing bottlenecks at high-traffic services.

---

### Option A — Browser-Side Face Recognition (face-api.js)

Run recognition entirely in the browser using a JavaScript library such as [face-api.js](https://github.com/justadudewhohacks/face-api.js) (built on TensorFlow.js).

**How it works:**
1. A webcam stream is opened on the kiosk page.
2. The library detects faces in real time and computes a 128-dimension face descriptor.
3. Descriptors are matched against a pre-loaded set of known member descriptors (fetched from a ChurchCRM API endpoint at page load).
4. On a confident match, the kiosk calls the existing check-in API to record attendance.

**Pros:**
- No external service dependency — works offline/on LAN.
- No biometric data leaves the local network.
- Reuses the existing kiosk infrastructure (`KioskDevice`, `KioskAssignment`).
- Models (~6 MB) are loaded once and cached by the browser.

**Cons:**
- Accuracy degrades with poor lighting or low-resolution webcams.
- Requires storing a reference photo per member (could use the existing member photo in `Person`).
- Initial descriptor index build can be slow for very large congregations (500+ members).
- face-api.js is no longer actively maintained upstream; consider `@mediapipe/face_detection` + a custom descriptor model as a long-term alternative.

**Integration points:**
- New API endpoint: `GET /event/api/face-descriptors` — returns JSON array of `{ personId, descriptor[] }` for all active members with a photo.
- New `FaceRecognitionService` in `src/ChurchCRM/Service/` to generate/cache descriptors server-side.
- Kiosk view gets an optional "Face Scan" mode toggle.

---

### Option B — Server-Side Face Recognition (Python Microservice)

Offload recognition to a small Python sidecar service using [DeepFace](https://github.com/serengil/deepface) or [InsightFace](https://github.com/deepinsight/insightface).

**How it works:**
1. The kiosk captures a JPEG frame every N seconds and POSTs it to the sidecar (e.g. `http://localhost:5000/recognize`).
2. The sidecar compares the frame against a pre-built face index and returns the matched `personId` + confidence score.
3. ChurchCRM calls its own check-in API to record attendance.

**Pros:**
- More accurate models available (ArcFace, FaceNet512).
- GPU acceleration possible on dedicated kiosk hardware.
- PHP/JS stays thin — no ML code in the main app.
- Easier to swap models without touching the CRM codebase.

**Cons:**
- Requires running a second process alongside ChurchCRM (Docker Compose or systemd service).
- Adds Python runtime dependency to the deployment stack.
- Latency: round-trip to the sidecar adds ~100–400 ms per frame.
- Self-hosted deployments need documentation and support for the extra service.

**Integration points:**
- New configurable system setting: `sFaceRecognitionServiceUrl` (defaults to disabled/empty).
- Kiosk polls sidecar when a URL is configured; falls back to manual mode otherwise.
- `FaceRecognitionService.php` wraps the HTTP call and handles timeouts gracefully.

---

### Option C — Cloud Vision API (AWS Rekognition / Google Cloud Vision / Azure Face)

Use a managed cloud API for recognition.

**How it works:**
1. Member photos are indexed in the cloud service's face collection on first use (or when photos are updated).
2. Kiosk sends a captured frame to ChurchCRM, which forwards it to the cloud API.
3. The API returns the matched `ExternalId` (mapped to `personId`), and attendance is recorded.

**Pros:**
- Best-in-class accuracy with no on-device ML.
- No extra infrastructure to maintain.
- Scales to arbitrarily large photo libraries.

**Cons:**
- Biometric data (face images) sent to a third party — significant privacy concern; requires explicit consent from members and GDPR/CCPA/BIPA review.
- Ongoing cost per API call (e.g. AWS Rekognition ~$0.001/image).
- Requires internet connectivity at check-in time.
- Third-party service availability becomes a dependency for attendance.

**Integration points:**
- New system settings: `sFaceRecognitionProvider`, `sFaceRecognitionApiKey`, `sFaceRecognitionRegion`.
- `CloudFaceRecognitionService.php` implementing a common `FaceRecognitionInterface`.
- Privacy consent flag per member: must opt-in before their photo is indexed.

---

### Option D — QR Code / NFC Hybrid ✅ Implemented

Members carry a printed or digital QR code that the self-service check-in endpoint verifies for instant attendance recording. **Phase 1 (QR code check-in) is complete** — see `src/ChurchCRM/Service/QrCodeService.php`, `src/external/routes/member-portal.php`, and `src/external/templates/checkin.php`.

**How it works:**
- Each member gets a unique QR code (URL-safe token derived from their `personId` + HMAC secret).
- Kiosk page uses `jsQR` or a device camera API to scan and verify the token.
- On match, attendance is recorded without manual lookup.

**Pros:**
- Works on any camera-equipped device, no ML required.
- High accuracy, fast scan (~0.5 s).
- No biometric data involved — simpler privacy posture.
- Can be the foundation that face recognition layers on top of.

**Cons:**
- Members must carry their code (phone or printed card).
- No benefit for members who forget or lose their code.

**Integration points:**
- New `QrCodeService` in `src/ChurchCRM/Service/` (see `src/ChurchCRM/Service/QrCodeService.php` if already scaffolded).
- Member profile page gains a "My Check-in QR Code" section.
- Kiosk gains a "Scan QR" mode alongside the existing name-lookup mode.

---

### Recommended Phasing

| Phase | Scope | Complexity |
|-------|-------|------------|
| 1 (short-term) | QR code check-in (Option D) | Low |
| 2 (mid-term) | Browser-side face recognition via face-api.js (Option A) | Medium |
| 3 (long-term) | Server-side sidecar for higher accuracy (Option B) | High |
| — | Cloud Vision API (Option C) | Medium (but privacy-heavy) |

Starting with QR codes delivers immediate efficiency gains with minimal risk, and the infrastructure (kiosk token endpoint, check-in API) directly supports face recognition in Phase 2.

---

### Privacy & Consent Requirements (all face recognition options)

Before any implementation:

- [ ] Add per-member biometric consent flag to the `Person` model.
- [ ] Display opt-in prompt during member onboarding and profile editing.
- [ ] Document data retention policy (how long are face descriptors/photos stored?).
- [ ] Legal review for applicable jurisdictions (GDPR Article 9, CCPA, Illinois BIPA, etc.).
- [ ] Never enroll a member's face without explicit opt-in.

---

### Open Questions

- What hardware (kiosk tablet/PC + camera) is the target deployment?
- What is the acceptable false-positive rate? (A wrong check-in is worse than a missed one.)
- Should face recognition work as a standalone kiosk or as a staff-facing tool?
- Is offline operation (no internet at check-in) a hard requirement?

---

## Deployment Strategy — Zero/Low-Cost Hosting

**Status:** Planning

ChurchCRM is a charity tool. Every hosting dollar spent is a dollar not spent on the congregation. This section maps all viable options from fully free to low-cost paid, so the best fit can be chosen based on the church's situation.

ChurchCRM already ships production-ready Docker Compose files in `docker/`:

- `docker-compose.yaml` — Apache + PHP 8 + MariaDB (used by CI)
- `docker-compose.nginx.yaml` — nginx + PHP-FPM + MariaDB (recommended for production)
- `docker-compose.frankenphp.yaml` — FrankenPHP (modern alternative)

Any self-hosted or VPS option below can use these configs directly with minimal changes.

---

### Option 1 — Cloudflare Tunnel + Your Own PC (Fully Free)

Cost: $0/month. You already own a PC. Cloudflare Tunnel (`cloudflared`) creates a secure, encrypted outbound connection from your machine to Cloudflare's edge — no port forwarding, no public IP, no router configuration needed. Traffic flows: `user → Cloudflare edge → cloudflared daemon on your PC → ChurchCRM`.

#### Option 1 — Setup

1. Install Docker Desktop on your PC (free).
2. Run ChurchCRM using `docker-compose.nginx.yaml` (already in the repo).
3. Create a free Cloudflare account and add your domain (or a free `trycloudflare.com` subdomain for testing).
4. Install `cloudflared` and run:

   ```bash
   cloudflared tunnel create churchcrm
   cloudflared tunnel route dns churchcrm yourchurch.yourdomain.com
   cloudflared tunnel run --url http://localhost:80 churchcrm
   ```

5. Cloudflare handles HTTPS/TLS automatically — your app is reachable at `https://yourchurch.yourdomain.com`.

#### Option 1 — Pros

- Completely free (Cloudflare free plan + your existing PC + electricity).
- No public IP required — works even behind CGNAT (mobile ISP, shared fiber).
- Cloudflare provides DDoS protection, WAF, and TLS for free.
- Full control over data — everything stays on your machine.
- Uses the existing Docker Compose configs with zero changes.

#### Option 1 — Cons

- PC must be on 24/7 — electricity cost (~$5–15/month depending on hardware).
- Home internet upload speed limits response time for many simultaneous users.
- If the PC crashes or power goes out, the app goes offline.
- Not suitable if your ISP blocks outbound tunnels (rare — check terms of service).

**Best for:** Small to medium congregations (< 200 concurrent users), tech-comfortable admin, spare PC or mini PC available (Raspberry Pi 4/5 also works).

---

### Option 2 — Oracle Cloud Free Tier (Fully Free, Cloud-Hosted)

Cost: $0/month (Forever Free tier). Oracle Cloud offers the most generous free cloud tier available:

- **4 ARM Ampere A1 cores + 24 GB RAM** (split across up to 4 instances)
- **2 AMD micro instances** (1 GB RAM each)
- **200 GB block storage**
- **MySQL HeatWave Free Tier** (1 instance, up to 50 GB)
- Free outbound bandwidth up to 10 TB/month

This is enough to run ChurchCRM comfortably with a real cloud IP and 99.9% uptime.

#### Option 2 — Setup

1. Create a free Oracle Cloud account (credit card required for identity verification — nothing is charged on the Always Free tier).
2. Provision an ARM VM (Ubuntu 22.04 LTS recommended).
3. Install Docker + Docker Compose on the VM.
4. Clone the repo and run `docker-compose.nginx.yaml`.
5. Point your domain's DNS A record to the VM's public IP, or put Cloudflare as a proxy in front (free).

#### Option 2 — Pros

- True cloud hosting — your PC stays off, uptime is Oracle's responsibility.
- 24 GB RAM vastly outperforms typical shared hosting.
- MySQL HeatWave free tier covers the database (or keep MariaDB in Docker).
- Static public IP — easy to point a domain at.

#### Option 2 — Cons

- Oracle account setup can be tedious (identity verification, credit card required).
- Always Free resources are regional — if Oracle changes terms, you may need to migrate.
- ARM architecture: Docker images must support `linux/arm64` (ChurchCRM's official images do).
- No GUI — requires SSH/command-line comfort.

**Best for:** Congregations that want cloud reliability at zero cost and have someone comfortable with Linux basics.

---

### Option 3 — DDNS + Home Server + Port Forwarding

Cost: $0/month. If your ISP gives you a dynamic public IP, a DDNS service keeps a hostname pointing to your current IP. Combined with router port forwarding, your home server is reachable from the internet.

Free DDNS providers:

- [DuckDNS](https://www.duckdns.org/) — completely free, `yourchurch.duckdns.org` subdomain, reliable
- [Dynu](https://www.dynu.com/) — free tier, supports custom domains
- [No-IP](https://www.noip.com/) — free tier (requires monthly confirmation click)

#### Option 3 — Setup

1. Install `ddclient` or the DuckDNS updater script — it auto-updates the DNS record when your IP changes.
2. Forward ports 80 and 443 on your home router to your PC's local IP.
3. Run ChurchCRM via Docker Compose.
4. Use [Let's Encrypt](https://letsencrypt.org/) + `certbot` (free) for HTTPS.

#### Option 3 — Pros

- Completely free.
- Full data ownership.
- Works with any PC or server hardware.

#### Option 3 — Cons

- Requires router access and port forwarding — not possible on all ISPs or apartment setups.
- Does NOT work if your ISP uses CGNAT (common on mobile/fiber ISPs in some regions — check first).
- Dynamic IP changes can cause brief downtime until DDNS updates propagate.
- You manage TLS renewal yourself (certbot auto-renewal handles it, but needs configuring).
- Home IP address exposed (mitigated by putting Cloudflare in proxy mode in front).

**Best for:** Tech-comfortable admin with a router they control and a non-CGNAT ISP.

---

### Option 4 — Shared PHP/MySQL Web Hosting (Paid, Best Value)

Cost: ~$1.50–$5/month. Traditional shared hosting still works well for PHP+MySQL apps. The host handles server maintenance, uptime, backups, and TLS.

| Provider | Price/month | PHP version | MySQL | Notes |
|----------|-------------|-------------|-------|-------|
| **Hostinger** | ~$1.99 (annual) | PHP 8.3/8.4 | MySQL 8 | Best value overall; fast NVMe; easy panel |
| **Namecheap Stellar** | ~$1.98 | PHP 8.1–8.3 | MySQL | Budget pick; decent performance |
| **DreamHost Shared** | ~$2.59 | PHP 8.3 | MySQL 8 | Unlimited bandwidth; good for low-traffic |
| **Hetzner Web Hosting** | €1.85 (~$2) | PHP 8.x | MySQL | Europe-based; excellent reliability |
| **InfinityFree** | $0 | PHP 8.x | MySQL | Truly free; limited resources; no SSH |

Hostinger is the recommended pick (~$24/year): PHP 8.4, MySQL 8, SSH access, one-click deployment panel. Hetzner is the best pick for European churches.

Note: ChurchCRM's build process (Composer, npm/Webpack) must be run locally first. You deploy the pre-built `src/` directory. The `docker/` Compose configs are for self-hosting only.

#### Option 4 — Pros

- No server maintenance — host handles updates, security patches, uptime.
- cPanel or similar GUI — no Linux CLI needed.
- TLS/HTTPS via Let's Encrypt is one-click on most panels.
- Automatic daily backups often included.

#### Option 4 — Cons

- Small monthly cost (unavoidable).
- Limited PHP config control (may need to tweak `php.ini` for ChurchCRM).
- Shared resources — performance degrades under peak load (unlikely for a church CRM).
- No Docker support — cannot use the existing Docker Compose configs.
- SSH access varies — some budget plans are FTP-only.

---

### Option 5 — VPS (Virtual Private Server) — Full Control, Low Cost

Cost: ~$4–$7/month. A VPS gives you a dedicated virtual machine with root access. More work than shared hosting, but more capable and directly compatible with the Docker Compose configs.

| Provider | Price/month | Specs | Notes |
|----------|-------------|-------|-------|
| **Hetzner Cloud CX22** | €3.79 (~$4) | 2 vCPU, 4 GB RAM, 40 GB SSD | Best price/performance ratio in Europe |
| **Contabo VPS S** | ~$4.50 | 4 vCPU, 8 GB RAM, 100 GB SSD | Very generous specs for price |
| **DigitalOcean Basic** | $6 | 1 vCPU, 1 GB RAM, 25 GB SSD | Polished UI, great docs |
| **Vultr High Frequency** | $6 | 1 vCPU, 1 GB RAM, 32 GB NVMe | Fast NVMe; good global coverage |

Hetzner CX22 is the top pick — €3.79/month for 4 GB RAM. Run `docker-compose.nginx.yaml` and ChurchCRM is production-ready in under 30 minutes.

#### Option 5 — Deploy steps

```bash
# On the VPS (Ubuntu 22.04)
sudo apt update && sudo apt install -y docker.io docker-compose-plugin git
git clone https://github.com/ChurchCRM/CRM.git churchcrm
cd churchcrm/docker
cp .env.example .env          # edit passwords
docker compose -f docker-compose.nginx.yaml up -d
```

Then point your domain to the VPS IP and put Cloudflare in proxy mode for free TLS + DDoS protection.

#### Option 5 — Pros

- Full root access — install anything, tune PHP config, add cron jobs.
- Docker Compose configs work as-is.
- Far more powerful than shared hosting for the same price.
- Easy to snapshot/backup the whole VM.

#### Option 5 — Cons

- You own server security — must keep OS and Docker images updated.
- Requires Linux command-line comfort.
- No free tier (unlike Oracle Cloud above).

---

### Decision Guide

```
Do you have a spare PC or can leave your main PC on 24/7?
  └─ YES → Option 1 (Cloudflare Tunnel) — Free, zero ongoing cost
          Does your ISP block tunnels?
            └─ YES → Also try Option 3 (DDNS) — check for CGNAT first
            └─ NO  → Cloudflare Tunnel is best

  └─ NO  → Do you want completely free cloud hosting?
              └─ YES → Option 2 (Oracle Cloud Free Tier)
                        Willing to do Linux CLI setup?
                          └─ YES → Oracle Cloud ARM VM
                          └─ NO  → Option 4 shared hosting (Hostinger)

              └─ NO  → Small budget OK (~$2–5/month)?
                          └─ Need simplicity (no CLI) → Option 4 Shared Hosting (Hostinger)
                          └─ Want Docker / full control → Option 5 VPS (Hetzner CX22)
```

---

### Recommended Path for This Project

Given the charity context and the Docker-ready codebase that already exists:

1. **Start now, free:** Use Cloudflare Tunnel + your PC (Option 1). Run `docker-compose.nginx.yaml`, install `cloudflared`, done. Zero cost beyond electricity.
2. **If uptime matters more:** Migrate to Oracle Cloud Free Tier (Option 2) — still free, but the server runs independently of your PC.
3. **If you want simplicity over control:** Hostinger (~$24/year) is the best-value paid option — no CLI needed, automatic backups, easy SSL.
4. **If you outgrow shared hosting:** Hetzner CX22 VPS (~$45/year) with Docker Compose is the cleanest long-term solution.

---

### Cloudflare Free Features Worth Using (All Options)

Regardless of which hosting option you choose, put Cloudflare in front of your domain. The free plan includes:

- **Automatic HTTPS/TLS** — no certbot, no certificate management
- **DDoS protection** — absorbs attack traffic before it reaches your server
- **Web Application Firewall** — blocks common web attacks (SQLi, XSS, etc.)
- **Caching** — serves static assets (JS, CSS, images) from Cloudflare's CDN
- **Analytics** — traffic stats without installing anything

Add your domain to Cloudflare, change your registrar's nameservers to Cloudflare's, and point the A record to your server IP with the "Proxied" (orange cloud) toggle on.

---

*Added: 2026-05-13*
