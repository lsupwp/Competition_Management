# Agent Constraints

## Tech Stack
- **Language:** PHP 8.2+ (Vanilla, OOP)
- **CSS:** Tailwind CSS v4 + DaisyUI v5 only
- **Database:** MariaDB 10.11
- **Database Driver:** mysqli (OOP style)
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
    - `web/views/team/manage.php` → `/team/manage`
  - JSON/AJAX: `web/api/*.php` → `/api/...`
    - e.g. `web/api/events-calendar.php` → `/api/events-calendar`
- **HTTP Methods:** ใช้ `$_SERVER['REQUEST_METHOD']` ตรวจสอบ GET/POST
- **Config via .env:** All environment variables in `.env`
- **No .htaccess:** Apache config via `docker/00-arpache.conf`

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
      -> verify.php       # /auth/verify
      -> logout.php       # /auth/logout
  -> api/                 # JSON / AJAX endpoints
    -> events-calendar.php
    -> team-members.php
  -> src/                 # PHP classes (OOP)
    -> Controllers/       # Request handlers
      -> AuthController.php  # Register, login, verify
    -> Services/          # Business logic
      -> Database.php     # DB connection (mysqli OOP singleton)
      -> EmailService.php # PHPMailer wrapper
      -> CsrfService.php  # CSRF protection (session-based)
    -> Models/            # Data models (future)
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

### Database Query (mysqli OOP)
```php
// Get database instance
$db = Database::getInstance();

// SELECT with prepared statement
$stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// INSERT
$stmt = $db->prepare("INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $email, $name, $passwordHash);
$stmt->execute();
$userId = $db->insert_id;
$stmt->close();

// UPDATE
$stmt = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
$stmt->bind_param('si', $name, $userId);
$stmt->execute();
$stmt->close();
```

### View pattern
```php
<?php
// web/views/index.php
$title = 'Page Title';

ob_start();
?>
<!-- HTML content -->
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
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
- Email/password authentication

## Database Schema
- **users** - Email/password login, email, avatar, password_hash, verification_token
- **teams** - Team info, owner reference
- **team_members** - Team membership with roles (owner/admin/member)
- **team_invitations** - Invite tokens with expiry
- **team_invitations** - Invite tokens with expiry
- **events** - Event basic info
- **event_dates** - Dynamic date ranges per event (start_datetime - end_datetime, date_type: competition, registration_deadline, meeting, etc.)
- **event_tags** - Custom tags with colors
- **event_registrations** - Event participation (individual/team)
- **event_visibility** - Control who can see private events
- **sessions** - Session management

## Authentication System
- **Password policy** (`web/src/Services/PasswordPolicyService.php`):
  - Min 8 characters, max 128
  - At least one letter and one number
  - Rejects a blocklist of common passwords
  - Used by register, reset-password, and settings change/add password
- **Register flow:**
  1. User fills form → validate data (including password policy)
  2. Check if email exists:
     - If email exists and verified → deny registration
     - If email exists but not verified → overwrite (update) user data
     - If email doesn't exist → create new user
  3. Generate verification token (expires in 24 hours)
  4. Send verification email via PHPMailer
  5. User clicks link → verify email → can login

- **Email verification:**
  - Token stored in `users.verification_token`
  - Expires in 24 hours (`verification_token_expires_at`)
  - Verify URL: `/auth/verify?token=xxx`

- **SMTP Config (.env):**
  - `SMTP_HOST` - SMTP server (e.g., smtp.gmail.com)
  - `SMTP_PORT` - SMTP port (default: 587)
  - `SMTP_USERNAME` - SMTP username
  - `SMTP_PASSWORD` - SMTP password / app password
  - `SMTP_ENCRYPTION` - tls or ssl
  - `MAIL_FROM_ADDRESS` - Sender email
  - `MAIL_FROM_NAME` - Sender name

- **Session Management:**
  - Store user info in `$_SESSION['user']` after login
  - Session contains: `id`, `email`, `name`, `avatar_url`
  - Logout: destroy session and redirect to login page

## Account Management System

### Settings Page (`/settings`)
- **Route:** `web/views/settings.php`
- **Auth required:** Redirects to login if not authenticated
- **POST-Redirect-GET pattern:** All POST actions redirect to prevent form resubmission on F5

### Settings Page Sections

#### 1. Edit Profile
- **Action:** `edit_profile`
- **Fields:** Name, Avatar upload
- **Avatar upload:**
  - Stored in `web/uploads/avatars/`
  - Filename: `avatar_{user_id}_{timestamp}.{ext}`
  - Allowed types: JPG, PNG, GIF, WebP
  - Max size: 2MB
  - Old avatar auto-deleted on upload
  - Updates `users.avatar_url` in database

#### 2. Change Email
- **Action:** `change_email`
- **Verification required before change:**
  - Verify with password
- **After verification:**
  - Update email in database
  - Set `email_verified_at = NULL`
  - Generate verification token (expires 24h)
  - Send verification email to new address
  - Update session with new email

#### 3. Change Password / Add Password
- **Action:** `change_password` or `add_password`
- **Conditional display:**
  - **Has password** → Show "Change Password" form (current + new + confirm)
  - **No password** → Show "Add Password" form (new + confirm only)
- **Validation:**
  - Same password policy as register (min 8, letter + number, not common)
  - New password must match confirmation
  - Current password must be correct (for change)

### Session Management Patterns

#### Flash Messages (POST-Redirect-GET)
```php
// Store flash message before redirect
$_SESSION['settings_flash'] = ['success' => 'Message'] or ['error' => 'Message'];
header('Location: /settings');
exit;

