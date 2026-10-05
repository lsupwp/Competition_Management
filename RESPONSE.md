# Response to Security & Bug Report

| Field | Value |
|-------|--------|
| Source report | `REPORT.md` (Event management audit 2026-10-01) |
| Response date | 2026-10-01 |
| Branch | `event-system` |

---

## Verdict

**EVT-02 fixed.** Non-members no longer learn team names via `/event?team=` or `/event/calendar?team=`.

| ID | Status |
|----|--------|
| EVT-02 | **Fixed** — `getTeamByIdForMember`; filter ignored without membership |
| Auth / settings suite | **Fixed** (prior) |
| SEC-02 | **Accepted / won't fix** |
| SEC-08 | **Deferred** |

### Fix detail

- Added `EventController::getTeamByIdForMember()` (JOIN `team_members`).
- `/event` and `/event/calendar` resolve team titles only for members; outsiders get default page (no name in title/H1).
- `/api/events-calendar` only applies `?team=` when the caller is a member.

---

## Paths

### URL routes (report surface)

| Area | Paths |
|------|-------|
| Auth | `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/verify`, `/auth/forgot-password`, `/auth/reset-password` |
| App | `/`, `/settings`, `/activity`, `/terms` |
| Team | `/team/manage`, `/team/create`, `/team/join`, `/team/invite`, `/team/settings` |
| Event | `/event`, `/event/create`, `/event/calendar`, `/event/view`, `/event/edit`, `/event/delete`, `/event/register`, `/event/unregister` |
| API | `/api/events-calendar`, `/api/team-members` |
| Uploads / blocked | `/uploads/avatars/*`, `/uploads/teams/*`, `/composer.json`, `/composer.lock`, `/.env` |
| EVT-02 | `/event?team=`, `/event/calendar?team=`, `/api/events-calendar?team=` |

### Code files touched (remediation)

```
.env.example
compose.yaml
database.sql
docker/00-arpache.conf
docker/entrypoint.sh
docker/security-hardening.conf
migrations/006_password_reset_tokens.sql
web/Dockerfile
web/api/events-calendar.php
web/api/team-members.php
web/src/Controllers/AuthController.php
web/src/Controllers/EventController.php
web/src/Controllers/TeamController.php
web/src/Services/ActivityLogService.php
web/src/Services/CsrfService.php
web/src/Services/EmailService.php
web/src/Services/ImageUploadService.php
web/src/Services/LoginRateLimiter.php
web/src/Services/PasswordPolicyService.php
web/src/Services/SecurityHeaders.php
web/src/Services/SessionService.php
web/src/bootstrap_env.php
web/templates/components/input.php
web/templates/header.php
web/templates/layout.php
web/views/auth/login.php
web/views/auth/logout.php
web/views/auth/register.php
web/views/auth/reset-password.php
web/views/event/calendar.php
web/views/event/index.php
web/views/index.php
web/views/settings.php
web/views/team/manage.php
web/views/terms.php
```

### EVT-02 only (this commit)

```
web/api/events-calendar.php
web/src/Controllers/EventController.php
web/views/event/calendar.php
web/views/event/index.php
```

---

## Checklist

- [x] EVT-02 team name disclosure closed
- [x] Calendar API membership gate
- [x] All report paths listed above
- [ ] SEC-08 UUID/ULID (optional backlog)
