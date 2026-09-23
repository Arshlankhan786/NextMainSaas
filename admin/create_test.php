<?php
require_once 'includes/header.php';

// ── Handle form submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $title = trim($_POST['title'] ?? '');
    $time_per_q = max(5, intval($_POST['time_per_question'] ?? 25));
    $json_raw = trim($_POST['questions_json'] ?? '');
    $errors = [];

    if (empty($title)) $errors[] = 'Test title is required.';
    if (empty($json_raw)) $errors[] = 'Questions JSON is required.';

    $questions = null;
    if (!empty($json_raw)) {
        $questions = json_decode($json_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON: ' . json_last_error_msg();
            $questions = null;
        } elseif (!is_array($questions) || count($questions) === 0) {
            $errors[] = 'JSON must be a non-empty array of question objects.';
            $questions = null;
        } else {
            // Validate each question
            foreach ($questions as $i => $q) {
                $idx = $i + 1;
                if (empty($q['question'])) $errors[] = "Q{$idx}: Missing 'question' field.";
                if (empty($q['A'])) $errors[] = "Q{$idx}: Missing option A.";
                if (empty($q['B'])) $errors[] = "Q{$idx}: Missing option B.";
                if (empty($q['C'])) $errors[] = "Q{$idx}: Missing option C.";
                if (empty($q['D'])) $errors[] = "Q{$idx}: Missing option D.";
                if (empty($q['answer']) || !in_array(strtoupper($q['answer']), ['A','B','C','D'])) {
                    $errors[] = "Q{$idx}: 'answer' must be A, B, C, or D.";
                }
            }
        }
    }

    if (empty($errors) && $questions) {
        // Insert test
        $stmt = $conn->prepare("INSERT INTO tests (title, time_per_question, admin_id) VALUES (?, ?, ?)");
        $admin_id = $_SESSION['admin_id'];
        $stmt->bind_param("sii", $title, $time_per_q, $admin_id);

        if ($stmt->execute()) {
            $test_id = $conn->insert_id;
            $stmt->close();

            // Insert questions
            $qStmt = $conn->prepare("INSERT INTO questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ok = true;
            foreach ($questions as $i => $q) {
                $qText = $q['question'];
                $oA = $q['A'];
                $oB = $q['B'];
                $oC = $q['C'];
                $oD = $q['D'];
                $ans = strtoupper($q['answer']);
                $sort = $i + 1;
                $qStmt->bind_param("issssssi", $test_id, $qText, $oA, $oB, $oC, $oD, $ans, $sort);
                if (!$qStmt->execute()) { $ok = false; break; }
            }
            $qStmt->close();

            if ($ok) {
                $_SESSION['success'] = "Test \"{$title}\" created with " . count($questions) . " questions!";
            } else {
                $_SESSION['error'] = "Test created but some questions failed to save.";
            }
        } else {
            $_SESSION['error'] = "Failed to create test: " . $stmt->error;
            $stmt->close();
        }
        header('Location: create_test.php');
        exit();
    } else {
        $error_msg = implode('<br>', $errors);
    }
}

// ── Fetch existing tests ────────────────────────────────────────
$tests = [];
$res = $conn->query("
    SELECT t.*, COUNT(q.id) as question_count,
           (SELECT COUNT(*) FROM assigned_tests WHERE test_id = t.id) as assigned_count
    FROM tests t
    LEFT JOIN questions q ON q.test_id = t.id
    GROUP BY t.id
    ORDER BY t.created_at DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $tests[] = $row;
}
?>

<!-- Select2 for future use -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
/* ── Create Test Page Styles ── */
.ct-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 24px;
}
.ct-header h4 { margin: 0; font-weight: 700; font-size: 20px; }
.ct-header .badge { font-size: 11px; padding: 5px 12px; }

.ct-card {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); box-shadow: var(--sh-sm);
    padding: 24px; margin-bottom: 24px;
}
.ct-card h6 {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .8px; color: var(--muted); margin-bottom: 16px;
}

/* JSON Editor area */
.json-editor {
    font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
    font-size: 13px; line-height: 1.6;
    min-height: 200px; resize: vertical;
    background: #0f0a2e; color: #e0e7ff;
    border: 2px solid var(--indigo-800);
    border-radius: 10px; padding: 16px;
}
.json-editor:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(99,102,241,.2);
}
.json-editor::placeholder { color: rgba(255,255,255,.25); }

/* Validation feedback */
.json-status {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; font-weight: 600; margin-top: 8px; min-height: 20px;
}
.json-status.valid { color: var(--emerald); }
.json-status.invalid { color: var(--crimson); }
.json-status i { font-size: 14px; }

