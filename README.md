# ISU Visa Management System

The **ISU Visa Management System** is a web-based PHP and MySQL system for managing international student visa-related processes. It supports student self-service workflows and staff/admin review workflows for student profiles, visa records, visa renewal applications, supporting documents, insurance information, claims, notifications, reports, and exit processes.

This project is designed as an academic/student management system for an International Student Services Unit (ISU/ISSU). It keeps the existing plain PHP structure and focuses on practical workflows that can be run locally through XAMPP, WAMP, LAMP, or a similar PHP/MySQL stack.

## Purpose of the System

International student administration often requires many repeated tasks:

- collecting student profile and contact information
- tracking visa and student pass expiry dates
- receiving visa renewal requests
- collecting passport, visa, insurance, and supporting documents
- reviewing student submissions
- updating progress statuses
- managing insurance records and claims
- managing exit requests and clearance actions
- giving students a single place to track progress
- giving staff a central dashboard for follow-up and reporting

The ISU Visa Management System exists to organize those workflows in one place. Students can register, log in, submit information, upload supporting documents, and monitor their status. Staff/admin users can review submissions, update records, process approvals, and view management reports.

## Main Users and Roles

### Student

Students use the system to manage their own international student service records. A student can register, log in, view dashboard summaries, update profile photos, view visa information, submit renewal requests, upload supporting documents, manage insurance-related records, submit exit requests, and read notifications.

### Staff/Admin

Staff and admin users use the system to manage student records and operational workflows. They can view dashboards, search and manage students, review visa renewal applications, update statuses, review documents, manage insurance claims and renewals, process exit cases, send notifications, and open reports.

The code currently treats `staff` and `admin` as staff-side roles. Some admin-only actions are available where the code checks for the `admin` role, such as running visa expiry reminders from the staff dashboard.

## Feature List

### Public/Home Pages

The project includes public-facing PHP pages:

- `index.php` for the main home page
- `about.php` for project/institution information
- `contact.php` for contact content
- `login.php` for user authentication
- `register.php` for student registration

These pages are available before login and provide entry points into the student and staff portals.

### Registration

Student registration is handled by `register.php`.

Registration includes:

- student ID
- official student email
- first name and last name
- phone number
- date of birth
- gender
- nationality
- school and program
- student type: pre-college, undergraduate, or postgraduate
- optional passport number
- emergency/contact-related information
- password and confirmation
- terms agreement on the client side

Important registration behavior:

- required fields are validated
- email format is validated
- student emails are expected to use the `@student.aiu.edu.my` domain
- duplicate email accounts are rejected
- duplicate student IDs are rejected
- passwords are hashed using PHP password hashing before storage
- related nationality, visa, and subtype records are created when applicable

### Login and Logout

Login is handled by `login.php`.

The login page supports:

- student login
- staff/admin login
- role selection
- password verification with `password_verify`
- account status checks
- redirect to the correct dashboard after login

Logout is handled by:

- `logout.php`
- `student/logout.php`
- `staff/logout.php`

Logout clears session data, destroys the session, removes the session cookie, and redirects back to the login page.

### Password Reset

The project now includes a password reset flow:

- `forgot-password.php` accepts a student or staff/admin email.
- `reset-password.php` accepts a secure reset token and lets the user set a new password.
- Reset tokens are randomly generated, stored as SHA-256 hashes, and expire after 1 hour.
- Used tokens are marked with `used_at` so the same link cannot be reused.
- Passwords are saved with PHP password hashing.

Required database table:

- `password_resets`

Import `database/security_updates.sql` or the fresh `database/schema.sql` file before using this feature.

Email sending is prepared but disabled by default for local XAMPP development. When email is disabled, reset links are written to the PHP error log for development/testing.

### Authentication and Session Protection

Protected student pages load `student/header.php`.

Protected staff/admin pages load `staff/header.php`.

The shared helper file `includes/functions.php` provides session helpers used by these headers. Protected pages require the correct role:

- student pages require `student`
- staff pages require `staff` or `admin`

This prevents student pages from being opened by staff accounts and staff pages from being opened by student accounts.

### CSRF Protection

The system includes reusable CSRF helpers in `includes/functions.php`:

- `csrf_token()`
- `csrf_field()`
- `verify_csrf_token()`
- `require_csrf()`

