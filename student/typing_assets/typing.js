/* ================================================================
   NEXT ACADEMY — Typing Competition Engine (JS)
   Real-time typing test with live WPM, Accuracy, Errors
   ── UPDATED: Continuous text loading for full-duration tests
   ================================================================ */

(function () {
    'use strict';

    /* ── Paragraph Bank ── */
    const PARAGRAPHS = [
        "The quick brown fox jumps over the lazy dog near the riverbank. Every morning, the fox would practice its leaps, aiming higher each time. The dog, unbothered by the spectacle, would simply yawn and stretch in the warm sunlight. Nature has a way of creating these unlikely friendships between creatures who should be adversaries.",

        "Technology has transformed the way we communicate, work, and live our daily lives. From smartphones that fit in our pockets to artificial intelligence that can compose music, the pace of innovation continues to accelerate. Each generation builds upon the discoveries of the previous one, creating an ever-expanding web of knowledge and capability.",

        "Programming is both an art and a science. It requires logical thinking to solve complex problems, but also creativity to design elegant solutions. The best programmers are those who can break down a large problem into smaller, manageable pieces and then assemble them into a cohesive whole that works seamlessly.",

        "The ocean covers more than seventy percent of our planet, yet we have explored less than five percent of it. Deep beneath the waves lie mountains taller than Everest, trenches deeper than any canyon on land, and ecosystems that thrive without sunlight. The mysteries of the deep sea continue to fascinate scientists worldwide.",

        "Learning to type efficiently is one of the most valuable skills in the modern world. Whether you are writing emails, coding software, or creating documents, the ability to type quickly and accurately saves countless hours over a lifetime. Practice and consistency are the keys to improving your typing speed.",

        "JavaScript is the language of the web. It powers interactive websites, server-side applications, mobile apps, and even desktop software. With frameworks like React, Vue, and Angular, developers can build complex user interfaces that respond instantly to user actions. The ecosystem continues to grow and evolve rapidly.",

        "The art of web development combines design aesthetics with technical expertise. A well-crafted website must not only look beautiful but also perform efficiently across different devices and browsers. Responsive design ensures that content adapts seamlessly to any screen size, from large desktop monitors to small smartphone displays.",

        "Artificial intelligence is reshaping industries across the globe. Machine learning algorithms can now detect diseases from medical images, predict weather patterns with unprecedented accuracy, and even write creative content. The ethical implications of these advances spark important conversations about the future of work and human creativity.",

        "In the world of competitive programming, speed and accuracy go hand in hand. Participants solve algorithmic challenges under strict time constraints, requiring both deep knowledge of data structures and the ability to think quickly on their feet. Practice sessions help build the muscle memory needed for rapid problem solving.",

        "The history of computing stretches back further than most people realize. From Charles Babbage's Analytical Engine in the eighteen thirties to the first electronic computers of the nineteen forties, each innovation built upon previous breakthroughs. Today's powerful machines are the culmination of nearly two centuries of relentless engineering progress.",

        "Cloud computing has revolutionized how businesses manage their infrastructure and deploy applications. Instead of maintaining expensive physical servers, companies can now rent computing resources on demand from providers around the world. This shift has democratized access to powerful technology for startups and enterprises alike.",

        "Good communication skills are essential in every profession. The ability to express ideas clearly, listen actively, and respond thoughtfully can make the difference between success and failure in any endeavor. Writing, in particular, forces us to organize our thoughts and present them in a logical, coherent manner.",

        "The human brain is the most complex organ in the known universe. It contains approximately eighty-six billion neurons, each connected to thousands of others, forming an intricate network that gives rise to consciousness, emotion, and thought. Despite decades of research, much about how the brain works remains a profound mystery.",

        "Cybersecurity has become one of the most critical fields in technology. As more of our lives move online, protecting sensitive data from malicious actors is paramount. Strong passwords, encryption, multi-factor authentication, and regular software updates form the foundation of personal and organizational security in the digital age.",

        "Music has the remarkable ability to evoke powerful emotions and create lasting memories. Whether it is the gentle melody of a classical piano or the driving beat of electronic dance music, sound waves interact with our brains in profound ways. Scientists continue to study why certain patterns of notes resonate so deeply within us.",

        "The principles of clean code apply across all programming languages. Writing code that is readable, maintainable, and well-documented is just as important as writing code that works correctly. Functions should do one thing well, variable names should be descriptive, and complex logic should be accompanied by clear comments explaining the reasoning.",

        "Space exploration represents one of humanity's greatest achievements. From the first satellite launched in nineteen fifty-seven to the rovers currently exploring Mars, our reach into the cosmos continues to expand. Future missions aim to establish permanent human settlements on the Moon and eventually send astronauts to Mars.",

        "Database management is a cornerstone of modern software development. Relational databases use structured query language to organize and retrieve data efficiently, while newer document-based systems offer flexibility for unstructured information. Choosing the right database architecture depends on the specific needs and scale of each application."
    ];

    /* ── State ── */
    let currentText = '';
    let charSpans = [];
    let typedChars = [];
    let currentIndex = 0;
    let totalCorrect = 0;
    let totalErrors = 0;
    let totalTyped = 0;

    let duration = 60;       // seconds
    let timeLeft = 60;
    let timerInterval = null;
    let testStarted = false;
    let testFinished = false;
    let startTime = null;

    // ── Continuous loading: track used paragraph indices ──
    let usedParagraphIndices = [];

    /* ── DOM Refs ── */
    const textDisplay    = document.getElementById('textDisplay');
    const hiddenInput    = document.getElementById('hiddenInput');
    const focusHint      = document.getElementById('focusHint');
    const durationSelect = document.getElementById('durationSelect');
    const timerValue     = document.getElementById('timerValue');
    const wpmValue       = document.getElementById('wpmValue');
    const accuracyValue  = document.getElementById('accuracyValue');
    const errorsCount    = document.getElementById('errorsCount');
    const progressFill   = document.getElementById('progressFill');
    const resultOverlay  = document.getElementById('resultOverlay');

    // Result elements
    const resultWpm      = document.getElementById('resultWpm');
    const resultAccuracy = document.getElementById('resultAccuracy');
    const resultErrors   = document.getElementById('resultErrors');
    const resultTime     = document.getElementById('resultTime');
    const resultTextDiff = document.getElementById('resultTextDiff');
    const retryBtn       = document.getElementById('retryBtn');
    const backBtn        = document.getElementById('backBtn');
    const resultStatus   = document.getElementById('resultStatus');

    /* ── Init ── */
    function init() {
        usedParagraphIndices = [];
        pickRandomParagraph();
        renderText();
        resetStats();
        bindEvents();
        hiddenInput.value = '';
        hiddenInput.focus();
    }

    function pickRandomParagraph() {
        // If all paragraphs used, reset the pool
        if (usedParagraphIndices.length >= PARAGRAPHS.length) {
            usedParagraphIndices = [];
        }

        // Build list of available indices
        var available = [];
        for (var i = 0; i < PARAGRAPHS.length; i++) {
            if (usedParagraphIndices.indexOf(i) === -1) {
                available.push(i);
            }
        }

        // Pick random from available
        var randIdx = available[Math.floor(Math.random() * available.length)];
        usedParagraphIndices.push(randIdx);
        currentText = PARAGRAPHS[randIdx];
    }

    function renderText() {
        textDisplay.innerHTML = '';
        charSpans = [];
        for (let i = 0; i < currentText.length; i++) {
            const span = document.createElement('span');
            span.className = 'char untyped';
            span.textContent = currentText[i];
            textDisplay.appendChild(span);
            charSpans.push(span);
        }
        // mark first char as current
        if (charSpans.length > 0) {
            charSpans[0].classList.remove('untyped');
            charSpans[0].classList.add('current');
        }
    }

    /* ── Append new paragraph seamlessly (continuous loading) ── */
    function appendNewParagraph() {
        // Pick a new paragraph
        if (usedParagraphIndices.length >= PARAGRAPHS.length) {
            usedParagraphIndices = [];
        }
        var available = [];
        for (var i = 0; i < PARAGRAPHS.length; i++) {
            if (usedParagraphIndices.indexOf(i) === -1) {
                available.push(i);
            }
        }
        var randIdx = available[Math.floor(Math.random() * available.length)];
        usedParagraphIndices.push(randIdx);

        var newParagraph = PARAGRAPHS[randIdx];

        // Add a space separator between paragraphs
        var separator = ' ';
        var appendText = separator + newParagraph;

        // Extend currentText
        currentText += appendText;

        // Create and append new character spans
        for (var j = 0; j < appendText.length; j++) {
            var span = document.createElement('span');
            span.className = 'char untyped';
            span.textContent = appendText[j];
            textDisplay.appendChild(span);
            charSpans.push(span);
        }

        // Mark the next char as current (it's right at currentIndex)
        if (currentIndex < charSpans.length) {
            charSpans[currentIndex].className = 'char current';
        }
    }

    function resetStats() {
        currentIndex = 0;
        totalCorrect = 0;
        totalErrors = 0;
        totalTyped = 0;
        typedChars = [];
        testStarted = false;
        testFinished = false;
        startTime = null;

        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

        duration = parseInt(durationSelect.value) || 60;
        timeLeft = duration;

        updateTimerDisplay();
        wpmValue.textContent = '0';
        accuracyValue.textContent = '100';
        errorsCount.textContent = '0';
        progressFill.style.width = '0%';

        timerValue.className = 'stat-value timer-val';

        // show focus hint
        focusHint.classList.remove('hidden');
        resultOverlay.classList.remove('show');
    }

    /* ── Events ── */
    function bindEvents() {
        // Focus on click anywhere in card
        document.querySelector('.typing-card').addEventListener('click', function () {
            if (!testFinished) {
                hiddenInput.focus();
                focusHint.classList.add('hidden');
            }
        });

        focusHint.addEventListener('click', function () {
            hiddenInput.focus();
            focusHint.classList.add('hidden');
        });

        // Main typing handler
        hiddenInput.addEventListener('input', handleInput);

        // Handle backspace via keydown
        hiddenInput.addEventListener('keydown', handleKeydown);

        // Blur detection — show hint
        hiddenInput.addEventListener('blur', function () {
            if (!testFinished && testStarted) {
                focusHint.classList.remove('hidden');
            }
        });

        hiddenInput.addEventListener('focus', function () {
            if (!testFinished) {
                focusHint.classList.add('hidden');
            }
        });

        // Duration change
        durationSelect.addEventListener('change', function () {
            resetTest();
        });

        // Anti-cheat: disable paste
        hiddenInput.addEventListener('paste', function (e) { e.preventDefault(); });

        // Anti-cheat: disable right-click
        document.addEventListener('contextmenu', function (e) { e.preventDefault(); });

        // Anti-cheat: disable drag
        document.addEventListener('dragstart', function (e) { e.preventDefault(); });

        // Retry button
        retryBtn.addEventListener('click', function () {
            resetTest();
        });
    }

    function handleKeydown(e) {
        if (testFinished) { e.preventDefault(); return; }

        // Backspace
        if (e.key === 'Backspace') {
            e.preventDefault();
            if (currentIndex > 0) {
                currentIndex--;
                typedChars.pop();

                // Recalculate totals from typedChars
                recalcFromTyped();

                // Update char classes
                charSpans[currentIndex].className = 'char current';
                if (currentIndex + 1 < charSpans.length) {
                    charSpans[currentIndex + 1].className = 'char untyped';
                }

                updateLiveStats();
                updateProgress();
                syncHiddenInput();
            }
            return;
        }

        // Prevent Tab, Enter etc
        if (['Tab', 'Enter', 'Escape'].includes(e.key)) {
            e.preventDefault();
        }
    }

    function handleInput(e) {
        if (testFinished) return;

        const inputData = e.data;
        if (inputData === null || inputData === undefined) return; // backspace or special

        // Start timer on first keystroke
        if (!testStarted) {
            testStarted = true;
            startTime = Date.now();
            startTimer();
        }

        // Process each character typed
        for (let i = 0; i < inputData.length; i++) {
            if (currentIndex >= currentText.length) break;

            const typedChar = inputData[i];
            const expectedChar = currentText[currentIndex];
            const isCorrect = typedChar === expectedChar;

            typedChars.push({ char: typedChar, correct: isCorrect });
            totalTyped++;

            if (isCorrect) {
                totalCorrect++;
                charSpans[currentIndex].className = 'char correct';
            } else {
                totalErrors++;
                charSpans[currentIndex].className = 'char incorrect';
            }

            currentIndex++;

            // Mark next char as current
            if (currentIndex < charSpans.length) {
                charSpans[currentIndex].className = 'char current';
            }
        }

        updateLiveStats();
        updateProgress();
        autoScroll();
        syncHiddenInput();

        // ── Continuous loading: if text completed but time remains, load more ──
        if (currentIndex >= currentText.length && !testFinished) {
            appendNewParagraph();
            updateProgress();
            autoScroll();
        }
    }

    function syncHiddenInput() {
        // Keep hidden input in sync so backspace works
        // Limit to last 100 chars to prevent input from growing too large
        var typed = typedChars.map(function(c) { return c.char; }).join('');
        if (typed.length > 100) {
            typed = typed.slice(-100);
        }
        hiddenInput.value = typed;
    }

    function recalcFromTyped() {
        totalTyped = typedChars.length;
        totalCorrect = 0;
        totalErrors = 0;
        for (let i = 0; i < typedChars.length; i++) {
            if (typedChars[i].correct) totalCorrect++;
            else totalErrors++;
        }
    }

    /* ── Timer ── */
    function startTimer() {
        timerInterval = setInterval(function () {
            timeLeft--;
            updateTimerDisplay();

            if (timeLeft <= 0) {
                finishTest();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        timerValue.textContent = mins > 0
            ? mins + ':' + String(secs).padStart(2, '0')
            : secs;

        // urgency colors
        const pct = timeLeft / duration;
        if (pct <= 0.1) {
            timerValue.className = 'stat-value timer-val timer-danger';
        } else if (pct <= 0.25) {
            timerValue.className = 'stat-value timer-val timer-warning';
        } else {
            timerValue.className = 'stat-value timer-val';
        }
    }

    /* ── Live Stats ── */
    function updateLiveStats() {
        // WPM
        const elapsedMs = testStarted ? Date.now() - startTime : 0;
        const elapsedMin = Math.max(elapsedMs / 60000, 1 / 60); // min 1 second
        const wpm = Math.round((totalCorrect / 5) / elapsedMin);
        wpmValue.textContent = isFinite(wpm) && wpm >= 0 ? wpm : 0;

        // Accuracy
        const accuracy = totalTyped > 0 ? Math.round((totalCorrect / totalTyped) * 100) : 100;
        accuracyValue.textContent = accuracy;

        // Errors
        errorsCount.textContent = totalErrors;
    }

    function updateProgress() {
        const pct = currentText.length > 0 ? (currentIndex / currentText.length) * 100 : 0;
        progressFill.style.width = pct + '%';
    }

    function autoScroll() {
        if (currentIndex < charSpans.length) {
            const span = charSpans[currentIndex];
            const container = textDisplay;
            const spanRect = span.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();

            if (spanRect.bottom > containerRect.bottom - 20 || spanRect.top < containerRect.top + 20) {
                span.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        }
    }

    /* ── Finish Test ── */
    function finishTest() {
        if (testFinished) return;
        testFinished = true;

        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

        const elapsedMs = startTime ? Date.now() - startTime : 0;
        const elapsedSec = Math.round(elapsedMs / 1000);
        const elapsedMin = Math.max(elapsedMs / 60000, 1 / 60);

        const finalWpm = Math.round((totalCorrect / 5) / elapsedMin);
        const finalAccuracy = totalTyped > 0 ? parseFloat(((totalCorrect / totalTyped) * 100).toFixed(2)) : 0;
        const timeTaken = Math.min(elapsedSec, duration);

        // Update result screen
        resultWpm.textContent = isFinite(finalWpm) && finalWpm >= 0 ? finalWpm : 0;
        resultAccuracy.textContent = finalAccuracy.toFixed(1) + '%';
        resultErrors.textContent = totalErrors;

        // Format time
        const tMins = Math.floor(timeTaken / 60);
        const tSecs = timeTaken % 60;
        resultTime.textContent = tMins > 0
            ? tMins + 'm ' + tSecs + 's'
            : tSecs + 's';

        // Build text diff
        buildTextDiff();

        // Show result overlay
        resultOverlay.classList.add('show');

        // Save via AJAX
        saveResult({
            wpm: isFinite(finalWpm) && finalWpm >= 0 ? finalWpm : 0,
            accuracy: finalAccuracy,
            errors: totalErrors,
            time_taken: timeTaken,
            test_text: currentText.substring(0, 5000), // Limit to avoid huge payloads
            typed_text: typedChars.map(function(c) { return c.char; }).join('').substring(0, 5000)
        });
    }

    function buildTextDiff() {
        resultTextDiff.innerHTML = '';
        // Only show up to the typed portion + a bit of untyped for context
        var showLength = Math.min(currentText.length, typedChars.length + 50);
        for (let i = 0; i < showLength; i++) {
            const span = document.createElement('span');
            span.textContent = currentText[i];

            if (i < typedChars.length) {
                span.className = typedChars[i].correct ? 'char-correct' : 'char-wrong';
            } else {
                span.className = 'char-missed';
            }
            resultTextDiff.appendChild(span);
        }
        // If there's more text not shown, add indicator
        if (showLength < currentText.length) {
            var moreSpan = document.createElement('span');
            moreSpan.className = 'char-missed';
            moreSpan.textContent = '...';
            moreSpan.style.opacity = '0.5';
            resultTextDiff.appendChild(moreSpan);
        }
    }

    /* ── AJAX Save ── */
    function saveResult(data) {
        resultStatus.textContent = 'Saving result...';
        resultStatus.className = 'typing-status saving';
        resultStatus.style.display = 'block';

        const formData = new FormData();
        formData.append('student_id', window.TYPING_CONFIG.studentId);
        formData.append('wpm', data.wpm);
        formData.append('accuracy', data.accuracy);
        formData.append('errors', data.errors);
        formData.append('time_taken', data.time_taken);
        formData.append('test_text', data.test_text);
        formData.append('typed_text', data.typed_text);

        fetch('ajax/save_typing_result.php', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (json.success) {
                resultStatus.textContent = '✓ Result saved successfully!';
                resultStatus.className = 'typing-status saved';
            } else {
                resultStatus.textContent = '✗ ' + (json.error || 'Failed to save');
                resultStatus.className = 'typing-status error';
            }
        })
        .catch(function (err) {
            resultStatus.textContent = '✗ Network error: ' + err.message;
            resultStatus.className = 'typing-status error';
        });
    }

    /* ── Reset Test ── */
    function resetTest() {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
        usedParagraphIndices = [];
        pickRandomParagraph();
        renderText();
        hiddenInput.value = '';
        resetStats();
        hiddenInput.focus();
        focusHint.classList.add('hidden');
    }

    /* ── Start ── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
