# Project Checklist & Security Requirements: CP423324 Database and Web Security

## 1. Basic System Functions

* [x] User registration or user management system
* [x] Login functionality
* [x] Logout functionality
* [x] View/List data items
* [x] Create new data
* [x] Update existing data
* [x] Delete data
* [x] Search data

## 2. Authentication & Authorization

* [x] **Authentication:**
  * [x] Store passwords using `password_hash()`
  * [x] Verify passwords using `password_verify()`
  * [x] Restrict direct URL access to protected pages (require login)
* [x] **Authorization:**
  * [x] Implement at least 2 user roles (`admin` and `user`)
  * [x] Enforce permission differences (e.g., users edit only their own data, admins manage all records)

## 3. Session Security

* [x] Call `session_regenerate_id()` immediately after successful login
* [x] Implement Session Timeout
* [x] Configure Session Cookies properly (e.g., `HttpOnly` and `SameSite`)

## 4. Database Security

* [x] Web application does not connect to the database using the `root` account
* [x] Create a dedicated database user for the system with Least Privilege permissions

## 5. Vulnerability Protection & Input Handling

* [x] **SQL Injection Prevention:** Use Prepared Statements (`prepare()`, `bind_param()`, `execute()`) for all user-supplied SQL queries
* [x] **Input Validation & Error Handling:**
  * [x] Perform server-side validation for: empty values, data type, length, format, and allowed values
  * [x] Hide SQL errors, database details, and file paths from users
* [x] **XSS Prevention:** Apply output encoding using `htmlspecialchars()` on user input rendered on pages (especially Search, Comments, Names, etc.)
* [x] **CSRF Protection:** Critical actions (like Delete, Update) must use POST requests and include CSRF tokens
* [x] **Security Logging:** Log critical events (e.g., successful/failed logins, data CRUD operations, file uploads)
* [x] **File Upload Security (if applicable):** Validate file extension/MIME type, enforce file size limits, and rename files securely before storage
