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
| SET-05 / SET-06 verify | 2026-10-01 — both fixed on live |
| Uploads nosniff | 2026-10-01 — verified on `/uploads/avatars/*` |
| Team management audit | 2026-10-01 |
| Event management audit | 2026-10-01 |
| EVT-02 verify | 2026-10-01 — fixed on live |
| Remaining paths audit | 2026-10-01 — `/terms`, `/activity`, `/team/settings`, `/team/invite`, `/api/team-members`, uploads |
| Audience | Development / engineering |
| Method | Authenticated black-box review; auth + `/settings` form review |

---

## 1. Executive summary

**Post-fix (verified live):** SEC-01, SEC-03–SEC-06, SEC-07 (banner), SEC-09 (per eng), AUTH-10, and AUTH-11 are **confirmed fixed** on the current tunnel. CSRF, session rotation, rate limit, and password policy remain in good shape.

**Open / remaining:**

| Priority | Item |
|----------|------|
| Accepted risk | **SEC-02** — Demo/admin creds on public ngrok (won't fix) |
| Low (backlog) | **SEC-08** — Sequential opaque IDs (deferred) |

**Settings / Teams / Events (verified):** SET-05/06 fixed; team management PASS; **EVT-02 fixed**.

**Remaining paths (2026-10-01):** `/terms`, `/activity`, `/team/settings`, `/team/invite`, `/api/team-members`, `/uploads/teams/*` — **PASS** (no new High/Medium). See §5e.

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
| SET-05 | Medium | **Fixed** | Stored XSS / HTML injection in Settings name `value` |
| SET-06 | Low | **Fixed** | Avatar upload save failure (functional) |
| EVT-02 | Medium | **Fixed** | Team name disclosure via `/event?team=` / calendar for non-members |

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

### SET-05 — Medium — Stored HTML/JS injection in Settings name field — **FIXED**

**Verify (2026-10-01):** Name containing `"><b…>` is stored but echoed into the input as entities only, e.g.  
`value="&quot;&gt;&lt;b data-set=&quot;xss&quot;&gt;SETTAG&lt;/b&gt;"` — no attribute breakout / raw tags.

---

### SET-06 — Low — Avatar upload fails to persist — **FIXED**

**Verify (2026-10-01):** Valid 1×1 PNG → “Name updated and Avatar updated successfully”;  
`GET /uploads/avatars/avatar_…png` → `200 image/png` (real PNG bytes).

---

## 5c. Team management audit (2026-10-01)

**Accounts:** `nanthaphat.ph@kkumail.com` (Owner on Hackthon + CTF), `lsupwp@gmail.com` (Admin on CTF only).

### Surface

| Route | Auth |
|-------|------|
| `/team/manage`, `/team/create`, `/team/join` | Login required (anon → `/auth/login`) |

### Authorization / IDOR

| Check | Result |
|-------|--------|
| u2 opens u1-only team `fnZdA2EIV2ZTAg` | No member emails / invite UI (fallback to own list) |
| Shared team `fnZdA2EIV2ZTAQ` | Both see members (expected) |
| u2 `transfer_ownership` | “Only team owner can transfer ownership” |
| u2 `change_role` | “Only team owner can change roles” |
| u2 `kick_member` (self/owner) | “Admins can only kick members” / blocked |
| u2 `revoke_token` | “Permission denied” |
| u2 invite/kick/transfer on private team | “Permission denied” / “not a member” / owner-only |

### CSRF / XSS / invites

| Check | Result |
|-------|--------|
| Create team wrong CSRF | “Invalid security token” |
| Join wrong CSRF | “Invalid security token” |
| Member search `"><img…onerror…>` | Escaped inside `value="&quot;&gt;&lt;img…"` |
| Team name with HTML markers | Displayed as entities (`TM&quot;&gt;&lt;b…`) — no raw tags |
| Invite tokens | 64-hex style; anon `/team/join?token=` → login |

### Notes (not new vulns)

| Note | Detail |
|------|--------|
| SEC-08 still applies | Team/user IDs share the same opaque encoder; e.g. user id for `lsupwp` matched team id `fnZdA2EIV2ZTAg` in forms. Authz checks still held. |
| Test team leftover | A probe team named like `TM"><b…` may still exist (`fnZdA2EIV2ZTBw`) — safe to delete in UI if present. |
| Team Settings UI | No separate settings/max_members form found on manage detail in this build (invite / roles / transfer / revoke only). |

### Verdict

**Team management: PASS** for this pass — no new open High/Medium findings. Remaining backlog unchanged: SEC-02 (accepted), SEC-08 (deferred).

---

## 5d. Event management audit (2026-10-01)

**Accounts:** same two demo users. Shared event: `Panda Fight!` (`/event/view?id=fnZdA2EIV2ZTAQ`) on RedPanda CTF.

### Surface / auth gate

| Route | Result |
|-------|--------|
| `/event`, `/event/create`, `/event/calendar`, `/event/view`, `/event/edit` | Anon → login |
| `/api/events-calendar` | Auth JSON feed (requires `start` & `end`) |

### Authorization (passed)

| Check | Result |
|-------|--------|
| Non-creator edit (GET/POST) | “Only the event creator can edit this event” |
| Non-creator delete | “You do not have permission to delete this event” |
| Create event on non-member team (`team_id=2` as u2) | “Only team owners and admins can create events” |
| Encoded foreign `team_id` on create | “Team is required” / rejected |
| View private-team event as non-member | Redirect / no access |
| Calendar JSON as u2 | Only teams the user belongs to (e.g. RedPanda CTF) |
| Unregister CSRF | Invalid token rejected |
| Event title/description HTML | Escaped in list/view (`&quot;&gt;&lt;b…`, `&lt;img…`) |
| Event search box | Escaped in `value` |

### EVT-02 — Medium — Team name disclosure for non-members — **FIXED**

**Verify (2026-10-01):** As non-member (`lsupwp@…`), `/event?team=` and `/event/calendar?team=` for Hackthon / admin teams show the generic Events/Calendar page — **no** foreign team name in title or H1. Member still sees `Events — RedPanda CTF` for their own team. `/api/events-calendar` does not apply unauthorized team filters to expose other teams’ events.

### Notes

| Note | Detail |
|------|--------|
| Event ids vs team ids | Some event view ids reuse the same encoder space as teams (e.g. shared event id equals shared team id) — reinforces SEC-08. |
| Creator can remove others’ registration | Owner/creator UI posts `/event/unregister` with `user_id` — appears intentional moderation; confirm product intent. |
| Calendar `team=` ignored when unauthorized | API returned the user’s normal visible events rather than erroring — OK if no foreign events leak; still fix HTML name leak (EVT-02). |

### Verdict

**Events: PASS** after EVT-02 fix (verified live).

---

## 5e. Remaining paths audit (2026-10-01)

Paths taken from eng `RESPONSE.md` that were not fully covered in earlier auth/settings/team/event deep dives.

### Matrix (summary)

| Path | Anon | Normal user | Notes |
|------|------|-------------|-------|
| `/terms` | 200 public | 200 | Static policy text; no reflected XSS from query |
| `/activity` | → login | → home + permission flash | **Admin only** (admin reaches `/activity`) |
| `/team/settings` | → login | Owner only | Admin member denied; non-member denied |
| `/team/invite` | → login | POST only useful | GET redirects manage; CSRF enforced; non-member invite denied |
| `/api/events-calendar` | **401** JSON | 400 without dates / 200 with range | Membership filtering OK |
| `/api/team-members` | 200 `Not authenticated` | Needs `team_id` (numeric) | Member OK; non-member `Access denied` |
| `/uploads/avatars/`, `/uploads/teams/` | **403** listing | — | Individual files may 404 if missing; `nosniff` when served |
| `/composer.json`, `/.env` | **403** | — | Still blocked |

### Checks that passed

| Check | Result |
|-------|--------|
| `/team/settings` as CTF Admin (u2) | No settings UI — permission message / redirect |
| `/team/settings` as non-member of Hackthon | No access |
| Team settings XSS in name `value` | Escaped (`TS&quot;&gt;&lt;b…`) |
| Team settings wrong CSRF | Rejected / no update |
| `/team/invite` as non-member of private team | Permission denied |
| `/team/invite` bad CSRF | Invalid security token |
| `/api/team-members?team_id=2` as u2 (not member) | `Access denied` (no emails) |
| `/api/team-members?team_id=1` as u2 (member) | Members list (expected) |
| Activity filters with junk / HTML | No raw tag reflection observed |
| Admin-only `/activity` link | Not shown to normal users on home |

### Notes (Low / Info — not opened as findings)

| Note | Detail |
|------|--------|
| API auth status inconsistency | `/api/events-calendar` → **401** when anon; `/api/team-members` → **200** + `{"success":false,"error":"Not authenticated"}`. Prefer 401 for both. |
| Numeric `team_id` on API | `/api/team-members` expects numeric ids (`1`,`2`,…) while UI urls use opaque ids — same SEC-08 theme; authz still enforced. |
| Access-denied uniformity | Non-existent and non-member `team_id`s both return `Access denied` for u2 (no clear existence oracle in this sample). |

### Verdict

**Remaining listed paths: PASS** — no new High/Medium issues.

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
2. **SEC-02** — If leaving course/demo context, rotate creds and lock the tunnel  

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
- [x] SET-05 Escape `name` in Settings attribute context *(verified live)*  
- [x] SET-06 Avatar upload save path/permissions *(verified live)*  
- [x] Uploads `X-Content-Type-Options: nosniff` *(verified live)*  
- [x] EVT-02 Non-member team name disclosure on event/calendar team filter *(verified live)*  

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
**Action requested:** No new open code findings from remaining-path sweep. SEC-02 accepted; SEC-08 deferred. Optional: unify anon API status codes to 401.
