# QUICK PROJECT CONTEXT
**Project:** Next Academy (NEXTMAIN) - Educational CRM & LMS
**Architecture:** Procedural Server-Rendered PHP with Bootstrap 5 UI. No MVC framework.
**Database:** MySQL/MariaDB (via `mysqli`).
**Auth Model:** Native PHP `$_SESSION`. Centralized gateway at `auth/login.php`.
**Entry Points:**
- Public: `index.php`
- Admin: `admin/index.php`
- Student: `student/dashboard.php`
**Critical Security Rules:** Use prepared statements (`$stmt = $conn->prepare()`). Prevent IDOR in student portals by enforcing `student_id = $_SESSION['student_id']`.
**Critical Business Rules:** Attendance ignores Sundays. Hold status pauses attendance logic. Overdue fees require no payment in the current month.
**Key Configs:** `admin/config/database.php` (DB), `admin/config/auth.php` (Admin Auth), `student/student_auth.php` (Student Auth).

---

# AI DEVELOPMENT RULES
- **Read claude.md before coding.** Do not perform unnecessary repository-wide exploration.
- **Prefer targeted inspection.** Use the Change Map to locate exactly what you need.
- **Do not introduce MVC or ORMs.** Stick to procedural PHP and `mysqli`.
- **Reuse existing logic.** Include `database.php`, `auth.php`, and `student_auth.php` helpers.
- **Preserve existing visual design.** Copy existing Bootstrap 5 structure and CSS variables (e.g., `--purple-primary`).
- **Protect student isolation.** Never trust a `student_id` passed via GET/POST in student-facing AJAX; always use the session identity.
- **Check roles.** Respect `isSuperAdmin()` logic. Administrator role is highly restricted.
- **Never expose secrets.** Do not hardcode database credentials or tokens.

---

# CLAUDE'S FUTURE WORKFLOW
1. Read `claude.md` first.
2. Determine which module/feature is affected.
3. Use the **Change Map** / **Master File Index** below to identify the likely files.
4. Read **only** those relevant files.
5. Inspect additional files **ONLY** if the documented context is insufficient or contradictory.
6. **Do not rediscover information that is already reliably documented in claude.md. Prefer targeted file inspection over repository-wide exploration.**
7. Make the smallest safe change necessary using existing helpers and architecture.
8. If introducing a major architectural or database change, update `claude.md`.

---

# PROJECT STRUCTURE
Directories Claude should inspect for specific types of work:

- **`/` (Root):** Public website pages (`index.php`, `about.php`).
- **`/admin/`:** Core CRM interface, stats, list pages.
- **`/admin/ajax/`:** Admin endpoints. Returns JSON. Inspect when fixing admin actions (e.g., approving projects).
- **`/admin/config/`:** Global configurations, DB connection, and Auth helpers.
- **`/admin/includes/`:** Shared admin UI (sidebar, header, footer, ranking helper).
- **`/student/`:** Student portal interface.
- **`/student/ajax/`:** Student endpoints. Returns JSON.
- **`/student/includes/`:** Shared student UI.
- **`/auth/`:** Authentication gateway (`login.php`).
- **`/assets/`:** Public CSS/JS. Admin/student specific assets are in `admin/assets/` and `student/assets/`.

---

# CHANGE MAP (WHERE TO MAKE CHANGES)

**Student Management & Status**
→ `admin/students.php`, `admin/student_details.php`
→ `admin/student_hold_actions.php`, `admin/hold_students.php`
→ `students`, `student_hold_history` tables

**Attendance System**
→ `admin/attendance_report.php` (Admin calculation view)
→ `student/attendance.php` (Student check-in/out logic)
→ `student_attendance` table

**Finance & Overdue Students**
→ `admin/payments.php`, `admin/add_payment.php`
→ `admin/overdue_students_full_list.php`
→ `payments`, `students` (`total_fees`) tables

**Test & Quiz Execution**
→ `student/take_quiz.php`, `student/my_tests.php`
→ `student/ajax/fetch_quiz_questions.php`, `student/ajax/save_quiz_answer.php`, `student/ajax/submit_quiz.php`
→ `assigned_tests`, `student_answers`, `test_results` tables

**Project Verification**
→ `student/projects.php`
→ `student/ajax/project_actions.php`
→ `admin/project_verification.php`
→ `admin/ajax/manage_projects.php`
→ `student_projects` table

**Points & Ranking Calculation**
→ `admin/includes/ranking_helper.php` (`getMonthlyRanking()` function)
→ `admin/ranking.php`

**Event & Activity Management**
→ `admin/events.php`, `admin/event_details.php`
→ `student/activities.php`
→ `events`, `event_participants` tables

---

