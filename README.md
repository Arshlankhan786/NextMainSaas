<div align="center">

# Next Academy SaaS

### Education Management • CRM • LMS • Student Operations

A PHP & MySQL based academy management platform that brings **student management, attendance, fees, assessments, projects, events, rankings, inquiries, and student self-service** into one system.

<p>
  <a href="https://github.com/Arshlankhan786/NextMainSaas">
    <img src="https://img.shields.io/badge/Repository-NextMainSaas-181717?style=for-the-badge&logo=github" alt="GitHub Repository">
  </a>
  <img src="https://img.shields.io/badge/PHP-7%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL%2FMariaDB-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL / MariaDB">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap">
  <img src="https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=000" alt="JavaScript">
</p>

</div>

---

## Overview

**Next Academy SaaS** is an institute-management application designed for training academies and educational businesses.

The platform combines three major experiences:

1. **Public Academy Website** — courses, academy information, gallery, contact/inquiry capture, and admission-oriented pages.
2. **Admin / CRM Panel** — student lifecycle, course operations, attendance, fees, tasks, assessments, projects, events, inquiries, reports, and rankings.
3. **Student Portal / LMS** — dashboard, attendance, tests, quizzes, typing practice, projects, activities, notifications, payments, receipts, and profile management.

The repository uses a **server-rendered procedural PHP architecture** with shared includes, session-based authentication, MySQL/MariaDB access through `mysqli`, and AJAX endpoints for interactive operations.

---

## Core Modules

### 🌐 Public Website

The public-facing website contains:

- Home page
- About page
- Course catalog
- Digital Marketing course pages
- Gallery
- Contact page
- Admission / inquiry submission
- Shared navbar, footer, favicon and splash-screen components
- SEO support files such as `robots.txt` and `sitemap.xml`

Main entry points:

```text
index.php
about.php
courses.php
contact.php
gallery.php
digital-marketing.php
digital-marketing-dark.php
inquiry_submit.php
```

---

### 🧑‍💼 Admin Panel

The `/admin` application works as the main **CRM + institute operations dashboard**.

#### Student Management

- Student registration and profile management
- Student detail pages
- Course assignment
- Morning / Evening batch support
- Student groups
- Active / Hold / Completed / Dropped / Deleted status handling
- Student hold history and actions
- Course expiry tracking
- Student analytics

Relevant files include:

```text
admin/students.php
admin/student_details.php
admin/student_groups.php
admin/group_details.php
admin/hold_students.php
admin/past_students.php
admin/students_course_expiry.php
admin/student_analytics.php
```

#### Attendance Management

- Daily student attendance
- Check-in / check-out time tracking
- Attendance reports
- Batch and operational reporting
- Holiday status support
- Attendance-aware student analytics

Holiday support is implemented through:

```text
migrations/001_add_holiday_status.sql
admin/ajax/declare_holiday.php
admin/attendance_report.php
```

#### Courses & Curriculum

- Course categories
- Course management
- Course fees by duration
- Course topic management
- Group topic management
- AJAX-powered course lookups

```text
admin/categories.php
admin/courses.php
admin/ajax/get_course.php
admin/ajax/get_course_fees.php
admin/ajax/get_courses_by_category.php
admin/ajax/manage_course_topics.php
admin/ajax/manage_group_topics.php
```

#### Fees & Finance

- Payment entry
- Payment history
- Fee tracking
- Pending fee calculations
- Overdue student tracking
- Expense management
- Receipt generation / viewing
- Paid-student reporting

```text
admin/payments.php
admin/add_payment.php
admin/expenses.php
admin/receipt.php
admin/overdue_students_full_list.php
admin/paid_students_full_list.php
```

#### Tests & Assessments

- Create tests
- Assign tests to students
- Remove / unassign tests
- MCQ question engine
- Student answer tracking
- Result calculation
- Typing competition results

