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
      -> verify.php       # /auth/verify
      -> logout.php       # /auth/logout
      -> google.php       # /auth/google (redirect to Google)
      -> google-callback.php  # /auth/google/callback
  -> api/                 # Reusable PHP components (include in views)
    -> hello.php          # Example component
  -> src/                 # PHP classes (OOP)
    -> Controllers/       # Request handlers
      -> AuthController.php  # Register, login, verify
    -> Services/          # Business logic
      -> Database.php     # DB connection (mysqli OOP singleton)
      -> EmailService.php # PHPMailer wrapper
      -> CsrfService.php  # CSRF protection (session-based)
      -> GoogleAuthService.php  # Google OAuth (league/oauth2-google)
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
- **users** - Google login, email, avatar, password_hash, verification_token
- **teams** - Team info, owner reference
- **team_members** - Team membership with roles (owner/admin/member)
- **team_invitations** - Invite tokens with expiry
- **events** - Event basic info
- **event_dates** - Dynamic date ranges per event (start_datetime - end_datetime, date_type: competition, registration_deadline, meeting, etc.)
- **event_tags** - Custom tags with colors
- **event_registrations** - Event participation (individual/team)
- **event_visibility** - Control who can see private events
- **sessions** - Session management

## Authentication System
- **Register flow:**
  1. User fills form → validate data
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

- **Google OAuth flow:**
  1. User clicks "Login with Google" or "Register with Google"
  2. Redirect to `/auth/google?source=login|register` → stores source in session → generates Google auth URL
  3. User authenticates on Google
  4. Google redirects to `/auth/google/callback` with code
  5. Exchange code for user info via `GoogleAuthService`
  6. Check if user exists:
     - If user exists with google_id → login
     - If user exists with same email → link Google account
     - If user doesn't exist → create new user (auto-verified)
  7. Store in session and redirect to home

- **Google OAuth Behavior by Source:**
  - **Login page** (`source=login`):
    - User not found → redirect to login with modal asking "Create account?"
    - User found with google_id → login directly
    - User found with same email (no google_id) → link account automatically
  - **Register page** (`source=register`):
    - User not found → auto-create account (no modal)
    - User found with google_id → login directly
    - User found with same email (no google_id) → link account automatically

- **Google OAuth Config (.env):**
  - `GOOGLE_CLIENT_ID` - Google OAuth client ID
  - `GOOGLE_CLIENT_SECRET` - Google OAuth client secret
  - `GOOGLE_REDIRECT_URI` - Callback URL (must match Google Console)

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
  - **Password only** → verify with password
  - **Google only** → verify with Google OAuth (`source=settings_email`)
  - **Both password and Google** → show modal to choose verification method
- **After verification:**
  - Update email in database
  - Set `email_verified_at = NULL`
  - Generate verification token (expires 24h)
  - Send verification email to new address
  - Update session with new email
- **Google verification flow:**
  - Store `pending_email_change` in session with `expected_google_id`
  - Redirect to `/auth/google?source=settings_email`
  - Callback validates Google account matches linked account
  - If matches → proceed with email change
  - If not matches → error "Invalid Google account. Please use your linked Google account."

#### 3. Change Password / Add Password
- **Action:** `change_password` or `add_password`
- **Conditional display:**
  - **Has password** → Show "Change Password" form (current + new + confirm)
  - **No password** → Show "Add Password" form (new + confirm only)
- **Validation:**
  - Password min 8 characters
  - New password must match confirmation
  - Current password must be correct (for change)

#### 4. Google Account
- **Actions:** `unlink_google` or link via `/auth/google?source=settings_link`
- **Conditional display:**
  - **Has Google** → Show "Unlink Google Account" button (only if has password)
  - **No Google** → Show "Connect Google Account" button
- **Unlink restriction:**
  - Cannot unlink if no password set (prevents account lockout)
  - Shows warning message instead of button
- **Link flow:**
  - Redirect to `/auth/google?source=settings_link`
  - Callback handles three scenarios:
    - **Google not linked to anyone** → link to current user
    - **Google already linked to current user** → error "already linked to your account"
    - **Google already linked to another user** → error "already linked to another user"
    - **Google email matches current user** → link to current user
    - **Google email matches another user** → error "email already associated with another user"

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

#### Google OAuth Source Tracking
```php
// Store source before Google redirect
$_SESSION['google_auth_source'] = 'login|register|settings_email|settings_link';

// Retrieve source in callback
$source = $_SESSION['google_auth_source'] ?? 'login';

// Handle different sources in callback
if ($source === 'settings_link') {
    // Link Google account to current user
} elseif ($source === 'settings_email') {
    // Verify email change with Google
} elseif ($source === 'register') {
    // Auto-create account if not found
} else {
    // Default login flow
}
```

#### Pending Operations
```php
// Store pending email change
$_SESSION['pending_email_change'] = [
    'new_email' => 'new@example.com',
    'expected_google_id' => '123456789' // for verification
];

// Store pending Google link (from AuthController)
$_SESSION['pending_google_link'] = [
    'google_id' => '123456789',
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'avatar_url' => 'https://...',
    'existing_user_id' => 1
];
```

### Important Implementation Notes

1. **Session write timing:** Always call `session_write_close()` AFTER all session modifications, before `header('Location: ...')` to ensure data persists.

2. **Original user ID:** When handling Google OAuth callback for settings operations, save `$originalUserId = $_SESSION['user']['id']` BEFORE calling `handleGoogleCallback()` because it may overwrite `$_SESSION['user']`.

3. **Email verification:** When changing email, always:
   - Set `email_verified_at = NULL`
   - Generate new verification token
   - Send verification email
   - User must verify new email before it's fully activated

4. **Google account validation:** When verifying email change with Google:
   - Store `expected_google_id` in session
   - In callback, validate `google_id` matches (not email)
   - Prevents using different Google account for verification

5. **File uploads:** Avatar uploads use `move_uploaded_file()` and store relative path `/uploads/avatars/filename.jpg` in database.

- **Navbar:**
  - Show profile dropdown when user is logged in
  - Dropdown menu items:
    - Settings (`/settings`)
    - Manage Team (`/team/manage`)
    - Join Team (`/team/join`)
    - Logout (`/auth/logout`)
  - Show Login button when user is not logged in

## Security
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