# MODULE → DATABASE → FILE MAP
| Module | Main Pages | Important AJAX Endpoints | Core Tables |
| :--- | :--- | :--- | :--- |
| **Auth** | `auth/login.php` | N/A | `admins`, `students` |
| **Students** | `admin/students.php` | N/A | `students`, `student_hold_history` |
| **Attendance** | `student/attendance.php` | N/A | `student_attendance` |
| **Courses** | `admin/courses.php` | `admin/ajax/manage_topics.php` | `courses`, `course_topics` |
| **Tests** | `admin/create_test.php` | `student/ajax/submit_quiz.php` | `tests`, `questions`, `assigned_tests`, `test_results` |
| **Projects** | `admin/project_verification.php`| `admin/ajax/manage_projects.php` | `student_projects` |
| **Finance** | `admin/payments.php` | N/A | `payments`, `students` |
| **Analytics**| `admin/index.php` | `admin/ajax/student_analytics_data.php` | All relevant tables |
| **Events** | `admin/events.php` | `admin/ajax/manage_events.php` | `events`, `event_participants` |

---

# AUTHENTICATION & AUTHORIZATION
**Admin Identity:**
- **File:** `auth/login.php`
- **Session Vars:** `$_SESSION['admin_id']`, `$_SESSION['admin_role']`
- **Auth Checker:** `requireLogin()` and `requireSuperAdmin()` in `admin/config/auth.php`
- **Roles:** `Super Admin` (Full access), `Admin` (Standard access), `Administrator` (Restricted to Task Manager).
- **Redirects:** Missing session -> `/auth/login.php`. 'Administrator' role forcibly redirected from `/admin/index.php` to `/admin/task_manager.php`.

**Student Identity:**
- **File:** `auth/login.php`
- **Session Vars:** `$_SESSION['student_id']`, `$_SESSION['student_code']`
- **Auth Checker:** `requireStudentLogin()` in `student/student_auth.php`
- **Eligibility:** Student `status` MUST be 'Active' AND `login_enabled` MUST be 1.
- **SECURITY RULE:** Do NOT trust POSTed `student_id` in student AJAX files. Always override/fetch with `$sid = $_SESSION['student_id']`.

---

# AJAX / API MAP
Important JSON endpoints logic:

| Endpoint | Method | Auth Level | Purpose | Related DB |
| :--- | :--- | :--- | :--- | :--- |
| `admin/ajax/manage_projects.php` | POST | Admin | Approves projects, optionally awards points | `student_projects` |
| `admin/ajax/manage_topics.php` | POST | Admin | CRUD for course curriculum topics | `course_topics` |
| `admin/ajax/student_analytics_data.php` | GET | Admin | Chart.js data generation for dashboards | Multiple tables |
| `student/ajax/fetch_quiz_questions.php` | GET | Student | Load MCQ questions for a test | `questions` |
| `student/ajax/save_quiz_answer.php` | POST | Student | Auto-saves individual option selection | `student_answers` |
| `student/ajax/submit_quiz.php` | POST | Student | Finalize test, calculate score | `test_results`, `assigned_tests` |
| `student/ajax/project_actions.php` | POST | Student | Upload project links/files | `student_projects` |

---

# DATABASE SCHEMA (VERIFIED)
*Note: Foreign keys are mostly logical/application-enforced rather than strict DB constraints.*

### `admins`
`id` (PK), `username`, `password` (hash), `role` (ENUM: 'Super Admin', 'Admin', 'Administrator', 'HR')

### `students`
`id` (PK), `student_code`, `status` (ENUM: 'Active', 'Hold', 'Completed', 'Dropped', 'Deleted'), `login_enabled` (TINYINT), `course_id`, `batch` (ENUM: 'Morning', 'Evening'), `total_fees`.
*Application Relations:* Parent to `payments`, `student_attendance`, `test_results`, etc.

### `payments`
`id` (PK), `student_id`, `amount_paid`, `payment_date`.
*Business Rule:* Calculates pending fees via `students.total_fees - SUM(amount_paid)`.

### `student_attendance`
`id` (PK), `student_id`, `attendance_date`, `status` (ENUM: 'Present', 'Absent', 'Holiday'), `check_in_time`, `check_out_time`.
*Business Rule:* Active students without a record on a working day are implicitly absent.

### `assigned_tests`
`id` (PK), `test_id`, `student_id`, `status` ('Pending', 'In Progress', 'Completed').

### `test_results`
`id` (PK), `student_id`, `assigned_test_id`, `score_percentage`, `points_awarded`.

### `events`
`id` (PK), `title`, `description`, `event_date`, `event_time`, `category`, `icon`, `status` (ENUM: 'Upcoming', 'Completed', 'Cancelled').

### `event_participants`
`id` (PK), `event_id`, `student_id`, `status` (ENUM: 'Participated', 'Not Participated'), `participated_at`, `marked_by`.

---

# REUSABLE FUNCTIONS / HELPERS
- **`executeQuery($conn, $sql, $types, $params)`**: In `admin/config/database.php`. Safe wrapper for prepared statements.
- **`getSingleResult($conn, $sql, $types, $params)`**: In `admin/config/database.php`. Fetch one row safely.
- **`safeRow($conn, $sql)`**: In `student/student_auth.php`. Fetches a single row.
- **`getMonthlyRanking($conn, $start, $end)`**: In `admin/includes/ranking_helper.php`. Dynamically calculates leaderboard points. Reuse whenever displaying ranks.
- **`isSuperAdmin()`**: In `admin/config/auth.php`. Returns boolean based on session role.

