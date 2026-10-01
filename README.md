# Team Competition Management System

ระบบจัดการงานแข่งของทีม - สร้างทีม ลงงาน ติดตามสถานะ

## Tech Stack

- **Backend:** PHP 8.2+ (Vanilla, OOP)
- **Frontend:** Tailwind CSS v4 + DaisyUI v5
- **Database:** MariaDB 10.11
- **Package Manager:** Composer
- **Container:** Docker

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
  - เพิ่ม tags ได้ไม่จำกัด (เช่น สนใจ, ลงแล้ว, รอ, ฯลฯ)
  - เลือกสีแบบ hex color สำหรับแต่ละ tag
  - แสดง tags แบบ badge สีสวยงาม
- Pagination สำหรับรายการงาน
- แสดง event dates และ tags ในหน้ารายการ
- หน้า `/event` แสดงรายชื่อทีมที่ user เป็นสมาชิกก่อน — คลิกทีมแล้วจึงแสดงรายการ event ของทีมนั้น (`/event?team=...`)
- ในหน้ารายการ event ของทีม: ค้นหาชื่อ event, กรองตาม tag, กรองตามช่วงวันที่ (event dates)
- **Event Calendar** (`/event/calendar`): มุมมองปฏิทินแบบ Google Calendar (FullCalendar) แสดง event dates ตามสิทธิ์การมองเห็น กรองตามทีมได้
- Soft-deleted rows จะถูก hard-delete อัตโนมัติทุก 5 นาที (MariaDB EVENT)
- Timezone: Asia/Bangkok (GMT+7)
### User Management
- Email/password authentication
- User roles (user, admin)
- Activity logging system (admin only)
- Profile management (avatar, name, email, password)

### Security
- CSRF protection on all forms
- Encrypted IDs in URLs (prevent enumeration)
- Soft delete ทุก table
- Password hashing with Argon2id

## Installation

### Prerequisites

- Docker & Docker Compose
- Git

### Setup

1. Clone repository
```bash
git clone <repository-url>
cd Project
```

2. Copy environment file
```bash
cp .env.example .env
```

3. Build and start containers
```bash
docker compose up -d --build
```

4. Access the application
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080

### Database Setup

Import database schema:
```bash
docker exec -i team_comp_db mysql -u app_user -papp_password team_competition < database.sql
```

### Run Migrations

If you have an existing database, run migration files:
```bash
docker exec -i team_comp_db mysql -u app_user -papp_password team_competition < migrations/001_add_user_role.sql
docker exec -i team_comp_db mysql -u app_user -papp_password team_competition < migrations/002_add_activity_logs.sql
docker exec -i team_comp_db mysql -u app_user -papp_password team_competition < migrations/003_add_team_to_events.sql
# Purge soft-deleted rows every 5 minutes (run as root; needs event_scheduler=ON)
docker exec -i team_comp_db bash -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < migrations/005_purge_soft_deleted.sql
```

### Create Admin Account

Create a super admin account for managing the system:
```bash
docker exec -it team_comp_app php create_admin.php
```

Default admin credentials:
- Email: `admin@teamcomp.local`
- Password: `Admin@123456`

**Important:** Change these credentials after first login!

## Project Structure

```
compose.yaml              # Docker Compose configuration
AGENT.md                  # Development constraints
.env                      # Environment variables (not in git)
.env.example              # Environment template
database.sql              # Database schema
migrations/               # Database migration files
docker/
  ├── 00-arpache.conf     # Apache virtual host
  └── entrypoint.sh       # Container entrypoint
web/
  ├── Dockerfile          # PHP + Apache image
  ├── composer.json       # PHP dependencies
  ├── views/              # Frontend routes (file-based routing)
  │   ├── index.php       # Home page
  │   ├── 404.php         # Not found page
  │   ├── activity.php    # Activity log (admin only)
  │   ├── settings.php    # User settings
  │   ├── auth/           # Authentication pages
  │   │   ├── login.php
  │   │   ├── register.php
  │   │   ├── logout.php
  │   │   ├── verify.php
  │   │   └── forgot-password.php
  │   ├── team/           # Team management pages
  │   │   ├── manage.php  # Team list & detail
  │   │   ├── create.php  # Create team
  │   │   ├── settings.php # Team settings
  │   │   ├── join.php    # Join team
  │   │   └── invite.php  # Invite handler
  │   └── event/          # Event management pages
  │       ├── index.php   # Event list
  │       ├── view.php    # Event detail
  │       ├── create.php  # Create event
  │       ├── edit.php    # Edit event
  │       ├── delete.php  # Delete event (POST)
  │       ├── register.php
  │       └── unregister.php
  ├── src/                # PHP classes (OOP)
  │   ├── Controllers/    # Request handlers
  │   │   ├── AuthController.php
  │   │   ├── TeamController.php
  │   │   └── EventController.php
  │   └── Services/       # Business logic
  │       ├── Database.php
  │       ├── EmailService.php
  │       ├── CsrfService.php
  │       ├── ActivityLogService.php
  │       └── IdEncoder.php
  ├── api/                # Reusable PHP components
  ├── templates/          # Reusable templates
  │   ├── layout.php      # Main layout
  │   ├── header.php      # Navigation
  │   ├── footer.php      # Footer
  │   └── components/     # UI components (card, button, alert, etc.)
  └── vendor/             # Composer dependencies (not in git)
```

