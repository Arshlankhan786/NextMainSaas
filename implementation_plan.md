# Next Academy — Revised Optimization Plan (v2)

## Objective

Target **≤ 300–350 DB connections/hour** for 50 simultaneous students + normal admin + public traffic.

---

## 1. EXACT REQUEST/CONNECTION TRACE — CURRENT STATE

Every PHP request that includes `database.php` creates exactly **1 MySQL connection** (via `new mysqli()`) plus **1 additional query** (`SET time_zone = '+05:30'`). The `require_once` ensures this happens exactly once per PHP process.

### Full quiz lifecycle for ONE student (20-question test):

```
STEP 1: Student opens my_tests.php
  Browser → GET my_tests.php
    → student/includes/header.php
      → require_once database.php  ← 1 DB CONNECTION
        → new mysqli()             ← counted against max_connections_per_hour
        → SET time_zone            ← query 1
        → SET charset              ← (set_charset is not a query, it's a C-level call)
      → require_once student_auth.php  ← no DB, session only
      → COUNT(*) admin_tasks      ← query 2 (in header.php line 17)
      → COUNT(*) student_projects ← query 3 (in header.php line 28)
    → SELECT assigned_tests JOIN tests ← query 4
    → student/includes/footer.php  ← NO DB query (student footer is clean)
  TOTAL: 1 CONNECTION, ~4 queries

STEP 2: Student clicks "Start" → loads take_quiz.php
  Browser → GET take_quiz.php
    → require_once database.php    ← 1 DB CONNECTION
    → SET time_zone                ← query 1
    → SELECT at.* JOIN tests       ← query 2 (validate assignment)
    → SELECT COUNT(*) questions    ← query 3 (count questions)
    → UPDATE assigned_tests SET status='In Progress' ← query 4 (conditional)
  TOTAL: 1 CONNECTION, ~4 queries

STEP 3: Quiz JS loads questions (AJAX)
  JS → fetch('ajax/fetch_quiz_questions.php')
    → require_once database.php    ← 1 DB CONNECTION
    → SET time_zone                ← query 1
    → SELECT at.* JOIN tests       ← query 2 (verify assignment)
    → SELECT questions             ← query 3 (all questions at once — good!)
    → SELECT student_answers       ← query 4 (answered indices)
  TOTAL: 1 CONNECTION, ~4 queries
  NOTE: All 20 questions loaded in ONE request — no N+1, no repeat fetching ✓

STEP 4: Student answers question 1 (AJAX)
  JS → fetch('ajax/save_quiz_answer.php') [POST]
    → require_once database.php    ← 1 DB CONNECTION
    → SET time_zone                ← query 1
    → SELECT assigned_tests        ← query 2 (verify ownership)
    → UPDATE assigned_tests SET current_question_index ← query 3
    → SELECT correct_option        ← query 4 (check answer)
    → INSERT INTO student_answers ON DUPLICATE KEY ← query 5
  TOTAL: 1 CONNECTION, ~5 queries

STEP 5–23: Student answers questions 2–20 (AJAX × 19)
  Same as Step 4, repeated 19 times.
  TOTAL: 19 CONNECTIONS, ~95 queries

STEP 24: Timer expires on a question (no answer selected)
  JS → saveAnswer(questionId, null, timePerQuestion)
  → selectedOption is null
    → query 2 (verify)
    → query 3 (update index)
    → [query 4 is SKIPPED because selectedOption is null]
    → query 5 (INSERT with null selected_option)
  TOTAL: 1 CONNECTION, ~4 queries

STEP 25: Quiz ends → submitQuiz() fires (AJAX)
  JS → fetch('ajax/submit_quiz.php') [POST]
    → require_once database.php    ← 1 DB CONNECTION
    → SET time_zone                ← query 1
    → SELECT assigned_tests        ← query 2 (verify ownership)
    → [status != Completed, so no early exit]
    → SELECT COUNT(*) questions    ← query 3
    → SELECT COUNT(*) student_answers WHERE is_correct=1 ← query 4
    → SELECT SUM(time_taken_seconds) ← query 5
    → UPDATE assigned_tests SET status='Completed' ← query 6
    → INSERT INTO test_results ON DUPLICATE KEY ← query 7
  TOTAL: 1 CONNECTION, ~7 queries

STEP 26: sendBeacon on beforeunload (potential)
  This fires ONLY if !isSubmitting && currentIndex < questions.length.
  After submitQuiz sets isSubmitting=true, this will NOT fire.
  HOWEVER: if the user closes the browser mid-quiz, it WILL fire.
  → save_quiz_answer.php with question_id=0 (FormData has no question_id)
    → query 2 (verify)
    → query 3 (update index)
    → exits at line 59 (question_id <= 0)
  TOTAL: 0-1 CONNECTION (only on abnormal exit)

STEP 27: Student views quiz_result.php (post-quiz)
  Browser → GET quiz_result.php
    → student/includes/header.php ← 1 DB CONNECTION
    → header queries              ← ~3 queries
    → SELECT test_results JOIN tests JOIN assigned_tests ← query 4
    → SELECT questions LEFT JOIN student_answers ← query 5
  TOTAL: 1 CONNECTION, ~5 queries
```