/* Preview panel */
.preview-panel {
    max-height: 460px; overflow-y: auto;
    scrollbar-width: thin;
}
.preview-q {
    background: #f8f9fc; border-radius: 10px;
    padding: 14px 16px; margin-bottom: 10px;
    border-left: 4px solid var(--indigo-600);
}
.preview-q .q-num {
    font-size: 10px; font-weight: 700; color: var(--indigo-600);
    text-transform: uppercase; letter-spacing: .6px; margin-bottom: 4px;
}
.preview-q .q-text { font-size: 13.5px; font-weight: 600; margin-bottom: 8px; }
.preview-q .q-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; }
.preview-q .q-opt {
    font-size: 12px; padding: 3px 0; color: var(--muted);
}
.preview-q .q-opt.correct { color: var(--emerald); font-weight: 700; }

/* Tests list */
.tests-table .status-dot {
    width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px;
}
.tests-table .status-dot.active { background: var(--emerald); }
.tests-table .btn-del {
    width: 30px; height: 30px; border-radius: 8px; border: none;
    background: rgba(220,38,38,.08); color: var(--crimson);
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; transition: var(--ease);
}
.tests-table .btn-del:hover { background: rgba(220,38,38,.15); }

/* JSON template button */
.json-template-btn {
    font-size: 11px; padding: 4px 12px; border-radius: 6px;
    background: var(--indigo-100); color: var(--indigo-700);
    border: none; cursor: pointer; font-weight: 600; transition: var(--ease);
}
.json-template-btn:hover { background: var(--indigo-600); color: #fff; }

@media(max-width:767px) {
    .preview-q .q-opts { grid-template-columns: 1fr; }
}
</style>

<!-- ═══ PAGE CONTENT ═══ -->
<div class="ct-header">
    <h4><i class="fas fa-clipboard-list text-purple me-2"></i>Create Test</h4>
    <span class="badge bg-purple"><?= count($tests) ?> Tests Created</span>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?= $_SESSION['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-1"></i> <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> <?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="createTestForm">
<div class="row g-4">
    <!-- LEFT: Form -->
    <div class="col-lg-7">
        <div class="ct-card">
            <h6><i class="fas fa-info-circle me-1"></i> Test Details</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold" style="font-size:12.5px">Test Title</label>
                    <input type="text" class="form-control" name="title" required
                           placeholder="e.g. HTML Basics Quiz" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" style="font-size:12.5px">Time / Question (sec)</label>
                    <input type="number" class="form-control" name="time_per_question"
                           min="5" max="300" value="<?= intval($_POST['time_per_question'] ?? 25) ?>">
                </div>
            </div>
        </div>

        <div class="ct-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0"><i class="fas fa-code me-1"></i> Questions JSON</h6>
                <button type="button" class="json-template-btn" id="insertTemplate">
                    <i class="fas fa-magic me-1"></i> Insert Template
                </button>
            </div>
            <textarea class="json-editor form-control" name="questions_json" id="jsonEditor"
                      placeholder='[&#10;  {&#10;    "question": "What is HTML?",&#10;    "A": "HyperText Markup Language",&#10;    "B": "High Tech ML",&#10;    "C": "HyperText Machine Language",&#10;    "D": "None of these",&#10;    "answer": "A"&#10;  }&#10;]'><?= htmlspecialchars($_POST['questions_json'] ?? '') ?></textarea>
            <div class="json-status" id="jsonStatus"></div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" name="create_test" class="btn btn-purple px-4">
                    <i class="fas fa-plus me-1"></i> Create Test
                </button>
                <button type="button" class="btn btn-outline-purple" id="previewBtn">
                    <i class="fas fa-eye me-1"></i> Preview
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT: Preview -->
    <div class="col-lg-5">
        <div class="ct-card">
            <h6><i class="fas fa-eye me-1"></i> Question Preview</h6>
            <div class="preview-panel" id="previewPanel">
                <p class="text-muted" style="font-size:13px">
                    <i class="fas fa-arrow-left me-1"></i> Enter JSON and click Preview to see questions here.
                </p>
            </div>
        </div>
    </div>
</div>
</form>

<!-- ═══ EXISTING TESTS TABLE ═══ -->
<?php if (!empty($tests)): ?>
<div class="ct-card mt-2">
    <h6><i class="fas fa-list me-1"></i> All Tests</h6>
    <div class="table-responsive">
        <table class="table table-hover tests-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Questions</th>
                    <th>Timer</th>
                    <th>Assigned</th>
                    <th>Created</th>
                    <th style="width:80px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $i => $t): ?>
                <tr id="testRow-<?= $t['id'] ?>">
                    <td class="fw-bold text-muted"><?= $i + 1 ?></td>
                    <td>
                        <span class="status-dot active"></span>
                        <strong><?= htmlspecialchars($t['title']) ?></strong>
                    </td>
                    <td><span class="badge bg-purple"><?= $t['question_count'] ?></span></td>
                    <td><?= $t['time_per_question'] ?>s</td>
                    <td><?= $t['assigned_count'] ?> students</td>
                    <td style="font-size:12px;color:var(--muted)"><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></td>
                    <td>
                        <button class="btn-del" onclick="deleteTest(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['title'])) ?>')" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// ── JSON Validator (live) ───────────────────────────────────────
const editor = document.getElementById('jsonEditor');
const status = document.getElementById('jsonStatus');
const previewPanel = document.getElementById('previewPanel');

function validateJSON() {
    const raw = editor.value.trim();
    if (!raw) { status.innerHTML = ''; return null; }
    try {
        const data = JSON.parse(raw);
        if (!Array.isArray(data) || data.length === 0) {
            status.className = 'json-status invalid';
            status.innerHTML = '<i class="fas fa-times-circle"></i> Must be a non-empty array';
            return null;
        }
        let issues = [];
        data.forEach((q, i) => {
            if (!q.question) issues.push(`Q${i+1}: missing "question"`);
            ['A','B','C','D'].forEach(o => { if (!q[o]) issues.push(`Q${i+1}: missing "${o}"`); });
            if (!q.answer || !['A','B','C','D'].includes(q.answer.toUpperCase()))
                issues.push(`Q${i+1}: invalid "answer"`);
        });
        if (issues.length) {
            status.className = 'json-status invalid';
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + issues.slice(0,3).join('; ');
            return null;
        }
        status.className = 'json-status valid';
        status.innerHTML = '<i class="fas fa-check-circle"></i> Valid — ' + data.length + ' question(s)';
        return data;
    } catch(e) {
        status.className = 'json-status invalid';
        status.innerHTML = '<i class="fas fa-times-circle"></i> ' + e.message;
        return null;
    }
}

editor.addEventListener('input', validateJSON);

// ── Preview ─────────────────────────────────────────────────────
document.getElementById('previewBtn').addEventListener('click', function() {
    const data = validateJSON();
    if (!data) {
        previewPanel.innerHTML = '<p class="text-danger" style="font-size:13px"><i class="fas fa-exclamation-triangle me-1"></i>Fix JSON errors first.</p>';
        return;
    }
    let html = '';
    data.forEach((q, i) => {
        const ans = (q.answer || '').toUpperCase();
        html += `<div class="preview-q">
            <div class="q-num">Question ${i+1}</div>
            <div class="q-text">${escHtml(q.question)}</div>
            <div class="q-opts">
                ${['A','B','C','D'].map(o =>
                    `<div class="q-opt ${o===ans?'correct':''}"><strong>${o}.</strong> ${escHtml(q[o]||'')} ${o===ans?'✓':''}</div>`
                ).join('')}
            </div>
        </div>`;
    });
    previewPanel.innerHTML = html;
});

// ── Insert Template ─────────────────────────────────────────────
document.getElementById('insertTemplate').addEventListener('click', function() {
    const tmpl = JSON.stringify([
        {"question":"What does HTML stand for?","A":"HyperText Markup Language","B":"High Tech ML","C":"HyperText Machine Language","D":"None of these","answer":"A"},
        {"question":"Which tag is used for the largest heading?","A":"<h6>","B":"<heading>","C":"<h1>","D":"<head>","answer":"C"}
    ], null, 2);
    editor.value = tmpl;
    validateJSON();
});

// ── Delete Test ─────────────────────────────────────────────────
function deleteTest(id, title) {
    if (!confirm('Delete "' + title + '" and all its questions/assignments/results?\n\nThis cannot be undone.')) return;
    fetch('ajax/delete_test.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'test_id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const row = document.getElementById('testRow-' + id);
            if (row) { row.style.opacity = '0'; row.style.transform = 'translateX(20px)'; row.style.transition = '.3s ease'; setTimeout(() => row.remove(), 350); }
        } else {
            alert(d.error || 'Failed to delete.');
        }
    })
    .catch(() => alert('Network error.'));
}

function escHtml(s) {
    const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
}

// Auto-validate on page load if there's content
if (editor.value.trim()) validateJSON();
</script>

<?php require_once 'includes/footer.php'; ?>