Important POST forms now include CSRF tokens, including login, registration, password reset, profile uploads, settings, student documents, student visa renewal, student insurance, student exit, staff dashboard actions, staff notifications, staff insurance actions, staff exit actions, staff student actions, and staff visa renewal actions.

### Student Dashboard

The student dashboard is located at:

- `student/dashboard.php`

It displays:

- student summary
- visa status
- visa expiry information
- latest visa renewal application
- insurance status
- exit request status
- nationality details
- profile shortcuts
- renewal shortcuts
- visual charts using Chart.js

### Student Visa Page

The student visa page is located at:

- `student/visa.php`

It is used for viewing student visa information, expiry dates, renewal prompts, and related document/status information.

### Student Visa Renewal

Visa renewal functionality is mainly handled by:

- `student/visa_renewal.php`
- `student/renewal.php`
- `student/renewal_status.php`
- `student/documents.php`

Student renewal features include:

- submitting a visa renewal request
- selecting requested renewal months
- viewing current active or latest application
- viewing status timelines
- uploading supporting documents
- uploading multiple documents
- editing document type or replacing uploaded files
- deleting own uploaded documents
- validating document ownership
- validating file extensions and MIME types for key renewal uploads

### Document Uploads

Students can upload visa renewal documents such as:

- passport copy
- visa page
- student pass sticker
- offer letter
- enrollment letter
- insurance document
- academic transcript
- other supporting documents

Main upload location:

- `uploads/visa_documents`

Profile photo upload locations:

- `student/uploads/profile`
- `staff/uploads/profile`

Other upload folders currently used or reserved by the project:

- `uploads/documents`
- `uploads/insurance_claims`
- `uploads/profile`

Real uploaded documents and profile images are ignored by Git for privacy and security.

### Secure Document Viewing

Visa renewal documents are served through:

```text
download.php?id=DOCUMENT_ID
```

The secure handler:

- requires login
- checks student ownership
- allows staff/admin/visa-officer style roles to review documents
- prevents path traversal
- only serves files from `uploads/visa_documents`
- logs sensitive document review/download events in `audit_logs`

Existing upload storage remains unchanged, but public pages no longer link directly to private visa document paths.

### Student Insurance Management

Student insurance functionality is handled mainly by:

- `student/insurance.php`
- `student/process_claim.php`
- `student/claim_success.php`

Student insurance features include:

- viewing current insurance policy details
- viewing provider details
- monitoring expiry dates
- submitting claims where supported by the database procedures
- viewing claim submission success states

### Student Exit Management

Student exit functionality is handled by:

- `student/exit.php`
- `student/submit_exit.php`

Students can submit exit-related information and track the status of their request.

### Student Profile and Settings

Student profile functionality is handled by:

- `student/profile.php`
- `student/settings.php`
- `student/student.php`

Student profile features include:

- viewing student details
- viewing academic program and school information
- viewing nationality details
- viewing academic calendar records
- uploading/cropping profile photos
- changing account settings where supported

### Student Notifications

Student notifications are handled by:

- `student/notifications.php`

Notifications are stored in the `notifications` table and can be shown in the student navigation badge and notification page.

The notification system is used for events such as:

- registration success
- visa renewal submission
- document upload
- visa status/timeline updates
- visa approval
- insurance claim/renewal status changes
- exit request submission and status changes
- staff updates to student records

Students only see notifications linked to their own `student_id`.

### Staff Dashboard

The staff dashboard is located at:

- `staff/dashboard.php`

It displays:

- total students
- active visa renewal counts
- pending insurance claims
- pending exit cases
- visa expiry reports
- insurance expiry reports
- passport-ready reports
- exit case reports
- dashboard charts
- admin-only visa reminder generation where permitted

### Staff Student Management

Student management is handled by:

- `staff/students.php`

Staff can view and manage student records using the existing database procedures and forms.

### Staff Visa Management

Visa management is handled by:

- `staff/visa_management.php`
- `staff/visa_renewal.php`

Staff visa features include:

- searching/filtering visa records
- viewing visa renewal applications
- opening application details
- viewing supporting documents
- adding timeline stages
- updating application status
- approving a renewal workflow by adding an approved timeline stage and updating the valid application status
- updating the student visa record

### Staff Visa Renewal Workflow

The staff visa renewal page supports:

- list view with search and filters
- detail view for a selected renewal application
- document review
- status timeline review
- adding new stages
- updating request status
- approval helper workflow
- deleting an application and related database records when needed
- CSRF token checks on staff renewal POST actions

### Staff Insurance Management

Insurance management is handled by:

- `staff/insurance_management.php`
- `staff/insurance_claims.php`

Staff can manage policies, renewal records, claim statuses, and related insurance workflows.

### Staff Exit Management

Exit management is handled by:

- `staff/exit_management.php`
- `staff/exit_cases.php`

Staff can review student exit cases, manage exit statuses, manage clearance records, and track exit visa actions where supported by the database procedures.

### Staff Notifications

Staff notification-related functionality is handled by:

- `staff/notifications.php`
- `staff/send_notification.php`

Staff can send and review notifications depending on the configured database tables.

The `notifications` table supports staff-targeted notifications through the optional `staff_id` column added by `database/security_updates.sql`. Staff/admin users can review notifications from `staff/notifications.php`, and active staff can receive alerts when students submit important requests.

### Audit Logs

Audit logging is supported through:

- table: `audit_logs`
- helper: `log_audit()`
- page: `staff/audit_logs.php`

Tracked events include password reset requests/completions, profile changes, student record updates/deletes, visa renewal status changes, visa approvals, insurance status changes, exit status changes, and secure document downloads.

Only admin/super-admin style users can open the audit log page.

### Staff Reports

Reports are handled by:

- `staff/reports.php`

Reports include visa expiry, insurance expiry, passport-ready, exit-case, and other operational views based on the SQL procedures in `issu.sql`.

### Profile Uploads

Student and staff profile upload pages include image cropping functionality through Cropper.js.

Profile upload folders:

- `student/uploads/profile`
- `staff/uploads/profile`

Default profile images are kept in:

- `student/uploads/default_image.png`
- `staff/uploads/default_image.png`
- `default_image.png`

## System Workflow

1. A student opens the system from the public home page.
2. The student registers using `register.php`.
3. Registration validates required fields, email format, student ID uniqueness, duplicate email accounts, and password confirmation.
4. The student record is created in the `student` table.
5. Related records such as nationality, student subtype, and optional visa record are created when applicable.
6. The student logs in from `login.php` using the student role.
7. The system verifies the password hash and account status.
8. The student is redirected to `student/dashboard.php`.
9. The student reviews visa, insurance, profile, renewal, document, notification, and exit information.
10. The student submits a visa renewal application when needed.
11. The student uploads required supporting documents.
12. Staff/admin users log in from `login.php` using the staff role.
13. Staff/admin users are redirected to `staff/dashboard.php`.
14. Staff review student records, visa renewals, insurance records, claims, and exit cases.
15. Staff update statuses and add timeline stages.
16. Students return to the student portal to track progress.
17. Staff use reports and dashboards to monitor expiring visas, expiring insurance policies, pending passport collection, and pending exit cases.

## Tech Stack

This project uses the technologies present in the repository:

- PHP
- MySQL/MariaDB
- HTML
- CSS
- JavaScript
- Bootstrap CSS
- Bootstrap Icons through CDN
- Chart.js through CDN
- Cropper.js through CDN
- XAMPP/WAMP/LAMP-style local server environment

The application is a plain PHP project. It is not built with Laravel, Symfony, React, Vue, or another full-stack framework.

## Folder Structure

```text
isu_visa_mangement_system/
├── index.php
├── about.php
├── contact.php
├── login.php
├── logout.php
├── register.php
├── hash.php
├── testdb.php
├── issu.sql
├── bootstrap.min.css
├── style.css
├── logo.png
├── default_image.png
├── .gitignore
├── README.md
├── includes/
│   ├── db.php
│   └── functions.php
├── student/
│   ├── header.php
│   ├── footer.php
│   ├── dashboard.php
│   ├── student.php
│   ├── profile.php
│   ├── settings.php
│   ├── visa.php
│   ├── visa_renewal.php
│   ├── renewal.php
│   ├── renewal_status.php
│   ├── documents.php
│   ├── insurance.php
│   ├── process_claim.php
│   ├── claim_success.php
│   ├── exit.php
│   ├── submit_exit.php
│   ├── notifications.php
│   ├── logout.php
│   ├── student_style.css
│   └── uploads/
│       ├── default_image.png
│       └── profile/
│           └── .gitkeep
├── staff/
│   ├── header.php
│   ├── footer.php
│   ├── dashboard.php
│   ├── students.php
│   ├── profile.php
│   ├── settings.php
│   ├── visa_management.php
│   ├── visa_renewal.php
│   ├── insurance_management.php
│   ├── insurance_claims.php
│   ├── exit_management.php
│   ├── exit_cases.php
│   ├── reports.php
│   ├── notifications.php
│   ├── send_notification.php
│   ├── logout.php
│   ├── staff_style.css
│   └── uploads/
│       ├── default_image.png
│       └── profile/
│           └── .gitkeep
├── uploads/
│   ├── documents/
│   │   └── .gitkeep
│   ├── insurance_claims/
│   │   └── .gitkeep
│   ├── profile/
│   │   └── .gitkeep
│   └── visa_documents/
│       └── .gitkeep
└── extra/
    ├── login.html
    ├── main.html
    └── register.html
```