```text
admin/create_test.php
admin/assign_test.php
admin/typing_results.php
admin/ajax/fetch_test_assignments.php
admin/ajax/delete_test.php
admin/ajax/unassign_test.php
```

#### Tasks & Projects

- Task management
- Task assignment
- Completion tracking
- Manual task cron support
- Project verification
- Project actions and analytics
- Manual points awarding

```text
admin/task_manager.php
admin/completed_tasks.php
admin/manual_task_cron.php
admin/project_verification.php
admin/student_project_analytics.php
admin/ajax/manage_projects.php
admin/ajax/manage_manual_points.php
```

#### Events & Activities

- Create academy events
- Upcoming / completed / cancelled statuses
- Event categories
- Event icon selection
- Monthly event filtering
- Event details
- Student participation tracking

```text
admin/events.php
admin/event_details.php
admin/ajax/manage_events.php
```

#### Rankings & Analytics

The application includes dynamic student ranking based on activity such as:

- Test results
- Project achievements
- Manual points
- Attendance-related point logic

The ranking helper is centralized in:

```text
admin/includes/ranking_helper.php
```

Analytics pages also use **Chart.js** for visual reporting.

---

## 🎓 Student Portal

The `/student` application gives enrolled students a self-service LMS-style experience.

### Student Dashboard

The dashboard brings together:

- Attendance overview
- Current activity
- Projects and progress
- Pending fees
- Ranking / points information
- Attendance history
- Project updates and timelines

```text
student/dashboard.php
```

### Attendance

Students can:

- Check in
- Check out
- View attendance history
- View attendance statistics
- Review attendance charts

```text
student/attendance.php
student/attendance_action.php
student/ajax/get_attendance.php
```

### Tests & Quizzes

Students can:

- View assigned tests
- Start quizzes
- Load MCQ questions dynamically
- Auto-save answers
- Submit quizzes
- View results
- Track progress

```text
student/my_tests.php
student/take_quiz.php
student/quiz_result.php
student/ajax/fetch_quiz_questions.php
student/ajax/save_quiz_answer.php
student/ajax/save_quiz_answers_batch.php
student/ajax/submit_quiz.php
```

### Typing Practice

The portal includes a dedicated typing competition interface:

```text
student/typing_competition.php
student/ajax/save_typing_result.php
student/typing_assets/
student/ajax/typing_assets/
```

### Projects

Students can submit project work and maintain progress updates.

```text
student/projects.php
student/ajax/project_actions.php
```

### Activities & Events

Students can view:

- Upcoming academy activities
- Event dates and times
- Participation history
- Attendance status for past activities
- Monthly activity summaries

```text
student/activities.php
```

### Payments & Receipts

Students can access:

- Total course fees
- Total paid
- Pending amount
- Payment progress
- Transaction history
- Receipt viewing
- Receipt download

```text
student/payments.php
student/receipts.php
student/receipt_view.php
student/receipt_download.php
```

### Other Student Features

```text
student/profile.php
student/notifications.php
student/task_manager.php
```

---

## 🔐 Authentication & Roles

The application uses **PHP sessions** for authentication.

There is a shared login gateway:

```text
auth/login.php
```

### Admin Roles

The codebase supports role-aware administration including:

- Super Admin
- Admin
- Administrator
- HR

Role helpers are handled in:

```text
admin/config/auth.php
```

Examples include:

- `requireLogin()`
- `requireSuperAdmin()`
- `requireHRAccess()`
- `isSuperAdmin()`
- `isHR()`

### Student Authentication

Student authentication is isolated through:

```text
student/config/student_auth.php
```

The student session uses the authenticated student's session ID rather than trusting a student ID supplied by the browser for protected operations.

---

## 🏗️ Architecture

### Application Structure

