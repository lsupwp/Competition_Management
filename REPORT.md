# Security & Bug Report — Team Competition Management

| Field | Value |
|-------|--------|
| Application | Team Comp (Team Competition Management System) |
| Environment tested | https://untriced-hee-petrous.ngrok-free.dev/ |
| Stack observed | Apache/2.4.68 (Debian), PHP/8.2.34, PHPMailer, phpdotenv |
| Initial report | 2026-10-01 |
| Auth retest | 2026-10-01 (post-remediation deploy) |
| Response verify | 2026-10-01 (AUTH-10/11, SEC-07) |
| AUTH-11 GET polish | 2026-10-01 — verified invalid tokens no longer show form |
| Settings form audit | 2026-10-01 |
| Audience | Development / engineering |
| Method | Authenticated black-box review; auth + `/settings` form review |

---

## 1. Executive summary

**Post-fix (verified live):** SEC-01, SEC-03–SEC-06, SEC-07 (banner), SEC-09 (per eng), AUTH-10, and AUTH-11 are **confirmed fixed** on the current tunnel. CSRF, session rotation, rate limit, and password policy remain in good shape.

**Open / remaining:**

| Priority | Item |
|----------|------|
| Medium | **SET-05** — Stored HTML/JS injection via profile **name** in Settings input `value` (attribute breakout) |
| Low | **SET-06** — Avatar upload fails to save (valid PNG → “Failed to save uploaded file”) |
| Accepted risk | **SEC-02** — Demo/admin creds on public ngrok (won't fix) |
| Low (backlog) | **SEC-08** — Sequential opaque IDs (deferred) |

Settings forms otherwise look solid: CSRF enforced, email/password changes require current password, password policy applied on change, unauth access blocked.

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
| SET-05 | Medium | **Open** | Stored XSS / HTML injection in Settings name `value` |
| SET-06 | Low | **Open** | Avatar upload save failure (functional) |

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

### AUTH-11 — Medium — Password reset completion route missing — **FIXED** (+ GET polish)

**Verify (2026-10-01):**
- `/auth/reset-password` exists (200)
- Missing token → “Reset token is missing or invalid”
- Form includes `csrf_token`, `token`, `password`, `password_confirmation` + policy hint
- POST with forged token → “Reset link is invalid or has expired” (not accepted)

**GET token validation (follow-up, verified live):**
- Invalid/expired/non-empty garbage tokens (`short`, `zzzz`, 64×`a`, etc.) **do not** show the password form
- Same invalid/expired messaging as POST; form only appears for a DB-valid unexpired token

---

## 5b. Settings form audit (2026-10-01)

### Forms mapped on `/settings`

| Form | Fields | Notes |
|------|--------|-------|
| Edit profile | `action=edit_profile`, `csrf_token`, `avatar`, `name` | `multipart/form-data` |
| Change email | `action=change_email`, `csrf_token`, `new_email`, `email_password` | Password required |
| Change password | `action=change_password`, `csrf_token`, `current_password`, `new_password`, `confirm_password` | Policy enforced |

### Controls that passed

| Check | Result |
|-------|--------|
| Unauthenticated GET/POST `/settings` | Redirects to login |
| CSRF on profile / email / password | Wrong or missing token → “Invalid security token” |
| Email change without password | “Invalid password” |
| Email change wrong password | Rejected |
| Password change without current | “Current and new password are required” |
| Weak new password | “Password must be at least 12 characters long” |
| Password mismatch / wrong current | Rejected with clear errors |
| Name length | “Name must not exceed 255 characters” |
| Display name on homepage | HTML-escaped correctly |
| Extra params (`user_id`, `role`, `is_admin`) | No privilege escalation to `/activity` |
| Avatar SVG / PHP / fake PNG | Rejected as invalid type |

### SET-05 — Medium — Stored HTML/JS injection in Settings name field — **OPEN**

**Component:** `/settings` → Edit Profile → `name`  

**Description**  
Display contexts (e.g. navbar `<span class="text-sm font-bold">…</span>`) escape the name correctly.  
The **Settings input** does **not** encode quotes when echoing into `value="…"`:

```html
<input ... value=""><b data-set="xss">SETTAG</b>" required />
```

A stored name containing `"` breaks out of the attribute and injects HTML into the Settings page for that user.

**Impact**  
Script/HTML runs in the victim’s session when they open **Account Settings**. With CSRF already fixed, this is primarily **self-XSS** / social-engineering, but still a real encoding bug and becomes worse if any admin “edit user” UI reuses the same pattern.

**Recommendation**  
- Escape for HTML attribute context (`htmlspecialchars($name, ENT_QUOTES, 'UTF-8')`) everywhere `name` is printed into attributes.  
- Prefer the same helper for all template outputs.  
- Add a regression test: name containing `"` / `<` must not break markup.

**Acceptance criteria**  
After saving a name with quotes/angle brackets, Settings source shows only escaped entities inside `value="…"`, and no raw injected tags.

---

### SET-06 — Low — Avatar upload fails to persist — **OPEN**

**Component:** `/settings` avatar  

**Description**  
A minimal valid 1×1 PNG upload returned “Failed to save uploaded file.” while name update still succeeded. Existing avatar URL under `/uploads/avatars/…` returned 404/`text/html` in this environment.

**Impact**  
Broken profile photo feature; may indicate permissions/disk path issues in Docker/ngrok deploy (not a confirmed RCE).

**Recommendation**  
Check upload directory permissions, path config, and disk space; confirm served files use correct `Content-Type` and `X-Content-Type-Options: nosniff`.

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

1. **SET-05** — Escape profile name in Settings input `value` (and any other attributes)  
2. **SET-06** — Fix avatar save path/permissions  
3. **SEC-08** — UUID/ULID when touching ID layer (optional)  
4. **SEC-02** — If leaving course/demo context, rotate creds and lock the tunnel

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
- [x] AUTH-11 GET token validation *(verified live — invalid tokens hide form)*  
- [ ] SET-05 Escape `name` in Settings attribute context  
- [ ] SET-06 Avatar upload save path/permissions  

---

## 10. Cleanup after reviews

Disposable registrations used `*@example.com` addresses during policy/enum tests. Remove those users if you want a clean DB. Profile name used for XSS markers was restored to `Nanthaphat` after testing.

---

## 11. Limitations

- Black-box against live ngrok; no application source in the tester workspace.  
- Auth retest partially constrained by the new IP login lockout after confirming SEC-05.  
- Password-reset **email contents** were not read (no mailbox access); AUTH-11 is based on HTTP route discovery + forgot-password UI behavior.  
- Settings audit covered profile / email / password / avatar forms; team/event settings are separate.

---

**Prepared for:** Development team  
**Action requested:** Fix **SET-05** next; then SET-06. SEC-02 remains accepted demo risk; SEC-08 remains backlog.
