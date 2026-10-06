# Project Guide — Team Competition Management

คู่มือรายละเอียดโปรเจกต์ (ติดตั้ง โครงสร้าง ฟีเจอร์สิทธิ์ และ env)  
ภาพรวมสั้นๆ ว่าเว็บนี้คืออะไร → ดู [README.md](README.md)

---

## Tech Stack

- **Backend:** PHP 8.2+ (Vanilla, OOP)
- **Frontend:** Tailwind CSS v4 + DaisyUI v5
- **Database:** MariaDB 10.11
- **Package Manager:** Composer
- **Container:** Docker

---

## Features

### Team Management

- สร้างและจัดการทีม (owner, admin, member)
- เชิญสมาชิกเข้าทีมด้วย token หรือ email
- โอนความเป็นเจ้าของทีม (Transfer Ownership) — เปลี่ยน owner ได้เฉพาะปุ่มนี้ (ไม่ผ่าน role selector)
- เปลี่ยน role สมาชิกได้เฉพาะ admin ↔ member
- ค้นหาสมาชิกในทีม
- Pagination สำหรับรายการทีม

### Event System

- สร้างและจัดการงานแข่ง
- **Create permission:** เฉพาะ **team owner** และ **team admin** เท่านั้นที่สร้าง event ได้ (member สร้างไม่ได้)
- **Team Selection:** เลือกทีมที่ต้องการสร้างงาน (แสดงเฉพาะทีมที่ user เป็น owner/admin)
- **Required Members:** กำหนดจำนวนสมาชิกที่ต้องการต่อทีม (ค่าเริ่มต้น: 3)
- **Member Visibility:** เลือกสมาชิกที่ต้องการให้มองเห็นงาน
  - แสดง checkbox รายชื่อสมาชิกในทีมที่เลือก
  - เลือกเฉพาะสมาชิกที่จะเข้าร่วมงาน (เช่น 3 คนจาก 10 คน)
  - เฉพาะสมาชิกที่ถูกเลือกเท่านั้นที่จะเห็นงานนี้
  - สมาชิกที่ลงทะเบียนแล้วไม่สามารถถูก revoke visibility ได้
- **Event Visibility rules:**
  - Event creator เห็นงานของตนเสมอ
  - **Team owner** เห็นทุก event ของทีมเสมอ (แม้ไม่ได้อยู่ใน visibility list)
  - User ที่ถูก grant visibility หรือลงทะเบียนแล้วเห็นงานนั้น
- **Event Edit:** เฉพาะ event creator เท่านั้น
- **Event Delete:** event creator หรือ **team owner** ของทีมที่ผูกกับ event (team owner ลบได้แต่แก้ไขไม่ได้)
- **Event Registration:** สมัครสมาชิกงาน
  - สมัครสมาชิกแบบ Individual หรือ Team (เฉพาะทีมของ event)
  - ตรวจสอบสิทธิ์การมองเห็นงานก่อนสมัคร
  - ป้องกันการสมัครซ้ำ
  - Unregister ได้จากตาราง registrations
  - Event creator สามารถ Kick ผู้ลงทะเบียนออกได้
- **Event Dates:** เพิ่มวันที่ได้ไม่จำกัด (วันแข่ง, วันสิ้นสุดลงทะเบียน, วันประชุม, อื่นๆ)
  - เลือกประเภทวันที่ (competition, registration_deadline, meeting, other)
  - กำหนดช่วงเวลาเริ่มต้น-สิ้นสุด
  - เพิ่มคำอธิบายแต่ละวันที่
- **Event Tags:** สร้าง tags แบบกำหนดเองพร้อมสี
- Pagination สำหรับรายการงาน
- หน้า `/event` แสดงรายชื่อทีมที่ user เป็นสมาชิกก่อน — คลิกทีมแล้วจึงแสดงรายการ event (`/event?team=...`)
- ในหน้ารายการ event ของทีม: ค้นหาชื่อ event, กรองตาม tag, กรองตามช่วงวันที่
- **Event Calendar** (`/event/calendar`): มุมมองปฏิทิน (FullCalendar) ตามสิทธิ์การมองเห็น กรองตามทีมได้
- Soft-deleted rows จะถูก hard-delete อัตโนมัติทุก 5 นาที (MariaDB EVENT)
- Timezone: Asia/Bangkok (GMT+7)

