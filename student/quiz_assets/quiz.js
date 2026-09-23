/**
 * NEXT ACADEMY — Quiz Engine (Vanilla JS)
 * Handles: question rendering, timer, anti-cheat (tab + devtools), state persistence, AJAX
 * ── RANKING: Sends points_awarded & is_disqualified on submit
 * ── ANTI-CHEAT: DevTools detection with 15s countdown + 3-strike policy
 * ── OPTIMIZED: Batch answer saving, embedded questions, localStorage recovery
 */

(function () {
    'use strict';

    // ── Config (injected from PHP via data attributes or global) ─────
    var CONFIG = window.QUIZ_CONFIG || {};
    var assignmentId = CONFIG.assignmentId || 0;
    var studentId = CONFIG.studentId || 0;
    var timePerQuestion = CONFIG.timePerQuestion || 25;
    var totalQuestions = CONFIG.totalQuestions || 0;
    var resumeIndex = CONFIG.resumeIndex || 0;

    // ── State ────────────────────────────────────────────────────────
    var questions = [];
    var currentIndex = resumeIndex;
    var timerInterval = null;
    var timeLeft = timePerQuestion;
    var isSubmitting = false;
    var hiddenSince = null;
    var tabLeaveTimer = null;
    var STORAGE_KEY = 'quiz_answers_' + assignmentId;
    var DEVTOOLS_KEY = 'quiz_devtools_' + assignmentId;
    var TAB_LEAVE_THRESHOLD = 30; // seconds

    // ── Answer batch queue ───────────────────────────────────────────
    var answerQueue = [];       // Answers not yet synced to server
    var isFlushing = false;     // Prevents concurrent batch flushes
    var midQuizFlushed = false; // Track if mid-quiz checkpoint fired

    // ── Anti-cheat: DevTools state ──────────────────────────────────
    let devtoolsOpenCount = 0;
    let devtoolsTimerId = null;
    let devtoolsCountdown = 15;
    let devtoolsCurrentlyOpen = false;
    let isDisqualified = false;

    // Restore devtools count from localStorage
    try {
        var savedDT = localStorage.getItem(DEVTOOLS_KEY);
        if (savedDT) devtoolsOpenCount = parseInt(savedDT) || 0;
    } catch (e) { }

    // ── DOM refs (assigned after load) ───────────────────────────────
    let $card, $progressFill, $progressText, $timerText, $timerRing, $container;

    // ═══════════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', function () {
        $container = document.getElementById('quizContainer');
        if (!$container || !assignmentId) return;

        loadQuestions();
        setupAntiCheat();
        setupDevToolsDetection();
        blockBackButton();
    });

    // ═══════════════════════════════════════════════════════════════
    // LOAD QUESTIONS (Strategy G: Use embedded questions from PHP)
    // ═══════════════════════════════════════════════════════════════
    function loadQuestions() {
        // Strategy G: Questions are embedded directly by take_quiz.php
        if (CONFIG.questions && CONFIG.questions.length > 0) {
            questions = CONFIG.questions;
            var serverAnswered = CONFIG.answeredIndices || [];
            initQuizFromLoadedData(serverAnswered);
            return;
        }

        // Fallback: fetch from server (for resume after browser crash)
        showLoading();
        fetch('ajax/fetch_quiz_questions.php?assignment_id=' + assignmentId)
            .then(function (r) {
                if (!r.ok) {
                    return r.text().then(function(txt) {
                        throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 200));
                    });
                }
                return r.text().then(function(txt) {
                    try {
                        return JSON.parse(txt);
                    } catch(e) {
                        throw new Error('Invalid JSON response: ' + txt.substring(0, 300));
                    }
                });
            })
            .then(function (data) {
                if (!data.success) {
                    showBlocked(data.error || 'Unable to load questions.');
                    return;
                }
                questions = data.questions || [];
                initQuizFromLoadedData(data.answered_indices || []);
            })
            .catch(function (err) {
                showBlocked(err.message || 'Network error. Please check your connection.');
            });
    }

    function initQuizFromLoadedData(serverAnsweredIndices) {
        if (questions.length === 0) {
            showBlocked('No questions found for this test.');
            return;
        }

        // Restore answer queue from localStorage (crash recovery)
        var saved = loadAnswerState();
        if (saved && saved.studentId === studentId && saved.assignmentId === assignmentId) {
            // Valid saved state for this student+assignment
            answerQueue = saved.answers || [];
            if (saved.currentIndex >= 0 && saved.currentIndex < questions.length) {
                currentIndex = Math.max(currentIndex, saved.currentIndex);
            }

            // If we have unsaved answers from a crash, flush them now
            var unsavedCount = 0;
            for (var i = 0; i < answerQueue.length; i++) {
                if (!answerQueue[i].savedToServer) unsavedCount++;
            }
            if (unsavedCount > 0) {
                flushAnswers(false); // Async, non-blocking
            }
        } else {
            // No valid saved state or wrong student/assignment — start fresh
            answerQueue = [];
        }

        // If server says we already answered some, advance past them
        if (serverAnsweredIndices && serverAnsweredIndices.length > 0) {
            var maxAnswered = Math.max.apply(null, serverAnsweredIndices);
            currentIndex = Math.max(currentIndex, maxAnswered + 1);

            // Mark those answers as already synced in our queue
            for (var j = 0; j < serverAnsweredIndices.length; j++) {
                var sidx = serverAnsweredIndices[j];
                if (sidx < questions.length) {
                    markAnswerSynced(questions[sidx].id);
                }
            }
        }

        if (currentIndex >= questions.length) {
            submitQuiz();
            return;
        }

        renderQuestion(currentIndex);
    }

    // ═══════════════════════════════════════════════════════════════
    // RENDER QUESTION
    // ═══════════════════════════════════════════════════════════════
    function renderQuestion(index) {
        if (index >= questions.length) {
            submitQuiz();
            return;
        }

        currentIndex = index;
        var q = questions[index];
        var progress = ((index) / questions.length * 100).toFixed(1);

        var html = '';
        // Progress bar
        html += '<div class="quiz-progress-wrap">';
        html += '  <div class="quiz-progress-info"><span>Question ' + (index + 1) + ' of ' + questions.length + '</span><span>' + Math.round(progress) + '%</span></div>';
        html += '  <div class="quiz-progress-bar"><div class="quiz-progress-fill" id="progressFill" style="width:' + progress + '%"></div></div>';
        html += '</div>';

        // Timer
        html += '<div class="quiz-timer-wrap">';
        html += '  <div class="timer-circle">';
        html += '    <svg viewBox="0 0 80 80"><circle class="timer-bg" cx="40" cy="40" r="35"/><circle class="timer-ring" id="timerRing" cx="40" cy="40" r="35"/></svg>';
        html += '    <div class="timer-text" id="timerText">' + timePerQuestion + '</div>';
        html += '  </div>';
        html += '</div>';

        // Question card
        html += '<div class="quiz-card-container">';
        html += '  <div class="quiz-card" id="quizCard">';
        html += '    <div class="q-number"><span class="q-dot"></span> Question ' + (index + 1) + '</div>';
        html += '    <div class="q-text">' + escHtml(q.question_text) + '</div>';
        html += '    <div class="quiz-options">';

        var opts = ['A', 'B', 'C', 'D'];
        var optFields = ['option_a', 'option_b', 'option_c', 'option_d'];
        for (var i = 0; i < 4; i++) {
            html += '<div class="quiz-option" data-option="' + opts[i] + '" onclick="window._quizSelectOption(this, \'' + opts[i] + '\')">';
            html += '  <div class="opt-letter">' + opts[i] + '</div>';
            html += '  <div class="opt-text">' + escHtml(q[optFields[i]]) + '</div>';
            html += '</div>';
        }

        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        $container.innerHTML = html;

        // Start timer
        $timerText = document.getElementById('timerText');
        $timerRing = document.getElementById('timerRing');
        startTimer();

        // Save state
        saveState();
    }

    // ═══════════════════════════════════════════════════════════════
    // SELECT OPTION
    // ═══════════════════════════════════════════════════════════════
    window._quizSelectOption = function (el, option) {
        // Prevent double-click
        var allOpts = document.querySelectorAll('.quiz-option');
        for (var i = 0; i < allOpts.length; i++) {
            allOpts[i].classList.add('disabled');
        }

        // Highlight selected
        el.classList.remove('disabled');
        el.classList.add('selected');

        // Stop timer
        clearInterval(timerInterval);

        // Calculate time taken
        var timeTaken = timePerQuestion - timeLeft;

        // Queue answer (batched — NOT sent to server individually)
        queueAnswer(questions[currentIndex].id, option, timeTaken);

        // Auto-advance after delay
        setTimeout(function () {
            advanceQuestion();
        }, 800);
    };

    // ═══════════════════════════════════════════════════════════════
    // TIMER
    // ═══════════════════════════════════════════════════════════════
    function startTimer() {
        clearInterval(timerInterval);
        timeLeft = timePerQuestion;
        updateTimerUI();

        var circumference = 2 * Math.PI * 35; // r=35
        if ($timerRing) {
            $timerRing.style.strokeDasharray = circumference;
            $timerRing.style.strokeDashoffset = '0';
        }

        timerInterval = setInterval(function () {
            timeLeft--;
            updateTimerUI();

            // Update ring
            if ($timerRing) {
                var offset = circumference * (1 - timeLeft / timePerQuestion);
                $timerRing.style.strokeDashoffset = offset;
            }

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                // Timeout — queue null answer (batched)
                queueAnswer(questions[currentIndex].id, null, timePerQuestion);
                setTimeout(function () {
                    advanceQuestion();
                }, 500);
            }
        }, 1000);
    }

    function updateTimerUI() {
        if (!$timerText || !$timerRing) return;
        $timerText.textContent = timeLeft;

        // Color transitions
        $timerText.setAttribute('class', 'timer-text');
        $timerRing.setAttribute('class', 'timer-ring');

        if (timeLeft <= 5) {
            $timerText.classList.add('danger');
            $timerRing.classList.add('danger');
        } else if (timeLeft <= 10) {
            $timerText.classList.add('warning');
            $timerRing.classList.add('warning');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ADVANCE QUESTION
    // ═══════════════════════════════════════════════════════════════
    function advanceQuestion() {
        var card = document.getElementById('quizCard');
        if (card) {
            card.classList.add('slide-out');
        }

        setTimeout(function () {
            currentIndex++;
            saveState();

            if (currentIndex >= questions.length) {
                submitQuiz();
            } else {
                renderQuestion(currentIndex);
            }
        }, 300);
    }

    // ═══════════════════════════════════════════════════════════════
    // ANSWER QUEUE (batched — NOT sent individually)
    // ═══════════════════════════════════════════════════════════════
    function queueAnswer(questionId, selectedOption, timeTaken) {
        // Check if this question already has an answer in the queue
        var found = false;
        for (var i = 0; i < answerQueue.length; i++) {
            if (answerQueue[i].questionId === questionId) {
                // Update existing answer
                answerQueue[i].selectedOption = selectedOption;
                answerQueue[i].timeTaken = timeTaken;
                answerQueue[i].savedToServer = false;
                answerQueue[i].answeredAt = Date.now();
                found = true;
                break;
            }
        }

        if (!found) {
            answerQueue.push({
                questionId: questionId,
                selectedOption: selectedOption,
                timeTaken: timeTaken,
                savedToServer: false,
                answeredAt: Date.now()
            });
        }

        // Save to localStorage immediately (crash recovery)
        saveAnswerState();

        // Mid-quiz checkpoint: flush at halfway point
        if (!midQuizFlushed && answerQueue.length >= Math.ceil(questions.length / 2)) {
            midQuizFlushed = true;
            flushAnswers(false);
        }
    }

    function markAnswerSynced(questionId) {
        for (var i = 0; i < answerQueue.length; i++) {
            if (answerQueue[i].questionId === questionId) {
                answerQueue[i].savedToServer = true;
                break;
            }
        }
    }

    function getUnsavedAnswers() {
        var unsaved = [];
        for (var i = 0; i < answerQueue.length; i++) {
            if (!answerQueue[i].savedToServer) {
                unsaved.push({
                    question_id: answerQueue[i].questionId,
                    selected_option: answerQueue[i].selectedOption,
                    time_taken: answerQueue[i].timeTaken
                });
            }
        }
        return unsaved;
    }

    // ═══════════════════════════════════════════════════════════════
    // BATCH FLUSH (sends unsaved answers to server)
    // ═══════════════════════════════════════════════════════════════
    function flushAnswers(isBeacon) {
        var unsaved = getUnsavedAnswers();
        if (unsaved.length === 0) return;

        if (isFlushing && !isBeacon) return; // Skip if already flushing (beacon always sends)
        isFlushing = true;

        var payload = JSON.stringify({
            assignment_id: assignmentId,
            current_index: currentIndex + 1,
            answers: unsaved
        });

        if (isBeacon) {
            // sendBeacon — fire and forget
            var blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('ajax/save_quiz_answers_batch.php', blob);
            // Optimistically mark as synced
            for (var i = 0; i < answerQueue.length; i++) {
                answerQueue[i].savedToServer = true;
            }
            saveAnswerState();
            isFlushing = false;
        } else {
            // fetch — wait for confirmation
            fetch('ajax/save_quiz_answers_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    // Mark all as synced
                    for (var i = 0; i < answerQueue.length; i++) {
                        answerQueue[i].savedToServer = true;
                    }
                    saveAnswerState();
                }
            })
            .catch(function () { /* silent — will retry on next flush */ })
            .finally(function () { isFlushing = false; });
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SUBMIT QUIZ (includes unsaved answers)
    // ═══════════════════════════════════════════════════════════════
    function submitQuiz(forceDisqualified) {
        if (isSubmitting) return;
        isSubmitting = true;
        clearInterval(timerInterval);
        clearInterval(devtoolsTimerId);

        if (forceDisqualified) {
            isDisqualified = true;
        }

        // Show loading
        $container.innerHTML =
            '<div class="quiz-loading">' +
            '  <div class="quiz-spinner"></div>' +
            '  <p>' + (isDisqualified ? 'Submitting (disqualified)…' : 'Submitting your answers…') + '</p>' +
            '</div>';

        // Include ALL unsaved answers in the submit request
        var unsaved = getUnsavedAnswers();
        var submitPayload = JSON.stringify({
            assignment_id: assignmentId,
            is_disqualified: isDisqualified ? 1 : 0,
            answers: unsaved
        });

        fetch('ajax/submit_quiz.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: submitPayload
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                clearAnswerState();
                clearDevToolsState();
                if (data.success) {
                    showComplete(data);
                } else {
                    showComplete({ score_percentage: 0, correct_count: 0, total_questions: questions.length, points_awarded: 0, is_disqualified: isDisqualified ? 1 : 0 });
                }
            })
            .catch(function () {
                clearAnswerState();
                clearDevToolsState();
                showComplete({ score_percentage: 0, correct_count: 0, total_questions: questions.length, points_awarded: 0, is_disqualified: isDisqualified ? 1 : 0 });
            });
    }

    // ═══════════════════════════════════════════════════════════════
    // COMPLETION SCREEN
    // ═══════════════════════════════════════════════════════════════
    function showComplete(data) {
        var dq = data.is_disqualified ? true : false;
        var pts = data.points_awarded || 0;

        var html = '<div class="quiz-complete">';

        if (dq) {
            html += '  <div class="complete-icon" style="background:linear-gradient(135deg,#FF6B6B,#DC2626);box-shadow:0 0 40px rgba(255,107,107,0.3);"><i class="fas fa-ban"></i></div>';
            html += '  <h2 style="color:#FF6B6B;">Disqualified</h2>';
            html += '  <p>Your test was disqualified due to anti-cheat violation. Score: 0%</p>';
            html += '  <div class="quiz-points-badge penalty"><i class="fas fa-arrow-down"></i> ' + pts + ' ranking points</div>';
        } else {
            html += '  <div class="complete-icon"><i class="fas fa-check"></i></div>';
            html += '  <h2>Test Complete!</h2>';
            html += '  <p>You scored ' + (data.correct_count || 0) + ' out of ' + (data.total_questions || questions.length) + ' questions correctly.</p>';
            if (pts > 0) {
                html += '  <div class="quiz-points-badge reward"><i class="fas fa-arrow-up"></i> +' + pts + ' ranking points</div>';
            } else if (pts < 0) {
                html += '  <div class="quiz-points-badge penalty"><i class="fas fa-arrow-down"></i> ' + pts + ' ranking points</div>';
            } else {
                html += '  <div class="quiz-points-badge neutral"><i class="fas fa-minus"></i> 0 ranking points</div>';
            }
        }

        html += '  <a href="quiz_result.php?assignment_id=' + assignmentId + '" class="result-btn">';
        html += '    <i class="fas fa-chart-pie"></i> View Detailed Results';
        html += '  </a>';
        html += '</div>';
        $container.innerHTML = html;
    }

    // ═══════════════════════════════════════════════════════════════
    // ANTI-CHEAT: TAB VISIBILITY
    // ═══════════════════════════════════════════════════════════════
    function setupAntiCheat() {
        // Tab visibility change
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                hiddenSince = Date.now();
                // Flush unsaved answers immediately via beacon (fire & forget)
                flushAnswers(true);
                tabLeaveTimer = setTimeout(function () {
                    // Auto-submit if hidden for > threshold
                    submitQuiz();
                }, TAB_LEAVE_THRESHOLD * 1000);
            } else {
                clearTimeout(tabLeaveTimer);
                if (hiddenSince) {
                    var elapsed = (Date.now() - hiddenSince) / 1000;
                    hiddenSince = null;
                    if (elapsed >= TAB_LEAVE_THRESHOLD) {
                        submitQuiz();
                    } else if (elapsed >= 3) {
                        showTabWarning();
                    }
                }
            }
        });

        // Before unload — flush unsaved answers via beacon
        window.addEventListener('beforeunload', function (e) {
            if (!isSubmitting && currentIndex < questions.length) {
                // Flush any unsaved answers via beacon (fire & forget)
                flushAnswers(true);

                e.preventDefault();
                e.returnValue = 'Your quiz is in progress. Are you sure you want to leave?';
            }
        });
    }

    function showTabWarning() {
        var overlay = document.getElementById('warningOverlay');
        if (overlay) overlay.classList.add('show');
    }

    window._quizDismissWarning = function () {
        var overlay = document.getElementById('warningOverlay');
        if (overlay) overlay.classList.remove('show');
    };

    // ═══════════════════════════════════════════════════════════════
    // ANTI-CHEAT: DEVTOOLS DETECTION
    // ═══════════════════════════════════════════════════════════════
    function setupDevToolsDetection() {
        var devtoolsCheckInterval = null;
        var lastDevtoolsState = false;

        // Detection method: window size differential
        function isDevToolsOpen() {
            var widthDiff = window.outerWidth - window.innerWidth;
            var heightDiff = window.outerHeight - window.innerHeight;
            return widthDiff > 160 || heightDiff > 160;
        }

        // Poll every 500ms
        devtoolsCheckInterval = setInterval(function () {
            if (isSubmitting) {
                clearInterval(devtoolsCheckInterval);
                return;
            }

            var currentState = isDevToolsOpen();

            // Transition: closed → open (new detection event)
            if (currentState && !lastDevtoolsState) {
                devtoolsCurrentlyOpen = true;
                devtoolsOpenCount++;
                saveDevToolsState();

                // Check 3-strike immediate disqualification
                if (devtoolsOpenCount > 3) {
                    immediateDisqualification();
                    clearInterval(devtoolsCheckInterval);
                    return;
                }

                // Show warning with 15-second countdown
                showDevToolsWarning();
                startDevToolsCountdown();
            }

            // Transition: open → closed (user complied)
            if (!currentState && lastDevtoolsState) {
                devtoolsCurrentlyOpen = false;
                dismissDevToolsWarning();
                stopDevToolsCountdown();
            }

            lastDevtoolsState = currentState;
        }, 500);
    }

    function showDevToolsWarning() {
        var overlay = document.getElementById('devtoolsWarningOverlay');
        if (!overlay) return;

        var countEl = overlay.querySelector('.dt-open-count');
        if (countEl) countEl.textContent = devtoolsOpenCount;

        var maxEl = overlay.querySelector('.dt-max-count');
        if (maxEl) maxEl.textContent = '3';

        overlay.classList.add('show');
    }

    function dismissDevToolsWarning() {
        var overlay = document.getElementById('devtoolsWarningOverlay');
        if (overlay) overlay.classList.remove('show');
    }

    function startDevToolsCountdown() {
        stopDevToolsCountdown();
        devtoolsCountdown = 15;
        updateDevToolsCountdownUI();

        devtoolsTimerId = setInterval(function () {
            devtoolsCountdown--;
            updateDevToolsCountdownUI();

            if (devtoolsCountdown <= 0) {
                stopDevToolsCountdown();
                // Time's up — disqualify
                immediateDisqualification();
            }
        }, 1000);
    }

    function stopDevToolsCountdown() {
        if (devtoolsTimerId) {
            clearInterval(devtoolsTimerId);
            devtoolsTimerId = null;
        }
    }

    function updateDevToolsCountdownUI() {
        var el = document.getElementById('devtoolsCountdown');
        if (el) el.textContent = devtoolsCountdown;
    }

    function immediateDisqualification() {
        stopDevToolsCountdown();
        dismissDevToolsWarning();
        isDisqualified = true;
        submitQuiz(true);
    }

    function saveDevToolsState() {
        try {
            localStorage.setItem(DEVTOOLS_KEY, devtoolsOpenCount.toString());
        } catch (e) { }
    }

    function clearDevToolsState() {
        try { localStorage.removeItem(DEVTOOLS_KEY); } catch (e) { }
    }

    // Block back button
    function blockBackButton() {
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, '', location.href);
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // LOCAL STORAGE STATE (answer queue with student/assignment isolation)
    // ═══════════════════════════════════════════════════════════════
    function saveAnswerState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                assignmentId: assignmentId,
                studentId: studentId,
                currentIndex: currentIndex,
                answers: answerQueue,
                version: Date.now()
            }));
        } catch (e) { /* quota exceeded — ignore */ }
    }

    function loadAnswerState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            // Verify this is for the correct student and assignment
            if (parsed.studentId !== studentId || parsed.assignmentId !== assignmentId) {
                // Wrong student or assignment — clear stale data
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return parsed;
        } catch (e) { return null; }
    }

    function clearAnswerState() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { }
    }

    // Legacy compat — saveState is called in renderQuestion
    function saveState() {
        saveAnswerState();
    }

    function clearState() {
        clearAnswerState();
    }

    function loadState() {
        return loadAnswerState();
    }

    // ═══════════════════════════════════════════════════════════════
    // UI HELPERS
    // ═══════════════════════════════════════════════════════════════
    function showLoading() {
        $container.innerHTML =
            '<div class="quiz-loading">' +
            '  <div class="quiz-spinner"></div>' +
            '  <p>Loading questions…</p>' +
            '</div>';
    }

    function showBlocked(msg) {
        $container.innerHTML =
            '<div class="quiz-blocked">' +
            '  <div class="blocked-icon"><i class="fas fa-lock"></i></div>' +
            '  <h2>Access Denied</h2>' +
            '  <p>' + escHtml(msg) + '</p>' +
            '  <a href="my_tests.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to My Tests</a>' +
            '</div>';
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

})();