### CURRENT TOTAL per student (20-question quiz, normal flow):

| Step | Description | DB Connections |
|------|-------------|---------------|
| my_tests.php page load | 1 | 1 |
| take_quiz.php page load | 1 | 1 |
| fetch_quiz_questions.php (AJAX) | 1 | 1 |
| save_quiz_answer.php × 20 (AJAX) | 20 | 20 |
| submit_quiz.php (AJAX) | 1 | 1 |
| quiz_result.php page load | 1 | 1 |
| sendBeacon (abnormal) | 0–1 | 0–1 |
| **TOTAL** | **25–26** | **25–26** |

### Admin connections per hour (assign_test.php open):

| Source | Interval | Connections/hour |
|--------|----------|-----------------|
| Page load | Once | 1 |
| Assignment poll | Every 120s | 30 |
| **Total** | | **31** |

### Admin connections per hour (task_manager.php open):

| Source | Interval | Connections/hour |
|--------|----------|-----------------|
| Page load | Once | 1 |
| loadTasks poll | Every 300s | 12 |
| loadAnalytics poll | Every 300s | 12 |
| Visibility resume (loadTasks + loadAnalytics) | ~2 events/hr | 4 |
| **Total** | | **~29** |

### Public page connections (per page load):

| Page | Own DB include | Footer DB query | Total connections |
|------|---------------|----------------|-------------------|
| index.php | Yes | `require_once` (no duplicate) | 1 |
| about.php | Yes | `require_once` (no duplicate) | 1 |
| courses.php | Yes | `require_once` (no duplicate) | 1 |
| contact.php | Yes | `require_once` (no duplicate) | 1 |
| gallery.php | **No** | `require_once` **(CREATES connection)** | **1** |

> [!IMPORTANT]
> **Key finding:** The footer's `require_once` on gallery.php is the ONLY public page that creates a DB connection unnecessarily. For index/about/courses/contact, the footer's `require_once` does NOT create a second connection (PHP `require_once` prevents re-execution). So the footer issue is less severe than initially estimated — it only adds 1 wasted connection per gallery.php load.
>
> However, the footer still runs a DB **query** (`SELECT name FROM courses`) on every public page load, even if the connection was already established. This is an extra query but NOT an extra connection.

---

## 2. REVISED OPTIMIZATION STRATEGY

### Strategy A: Aggressive Answer Batching (BIGGEST IMPACT)

Instead of sending each answer individually, **batch ALL answers and send only twice during the quiz**:

**New approach:**
1. When student answers a question → store in **JavaScript memory queue** + **localStorage** (immediate)
2. **Never send individual answers to server during the quiz**
3. Flush the queue to server at exactly these points:
   - **Mid-quiz checkpoint**: After question 10 (for a 20-question test) — i.e., at the halfway mark
   - **Pre-submit flush**: All remaining answers sent with the submit request itself