## Important Files

### `includes/db.php`

Loads the MySQL connection settings. The default local configuration is:

- host: `localhost`
- username: `root`
- password: empty local password
- database: `issu`

Do not put production passwords or private credentials in this file before committing.

### `includes/db.local.php`

Optional local-only database configuration file. This file is ignored by Git and should be used for real local/production credentials.

Create it by copying:

```text
includes/db.example.php
```

Example format:

```php
<?php
return [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'issu',
    'port' => 3306,
];
```

### `includes/db.example.php`

Safe example database config file committed to the repository.

### `includes/functions.php`

Contains shared helpers for:

- secure session startup
- login checks
- role checks
- redirects
- session destruction
- upload validation helpers
- safe alert output helpers
- session timeout checking

### `issu.sql`

Contains the database schema, stored procedures, triggers, indexes, constraints, and sample data exported from phpMyAdmin/MariaDB.

### `database/schema.sql`

Schema-first database import file generated from the existing SQL export. It includes structure, procedures, triggers, constraints, and the new security/password-reset/audit/notification tables.

### `database/demo_data.sql`

Demo/sample insert file generated from the existing SQL export. Insert blocks containing local upload paths are skipped so private upload references are not included.

### `database/security_updates.sql`

Migration file for existing installs. Import this into an existing `issu` database to add:

- `password_resets`
- `audit_logs`
- extended `notifications` columns for staff notifications and notification types

### `student/header.php`

Protects student pages and loads the student sidebar/navigation.

### `staff/header.php`

Protects staff/admin pages and loads the staff sidebar/navigation.

### `.gitignore`

Prevents uploaded private documents and generated profile photos from being committed.

## Installation Guide for Windows/XAMPP

### 1. Install XAMPP

Install XAMPP from the Apache Friends website. During installation, make sure Apache, MySQL/MariaDB, PHP, and phpMyAdmin are available.

### 2. Start Apache and MySQL

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Clone the Repository

Clone the project:

```bash
git clone https://github.com/chitko84/isu_visa_mangement_system.git
```

### 4. Move or Copy the Project to `htdocs`

If your clone is not already inside XAMPP's web folder, copy it into:

```text
C:\xampp\htdocs\isu_visa_mangement_system
```

The project can then be opened through:

```text
http://localhost/isu_visa_mangement_system/
```

### 5. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
issu
```

Use `utf8mb4` collation where possible.

### 6. Import the SQL Files

In phpMyAdmin:

1. Select the `issu` database.
2. Click **Import**.
3. Choose `database/schema.sql`.
4. Click **Go**.
5. Optional: import `database/demo_data.sql` if you want sample/demo rows.

For existing databases that were already imported from `issu.sql`, import:

```text
database/security_updates.sql
```

This adds the password reset, audit log, and enhanced notification structures without replacing existing data.

### 7. Configure Database Connection

Open:

```text
includes/db.php
```

Confirm these values match your local environment:

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "issu";
```

For a default XAMPP installation, these values normally work.

Recommended local override:

1. Copy `includes/db.example.php` to `includes/db.local.php`.
2. Edit `includes/db.local.php`.
3. Keep `includes/db.local.php` uncommitted.

### 8. Create Upload Folders if Missing

The repository includes `.gitkeep` placeholders, but if folders are missing locally, create:

```text
staff/uploads/profile
student/uploads/profile
uploads/visa_documents
uploads/documents
uploads/insurance_claims
uploads/profile
```