```text
NextMainSaas/
│
├── index.php
├── about.php
├── courses.php
├── contact.php
├── gallery.php
├── digital-marketing.php
├── digital-marketing-dark.php
├── auth/
│   └── login.php
│
├── admin/
│   ├── config/
│   ├── includes/
│   ├── ajax/
│   ├── assets/
│   └── *.php
│
├── student/
│   ├── config/
│   ├── includes/
│   ├── ajax/
│   ├── assets/
│   ├── quiz_assets/
│   ├── typing_assets/
│   └── *.php
│
├── includes/
│   ├── navbar.php
│   ├── footer.php
│   └── splash-screen.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── migrations/
├── sql/
├── .htaccess
├── robots.txt
└── sitemap.xml
```

### Request Model

The project is primarily **server rendered**:

```text
Browser
   ↓
PHP Page
   ↓
Session / Auth Check
   ↓
MySQL / MariaDB
   ↓
HTML Response
```

For interactive features:

```text
Browser JavaScript
        ↓
      AJAX
        ↓
PHP Endpoint
        ↓
MySQL / MariaDB
        ↓
   JSON Response
        ↓
UI Update
```

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| Backend | Procedural PHP |
| Database | MySQL / MariaDB |
| Database API | `mysqli` |
| Frontend | HTML5, CSS3, JavaScript |
| UI Framework | Bootstrap 5.3 |
| Charts | Chart.js |
| Icons | Font Awesome |
| Authentication | PHP Sessions |
| Passwords | PHP `password_hash()` / `password_verify()` |
| Hosting Target | Apache / PHP shared hosting |
| Local Development | XAMPP / Apache + MySQL |

No MVC framework is required by the current application architecture.

---

## 🗄️ Database

The application uses a relational MySQL/MariaDB database.

The main business entities include tables for:

- Admin accounts
- Students
- Categories
- Courses
- Course fees
- Payments
- Attendance
- Tests
- Test assignments
- Questions
- Student answers
- Test results
- Projects
- Project activity
- Tasks
- Events
- Event participants
- Inquiries
- Notifications
- Student groups
- Manual points
- Hold history

### Important Business Rules

#### Attendance

- Sundays are excluded from working-day attendance calculations.
- Holiday attendance is treated as non-penalizing in attendance calculations.
- Students on **Hold** are excluded from normal attendance denominators.

#### Fees

Pending fees are calculated from:

```text
Total Course Fees - Sum of Payments
```

Overdue logic is based on outstanding balance plus the absence of a payment record for the relevant current month.

#### Quiz Completion

Once an assigned test is marked **Completed**, the student cannot simply retake that same assignment.

#### Ranking

Ranking points are calculated dynamically from multiple activity sources rather than relying on one permanent total-points field.

---

## ⚙️ Database Configuration

Database configuration is centralized in:

```text
admin/config/database.php
```

Credentials are separated into:

```text
admin/config/db_credentials.php
```

The database layer:

- Creates one MySQL connection per request
- Uses `utf8mb4`
- Sets timezone to **Asia/Kolkata**
- Provides prepared-statement helpers
- Closes the connection at shutdown
- Contains utility functions for IST date/time formatting

### Recommended Credential Setup

Keep real credentials outside publicly visible source control.

Example structure:

```php
<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'your_database_name');
```

Do **not** commit production passwords to a public repository.

---

## 🚀 Local Installation

### 1. Requirements

Install:

- XAMPP or another Apache + PHP environment
- MySQL or MariaDB
- PHP with the `mysqli` extension
- A modern browser

### 2. Clone the Repository

```bash
git clone https://github.com/Arshlankhan786/NextMainSaas.git
cd NextMainSaas
```

### 3. Place the Project

For XAMPP:

```text
C:\xampp\htdocs\NextMainSaas
```

### 4. Create the Database

Create a MySQL/MariaDB database for the application.

Then import your project database SQL dump through **phpMyAdmin** or the MySQL CLI.

The exact dump/schema files should be treated as the source of truth for the deployed database version.

### 5. Configure Database Credentials

Update:

```text
admin/config/db_credentials.php
```