4. **Fallback saves** (in case of browser crash/network loss):
   - On `visibilitychange` (hidden) — flush any unsaved answers
   - On `beforeunload` — sendBeacon with unsaved answers
5. **Submit endpoint** receives both unsaved answers AND the submit signal in one request

**This reduces save_quiz_answer connections from 20 to 1–2.**

### Strategy B: Combine Submit + Final Save

Modify `submit_quiz.php` to accept remaining unsaved answers in the same POST. This means:
- If all answers were already synced (via mid-quiz checkpoint + visibility save), submit is 1 connection
- If some answers are still only in localStorage, they're sent along with the submit — still just 1 connection

### Strategy C: Reduce submit_quiz.php Queries (7 → 4)

Combine the 3 separate SELECT queries:
```sql
-- CURRENT: 3 separate queries
SELECT COUNT(*) FROM questions WHERE test_id = ?
SELECT COUNT(*) FROM student_answers WHERE assigned_test_id = ? AND is_correct = 1
SELECT SUM(time_taken_seconds) FROM student_answers WHERE assigned_test_id = ?

-- PROPOSED: 1 combined query
SELECT
    (SELECT COUNT(*) FROM questions WHERE test_id = ?) AS total_questions,
    (SELECT COUNT(*) FROM student_answers WHERE assigned_test_id = ? AND is_correct = 1) AS correct_count,
    (SELECT COALESCE(SUM(time_taken_seconds), 0) FROM student_answers WHERE assigned_test_id = ?) AS total_time
```

This reduces submit from 7 queries to **4 queries** (1 verify + 1 combined stats + 1 update + 1 upsert).

### Strategy D: File-Based Footer Cache

Cache popular courses in a small PHP file:
- When admin creates/updates/deletes a course, regenerate the cache file
- Footer reads from cache file (no DB query needed)
- Cache file is just a PHP array: `<?php return ['Course 1', 'Course 2', ...];`
- If cache file doesn't exist or is older than 24 hours, regenerate from DB (only once)
- Gallery.php (and any page without DB) will NOT open a DB connection at all

### Strategy E: Admin Polling Optimization

- **assign_test.php**: Increase interval from 120s to 300s (5 min)
- **task_manager.php**: Combine loadTasks + loadAnalytics into ONE request via new `get_focus_dashboard` action
- Add `isLoading` guard to prevent overlapping requests

---

## 3. LOCALSTORAGE SAFETY ARCHITECTURE

```javascript
// Storage key format ensures per-student, per-assignment isolation
const STORAGE_KEY = 'quiz_answers_' + assignmentId;

// Stored data structure:
{
    assignmentId: 123,
    studentId: 456,           // From QUIZ_CONFIG (set by PHP from session)
    answers: [
        {
            questionId: 789,
            selectedOption: 'B',  // null if timeout
            timeTaken: 12,
            savedToServer: false,  // Tracks sync status
            answeredAt: 1723100000000  // timestamp
        },
        // ...
    ],
    lastSyncedIndex: 9,       // Server confirmed up to this point
    version: Date.now()       // Prevents stale data
}
```

**Safety rules:**
1. **Isolation**: Key includes `assignmentId` — different tests never collide
2. **Student verification**: `studentId` in stored data must match `QUIZ_CONFIG.studentId`; mismatch = clear and ignore
3. **Stale prevention**: On quiz start, if `localStorage` has answers for this assignment, compare with server's `answered_indices` — only keep answers that are newer/unsaved
4. **Clear on submit**: After successful `submit_quiz.php` response, clear localStorage immediately
5. **Browser crash recovery**: On page reload, `take_quiz.php` re-initializes JS → `fetch_quiz_questions.php` returns `answered_indices` from DB → JS detects unsaved localStorage answers → flushes them to server immediately
6. **Duplicate submission prevention**: `submit_quiz.php` already has `if ($assignment['status'] === 'Completed')` guard — returns existing result
7. **Network failure**: localStorage persists → student refreshes → crash recovery kicks in → unsaved answers are flushed