---

# UI / DESIGN SYSTEM
- **Framework:** Bootstrap 5.3.
- **Aesthetic:** "Dark Mode SaaS". Flat, dark backgrounds with purple/neon accents.
- **CSS Architecture:** Heavy reliance on `:root` variables (`--bg-card`, `--purple-primary`, `--text-1`).
- **Components:** Replicate classes like `glass-card`, `alert-block`, and `badge-pill` when building new UI to ensure visual consistency. Do not introduce new CSS frameworks.

---

# BUSINESS RULES (MANDATORY PRESERVATION)
1. **Attendance Calculation:** Sundays (`date('N') == 7`) are EXCLUDED from working day calculations. 'Holiday' status counts identically to 'Present' for percentage formulas so students aren't penalized.
2. **Student Hold Status:** If `students.status = 'Hold'`, attendance tracking is paused. They do not negatively impact batch attendance rates.
3. **Overdue Fees:** A student is ONLY "Overdue" if they have `pending_fees > 0` AND no payment record exists matching `YEAR(CURRENT_DATE)` and `MONTH(CURRENT_DATE)`.
4. **Quiz Limits:** Once `assigned_tests.status` = 'Completed', the student cannot retake it without an admin assigning a new record.
5. **Dynamic Ranking:** Leaderboard points are NOT stored as a single total integer. They are calculated dynamically by aggregating `test_results`, `student_projects`, and `student_manual_points`.
6. **Event Participation & Attendance:** Event participation is explicitly marked in `event_participants`. If missing, it falls back to the student's daily attendance record for past, non-Sunday events. Attendance and participation are tracked and displayed independently in `student/activities.php`.

---

# SECURITY RULES
- **IMPLEMENTED:** Prepared statements via `mysqli` (mostly), `password_hash()` for passwords, role-based session checking, isolated student queries.
- **RULE:** Never use `$_GET['student_id']` inside `/student/` files to perform DB writes. Always define `$sid = $_SESSION['student_id']`.
- **TECHNICAL DEBT:** Procedural architecture can lead to missed auth checks if `requireLogin()` is accidentally omitted from the top of a new file. Always include it.

---

# WORKFLOWS

**Quiz Execution Flow:**
`admin/assign_test.php` (Creates `assigned_tests` record)
↓
`student/my_tests.php` (Student views Pending test)
↓
`student/take_quiz.php` (Student UI)
↓
`student/ajax/save_quiz_answer.php` (Autosaves during test)
↓
`student/ajax/submit_quiz.php` (Calculates score % -> Creates `test_results` -> Marks completed)

**Student Hold Flow:**
`admin/student_hold_actions.php`
↓
Updates `students.status` to 'Hold'
↓
Inserts record into `student_hold_history`
↓
Pauses `attendance_report.php` denominators.

---

# PUBLIC WEBSITE & EVENT SYSTEM
- **Public Pages:** `index.php`, `about.php`, `courses.php`, `contact.php`.
- **Activity Calendar (Public):** Located on `index.php` hero slider. This is 100% **hardcoded HTML**. There are no database tables currently powering this public calendar.

### Event Management System (Internal)
- **Admin Management:** Admins create individual event records in the `events` table via `admin/events.php`.
- **Student Portal:** Students track their upcoming events and activity history in `student/activities.php`.
- **Status rules:** Events are UNIQUE PER MONTH. History remains indefinitely.

---

# MASTER FILE INDEX
*Actionable index of primary files.*

## Core & Auth
- `admin/config/database.php` → Connection and SQL wrappers.
- `admin/config/auth.php` → Admin session helpers.
- `student/student_auth.php` → Student session helpers.
- `auth/login.php` → Master login gateway.

## Admin Features
- `admin/index.php` → Dashboard stats.
- `admin/students.php` → Registration & List.
- `admin/attendance_report.php` → Batch percentage calculations.
- `admin/payments.php` → Transactions list.
- `admin/overdue_students_full_list.php` → Overdue calculation logic.
- `admin/ranking.php` → Global leaderboard view.
- `admin/events.php` → Event & activity management.

## Student Features
- `student/attendance.php` → Daily check-in/out form.
- `student/dashboard.php` → Personal stats overview.
- `student/projects.php` → Submission UI.
- `student/my_tests.php` → Test list.
- `student/activities.php` → Event activity history and upcoming events.

---

## Documentation Audit Notes
- **Verified:** Actual database relationships, authentication pathways, exact overdue logic (`YEAR/MONTH` matching), attendance inference logic, hardcoded state of the Activity Calendar, exact AJAX endpoints for quizzes and projects.
- **Corrected:** Removed assumptions about MVC frameworks and strict DB foreign keys (many are application-enforced). Clarified that ranking points are calculated dynamically, not stored in a master column.
- **Date of Audit:** September 17, 2026.