### User Management

- Email/password authentication
- User roles (user, admin)
- Activity logging system (admin only)
- Profile management (avatar, name, email, password)

### Security

- CSRF protection on all forms
- Opaque IDs in URLs (`IdEncoder`)
- Soft delete ทุก table
- Password hashing with Argon2id
- Login rate limiting, password policy (min 8 chars, letter + number), security headers

---

## Installation

### Prerequisites

- Docker & Docker Compose
- Git

### Setup

1. Clone repository
```bash
git clone <repository-url>
cd Competition_Management
```

2. Copy environment file
```bash
cp .env.example .env
```

3. Build and start containers
```bash
docker compose up -d --build
```

Optional public HTTPS tunnel (`compose.ngrok.yaml`):
```bash
# Set NGROK_AUTHTOKEN and NGROK_URL in .env, keep APP_URL as local (e.g. http://localhost:8000)
docker compose -f compose.ngrok.yaml up -d
```
That starts the full stack + ngrok and overrides app `APP_URL` to `NGROK_URL` (email links, cookies).
Plain `docker compose up -d` uses your `.env` `APP_URL` and does not start ngrok.
Inspector: http://localhost:4040

4. Access the application
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080

### Database

Fresh Docker volumes load `database.sql` automatically. To re-import manually:
```bash
docker exec -i team_comp_db mysql -u app_user -papp_password team_competition < database.sql
```

Schema changes go in `database.sql` only (no separate migrations folder).

### Create Admin Account

```bash
docker exec -it team_comp_app php create_admin.php
```

Default admin credentials:
- Email: `admin@teamcomp.local`
- Password: `Admin@123456`

**Important:** Change these credentials after first login!

---

## Project Structure

```
compose.yaml              # Docker Compose configuration
compose.ngrok.yaml        # Optional ngrok tunnel + APP_URL override
AGENT.md                  # Development constraints
GUIDE.md                  # This file — project details
checklist.md              # Course checklist
.env                      # Environment variables (not in git)
.env.example              # Environment template
database.sql              # Database schema (single source of truth)
docker/
  ├── 00-arpache.conf     # Apache virtual host
  ├── security-hardening.conf
  └── entrypoint.sh       # Container entrypoint
web/
  ├── Dockerfile          # PHP + Apache image
  ├── composer.json       # PHP dependencies
  ├── create_admin.php    # Seed admin account
  ├── views/              # Frontend routes (file-based routing)
  ├── src/                # Controllers + Services (OOP)
  ├── api/                # JSON / AJAX endpoints
  ├── templates/          # layout, header, footer, components
  └── vendor/             # Composer dependencies (not in git)
```

---

## Routing

File-based routing — filename = URL path:

| File | URL | Description |
|------|-----|-------------|
| `web/views/index.php` | `/` | Home page |
| `web/views/activity.php` | `/activity` | Activity log (admin only) |
| `web/views/settings.php` | `/settings` | User settings |
| `web/views/terms.php` | `/terms` | Terms of Service |
| `web/views/auth/login.php` | `/auth/login` | Login |
| `web/views/auth/register.php` | `/auth/register` | Registration |
| `web/views/auth/logout.php` | `/auth/logout` | Logout (POST + CSRF) |
| `web/views/auth/verify.php` | `/auth/verify` | Email verification |
| `web/views/auth/forgot-password.php` | `/auth/forgot-password` | Request reset |
| `web/views/auth/reset-password.php` | `/auth/reset-password` | Complete reset |
| `web/views/team/manage.php` | `/team/manage` | Team list & detail |
| `web/views/team/create.php` | `/team/create` | Create team |
| `web/views/team/settings.php` | `/team/settings` | Team settings |
| `web/views/team/join.php` | `/team/join` | Join team |
| `web/views/team/invite.php` | `/team/invite` | Invite handler |
| `web/views/event/index.php` | `/event` | Team list; `?team=` shows that team's events |
| `web/views/event/calendar.php` | `/event/calendar` | Calendar view |
| `web/api/events-calendar.php` | `/api/events-calendar` | Calendar JSON feed |
| `web/views/event/view.php` | `/event/view` | Event detail |
| `web/views/event/create.php` | `/event/create` | Create event (owner/admin only) |
| `web/views/event/edit.php` | `/event/edit` | Edit event (creator only) |
| `web/views/event/delete.php` | `/event/delete` | Delete event (POST) |
| `web/views/event/register.php` | `/event/register` | Register (POST) |
| `web/views/event/unregister.php` | `/event/unregister` | Unregister/kick (POST) |
| `web/api/team-members.php` | `/api/team-members` | Team members (AJAX) |