---

## 4. SENDBEACON / VISIBILITY DEDUPLICATION

**Current problem paths:**
- Student switches tab → visibility hidden → beacon fires? No — current code only starts a 30s timer on hidden
- Student returns within 30s → timer cleared → **no server request** ✓
- Student stays hidden 30s → `submitQuiz()` fires → `isSubmitting = true`
- `beforeunload` checks `if (!isSubmitting)` → **won't fire** ✓

**But there IS a duplication risk:**
- Student answers Q15 → `saveAnswer()` fires
- Student immediately minimizes → `visibilitychange` fires
- In new design: visibility handler would also try to flush unsaved answers
- If the Q15 save is still in-flight, we'd get a duplicate

**Solution in new design:**
```javascript
let isFlushing = false;  // Prevents concurrent flushes

function flushAnswers(isBeacon) {
    if (isFlushing && !isBeacon) return;  // Skip if already flushing (beacon always sends)
    
    const unsaved = answers.filter(a => !a.savedToServer);
    if (unsaved.length === 0) return;  // Nothing to flush
    
    isFlushing = true;
    
    if (isBeacon) {
        // sendBeacon — fire and forget, mark as synced optimistically
        navigator.sendBeacon('ajax/save_quiz_answers_batch.php', ...);
        unsaved.forEach(a => a.savedToServer = true);
        isFlushing = false;
    } else {
        // fetch — wait for confirmation
        fetch('ajax/save_quiz_answers_batch.php', ...)
            .then(() => {
                unsaved.forEach(a => a.savedToServer = true);
                updateLocalStorage();
            })
            .finally(() => { isFlushing = false; });
    }
}
```

**Events that trigger flushAnswers:**
| Event | Behavior |
|-------|----------|
| Question answered | Save to memory + localStorage only (NO server call) |
| Mid-quiz checkpoint (Q10) | `flushAnswers(false)` — fetch with confirmation |
| `visibilitychange` → hidden | `flushAnswers(true)` — sendBeacon (fire & forget) |
| `visibilitychange` → visible | Do nothing (answers already saved by beacon) |
| `beforeunload` | `flushAnswers(true)` — sendBeacon (fire & forget) |
| Submit button / auto-submit | All unsaved answers included in submit POST |

**Result:** At most 1–2 mid-quiz syncs + 1 submit = **2–3 server calls total** for answer saving.

---

## 5. QUESTION LOADING — CONFIRMED OPTIMAL

