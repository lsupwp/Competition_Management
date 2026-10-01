# Response to Security & Bug Report

| Field | Value |
|-------|--------|
| Source report | `REPORT.md` (settings audit 2026-10-01) |
| Response date | 2026-10-01 |
| Branch | `auth` |

---

## This pass

| ID | Status | Action |
|----|--------|--------|
| **SET-05** | **Fixed** | Escape all input component attributes with `htmlspecialchars(..., ENT_QUOTES)` |
| **SET-06** | **Fixed** | Restore `www-data` ownership on `uploads/` every container start; clearer writable errors |
| SEC-02 | Accepted | won't fix |
| SEC-08 | Deferred | backlog |

Prior auth findings remain closed (verified in earlier passes).

---

## Checklist

- [x] SET-05 Settings name attribute encoding
- [x] SET-06 Avatar upload directory permissions
- [x] SEC-02 accepted feature
- [ ] SEC-08 UUID/ULID (optional)