---

## Database Schema

- **users** — accounts with roles (`user`, `admin`)
- **teams** — team information
- **team_members** — membership roles (`owner`, `admin`, `member`)
- **team_invitations** — invite tokens
- **events** — competition info (`team_id`, `required_members`)
- **event_dates** — dynamic date ranges
- **event_tags** — custom tags with colors
- **event_registrations** — participation
- **event_visibility** — who can see an event
- **activity_logs** — system-wide activity logging
- **sessions** — session management

All tables support soft delete (`deleted_at`). Soft-deleted rows are hard-purged every 5 minutes.

---

## Development notes

### Theme

- Default: light
- Toggle: light ↔ dark (saved in localStorage)

### View pattern

```php
<?php
$title = 'Page Title';
ob_start();
?>
<!-- HTML content -->
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
```

### CDN (in layout)

- Tailwind CSS v4 browser build
- DaisyUI v5 + themes

---

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application / container name | team_comp_app |
| `APP_PORT` | Web port | 8000 |
| `APP_URL` | Public app URL when not tunneling | http://localhost:8000 |
| `APP_TIMEZONE` | App/DB timezone | Asia/Bangkok |
| `APP_KEY` | Key for opaque IDs | (set in `.env`) |
| `APP_DEBUG` | Show PHP errors when true | false |
| `SESSION_TIMEOUT` | Idle session timeout (seconds) | 1800 |
| `NGROK_AUTHTOKEN` | ngrok agent auth token | (from ngrok dashboard) |
| `NGROK_URL` | Tunnel URL; used as `APP_URL` when `compose.ngrok.yaml` is included | https://untriced-hee-petrous.ngrok-free.dev |
| `NGROK_CONTAINER_NAME` | ngrok container name | team_comp_ngrok |
| `DB_HOST` | DB host (`mariadb` in Docker) | mariadb |
| `DB_PORT` | DB port | 3306 |
| `DB_DATABASE` | Database name | team_competition |
| `DB_USERNAME` | DB user | app_user |
| `DB_PASSWORD` | DB password | app_password |
| `DB_ROOT_PASSWORD` | MariaDB root password | - |
| `DB_CONTAINER_NAME` | MariaDB container name | team_comp_db |
| `DB_EXTERNAL_PORT` | Host-mapped MariaDB port | 3306 |
| `PMA_CONTAINER_NAME` | phpMyAdmin container name | team_comp_pma |
| `PMA_EXTERNAL_PORT` | phpMyAdmin web port | 8080 |
| `SMTP_*` / `MAIL_*` | Outbound mail for verify / reset | - |

---

## User Roles

### Regular user

- Create and manage teams
- Join teams via invitation
- Create events only if team **owner** or **admin**
- View events via visibility, registration, creator, or team owner rules
- Register / unregister for events
- Manage profile settings

### Team owner

- See every event belonging to the team
- Delete team events (cannot edit events created by others)
- Full team management (settings, roles, transfer)

### Team admin

- Create events for the team
- Help manage team members (per team rules)

### System admin

- All regular user permissions
- Access activity log (`/activity`)
- Filter logs by action type, user, date range

---

## Activity Logging

Logged areas include authentication, team CRUD/roles/invites, event CRUD/registration, and profile/password/email changes. View at `/activity` (system admin only).

---

## Security Features

- CSRF on forms; logout is POST + CSRF
- Opaque IDs in URLs
- Argon2id password hashing + password policy (min 8, letter + number, common-password blocklist via `PasswordPolicyService`)
- Email verification required for new accounts
- Login rate limit (IP)
- Security headers / reduced server fingerprint
- Soft deletes + scheduled hard purge