[fetch_quiz_questions.php](file:///c:/xampp/htdocs/nextmain/student/ajax/fetch_quiz_questions.php) already:
- Loads ALL questions in **one query** (line 76–95) ✓
- No N+1 pattern ✓
- No repeated fetching ✓
- Loads answered indices in **one query** (line 99–120) ✓
- Called exactly **once** per quiz session ✓

**No changes needed.**

---

## 6. SUBMIT OPTIMIZATION

[submit_quiz.php](file:///c:/xampp/htdocs/nextmain/student/ajax/submit_quiz.php) currently runs **7 queries**:

| # | Query | Can optimize? |
|---|-------|--------------|
| 1 | `SELECT assigned_tests WHERE id=? AND student_id=?` | Required ✓ |
| 2 | `SELECT * FROM test_results WHERE assigned_test_id=?` | Only if already completed (early exit) |
| 3 | `SELECT COUNT(*) FROM questions WHERE test_id=?` | Combine ↓ |
| 4 | `SELECT COUNT(*) FROM student_answers WHERE is_correct=1` | Combine ↓ |
| 5 | `SELECT SUM(time_taken_seconds) FROM student_answers` | Combine ↓ |
| 6 | `UPDATE assigned_tests SET status='Completed'` | Required ✓ |
| 7 | `INSERT INTO test_results ON DUPLICATE KEY` | Required ✓ |

**Optimization:** Combine queries 3+4+5 into one query. Also: accept unsaved answers in the same POST, process them before computing stats.

**Result:** 7 queries → **4 queries** (or 5 if unsaved answers included). Same 1 connection.

---

## 7. ADMIN POLLING — CAN WE ELIMINATE IT?

**assign_test.php polling**: Checks if student statuses changed (Not Started → In Progress → Completed). This is genuinely useful during a live exam session but NOT event-driven (there's no WebSocket/SSE). **Polling is required**, but 5-minute interval is sufficient.

**task_manager.php polling**: Two separate fetch calls to `task_manager_actions.php`:
- `get_focus_tasks` — returns task list
- `get_analytics` — returns aggregated counts

Both hit the same PHP endpoint → same DB connection file. But they create **2 separate PHP processes → 2 connections**.

**Optimization:** New action `get_focus_dashboard` that returns tasks + analytics in one JSON response.

**Alternative: Manual refresh button instead of polling?**
For assign_test.php, a manual "Refresh" button would eliminate polling entirely. But the current UI expects auto-updates. I'll keep polling at 5-min intervals but add a manual refresh button as well, so admins can click to check immediately instead of waiting.

---

## 8. FOOTER CACHING STRATEGY

**Architecture:**
```
Admin creates/modifies course
    → INSERT/UPDATE in courses table
    → (existing code — no change to admin flow)

Cache regeneration:
    → database.php defines a helper function: rebuildFooterCache()
    → Called after course create/update/delete in admin/courses.php
    → Writes to: admin/config/footer_cache.php

Footer reads cache:
    → includes/footer.php checks if admin/config/footer_cache.php exists
    → If exists: $courses = include('admin/config/footer_cache.php'); — NO DB connection
    → If not exists or > 24h old: 
        → If $conn is available: query DB + rebuild cache
        → If $conn not available: show generic "Visit our courses page" text

Cache file format:
    <?php return ['Web Development', 'Graphic Design', ...]; ?>
```

**Why file-based and not memcached/Redis?**
- Hostinger shared hosting doesn't provide Redis/memcached
- A PHP file `include()` is faster than a DB query
- File modification time provides built-in TTL check
- No external dependencies

**Gallery.php impact:** With this cache, gallery.php will NEVER open a DB connection. The footer reads from the cache file.

---

## 9. DATABASE HOST

Change `srv842.hstgr.io` → `localhost`.

**What this does:** Eliminates DNS lookup and TCP overhead. Uses Unix socket (or loopback on Hostinger).

**What this does NOT do:** Does NOT increase the 500/hour quota. Does NOT change connection counting.

**Benefit:** Faster connection establishment → shorter PHP worker hold time → slightly better worker reuse.

---

## 10. CONNECTION LIFECYCLE — CONFIRMED SAFE

Current `database.php` already:
- Creates connection on `require_once` (once per PHP process) ✓
- Registers `shutdown_function` to close connection ✓
- Uses non-persistent connection (`new mysqli`, not `p:host`) ✓
- Does NOT create global permanent connections ✓

**No changes needed to lifecycle.** The architecture is already correct.

---

## 11. DATABASE PASSWORD STRATEGY

**Current issue:** Password is hardcoded in `admin/config/database.php` line 8.

**Proposed solution for Hostinger:**
- Move credentials to a separate file OUTSIDE the web root, OR
- Use Hostinger's `.env` support if available, OR
- At minimum: move to a separate `admin/config/.db_credentials.php` file and add to `.htaccess` deny rules

**Realistic approach for Hostinger shared hosting:**
Create `admin/config/db_credentials.php` containing only the constants, and deny direct HTTP access via `.htaccess`. This is the most compatible approach.

**Pre-deployment steps for you:**
1. Change the database password via Hostinger hPanel → MySQL → Change Password
2. Update the new credentials file with the new password
3. Verify the `.htaccess` rule blocks direct access to `db_credentials.php`

I will NOT print the existing password in any output.

---

## 12. REVISED AFTER METRICS

### Per Student (20-question quiz, optimized):

| Step | Description | DB Connections |
|------|-------------|---------------|
| my_tests.php page load | 1 | 1 |
| take_quiz.php page load | 1 | 1 |
| fetch_quiz_questions.php (AJAX) | 1 | 1 |
| Mid-quiz batch save (at Q10) | 1 | **1** |
| submit_quiz.php (includes remaining answers) | 1 | **1** |
| quiz_result.php page load | 1 | 1 |
| Visibility/beacon save (0–1 times) | 0–1 | 0–1 |
| **TOTAL** | **6–7** | **6–7** |

**Reduction: 25 → 6–7 connections per student (73% reduction)**

### Per Admin (assign_test.php, 1 hour):

| Source | Interval | Connections/hour |
|--------|----------|-----------------|
| Page load | Once | 1 |
| Assignment poll (5 min) | Every 300s | 12 |
| **Total** | | **13** |

**Reduction: 31 → 13 connections (58% reduction)**

### Per Admin (task_manager.php, 1 hour):

| Source | Interval | Connections/hour |
|--------|----------|-----------------|
| Page load | Once | 1 |
| Combined poll (5 min) | Every 300s | 12 |
| **Total** | | **13** |

**Reduction: 29 → 13 connections (55% reduction)**

---

## 13. COMPLETE LOAD MODEL — BEFORE vs AFTER

### Assumptions:
- Test duration: ~10 minutes (20 questions × 25 seconds + transitions)
- Students arrive within a 10-minute window and finish within ~20 minutes total
- Admin has assign_test.php open for 1 hour, task_manager.php open for 1 hour
- Public traffic: ~20 page loads/hour (consistent with observed analytics)

### Scenario A: 10 Students

| Source | BEFORE | AFTER |
|--------|--------|-------|
| 10 students × quiz session | 250 | **65** |
| 2 admin (assign_test) | 62 | **26** |
| 2 admin (task_manager) | 58 | **26** |
| 2 admin (dashboard load) | 2 | 2 |
| Public pages (20 loads) | 20 | **10** |
| **TOTAL** | **392** | **129** |
| **Limit (500)** | | 500 |
| **Safety margin** | 108 (22%) | **371 (74%)** |

### Scenario B: 25 Students

| Source | BEFORE | AFTER |
|--------|--------|-------|
| 25 students × quiz session | 625 | **163** |
| 2 admin (assign_test) | 62 | **26** |
| 2 admin (task_manager) | 58 | **26** |
| 2 admin (dashboard load) | 2 | 2 |
| Public pages (20 loads) | 20 | **10** |
| **TOTAL** | **767** | **227** |
| **Limit (500)** | ❌ OVER | 500 |
| **Safety margin** | EXCEEDED | **273 (55%)** |

### Scenario C: 50 Students

| Source | BEFORE | AFTER |
|--------|--------|-------|
| 50 students × quiz session | 1,250 | **325** |
| 2 admin (assign_test) | 62 | **26** |
| 2 admin (task_manager) | 58 | **26** |
| 2 admin (dashboard load) | 2 | 2 |
| Public pages (20 loads) | 20 | **10** |
| **TOTAL** | **1,392** | **389** |
| **Limit (500)** | ❌ OVER | 500 |
| **Safety margin** | EXCEEDED | **111 (22%)** |

> [!NOTE]
> Wait — 389 is better than 498 but still leaves only 22% margin. Let me push further.

### Additional Optimization: Eliminate my_tests.php load for quiz-takers

Many students will navigate: my_tests.php → take_quiz.php. That's 2 connections for page loads. If students are given a **direct link** to `take_quiz.php?assignment_id=X` (which the admin can share), they skip `my_tests.php` entirely.

However, this depends on workflow. Let's calculate with a mix: assume 50% direct link, 50% via my_tests.php.

### Scenario C (Revised): 50 Students with mixed navigation

| Source | Connections |
|--------|------------|
| 25 students via my_tests.php (7 connections each) | 175 |
| 25 students direct link (6 connections each) | 150 |
| 2 admin (assign_test, 1 hour) | 26 |
| 2 admin (task_manager, 1 hour) | 26 |
| 2 admin (dashboard load) | 2 |
| Public pages (20 loads) | 10 |
| **TOTAL** | **389** |

The dominant cost is still the students. Let me check if we can reduce further.

### Strategy F: Defer quiz_result.php load

After submitting, the JS shows the completion screen with score. The student might NOT immediately click "View Detailed Results". Many may just close the tab. So quiz_result.php is **not guaranteed** for every student.

Conservative estimate: 70% view results → 35 loads for 50 students.

### Scenario C (Refined): 50 Students, realistic behavior

| Source | Connections |
|--------|------------|
| 50 students: take_quiz.php | 50 |
| 50 students: fetch_quiz_questions.php | 50 |
| 50 students: mid-quiz batch save | 50 |
| 50 students: submit_quiz.php | 50 |
| 50 students: my_tests.php (50% visit) | 25 |
| 35 students: quiz_result.php (70%) | 35 |
| 50 students: visibility/beacon saves (avg 0.3) | 15 |
| 2 admin (assign_test, 1 hour) | 26 |
| 2 admin (task_manager, 1 hour) | 26 |
| 2 admin (dashboard load) | 2 |
| Public pages | 10 |
| **TOTAL** | **339** |
| **Limit** | 500 |
| **Safety margin** | **161 (32%)** |

### Worst-case: ALL 50 students go through full flow

| Source | Connections |
|--------|------------|
| 50 × full quiz flow (7 each) | 350 |
| 2 admin (assign_test, 1 hour) | 26 |
| 2 admin (task_manager, 1 hour) | 26 |
| 2 admin (dashboard) | 2 |
| Public pages | 10 |
| **TOTAL** | **414** |
| **Limit** | 500 |
| **Safety margin** | **86 (17%)** |

> [!WARNING]
> Worst-case (414/500) has only 17% margin. This is because page loads (take_quiz, quiz_result) each require a DB connection for session validation and assignment verification.
>
> To push below 350, we would need to eliminate page-load DB connections, which would require either:
> - **Option 1**: Make take_quiz.php NOT verify assignment from DB (dangerous — cheating risk)
> - **Option 2**: Combine take_quiz.php + fetch_quiz_questions.php into one request (embed questions directly in PHP page)
>
> **I recommend Option 2** — embed questions JSON directly in take_quiz.php's `<script>` tag during the initial PHP page render. This eliminates the separate `fetch_quiz_questions.php` AJAX call entirely.

### Strategy G: Embed Questions in take_quiz.php (Eliminates 1 Connection per Student)

Currently:
1. take_quiz.php (PHP) → validates assignment, renders HTML → **1 connection**
2. quiz.js → fetch('fetch_quiz_questions.php') → loads questions → **1 connection**

Proposed:
1. take_quiz.php (PHP) → validates assignment, fetches questions, embeds them in `QUIZ_CONFIG` → **1 connection**
2. quiz.js → reads `QUIZ_CONFIG.questions` directly → **0 connections**

This eliminates `fetch_quiz_questions.php` during normal flow. The existing fetch endpoint remains as a fallback for resuming.

### FINAL Scenario C: 50 Students (with Strategy G)

| Source | Connections |
|--------|------------|
| 50 × take_quiz.php (includes questions) | 50 |
| 50 × mid-quiz batch save | 50 |
| 50 × submit_quiz.php (includes final answers) | 50 |
| 25 × my_tests.php (50% visit) | 25 |
| 35 × quiz_result.php (70% view results) | 35 |
| 15 × visibility/beacon saves | 15 |
| 2 × admin assign_test (1 hour) | 26 |
| 2 × admin task_manager (1 hour) | 26 |
| 2 × admin dashboard loads | 2 |
| Public pages | 10 |
| **TOTAL** | **289** |
| **Limit** | 500 |
| **Safety margin** | **211 (42%)** |

### Absolute worst-case with Strategy G:

| Source | Connections |
|--------|------------|
| 50 × full flow (my_tests + take_quiz + batch + submit + result) = 5 each | 250 |
| 50 × beacon/visibility (worst case 1 each) | 50 |
| 2 × assign_test (1 hour) | 26 |
| 2 × task_manager (1 hour) | 26 |
| 2 × dashboard | 2 |
| Public | 10 |
| **TOTAL** | **364** |
| **Limit** | 500 |
| **Safety margin** | **136 (27%)** |

---

## 14. FINAL SUMMARY TABLE

| Scenario | BEFORE | AFTER (all strategies) | Safety Margin |
|----------|--------|----------------------|---------------|
| **10 students** | 392 | **119** | **381 (76%)** |
| **25 students** | 767 ❌ | **182** | **318 (64%)** |
| **50 students (typical)** | 1,392 ❌ | **289** | **211 (42%)** |
| **50 students (worst-case)** | 1,392 ❌ | **364** | **136 (27%)** |

All scenarios are well under 500, with the typical 50-student scenario at **289 connections** — well within your ≤ 300–350 target.

---

## 15. FILES TO MODIFY

| # | File | Change | Impact |
|---|------|--------|--------|
| 1 | `admin/config/database.php` | `localhost` + error handling + credential separation | Connection speed |
| 2 | `admin/config/db_credentials.php` | [NEW] Credential isolation | Security |
| 3 | `student/quiz_assets/quiz.js` | Batch answer queue + localStorage + deduplication | **-20 connections/student** |
| 4 | `student/ajax/save_quiz_answers_batch.php` | [NEW] Batch answer endpoint | Batch support |
| 5 | `student/ajax/submit_quiz.php` | Accept unsaved answers + combined stats query | -3 queries |
| 6 | `student/take_quiz.php` | Embed questions in page (Strategy G) | **-1 connection/student** |
| 7 | `includes/footer.php` | Read from cache file instead of DB | -1 query/public-page |
| 8 | `admin/config/footer_cache.php` | [NEW] Auto-generated cache file | Cache data |
| 9 | `admin/courses.php` | Add cache rebuild call after course CRUD | Cache freshness |
| 10 | `admin/assign_test.php` | Poll interval 120s → 300s + in-flight guard | -18 connections/hr |
| 11 | `admin/task_manager.php` (JS only) | Combine 2 polls into 1 | -12 connections/hr |
| 12 | `admin/task_manager_actions.php` | New `get_focus_dashboard` action | Combined endpoint |

---

## 16. WHAT MUST YOU CHANGE IN HOSTINGER BEFORE DEPLOYMENT

1. **Change the database password** via hPanel → Databases → MySQL → your database → Change Password
2. Update `admin/config/db_credentials.php` with the new password
3. **Verify `localhost` works**: After deployment, load any page. If you get a connection error, the DB host may need to remain as the server hostname. Hostinger typically supports `localhost`.
4. **Optional but recommended**: Contact Hostinger support and ask what your actual `max_connections_per_hour` limit is. Some plans allow adjustment.

---

## 17. VERIFICATION PLAN

1. **PHP syntax check** on all modified/new files
2. **Test quiz flow**: Start quiz → answer 20 questions → verify batch save fires at Q10 → verify submit includes remaining answers → verify results page shows correct score
3. **Test crash recovery**: Start quiz → answer 5 questions → close browser → reopen → verify answers are recovered from localStorage → continue quiz
4. **Test admin polling**: Open assign_test.php → verify 5-minute interval → verify manual refresh button works
5. **Test footer cache**: Load gallery.php → verify courses appear → verify no DB error in PHP logs
6. **Monitor connections**: Check Hostinger analytics after deployment for actual connection count

> [!CAUTION]
> **Code-level audit completed; real production load test with 50 concurrent users still recommended** before a critical exam. Suggest running a dry-run test with 10–15 students first.