with your local database credentials.

### 6. Run the Application

Start:

- Apache
- MySQL

Then open:

```text
http://localhost/NextMainSaas/
```

Login gateway:

```text
http://localhost/NextMainSaas/auth/login.php
```

---

## 🔄 Migrations

Database changes are tracked under:

```text
migrations/
```

For example, holiday attendance support is introduced through:

```text
migrations/001_add_holiday_status.sql
```

The repository also contains SQL helper scripts under:

```text
sql/
```

Before running a migration in production:

1. Back up the database.
2. Verify the migration against the current schema.
3. Test it in staging/local environment.
4. Apply it to production.

---

## 🔌 AJAX Endpoints

The application uses small PHP endpoints for dynamic operations.

### Admin AJAX

Examples:

```text
admin/ajax/get_course.php
admin/ajax/get_course_fees.php
admin/ajax/get_courses_by_category.php
admin/ajax/get_student_attendance.php
admin/ajax/manage_projects.php
admin/ajax/manage_events.php
admin/ajax/manage_topics.php
admin/ajax/manage_manual_points.php
admin/ajax/student_analytics_data.php
```

### Student AJAX

Examples:

```text
student/ajax/fetch_quiz_questions.php
student/ajax/save_quiz_answer.php
student/ajax/save_quiz_answers_batch.php
student/ajax/submit_quiz.php
student/ajax/project_actions.php
student/ajax/get_attendance.php
student/ajax/save_typing_result.php
```

---

## 🔒 Security Practices

The current codebase includes several security-oriented patterns:

- Prepared statements through `mysqli`
- Password hashing and verification
- Session-based authentication
- Role-based access control
- Separate admin and student auth helpers
- Escaping output with `htmlspecialchars()`
- Student-side authorization based on the authenticated session
- Database credentials separated from the main connection file
- Generic database failure messages instead of exposing connection details
- Explicit server-side permission checks for sensitive admin features

### Security Checklist for Production

Before production deployment, also verify:

- Production database credentials are not exposed publicly.
- Debug scripts and diagnostic pages are restricted or removed.
- File uploads are validated for extension, MIME type, size, and storage path.
- HTTPS is enabled.
- Session cookies use secure production settings.
- CSRF protection is added to sensitive state-changing forms where required.
- `.env` / credential files are excluded from version control when applicable.
- Database backups and restore procedures are tested.

---

## 📦 Deployment

The project is suitable for traditional PHP hosting such as:

- cPanel hosting
- Apache shared hosting
- Hostinger-style PHP hosting
- VPS / LAMP servers

General deployment flow:

```text
1. Upload project files
2. Create production database
3. Import schema/data
4. Configure database credentials
5. Verify PHP version/extensions
6. Set file permissions for upload directories
7. Verify Apache / .htaccess rules
8. Test login
9. Test admin operations
10. Test student portal
11. Test email / receipt / upload functionality
```

---

## 📊 Performance Notes

The application is designed around a **single database connection per PHP request** rather than persistent connections.

The database helper also centralizes prepared query execution.

For higher traffic, production environments should additionally consider:

- Query/index review
- PHP OPcache
- Browser and server caching
- Static asset caching
- Image compression
- Rate limiting on public forms
- Monitoring for slow queries
- Proper shared-host connection limits
- Background processing for heavy reports or notifications

---

## 🧑‍💻 Development Guidelines

When extending the project:

### Follow the current architecture

Use the existing procedural PHP structure rather than introducing a new framework into only one feature.

### Reuse shared authentication

Admin pages should use the existing admin auth helpers.

Student pages should use the existing student auth helpers.

### Use prepared statements

Prefer prepared statements for database queries involving user input.

### Preserve existing business logic

Important rules around:

- Attendance
- Hold students
- Fee calculation
- Test completion
- Ranking
- Event participation

should remain consistent across admin and student views.

### Reuse the existing UI system

