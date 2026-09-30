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
- โอนความเป็นเจ้าของทีม (Transfer Ownership)
- ค้นหาสมาชิกในทีม
- Pagination สำหรับรายการทีม

### Event System
- สร้างและจัดการงานแข่ง
- Custom tags สำหรับงาน (สนใจ, ลงแล้ว, รอ, ฯลฯ)
- Dynamic event dates (วันแข่ง, วันสิ้นสุดลงทะเบียน, วันประชุม)
- ควบคุมการมองเห็นงาน (public/private)
- สมัครสมาชิกงาน (individual/team)
- Pagination สำหรับรายการงาน

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
  │       └── create.php  # Create event
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
| `web/views/event/index.php` | `/event` | Event list |
| `web/views/event/view.php` | `/event/view` | Event detail |
| `web/views/event/create.php` | `/event/create` | Create event |
| `web/api/hello.php` | - | Include in views |

## Database Schema

- **users** - User accounts with roles (user, admin)
- **teams** - Team information
- **team_members** - Team membership with roles (owner, admin, member)
- **team_invitations** - Invite tokens (email or shareable link)
- **events** - Event/competition info
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
- Create and view events
- Register for events
- Manage profile settings

### Admin
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
- Event creation
- Event registration

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
- **Role-Based Access:** Admin-only pages protected
- **Soft Deletes:** All tables support soft delete for data recovery

## License

MIT
