# Response to Security & Bug Report

| Field | Value |
|-------|--------|
| Source report | `REPORT.md` (initial + auth retest 2026-10-01) |
| Response date | 2026-10-01 |
| Status | Remaining open code items closed except accepted/deferred |

---

## Summary

| ID | Status |
|----|--------|
| SEC-01 | **Fixed** |
| SEC-02 | **Accepted feature / won't fix** (demo admin on ngrok) |
| SEC-03 | **Fixed** |
| SEC-04 | **Fixed** |
| SEC-05 | **Fixed** |
| SEC-06 | **Fixed** |
| SEC-07 | **Fixed** (`Server: Apache` only; version string removed) |
| SEC-08 | **Deferred** (IdEncoder / UUID backlog) |
| SEC-09 | **Fixed** |
| AUTH-10 | **Fixed** (logout is POST + CSRF; GET does not log out) |
| AUTH-11 | **Fixed** (`/auth/reset-password` + email token flow) |

---

## Finding-by-finding

### SEC-02 — Demo credentials — **Accepted feature**

Shared demo/admin passwords on public ngrok remain intentional for course/demo. Not rotated in code.

### AUTH-10 — Logout CSRF — **Fixed**

- Logout is `POST /auth/logout` with CSRF token (header menu form).
- `GET /auth/logout` redirects home and does **not** destroy the session.

### AUTH-11 — Password reset completion — **Fixed**

- Forgot-password stores one-time `password_reset_token` (1 hour) and emails `/auth/reset-password?token=…`
- Reset page validates CSRF, token expiry, password policy; clears token after success
- Migration: `migrations/006_password_reset_tokens.sql`

### SEC-07 — Apache version — **Fixed**

- `ServerTokens Prod` via `docker/security-hardening.conf`
- Retest expectation: `Server: Apache` (no patch version)

---

## Checklist

- [x] SEC-01 Composer files not web-accessible
- [x] SEC-02 Demo credentials — accepted feature / won't fix
- [x] SEC-03 Registration anti-enum
- [x] SEC-04 Stronger password rules
- [x] SEC-05 Login rate limit
- [x] SEC-06 Security headers
- [x] SEC-07 Server fingerprint reduced to `Apache`
- [ ] SEC-08 UUID/ULID *(optional backlog)*
- [x] SEC-09 Login timing alignment
- [x] AUTH-10 CSRF-safe logout
- [x] AUTH-11 Password reset completion flow
