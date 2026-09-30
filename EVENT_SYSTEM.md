# Event System Implementation Summary

## Overview
Complete event management system with dynamic dates, custom tags, and visibility control.

## Database Tables

### events
- `id` - Primary key
- `created_by` - User who created the event
- `team_id` - Team creating the event (NULL for individual events)
- `title` - Event title (VARCHAR 255)
- `description` - Event description (TEXT)
- `location` - Event location (VARCHAR 500)
- `required_members` - Number of members needed per team (default: 3)
- `created_at`, `updated_at`, `deleted_at` - Timestamps

### event_dates
- `id` - Primary key
- `event_id` - Foreign key to events
- `date_type` - Type of date (competition, registration_deadline, meeting, other)
- `start_datetime` - Start date and time
- `end_datetime` - End date and time
- `description` - Optional description for this date
- `created_at`, `updated_at`, `deleted_at` - Timestamps

### event_tags
- `id` - Primary key
- `event_id` - Foreign key to events
- `name` - Tag name (VARCHAR 100)
- `color` - Hex color code (VARCHAR 7)
- `created_at`, `updated_at`, `deleted_at` - Timestamps

### event_registrations
- `id` - Primary key
- `event_id` - Foreign key to events
- `user_id` - Foreign key to users
- `team_id` - Optional foreign key to teams (NULL for individual registration)
- `status` - Registration status (pending, confirmed, cancelled)
- `registered_at` - Registration timestamp
- `created_at`, `updated_at`, `deleted_at` - Timestamps

### event_visibility
- `id` - Primary key
- `event_id` - Foreign key to events
- `user_id` - Foreign key to users who can see this event
- `granted_by` - User who granted visibility
- `created_at`, `updated_at`, `deleted_at` - Timestamps

## Features Implemented

### 1. Event Creation (`/event/create`)
- **Basic Information**
  - Event title (required, max 255 chars)
  - Location (optional, max 500 chars)
  - Description (optional, textarea)

- **Team & Members**
  - Team selection dropdown (only shows teams user is member of)
  - Required members per team (default: 3, range: 1-100)
  - Member visibility selection (checkboxes)
    - Dynamically loads team members via AJAX when team is selected
    - Only selected members can see the event
    - Validates user is member of selected team

- **Event Dates** (Dynamic, unlimited)
  - Date type selection (competition, registration_deadline, meeting, other)
  - Start datetime (required)
  - End datetime (required)
  - Description (optional)
  - Add/Remove dates dynamically with JavaScript
  - Visual feedback with numbered sections

- **Event Tags** (Dynamic, unlimited)
  - Tag name input
  - Color picker (hex color)
  - Add/Remove tags dynamically with JavaScript
  - Visual preview with colored badges

### 2. Event List (`/event`)
- Display all public events
- Show event dates (up to 3, with "+X more" indicator)
- Show event tags with colors
- Show registration count
- Show creator name and creation date
- Pagination (10 events per page)
- Link to event details

### 3. Event Detail (`/event/view`)
- Full event information
- All event dates with type, datetime range, and description
- All event tags with colors
- Registration list with:
  - User name and email
  - Team name (if team registration)
  - Registration status
  - Registration date
- Edit button (only for event creator)

### 4. Event Controller (`EventController.php`)
- `createEvent()` - Create event with team, dates, tags, and visibility
  - Uses database transactions for data integrity
  - Validates required fields and team membership
  - Inserts into events, event_visibility, event_dates, event_tags tables
  - Logs activity
  - Rollback on error

- `getEvents()` - Get paginated event list
  - Join with users table for creator name
  - Count registrations
  - Fetch dates and tags for each event

- `getEventById()` - Get single event with all details
  - Fetch all dates, tags, and registrations
  - Join with users and teams tables

- `getUserTeams()` - Get teams user is member of
  - Returns team id and name
  - Used for team selection dropdown

- `getTeamMembers()` - Get members of a team
  - Returns user id, name, email
  - Used for member visibility selection

- Helper methods:
  - `isUserTeamMember()` - Check if user is member of team
  - `getEventDates()` - Get all dates for an event
  - `getEventTags()` - Get all tags for an event
  - `getEventRegistrations()` - Get all registrations for an event

## Technical Implementation

### Frontend (create.php)
- Dynamic form sections using JavaScript
- Team selection dropdown with AJAX member loading
- Add/Remove buttons for dates and tags
- Indexed array inputs for form submission:
  - `dates[0][date_type]`, `dates[0][start_datetime]`, etc.
  - `tags[0][name]`, `tags[0][color]`, etc.
  - `visibility_users[]` - Array of selected user IDs
- Color picker input type="color"
- Datetime-local input type for date/time selection
- CSRF protection on all forms
- AJAX endpoint: `/api/team-members?team_id=X`

### Backend (EventController.php)
- Transaction-based event creation
- Array iteration for dates, tags, and visibility users
- Validation of required fields and team membership
- Error handling with rollback
- Activity logging integration

### API (team-members.php)
- GET endpoint to fetch team members
- Validates user authentication
- Validates user is member of requested team
- Returns JSON with member list (id, name, email)
- Used by create.php for dynamic member selection

### Database
- Foreign key constraints with CASCADE delete
- Indexes for performance
- Soft delete support (deleted_at column)
- Unique constraints where needed

## Security Features
- CSRF token validation on all forms
- Encrypted IDs in URLs (IdEncoder)
- User authentication required
- Transaction-based data integrity
- Input validation and sanitization

## User Experience
- Clean, modern UI with Tailwind CSS + DaisyUI
- Visual feedback for dynamic form elements
- Color-coded tags for easy identification
- Responsive design for mobile devices
- Clear section dividers and labels
- Helpful tooltips and info boxes

## Future Enhancements (Not Yet Implemented)
- Event editing functionality
- Event deletion with confirmation
- Event registration form
- Event visibility management interface
- Event search and filtering
- Event categories
- File attachments for events
- Event reminders/notifications
- Calendar view integration
- Export events to iCal format

## Testing Checklist
- [x] Create event with basic info only
- [x] Create event with team selection
- [x] Create event with required members
- [x] Create event with member visibility selection
- [x] Create event with multiple dates
- [x] Create event with multiple tags
- [x] Add/Remove dates dynamically
- [x] Add/Remove tags dynamically
- [x] Form validation (required fields)
- [x] Team membership validation
- [x] Database transaction rollback on error
- [x] Activity logging
- [x] View event list with dates and tags
- [x] View event detail with all information
- [x] Pagination on event list
- [x] Encrypted IDs in URLs
- [x] CSRF protection
- [x] AJAX team member loading

## Files Modified/Created
- `web/views/event/create.php` - Event creation form with team and member selection
- `web/views/event/index.php` - Event list page
- `web/views/event/view.php` - Event detail page
- `web/src/Controllers/EventController.php` - Event business logic with team methods
- `web/api/team-members.php` - API endpoint for team members
- `database.sql` - Database schema (updated with team_id, required_members)
- `migrations/003_add_team_to_events.sql` - Migration for existing databases
- `README.md` - Updated documentation
- `EVENT_SYSTEM.md` - Implementation summary

## Commit History
1. `feat: add event system mockup` - Initial event system with basic CRUD
2. `fix: add Apache rewrite rule for directory-style URLs` - Fix 404 error
3. `fix: correct autoload path in activity.php` - Fix path issues
4. `docs: update README with event system and security features` - Documentation
5. `feat: add event dates, tags, and visibility to create form` - Complete event creation
6. `docs: update README with detailed event system features` - Detailed documentation
7. `feat: add team selection and member visibility to event creation` - Team-based events with visibility control