On Windows/XAMPP, these folders usually work without extra permission changes. On Linux/LAMP, make sure the web server user can write to these folders.

### 9. Open the Application

Open:

```text
http://localhost/isu_visa_mangement_system/
```

Useful entry points:

```text
http://localhost/isu_visa_mangement_system/register.php
http://localhost/isu_visa_mangement_system/login.php
http://localhost/isu_visa_mangement_system/student/dashboard.php
http://localhost/isu_visa_mangement_system/staff/dashboard.php
```

Student and staff dashboard URLs require login.

## Database Setup

The project uses a MySQL/MariaDB database named:

```text
issu
```

The original full SQL export is:

```text
issu.sql
```

The recommended fresh-install files are:

```text
database/schema.sql
database/demo_data.sql
```

The recommended existing-install migration file is:

```text
database/security_updates.sql
```

The SQL file defines tables such as:

- `student`
- `staff`
- `program`
- `school`
- `country`
- `nationality`
- `student_visa`
- `visa_renewal_application`
- `visa_renewal_status`
- `visa_document`
- `insurance_provider`
- `insurance_policy`
- `insurance_claim`
- `insurance_renewal_record`
- `exit_case`
- `clearance_record`
- `unit_clearance`
- `exit_visa_action`
- `notifications`
- `reminder_queue`
- `academic_dates`

It also defines stored procedures used by the PHP pages, including student profile, visa renewal, insurance, exit, and report procedures.

New security tables:

- `password_resets`
- `audit_logs`

Updated notification support:

- `notifications.staff_id`
- `notifications.notification_type`

If import fails, check:

- the database name is exactly `issu`
- MySQL/MariaDB is running
- your phpMyAdmin upload limit is large enough
- stored procedures/triggers are allowed in your local MySQL configuration
- you imported into an empty database

## Upload Folders

Uploaded user files are intentionally not tracked by Git.

Important upload folders:

```text
staff/uploads/profile
student/uploads/profile
uploads/visa_documents
uploads/documents
uploads/insurance_claims
uploads/profile
```

These folders are used for:

- staff profile photos
- student profile photos
- visa renewal supporting documents
- insurance claim files
- other document uploads

The repository keeps `.gitkeep` placeholder files so the folder structure exists after cloning. Real uploaded PDFs, images, and private documents should remain local and should not be committed.

## Security Notes

This project includes practical security improvements while keeping the existing PHP structure:

- protected pages require login
- student pages require the `student` role
- staff pages require `staff` or `admin`
- session cookies are configured with `HttpOnly` and `SameSite=Lax`
- successful login regenerates the session ID
- logout clears session data and removes the session cookie
- registration stores password hashes instead of plain text
- login uses `password_verify`
- many database operations use prepared statements
- important staff visa renewal POST actions use CSRF tokens
- output is escaped in many display locations using `htmlspecialchars`
- upload file extensions and sizes are validated in upload flows
- key visa renewal uploads validate MIME type as well as extension
- private upload folders are ignored by Git
- database connection errors are logged instead of printing raw connection details
- secure password reset tokens are hashed and expire
- sensitive visa documents are served through permission-checked `download.php`
- important actions are recorded in `audit_logs`
- in-app notifications are stored in the database
- local database credentials can be kept in ignored `includes/db.local.php`

Important security reminders:

- Do not commit real database credentials.
- Do not commit uploaded visa documents.
- Do not commit passport scans.
- Do not commit profile photos from real users.
- Do not commit insurance claim documents.
- Do not deploy this project publicly without additional production hardening.

Recommended production improvements:

- force HTTPS
- move private uploads outside the web root
- serve documents through permission-checked download controllers
- add CSRF protection consistently to every mutating form
- add audit logs for staff actions
- add account lockout/rate limiting
- add password reset through email
- review all sample data before deployment

## Email Notification Setup

Email support is centralized in `send_email_notification()` inside `includes/functions.php`.

For XAMPP/local development:

- Email sending is disabled by default.
- Password reset links and email messages are written to the PHP error log.
- This prevents local testing from crashing when SMTP is not configured.

To enable basic PHP `mail()` sending:

1. Configure SMTP/sendmail for your PHP installation.
2. Set environment variable:

```text
ISU_EMAIL_ENABLED=1
```