The project already includes shared Bootstrap-based layouts, CSS variables, cards, tables, badges, forms, and responsive patterns.

---

## 🗂️ Important Files

| File | Purpose |
|---|---|
| `auth/login.php` | Shared login gateway |
| `admin/index.php` | Admin dashboard |
| `admin/config/database.php` | Database connection + query helpers |
| `admin/config/auth.php` | Admin authentication / roles |
| `admin/students.php` | Student management |
| `admin/courses.php` | Course management |
| `admin/attendance_report.php` | Attendance reporting |
| `admin/payments.php` | Payment management |
| `admin/task_manager.php` | Task management |
| `admin/project_verification.php` | Project review |
| `admin/events.php` | Event management |
| `admin/ranking.php` | Ranking / leaderboard |
| `student/config/student_auth.php` | Student authentication |
| `student/dashboard.php` | Student dashboard |
| `student/attendance.php` | Student attendance |
| `student/my_tests.php` | Assigned tests |
| `student/take_quiz.php` | Quiz interface |
| `student/projects.php` | Student projects |
| `student/activities.php` | Events / activities |
| `student/payments.php` | Student payment history |
| `migrations/` | Database migrations |
| `sql/` | SQL utilities |

---

## 🧭 Main Workflows

### Student Login

```text
auth/login.php
      ↓
Validate student credentials
      ↓
Create student session
      ↓
student/dashboard.php
```

### Admin Login

```text
auth/login.php
      ↓
Validate admin credentials
      ↓
Create admin session
      ↓
Role-based redirect
      ↓
Admin dashboard / Administrator task area
```

### Quiz Flow

```text
Admin creates test
      ↓
Admin assigns test
      ↓
Student sees assigned test
      ↓
Student starts quiz
      ↓
Questions loaded through AJAX
      ↓
Answers auto-saved
      ↓
Quiz submitted
      ↓
Score + result stored
      ↓
Student views result
```

### Project Flow

```text
Student opens project
      ↓
Submits project / work update
      ↓
Admin reviews submission
      ↓
Admin verifies / awards points
      ↓
Ranking reflects activity
```

### Event Flow

```text
Admin creates event
      ↓
Event becomes Upcoming
      ↓
Student sees activity
      ↓
Admin records participation
      ↓
Event becomes Completed / Cancelled
      ↓
History remains available
```

---

## 🎨 UI & Design

The application uses a Bootstrap-based custom design system with:

- Responsive layouts
- Dashboard cards
- Tables
- Modal forms
- Status badges
- Dark student-portal styling
- Analytics charts
- Mobile-friendly components
- Font Awesome iconography

The project avoids requiring a frontend build system; CSS and JavaScript are served directly with the PHP application.

---

## 📚 Project Documentation

Additional project documentation already included in the repository:

```text
project_overview.txt
implementation_plan.md
claude.md
REVIEW.md
admin/README.md
```

These files provide deeper implementation notes, optimization information, and development context.

---

## ⚠️ Production Notes

This repository is a working application codebase, not a generic starter template.

Before offering it as a multi-institute SaaS product, additional SaaS-layer work should be planned around:

- Tenant / institute isolation
- Per-tenant database strategy
- Subscription plans
- Billing
- Tenant onboarding
- Central platform administration
- Custom domains / subdomains
- Tenant-level branding
- Feature flags
- Audit logs
- Backup / restore strategy
- Central monitoring
- API rate limits
- Multi-branch data isolation

The current application should therefore be treated as the **academy management core** on top of which full multi-tenant SaaS infrastructure can be added.

---

## 👨‍💻 Author

Developed by **[Arshlankhan786](https://github.com/Arshlankhan786)**.

Repository:

**[github.com/Arshlankhan786/NextMainSaas](https://github.com/Arshlankhan786/NextMainSaas)**

---

<div align="center">

### Next Academy SaaS

**One platform for academy operations, student learning, and institute management.**

</div>