## Routing

File-based routing - filename = URL path:

| File | URL | Description |
|------|-----|-------------|
| `web/views/index.php` | `/` | Home page |
| `web/views/activity.php` | `/activity` | Activity log (admin only) |
| `web/views/settings.php` | `/settings` | User settings |
| `web/views/auth/login.php` | `/auth/login` | Login page |
| `web/views/auth/register.php` | `/auth/register` | Registration page |
| `web/views/auth/logout.php` | `/auth/logout` | Logout handler |
| `web/views/auth/verify.php` | `/auth/verify` | Email verification |
| `web/views/team/manage.php` | `/team/manage` | Team list & detail |
| `web/views/team/create.php` | `/team/create` | Create team |
| `web/views/team/settings.php` | `/team/settings` | Team settings |
| `web/views/team/join.php` | `/team/join` | Join team |
| `web/views/event/index.php` | `/event` | Team list; `?team=` shows that team's events |
| `web/views/event/calendar.php` | `/event/calendar` | Calendar view (FullCalendar) |
| `web/api/events-calendar.php` | `/api/events-calendar` | Calendar JSON feed |
| `web/views/event/view.php` | `/event/view` | Event detail |
| `web/views/event/create.php` | `/event/create` | Create event (owner/admin only) |
| `web/views/event/edit.php` | `/event/edit` | Edit event (creator only) |
| `web/views/event/delete.php` | `/event/delete` | Delete event (POST) |
| `web/views/event/register.php` | `/event/register` | Register for event (POST) |
| `web/views/event/unregister.php` | `/event/unregister` | Unregister/kick (POST) |
| `web/api/team-members.php` | `/api/team-members` | Get team members (AJAX) |
| `web/api/hello.php` | - | Include in views |

## Database Schema

- **users** - User accounts with roles (user, admin)
- **teams** - Team information
- **team_members** - Team membership with roles (owner, admin, member)
- **team_invitations** - Invite tokens (email or shareable link)
- **events** - Event/competition info (team_id, required_members)
- **event_dates** - Dynamic date ranges per event (competition, registration_deadline, meeting, etc.)
- **event_tags** - Custom tags with colors
- **event_registrations** - Event participation (individual or team)
- **event_visibility** - Control event visibility (public/private)
- **activity_logs** - System-wide activity logging
- **sessions** - Session management

All tables support soft delete (`deleted_at` column).

## Development

### Theme

- Default: light
- Toggle: light ↔ dark (saved in localStorage)
- Primary color: cyan/blue

### Code Patterns

```php
// View with component
<?php
$title = 'Page Title';
include_once __DIR__ . '/../api/component.php';

ob_start();
?>
<!-- HTML content -->
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
```

### CDN Setup

```html
<!-- Tailwind CSS v4 -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<!-- DaisyUI v5 -->
<link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
<link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | team_comp_app |
| `APP_PORT` | Web port | 8000 |
| `APP_URL` | Public app URL | http://localhost:8000 |
| `APP_TIMEZONE` | App/DB timezone | Asia/Bangkok |
| `APP_KEY` | Encryption key for IDs | (auto-generated) |
| `DB_HOST` | Database host | mariadb |
| `DB_PORT` | Database port | 3306 |
| `DB_DATABASE` | Database name | team_competition |
| `DB_USERNAME` | Database user | app_user |
| `DB_PASSWORD` | Database password | app_password |
| `PMA_PORT` | phpMyAdmin port | 8080 |
| `SMTP_HOST` | SMTP server | smtp.gmail.com |
| `SMTP_PORT` | SMTP port | 587 |
| `SMTP_USERNAME` | SMTP username | - |
| `SMTP_PASSWORD` | SMTP password | - |
| `MAIL_FROM_ADDRESS` | Sender email | - |
| `MAIL_FROM_NAME` | Sender name | - |

## User Roles

### Regular User
- Create and manage teams
- Join teams via invitation
- Create events only if team **owner** or **admin**
- View events granted by visibility, registration, or as event creator / team owner
- Register / unregister for events
- Manage profile settings

### Team owner
- View every event belonging to the team
- Delete team events (cannot edit events created by others)
- Full team management (settings, roles, transfer)

### Team admin
- Create events for the team
- Help manage team members (per existing team rules)

### Admin (system)
- All regular user permissions
- Access activity log (`/activity`)
- View all system activities
- Filter logs by action type, user, date range

## Activity Logging

The system logs all important actions:

**Authentication:**
- User login/logout
- User registration
- Email verification

**Team Actions:**
- Team creation/deletion
- Member join/leave/kick
- Role changes
- Ownership transfer
- Invitation create/revoke

**Event Actions:**
- Event creation / update / delete
- Event registration / unregister / kick

**User Actions:**
- Profile updates
- Email changes
- Password changes

View logs at `/activity` (admin only).

## Security Features

- **CSRF Protection:** All forms include CSRF tokens
- **Encrypted IDs:** All IDs in URLs are encrypted to prevent enumeration
- **Password Hashing:** Argon2id algorithm
- **Email Verification:** Required for new accounts
- **Role-Based Access:** Admin-only pages protected; event create limited to team owner/admin
- **Soft Deletes:** All tables support soft delete; hard-purged every 5 minutes

## License

MIT