For production, replace or extend `send_email_notification()` with a real SMTP library such as PHPMailer or Symfony Mailer. Keep SMTP host, username, password, port, and encryption settings outside Git, preferably in environment variables or an ignored local config file.

Prepared notification use cases:

- password reset
- visa expiry reminders
- visa renewal status changes
- missing document reminders
- insurance expiry reminders
- exit case status changes

Some reminder workflows still require scheduled jobs or staff-triggered actions to call the helper at the right time.

## Student Usage Guide

### Register

1. Open `register.php`.
2. Enter your student ID.
3. Enter your official AIU student email.
4. Fill in personal details.
5. Select nationality, school, program, and student type.
6. Enter a password and confirm it.
7. Submit the registration form.
8. After successful registration, open the login page.

### Log In

1. Open `login.php`.
2. Enter your email and password.
3. Select **Student** as the role.
4. Submit the form.
5. You will be redirected to the student dashboard.

### View Dashboard

The student dashboard shows:

- personal summary
- visa expiry information
- latest renewal application
- insurance status
- exit request status
- nationality records
- quick links to profile and visa renewal

### Manage Profile

Open:

```text
student/profile.php
```

You can view academic and personal details and upload/crop a profile image.

### Submit Visa Renewal

Open:

```text
student/visa_renewal.php
```

Submit a renewal application and upload supporting documents. After submitting, you can track the timeline and status.

### Upload Documents

Open:

```text
student/documents.php
```

Use this page to upload, view, edit, replace, or delete documents attached to your latest renewal application.

### Manage Insurance

Open:

```text
student/insurance.php
```

Review insurance policy information and submit claim-related information where supported.

### Submit Exit Request

Open:

```text
student/exit.php
```

Submit exit-related information and track status.

### Read Notifications

Open:

```text
student/notifications.php
```

Unread notification counts appear in the student navigation.

## Staff/Admin Usage Guide

### Log In

1. Open `login.php`.
2. Enter staff/admin email and password.
3. Select **Staff** as the role.
4. Submit the form.
5. You will be redirected to the staff dashboard.

### View Staff Dashboard

Open:

```text
staff/dashboard.php
```

The dashboard shows operational counts, reports, and expiry summaries.

### Manage Students

Open:

```text
staff/students.php
```

Use this page to search, view, and manage student information.

### Manage Visa Records

Open:

```text
staff/visa_management.php
```

Use this page for visa records and visa-related student data.

### Review Visa Renewals

Open:

```text
staff/visa_renewal.php
```

Staff can:

- search applications
- open details
- view documents
- add status timeline stages
- update application status
- approve and update visa records
- delete incorrect applications where necessary

### Manage Insurance

Open:

```text
staff/insurance_management.php
```

Staff can review insurance policies, renewals, and claims.

### Manage Exit Cases

Open:

```text
staff/exit_management.php
```

Staff can review and update student exit cases, clearances, and exit visa actions.

### Send Notifications

Open:

```text
staff/send_notification.php
```

Use this page to send notifications to students where supported by the database.

### View Reports

Open:

```text
staff/reports.php
```

Reports help staff monitor expiring visas, insurance deadlines, pending passport collection, and exit cases.

### View Audit Logs

Admin users can open:

```text
staff/audit_logs.php
```

Use this page to review important staff/admin actions and sensitive document downloads.

### Review Notifications

Staff/admin users can open:

```text
staff/notifications.php
```

This page shows notifications related to student submissions and staff activity. Staff-targeted unread counts require the updated `notifications.staff_id` column from `database/security_updates.sql`.

## Project Status

This is an academic/student management system project and is actively improved. The current version is suitable for local development, demonstrations, academic review, and portfolio presentation. Before real production use, it should receive a full security review, deployment review, and privacy review.

## Future Improvements

Realistic future improvements include:

- email notifications for renewal reminders and status updates
- SMTP-backed password reset emails
- advanced admin analytics
- configurable role permissions
- audit logs for staff/admin actions
- stronger CSRF coverage across all forms
- private document download controller
- better file preview for PDFs and images
- responsive UI refinements
- deployment guide for shared hosting or VPS
- environment-based database configuration
- API support for mobile or integration use cases
- activity history for each student
- configurable document requirements by nationality/program
- automatic reminders for visa and insurance expiry
- export reports to CSV/PDF

## Troubleshooting

### Database Connection Error

Check:

- MySQL is running in XAMPP
- database name is `issu`
- `includes/db.php` has the correct username/password
- `issu.sql` has been imported

### Localhost Page Not Found

Check:

- Apache is running
- the folder is inside `htdocs`
- the URL matches the folder name

Example:

```text
http://localhost/isu_visa_mangement_system/
```

### Missing Tables or Stored Procedures

For a fresh setup, import `database/schema.sql`. Optionally import `database/demo_data.sql`.

For an existing setup, import `database/security_updates.sql`.

Many pages depend on stored procedures, so importing only table definitions may not be enough.

### Password Reset Table Missing

Import:

```text
database/security_updates.sql
```

The password reset flow requires the `password_resets` table.

### Audit Log Table Missing

Import:

```text
database/security_updates.sql
```

The audit page requires the `audit_logs` table.

### Staff Notifications Not Showing as Expected

Import:

```text
database/security_updates.sql
```

Staff-targeted notifications require the `staff_id` column on the `notifications` table.

### Upload Folder Permission Issue

Make sure these folders exist and are writable:

```text
staff/uploads/profile
student/uploads/profile
uploads/visa_documents
uploads/documents
uploads/insurance_claims
uploads/profile
```

On Linux, update ownership/permissions for the web server user. On Windows/XAMPP, make sure files are not blocked by antivirus or folder permissions.

### File Upload Errors

Check:

- file type is allowed
- file size is within the limit
- upload folder exists
- PHP upload settings allow the file size

Relevant PHP settings:

- `upload_max_filesize`
- `post_max_size`
- `file_uploads`

### Session/Login Issues

Try:

- clearing browser cookies
- logging out and back in
- checking that the account status is `Active`
- selecting the correct role on the login page
- checking that the password column contains a valid password hash

### Student Cannot Access Staff Page

This is expected. Student pages and staff pages are role-protected.

### Staff Cannot Access Student Page

This is expected. Staff/admin users should use the staff portal.

### Forgot Password

Use:

```text
forgot-password.php
```

If email is not configured on XAMPP, check the PHP error log for the generated reset link.

## Deployment Guide

### XAMPP Local Testing

1. Start Apache and MySQL from XAMPP Control Panel.
2. Place the project in:

```text
C:\xampp\htdocs\isu_visa_mangement_system
```

3. Create database `issu`.
4. Import `database/schema.sql`.
5. Optional: import `database/demo_data.sql`.
6. Copy `includes/db.example.php` to `includes/db.local.php` if your credentials differ.
7. Open:

```text
http://localhost/isu_visa_mangement_system/
```

8. Test:

- registration
- login/logout
- forgot password
- student dashboard
- visa renewal submission
- document upload and secure view
- staff visa renewal review
- notification pages
- audit log page

### cPanel or Shared Hosting

1. Upload the project files to `public_html` or a subfolder.
2. Create a MySQL database and user from cPanel.
3. Import `database/schema.sql` with phpMyAdmin.
4. Optional: import `database/demo_data.sql`.
5. Create `includes/db.local.php` on the server with hosting credentials.
6. Make upload folders writable:

```text
staff/uploads/profile
student/uploads/profile
uploads/visa_documents
uploads/documents
uploads/insurance_claims
uploads/profile
```

7. Configure HTTPS.
8. Configure SMTP/email if using password reset emails.
9. Confirm `.env`, `db.local.php`, logs, and private uploads are not publicly downloadable or committed.

### VPS Deployment Notes

1. Install Apache or Nginx, PHP 8+, MySQL/MariaDB, and required PHP extensions.
2. Configure a virtual host pointing to the project directory.
3. Import `database/schema.sql`.
4. Create `includes/db.local.php` with production credentials.
5. Set upload folder ownership to the web server user.
6. Enable HTTPS with Certbot or another TLS provider.
7. Configure PHP upload limits according to your document size requirements.
8. Configure SMTP and set `ISU_EMAIL_ENABLED=1` only after mail is tested.
9. Review production security settings before exposing real student data.

## GitHub Notes

Do not commit:

- real uploaded visa PDFs
- passport scans
- profile photos
- insurance claim files
- private student documents
- real production database credentials
- local-only secrets

Upload folders are ignored by `.gitignore`, and `.gitkeep` files are used only to preserve folder structure.

## Author

Developed by **Chit Ko / chitko84**.

## License

License not specified yet.
