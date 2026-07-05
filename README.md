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

### Authentication and Session Protection

Protected student pages load `student/header.php`.

Protected staff/admin pages load `staff/header.php`.

The shared helper file `includes/functions.php` provides session helpers used by these headers. Protected pages require the correct role:

- student pages require `student`
- staff pages require `staff` or `admin`

This prevents student pages from being opened by staff accounts and staff pages from being opened by student accounts.

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

Stores the MySQL connection settings. The default local configuration is:

- host: `localhost`
- username: `root`
- password: empty local password
- database: `issu`

Do not put production passwords or private credentials in this file before committing.

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

### 6. Import the SQL File

In phpMyAdmin:

1. Select the `issu` database.
2. Click **Import**.
3. Choose `issu.sql` from the project root.
4. Click **Go**.

The SQL file includes tables, constraints, stored procedures, triggers, indexes, and sample records.

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

The main SQL file is:

```text
issu.sql
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

## Project Status

This is an academic/student management system project and is actively improved. The current version is suitable for local development, demonstrations, academic review, and portfolio presentation. Before real production use, it should receive a full security review, deployment review, and privacy review.

## Future Improvements

Realistic future improvements include:

- email notifications for renewal reminders and status updates
- password reset flow
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

Import `issu.sql` again into a clean `issu` database. Many pages depend on stored procedures, so importing only table definitions may not be enough.

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

A self-service password reset page is not currently implemented. Staff/admin intervention or database-level password hash update may be required in a local academic setup.

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
