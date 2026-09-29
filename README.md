# Team Competition Management System

ระบบจัดการงานแข่งของทีม - สร้างทีม ลงงาน ติดตามสถานะ

## Tech Stack

- **Backend:** PHP 8.2+ (Vanilla, OOP)
- **Frontend:** Tailwind CSS v4 + DaisyUI v5
- **Database:** MariaDB 10.11
- **Package Manager:** Composer
- **Container:** Docker

## Features

- สร้างและจัดการทีม (owner, admin, member)
- เชิญสมาชิกเข้าทีมด้วย token
- สร้างและจัดการงานแข่ง
- Custom tags สำหรับงาน (สนใจ, ลงแล้ว, รอ, ฯลฯ)
- Dynamic event dates (วันแข่ง, วันสิ้นสุดลงทะเบียน, วันประชุม)
- ควบคุมการมองเห็นงาน (public/private)
- Email/password authentication
- Soft delete ทุก table

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

## Project Structure

```
compose.yaml              # Docker Compose configuration
AGENT.md                  # Development constraints
.env                      # Environment variables (not in git)
.env.example              # Environment template
database.sql              # Database schema
docker/
  ├── 00-arpache.conf     # Apache virtual host
  └── entrypoint.sh       # Container entrypoint
web/
  ├── Dockerfile          # PHP + Apache image
  ├── composer.json       # PHP dependencies
  ├── views/              # Frontend routes (file-based routing)
  │   ├── index.php       # Home page
  │   ├── 404.php         # Not found page
  │   └── auth/           # Authentication pages
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

| File | URL |
|------|-----|
| `web/views/index.php` | `/` |
| `web/views/team.php` | `/team` |
| `web/views/auth/login.php` | `/auth/login` |
| `web/api/hello.php` | Include in views |

## Database Schema

- **users** - User accounts (email/password login)
- **teams** - Team information
- **team_members** - Team membership with roles
- **team_invitations** - Invite tokens
- **events** - Event/competition info
- **events** - Event/competition info
- **event_dates** - Dynamic date ranges per event
- **event_tags** - Custom tags with colors
- **event_registrations** - Event participation
- **event_visibility** - Control event visibility
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
| `DB_HOST` | Database host | mariadb |
| `DB_PORT` | Database port | 3306 |
| `DB_DATABASE` | Database name | team_competition |
| `DB_USERNAME` | Database user | app_user |
| `DB_PASSWORD` | Database password | app_password |
| `PMA_PORT` | phpMyAdmin port | 8080 |

## License

MIT
