# Agent Constraints

## Tech Stack
- **Language:** PHP 8.2+ (Vanilla, OOP)
- **CSS:** Tailwind CSS v4 + DaisyUI v5 only
- **Database:** MariaDB 10.11
- **Package Manager:** Composer (autoloading + phpdotenv)
- **Environment:** vlucas/phpdotenv

## CDN Setup
```html
<!-- Tailwind CSS v4 (Play CDN) - 1 file -->
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<!-- DaisyUI v5 - 2 files -->
<link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
<link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
```

## Architecture
- **Pattern:** OOP, Vanilla PHP (no framework)
- **Routing:** File-based (filename = route path)
  - Frontend: `web/views/*.php` → URL path
    - `web/views/index.php` → `/` (via DirectoryIndex)
    - `web/views/team.php` → `/team`
  - Reusable components: `web/api/*.php` → include ใน views
    - `web/api/hello.php` → `include __DIR__ . '/../api/hello.php';`
- **HTTP Methods:** ใช้ `$_SERVER['REQUEST_METHOD']` ตรวจสอบ GET/POST
- **No API endpoints:** ทุกอย่างเป็น PHP page + include
- **No .htaccess:** Apache config via `docker/00-arpache.conf`
- **Config via .env:** All environment variables in `.env`

## Folder Structure
```
compose.yaml              # Docker Compose (global)
AGENT.md                  # Constraints (global)
.env                      # Environment config (global)
docker/                   # Docker config (global)
  -> 00-arpache.conf      # Apache vhost config
  -> entrypoint.sh        # Container entrypoint
web/                      # Web application root
  -> Dockerfile           # Web Docker build (context: .)
  -> composer.json        # PHP dependencies
  -> views/               # Frontend routes (filename = URL path)
    -> index.php          # / (via DirectoryIndex)
    -> 404.php            # 404 page
    -> auth/              # Authentication pages
      -> login.php        # /auth/login
      -> register.php     # /auth/register
      -> forgot-password.php  # /auth/forgot-password
  -> api/                 # Reusable PHP components (include in views)
    -> hello.php          # Example component
  -> templates/           # Reusable templates
    -> layout.php         # Main layout (header + footer)
    -> header.php         # Navbar
    -> footer.php         # Footer
    -> components/        # UI components
      -> card.php         # Card component
      -> button.php       # Button component
      -> alert.php        # Alert component
      -> input.php        # Input component
      -> badge.php        # Badge component
      -> modal.php        # Modal component
```

## Code Patterns

### View with include component
```php
<?php
// web/views/index.php
$title = 'Page Title';

// Include reusable component (once)
include_once __DIR__ . '/../api/hello.php';

ob_start();
?>
<!-- HTML content -->
<div class="alert alert-info">
    <span><?= htmlspecialchars($helloMessage) ?></span>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
```

### Reusable component
```php
<?php
// web/api/hello.php
$helloMessage = 'Hello from API!';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $helloMessage = 'POST request received!';
}
```

### Template usage
```php
<?php
// In view file
$cardTitle = 'Card Title';
$cardBody = 'Card body content';
$cardActions = '<button class="btn btn-primary btn-sm">Action</button>';
include __DIR__ . '/../templates/components/card.php';
```

## Theme
- **Default:** light
- **Toggle:** light ↔ dark (localStorage)
- **Primary color:** cyan/blue (oklch 198°)

## Project Description
Team Competition Management System:
- Create teams with owner and members
- Create events/competitions to join
- Custom tags for events (e.g., interested, registered, etc.)
- Dynamic event dates (event_dates table - competition, registration_deadline, meeting, etc.)
- Visibility control: only members who registered can see event details
- Invite to team via token
- Soft delete all tables (deleted_at column)
- Google login authentication

## Database Schema
- **users** - Google login, email, avatar
- **teams** - Team info, owner reference
- **team_members** - Team membership with roles (owner/admin/member)
- **team_invitations** - Invite tokens with expiry
- **events** - Event basic info
- **event_dates** - Dynamic date ranges per event (start_datetime - end_datetime, date_type: competition, registration_deadline, meeting, etc.)
- **event_tags** - Custom tags with colors
- **event_registrations** - Event participation (individual/team)
- **event_visibility** - Control who can see private events
- **sessions** - Session management

## Rules
- No .htaccess files
- All config through .env
- OOP only, Vanilla PHP
- Tailwind CSS v4 + DaisyUI v5 for styling (no other CSS frameworks)
- No API endpoints (JSON) - ทุกอย่างเป็น PHP page
- ใช้ `include` สำหรับ component ที่อาจใช้ซ้ำ (เช่น card, button, alert)
- ใช้ `include_once` สำหรับไฟล์ที่มีครั้งเดียว (เช่น layout, header, footer, api components)
- ใช้ `$_SERVER['REQUEST_METHOD']` สำหรับ HTTP methods
