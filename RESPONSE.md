# Response to Security & Bug Report

| Field | Value |
|-------|--------|
| Source report | `REPORT.md` (verify pass 2026-10-01) |
| Response date | 2026-10-01 |
| Branch | `auth` |

---

## Summary

Live retest confirmed prior remediations. Only optional polish remained.

| ID | Status |
|----|--------|
| SEC-01 … SEC-07, SEC-09 | **Fixed** (verified) |
| SEC-02 | **Accepted / won't fix** (demo creds) |
| SEC-08 | **Deferred** |
| AUTH-10 | **Fixed** (verified) |
| AUTH-11 | **Fixed** + **GET token validation** added |

---

## AUTH-11 polish (this pass)

**Finding:** GET `/auth/reset-password?token=…` showed the form for any non-empty token; invalid tokens only failed on POST.

**Fix:** `AuthController::isValidPasswordResetToken()` checks DB expiry on GET. Invalid/expired/missing tokens never show the password form — same message as POST (`Reset link is invalid or has expired` / missing).

---

## Unchanged by design

- **SEC-02** — shared demo admin on ngrok remains intentional  
- **SEC-08** — sequential `IdEncoder` backlog  
- **Remember me** — UI checkbox only; session cookie lifetime (not a long-lived remember token) — product note, not changed  

---

## Checklist

- [x] SEC-01 … SEC-07, SEC-09, AUTH-10, AUTH-11 (route)
- [x] AUTH-11 GET token validation
- [x] SEC-02 accepted feature
- [ ] SEC-08 UUID/ULID (optional backlog)
