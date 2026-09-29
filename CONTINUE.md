# Project Status & Next Steps

## ✅ Completed Features

### Authentication System
- **Email/Password Registration**
  - Form validation (fail-fast: return first error)
  - Email verification with token (24h expiry)
  - Argon2id password hashing
  - CSRF protection on all forms
  
- **Email/Password Login**
  - Session-based authentication
  - "Invalid email or password" for all failures (security)
  - No-cache headers on auth pages
  - Password visibility toggle (eye icon)

### UI/UX
- Tailwind CSS v4 + DaisyUI v5
- Light/Dark theme toggle
- Responsive navbar with profile dropdown
- Profile avatar (first letter)
- Dropdown menu: Settings, Manage Team, Join Team, Logout
- Alert boxes for success/error messages
- Loading animations on forms

### Database
- MariaDB 10.11
- Soft delete (deleted_at column)
- Tables: users, teams, team_members, team_invitations, events, event_dates, event_tags, event_registrations, event_visibility, sessions

### Infrastructure
- Docker Compose (app, mariadb, phpmyadmin)
- Apache with mod_rewrite
- File-based routing (filename = URL path)
- No .htaccess (use Apache config)
- mysqli OOP (no PDO)
- Composer autoloading

## 🚧 In Progress / Not Started

### Authentication (Partial)
- [ ] **Forgot Password** - Page exists but logic not implemented
  - Need: Generate reset token, send email, reset password page
  
- [ ] **Email OTP Verification** - For account linking
  - Need: Generate OTP, send email, verify OTP page

### Team Management
- [ ] **Create Team** - Form and backend
- [ ] **Manage Team** (`/team/manage`) - Page not created
  - View team members
  - Invite members (generate token)
  - Remove members
  - Change roles
- [ ] **Join Team** (`/team/join`) - Page not created
  - Enter invite token
  - Accept invitation

### Event Management
- [ ] **Create Event** - Form with dynamic dates and tags
- [ ] **Event List** - Browse events
- [ ] **Event Details** - View event info
- [ ] **Register for Event** - Individual or team registration
- [ ] **Event Visibility** - Control who can see private events

### Settings
- [ ] **Settings Page** (`/settings`) - Not created
  - Update profile (name, avatar)
  - Change password
  - Email preferences

### Security
- [ ] **Session Management** - Basic implementation exists
  - Need: Session timeout, remember me functionality
  - Need: Logout from all devices
  
- [ ] **Rate Limiting** - Not implemented
  - Login attempts
  - Registration attempts
  - Password reset attempts

### Testing
- [ ] Unit tests for controllers
- [ ] Integration tests for auth flows
- [ ] Manual testing checklist

## 📝 Current Branch
`auth` - Authentication system implementation (email/password only)

## 🔧 Recent Commits
```
3452d46 Fix bind_param type count in promoteUnverifiedToGoogle
3f56844 Add password toggle and fix password field bug
0f23fce Fix Google login: separate accounts, no auto-linking
f0b3715 Add modal for Google OAuth signup
e8405d3 Revert modal, fix login error message, add no-cache headers
75cfd1f Add modal for quick registration from login page
55c44f3 Fix Google OAuth callback path and autoload location
245944c Fix Google OAuth to use env_file from compose
60b4fb5 Fix Google OAuth env loading
74b0429 Add Google OAuth authentication
24b07fc Add user profile dropdown and logout functionality
6385940 Implement authentication system with email verification
```

## 🎯 Recommended Next Steps

### Priority 1: Complete Authentication
1. Implement Forgot Password flow
2. Add Email OTP verification for account linking
3. Implement "Remember Me" functionality

### Priority 2: Team Management
1. Create team management pages
2. Implement invite system with tokens
3. Add team member roles and permissions

### Priority 3: Event Management
1. Create event CRUD operations
2. Implement event registration
3. Add event visibility controls

### Priority 4: Settings & Profile
1. Create settings page
2. Allow profile updates
3. Add password change functionality

## 📚 Important Files

### Auth System
- `web/src/Controllers/AuthController.php` - All auth logic
- `web/views/auth/` - Auth pages (login, register, verify, etc.)
- `web/src/Services/` - Database, Email, CSRF services

### Configuration
- `AGENT.md` - Project constraints and rules
- `database.sql` - Database schema
- `.env.example` - Environment variables template

### Docker
- `compose.yaml` - Docker Compose configuration
- `web/Dockerfile` - PHP + Apache setup
- `docker/00-arpache.conf` - Apache virtual host config

## 🔐 Environment Variables Required

Already configured:
- Database credentials
- SMTP settings (for emails)
- APP_URL

Need to add:
- Session timeout settings
- Rate limiting config
- Email OTP settings

## 🐛 Known Issues
- None currently (last bug fixed: bind_param type count)

## 📖 Notes
- All UI text is in English (except user input)
- Password hashing: Argon2id
- CSRF tokens: Session-based, 2h expiry
- Email verification: 24h expiry
- Database: mysqli OOP (no PDO)
- Routing: File-based (filename = URL path)
