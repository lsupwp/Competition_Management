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

## Checklist

- [x] EVT-02 team name disclosure closed
- [x] Calendar API membership gate
- [ ] SEC-08 UUID/ULID (optional backlog)