// Retrieve flash message on page load
if (isset($_SESSION['settings_flash'])) {
    $flash = $_SESSION['settings_flash'];
    unset($_SESSION['settings_flash']);
    if (isset($flash['error'])) $error = $flash['error'];
    if (isset($flash['success'])) $success = $flash['success'];
}
```

#### Pending Operations
```php
// Store pending email change
$_SESSION['pending_email_change'] = [
    'new_email' => 'new@example.com'
];
```

### Important Implementation Notes

1. **Session write timing:** Always call `session_write_close()` AFTER all session modifications, before `header('Location: ...')` to ensure data persists.

2. **Email verification:** When changing email, always:
   - Set `email_verified_at = NULL`
   - Generate new verification token
   - Send verification email
   - User must verify new email before it's fully activated

3. **File uploads:** Avatar uploads use `move_uploaded_file()` and store relative path `/uploads/avatars/filename.jpg` in database.

- **Navbar:**
  - Show profile dropdown when user is logged in
  - Dropdown menu items:
    - Settings (`/settings`)
    - Manage Team (`/team/manage`)
    - Join Team (`/team/join`)
    - Logout (`/auth/logout`)
  - Show Login button when user is not logged in

## Security
- **Password policy:** min 8 / max 128, letter + number, common-password blocklist (`PasswordPolicyService`)
- **CSRF Protection:** ทุก POST form ต้องมี CSRF token
  - ใช้ PHP session เก็บ token (หมดอายุ 2 ชั่วโมง)
  - `CsrfService::generateToken()` สร้าง token
  - `CsrfService::validateToken($token)` ตรวจสอบ
  - Component: `web/templates/components/csrf.php`
  - Usage ใน form: `<?php include __DIR__ . '/../../templates/components/csrf.php'; ?>`

## Rules
- No .htaccess files
- All config through .env
- OOP only, Vanilla PHP
- Database: mysqli OOP style (no PDO)
- Tailwind CSS v4 + DaisyUI v5 for styling (no other CSS frameworks)
- No API endpoints (JSON) - ทุกอย่างเป็น PHP page
- ใช้ `include` สำหรับ component ที่อาจใช้ซ้ำ (เช่น card, button, alert)
- ใช้ `include_once` สำหรับไฟล์ที่มีครั้งเดียว (เช่น layout, header, footer, api components)
- ใช้ `$_SERVER['REQUEST_METHOD']` สำหรับ HTTP methods
