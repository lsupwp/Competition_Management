# Security & Bug Report — Team Competition Management

| Field | Value |
|-------|--------|
| Application | Team Comp (Team Competition Management System) |
| Environment tested | https://untriced-hee-petrous.ngrok-free.dev/ |
| Stack observed | Apache/2.4.68 (Debian), PHP/8.2.34, PHPMailer, phpdotenv |
| Initial report | 2026-10-01 |
| Auth retest | 2026-10-01 (post-remediation deploy) |
| Audience | Development / engineering |
| Method | Authenticated black-box review; auth-focused retest after `RESPONSE.md` fixes |

---

## 1. Executive summary

**Post-fix:** Several High/Medium items from the first pass are **confirmed fixed** on the live tunnel (Composer deny, registration anti-enum, password policy, login rate limit, security headers, CSRF still enforced, session rotation).

**Still open / new from auth retest:**

| Priority | Item |
|----------|------|
| High (ops) | **SEC-02** — Shared demo admin credentials still accepted on the public ngrok URL |
| Medium | **AUTH-11** — Forgot-password UI succeeds, but no working reset completion route was found (`/auth/reset-password` → 404) |
| Low | **AUTH-10** — Logout via `GET /auth/logout` (logout CSRF) |
| Low | **SEC-07** — Apache version string still disclosed |
| Low (backlog) | **SEC-08** — Sequential opaque IDs (deferred by eng) |

Auth feature review (login, register, logout, forgot/verify, session, rate limit, CSRF, unauth gates) did **not** find login CSRF bypass, session fixation, auth bypass of protected routes, or registration email enumeration on the current build.

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
| SEC-02 | High | **Open (ops)** | Demo/admin credentials on public tunnel |
| SEC-03 | Medium | **Fixed** | Registration email enumeration |
| SEC-04 | Medium | **Fixed** | Weak password policy |
| SEC-05 | Medium | **Fixed** | No login rate limiting |
| SEC-06 | Medium | **Fixed** | Missing security headers |
| SEC-07 | Low | **Partial** | Server / PHP version disclosed |
| SEC-08 | Low | **Deferred** | Sequential opaque IDs |
| SEC-09 | Low | **Fixed (claimed)** | Login timing skew (code fix; not re-timed this pass) |
| AUTH-10 | Low | **New / Open** | Logout via GET enables logout CSRF |
| AUTH-11 | Medium | **New / Open** | Password reset completion route not found (404) |

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

### SEC-02 — High — Demo credentials on public exposure — **OPEN (OPS)**

**Component:** Ops / accounts / staging  

**Description**  
During auth retest, `admin@teamcomp.local` with the previously shared demo password still authenticated successfully on the public ngrok URL (before the tester IP hit rate limit).

**Impact**  
Anyone with the shared credentials can use admin features (including `/activity`) on the exposed tunnel.

**Recommendation**  
- Rotate admin and all shared demo passwords now.  
- Prefer VPN / ngrok auth / IP allowlist for demos.  
- Set `APP_DEBUG=false` on public exposures.

**Acceptance criteria**  
Old shared passwords fail login; staging is not anonymously reachable with default creds.

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

### SEC-07 — Low — Version fingerprinting — **PARTIAL**

**Retest:** `X-Powered-By` removed. **`Server: Apache/2.4.68 (Debian)` still present.**

**Recommendation**  
Further reduce Server banner if Apache config allows (`ServerTokens Prod` may still show version on this build—verify image/config actually applied).

---

### SEC-08 — Low — Sequential opaque IDs — **DEFERRED**

Per engineering response: keep `IdEncoder`; UUID/ULID optional backlog. Authorization remains the primary control. No change this pass.

---

### SEC-09 — Low — Login timing skew — **FIXED (code; light retest)**

Engineering reports dummy Argon2 verify for missing users. Full timing re-benchmark skipped this pass due to rate-limit lockout after SEC-05 verification.

---

### AUTH-10 — Low — Logout via GET (logout CSRF) — **NEW**

**Component:** `/auth/logout`  

**Description**  
Authenticated sessions are terminated by a simple `GET /auth/logout` (link in UI). No CSRF token is required for logout.

**Impact**  
A malicious page can force a logged-in victim’s browser to hit logout (annoyance / availability), not account takeover. Severity Low for most apps; higher if “logout” is abused to disrupt admin sessions during sensitive actions.

**Recommendation**  
- Prefer `POST /auth/logout` with CSRF token, or  
- Keep GET but accept residual logout-CSRF risk as documented.

**Acceptance criteria**  
Logout requires CSRF-protected POST (or equivalent), and GET does not change session state.

---

### AUTH-11 — Medium — Password reset completion route missing — **NEW**

**Component:** `/auth/forgot-password` → reset landing  

**Description**  
Forgot-password form accepts email and returns the generic “If your email is registered…” message. Common reset completion URLs return **404**, including:

- `/auth/reset-password`
- `/auth/reset-password?token=…`
- `/auth/reset-password/{token}`
- Several other conventional aliases

Email verification at `/auth/verify` exists and validates tokens. A parallel reset completion endpoint was not found in black-box probing.

**Impact**  
Users may believe a reset email will work while the completion page is missing or unpublished—**broken recovery** and support burden. If emails still contain a working secret link on an obscure path, that path should be documented and hardened; if emails are sent to a 404, account recovery is broken.

**Recommendation**  
- Confirm the exact reset URL in the email template.  
- Ensure the route is registered, CSRF-protected, token one-time, and rate-limited.  
- Align forgot-password success with a real completion flow end-to-end.

**Acceptance criteria**  
Valid reset email link opens a password form; invalid/expired tokens fail safely; unused conventional paths either redirect consistently or are intentionally unused and documented.

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

1. **SEC-02** — Rotate demo/admin passwords; lock down public tunnel  
2. **AUTH-11** — Confirm/fix password-reset completion flow end-to-end  
3. **AUTH-10** — POST+CSRF logout (optional hardening)  
4. **SEC-07** — Finish hiding Apache version  
5. **SEC-08** — UUID/ULID when touching ID layer  

---

## 9. Remediation checklist

- [x] SEC-01 Composer files not web-accessible  
- [ ] SEC-02 Demo passwords rotated / staging locked down *(ops)*  
- [x] SEC-06 Security headers on HTML responses  
- [~] SEC-07 Fingerprinting reduced *(X-Powered-By gone; Server version remains)*  
- [x] SEC-04 Stronger password rules  
- [x] SEC-05 Login rate limit  
- [x] SEC-03 Registration anti-enum  
- [x] SEC-09 Login timing alignment *(per eng; light retest)*  
- [ ] SEC-08 UUID/ULID *(optional backlog)*  
- [ ] AUTH-10 CSRF-safe logout  
- [ ] AUTH-11 Password reset completion route verified in email + app  

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
