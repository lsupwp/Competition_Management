# Security & Bug Report — Team Competition Management

| Field | Value |
|-------|--------|
| Application | Team Comp (Team Competition Management System) |
| Environment tested | https://untriced-hee-petrous.ngrok-free.dev/ |
| Stack observed | Apache/2.4.68 (Debian), PHP/8.2.34, PHPMailer, phpdotenv |
| Initial report | 2026-10-01 |
| Auth retest | 2026-10-01 (post-remediation deploy) |
| Response verify | 2026-10-01 (second `RESPONSE.md` — AUTH-10/11, SEC-07) |
| Audience | Development / engineering |
| Method | Authenticated black-box review; auth-focused retest after `RESPONSE.md` fixes |

---

## 1. Executive summary

**Post-fix (verified live):** SEC-01, SEC-03–SEC-06, SEC-07 (banner), SEC-09 (per eng), AUTH-10, and AUTH-11 are **confirmed fixed** on the current tunnel. CSRF, session rotation, rate limit, and password policy remain in good shape.

**Remaining:**

| Priority | Item |
|----------|------|
| Accepted risk | **SEC-02** — Eng accepted shared demo/admin creds on public ngrok (still work; won't fix) |
| Low (backlog) | **SEC-08** — Sequential opaque IDs (deferred) |
| Info | **AUTH-11 note** — GET `/auth/reset-password?token=…` shows the form for any non-empty token; invalid tokens are rejected on **POST** (OK). Prefer validating on GET too. |

No login CSRF bypass, session fixation, or protected-route auth bypass found on the current build.

---

## 2. Severity definitions

| Severity | Meaning |
|----------|---------|
| High | Likely useful to an attacker or exposes sensitive operational data; fix soon |
| Medium | Meaningful weakness; fix in the current sprint if possible |
| Low | Hardening / defense-in-depth; schedule when convenient |
| Info | Positive finding or note for awareness |

---

## 3. Findings overview (current)

| ID | Severity | Status | Title |
|----|----------|--------|-------|
| SEC-01 | High | **Fixed** | `composer.json` / `composer.lock` publicly readable |
| SEC-02 | High | **Accepted / won't fix** | Demo/admin credentials on public tunnel |
| SEC-03 | Medium | **Fixed** | Registration email enumeration |
| SEC-04 | Medium | **Fixed** | Weak password policy |
| SEC-05 | Medium | **Fixed** | No login rate limiting |
| SEC-06 | Medium | **Fixed** | Missing security headers |
| SEC-07 | Low | **Fixed** | Server / PHP version disclosed |
| SEC-08 | Low | **Deferred** | Sequential opaque IDs |
| SEC-09 | Low | **Fixed** | Login timing skew |
| AUTH-10 | Low | **Fixed** | Logout via GET enables logout CSRF |
| AUTH-11 | Medium | **Fixed** | Password reset completion route not found (404) |

---

## 4. Auth retest results (2026-10-01)

### 4.1 Scope covered

| Area | Result |
|------|--------|
| `/auth/login` CSRF | Wrong/missing `csrf_token` rejected (“Invalid or expired token”) |
| Session fixation | `PHPSESSID` rotated on successful login |
| Session cookie flags | `Secure; HttpOnly; SameSite=Lax` |
| Unauth access | `/settings`, `/team/*`, `/event*`, `/activity` redirect to login |
| Registration anti-enum | Same generic message for existing and new emails |
| Password policy | Min 12; letter+number; weak/common patterns rejected |
| Login rate limit | After 5 failures: “Too many failed login attempts… 15 minutes” (per IP observed) |
| Forgot-password messaging | Generic for registered and unknown emails |
| Email verification | `/auth/verify` rejects missing/invalid tokens with clear errors |
| Demo admin login | **Still succeeded** with shared password before IP lockout |
| Open redirect params | No external redirect confirmed on successful earlier logins; later attempts blocked by rate limit |

### 4.2 Rate-limit note

The new IP-based lockout is effective. During this retest it also blocked further authenticated checks (settings password/email change, concurrent-session proof) from the scanner IP for 15 minutes. That is expected security behavior; shared NAT users may feel the same.

---

## 5. Detailed findings

### SEC-01 — High — Public Composer manifests — **FIXED**

**Retest:** `/composer.json`, `/composer.lock`, `/.env` → **403**.

No further action unless deploy config regresses.

---

### SEC-02 — High — Demo credentials on public exposure — **ACCEPTED / WON'T FIX**

**Component:** Ops / accounts / staging  

**Engineering response:** Intentional for course/demo; passwords not rotated.

**Verify (2026-10-01):** Shared `admin@teamcomp.local` password still logs in on the public tunnel.

**Residual risk:** Anyone with the shared demo credentials can use admin features (including `/activity`) while the tunnel is public. Document as accepted risk for demos; do not carry the same defaults into production.

---

### SEC-03 — Medium — Registration email enumeration — **FIXED**

**Retest:** Existing (`admin@teamcomp.local`) and new emails both return:  
“If this email can be registered, you will receive a verification link shortly.”

---

### SEC-04 — Medium — Weak password policy — **FIXED**

**Retest UI copy:** “At least 12 characters, with a letter and a number.”

| Password | Result |
|----------|--------|
| `password` (8) | Rejected — min length |
| `abcdefghijkl` | Rejected — need number |
| `123456789012` | Rejected — need letter |
| `GoodPass12ab` | Accepted (generic success message) |

---

### SEC-05 — Medium — Login rate limiting — **FIXED**

**Retest:** 6th failed attempt returned lockout message for 15 minutes. Confirmed working.

**Dev note:** Consider separating “per account” and “per IP” counters carefully so one abusive IP cannot lock out unrelated users on the same NAT more than intended—or document that trade-off.

---

### SEC-06 — Medium — Missing security headers — **FIXED**

**Retest on `/` and `/auth/login`:** CSP, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS present. `X-Powered-By` absent.

---

### SEC-07 — Low — Version fingerprinting — **FIXED**

**Verify (2026-10-01):** `X-Powered-By` absent; `Server: Apache` only (no patch version string).

---

### SEC-08 — Low — Sequential opaque IDs — **DEFERRED**

Per engineering response: keep `IdEncoder`; UUID/ULID optional backlog. Authorization remains the primary control. No change this pass.

---

### SEC-09 — Low — Login timing skew — **FIXED (code; light retest)**

Engineering reports dummy Argon2 verify for missing users. Full timing re-benchmark skipped this pass due to rate-limit lockout after SEC-05 verification.

---

### AUTH-10 — Low — Logout via GET (logout CSRF) — **FIXED**

**Verify (2026-10-01):**
- UI uses `POST /auth/logout` with `csrf_token` (no logout `href`)
- `GET /auth/logout` redirects home and **does not** end the session (`/settings` still accessible)
- `POST /auth/logout` with CSRF logs the user out (`/settings` → login)

---

### AUTH-11 — Medium — Password reset completion route missing — **FIXED**

**Verify (2026-10-01):**
- `/auth/reset-password` exists (200)
- Missing token → “Reset token is missing or invalid”
- Form includes `csrf_token`, `token`, `password`, `password_confirmation` + policy hint
- POST with forged token → “Reset link is invalid or has expired” (not accepted)

**Optional hardening:** GET currently shows the password form for any non-empty `token` (even `short` / random). Validation correctly happens on POST; rejecting invalid tokens on GET would be cleaner UX and slightly less noisy.

---

## 6. Positive auth controls (do not regress)

| Area | Observation |
|------|-------------|
| Login/register CSRF | Enforced |
| Session cookie | `Secure` + `HttpOnly` + `SameSite=Lax` |
| Session regenerate | Rotates on login |
| Authz gate | Unauthenticated users cannot open settings/teams/events/activity |
| Register anti-enum | Generic message |
| Forgot-password anti-enum | Generic message |
| Password policy | 12+ with letter and number |
| Login throttle | 5 failures → 15 minute lockout (observed) |
| Security headers | Present on auth pages |
| Composer / `.env` | 403 |

---

## 7. Functional / product notes (auth)

| Note | Detail |
|------|--------|
| Remember me | Checkbox present; only `PHPSESSID` observed (no long-lived remember cookie). Confirm whether feature is incomplete or session-lifetime only. |
| `/auth/verify` | Handles missing vs invalid tokens with distinct messages (OK for verify UX; not the same risk as login enum). |
| Concurrent sessions | Not fully re-verified this pass (IP lockout). Earlier design likely allows multiple sessions; logout is per-session. |

---

## 8. Recommended backlog order (updated)

1. **SEC-08** — UUID/ULID when touching ID layer (optional)  
2. **SEC-02** — If this ever leaves course/demo context, rotate creds and lock the tunnel  
3. **AUTH-11 polish** — Validate reset tokens on GET as well as POST (optional)

---

## 9. Remediation checklist

- [x] SEC-01 Composer files not web-accessible  
- [x] SEC-02 Demo credentials — **accepted feature / won't fix** (eng)  
- [x] SEC-06 Security headers on HTML responses  
- [x] SEC-07 Fingerprinting reduced (`Server: Apache`)  
- [x] SEC-04 Stronger password rules  
- [x] SEC-05 Login rate limit  
- [x] SEC-03 Registration anti-enum  
- [x] SEC-09 Login timing alignment  
- [ ] SEC-08 UUID/ULID *(optional backlog)*  
- [x] AUTH-10 CSRF-safe logout *(verified live)*  
- [x] AUTH-11 Password reset completion route *(verified live; email body not read)*  

---

## 10. Cleanup after reviews

Disposable registrations used `*@example.com` addresses during policy/enum tests. Remove those users if you want a clean DB.

---

## 11. Limitations

- Black-box against live ngrok; no application source in the tester workspace.  
- Auth retest partially constrained by the new IP login lockout after confirming SEC-05.  
- Password-reset **email contents** were not read (no mailbox access); AUTH-11 is based on HTTP route discovery + forgot-password UI behavior.  
- Not a full penetration test of teams/events/uploads in this pass—auth was the focus.

---

**Prepared for:** Development team  
**Action requested:** Close SEC-02 (ops) and AUTH-11 next; treat AUTH-10 as hardening.
