// quiz_script.js — updated (Back-to-Results fixed + robust bindings)
// Drop this in place of your current quiz_script.js

// ===============================
// QUIZ SCRIPT — Full featured (updated)
// ===============================

(async function () {
    'use strict';

    // ---- Config (endpoints)
    const API = {
        quizzes: 'api/get_quizzes.php',
        questions: (quizId) => `api/get_questions.php?quiz_id=${encodeURIComponent(quizId)}`,
        whoami: 'api/whoami.php',
        submit: 'api/submit_score.php',
        leaderboard: 'api/get_leaderboard.php'
    };

    // ---- State
    let quizzes = [];
    let questions = [];
    let currentQuiz = null;
    let totalTime = 0; // seconds
    let timer = null;
    let quizStartTime = 0;
    let resultChart = null;

    // single source of truth for the last result (used for review <-> results round-trip)
    const lastResultStore = { correct: 0, total: 0, details: [], username: 'Anonymous' };
    window.userName = 'Anonymous';

    // ---- Helpers
    async function getJSON(url) {
        const res = await fetch(url, { cache: 'no-store' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function postJSON(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return res.ok ? res.json().catch(() => ({ ok: true })) : Promise.reject(new Error('submit failed'));
    }

    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return Array.from(document.querySelectorAll(sel)); }
    function escapeHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    // ---- Small audio helper (optional)
    const audioCtx = (typeof window !== 'undefined' && window.AudioContext) ? new AudioContext() : null;
    function tone(freq, dur = 0.06, type = 'sine', vol = 0.03) {
        if (!audioCtx) return;
        const o = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        o.type = type; o.frequency.value = freq; g.gain.value = vol;
        o.connect(g); g.connect(audioCtx.destination);
        o.start();
        setTimeout(() => { try { o.stop(); o.disconnect(); g.disconnect(); } catch (e) { } }, dur * 1000);
    }
    function soundGood() { tone(880, 0.05, 'sine', 0.02); tone(1320, 0.04, 'sine', 0.015); }
    function soundBad() { tone(200, 0.08, 'sine', 0.03); }

    // ---- UI builders
    function clearChildren(el) { while (el && el.firstChild) el.removeChild(el.firstChild); }

    function createQuizRow(q) {
        const row = document.createElement('div');
        row.className = 'list-group-item d-flex justify-content-between align-items-center';
        row.innerHTML = `
      <div>
        <strong style="color:#fff; display:block">${escapeHtml(q.title)}</strong>
        <small style="color:#ccc">${escapeHtml(q.description || '')}</small>
      </div>
      <button class="btn btn-sm btn-dark startBtn">Start</button>
    `;
        // stop propagation on button click (so row click doesn't fire twice)
        const btn = row.querySelector('.startBtn');
        btn.addEventListener('click', (ev) => { ev.stopPropagation(); startQuiz(q.id); });
        row.addEventListener('click', () => startQuiz(q.id));
        return row;
    }

    function buildQuestionBlock(q, i) {
        const wrapper = document.createElement('div');
        wrapper.className = 'qblock mb-3 p-3';
        wrapper.innerHTML = `
      <h5 style="color:#ffcc00">Q${i + 1}. ${escapeHtml(q.question)}</h5>
      <div class="options">
        <label class="d-block option-row"><input type="radio" name="q${i}" value="1"> <span>${escapeHtml(q.option1 || '')}</span></label>
        <label class="d-block option-row"><input type="radio" name="q${i}" value="2"> <span>${escapeHtml(q.option2 || '')}</span></label>
        <label class="d-block option-row"><input type="radio" name="q${i}" value="3"> <span>${escapeHtml(q.option3 || '')}</span></label>
        <label class="d-block option-row"><input type="radio" name="q${i}" value="4"> <span>${escapeHtml(q.option4 || '')}</span></label>
      </div>
    `;
        // ensure clicking the label checks the radio
        Array.from(wrapper.querySelectorAll('.option-row')).forEach(lbl => {
            lbl.addEventListener('click', () => {
                const r = lbl.querySelector('input[type=radio]');
                if (r) r.checked = true;
            });
        });
        return wrapper;
    }

    // ---- Load quizzes UI
    async function loadQuizzesUI() {
        const container = $('#quizzes');
        if (!container) return;
        container.innerHTML = '';
        try { quizzes = await getJSON(API.quizzes); }
        catch (e) { quizzes = []; console.error('Failed to load quizzes', e); }
        if (!quizzes.length) { container.innerHTML = '<div class="text-muted">No quizzes found.</div>'; return; }
        quizzes.forEach(q => container.appendChild(createQuizRow(q)));
    }

    // ---- Start quiz
    async function startQuiz(id) {
        currentQuiz = id;
        try { questions = await getJSON(API.questions(id)); }
        catch (e) { console.error('Failed to load questions', e); questions = []; }
        if (!questions.length) { alert('No questions available for this quiz.'); return; }

        // show quiz
        $('#startCard') && ($('#startCard').style.display = 'none');
        $('#resultCard') && ($('#resultCard').style.display = 'none');
        $('#quizCard') && ($('#quizCard').style.display = 'block');

        const meta = quizzes.find(x => String(x.id) === String(id));
        if ($('#quizTitle')) $('#quizTitle').textContent = meta ? meta.title : 'Quiz';
        if ($('#quizDesc')) $('#quizDesc').textContent = meta ? (meta.description || '') : '';

        // render questions
        const qcont = $('#questionsContainer');
        if (!qcont) return;
        clearChildren(qcont);
        questions.forEach((q, i) => qcont.appendChild(buildQuestionBlock(q, i)));

        totalTime = questions.length * 30;
        quizStartTime = Date.now();
        startTimer();
    }

    // ---- Timer
    function startTimer() {
        clearInterval(timer);
        let t = totalTime;
        updateTimerUI(t);
        timer = setInterval(() => {
            t--;
            if (t < 0) { clearInterval(timer); submitQuiz(); return; }
            updateTimerUI(t);
        }, 1000);
    }
    function updateTimerUI(s) {
        const m = Math.floor(s / 60), sec = s % 60;
        const el = $('#timeLeft');
        if (el) el.textContent = `${m < 10 ? '0' : ''}${m}:${sec < 10 ? '0' : ''}${sec}`;
    }

    // ---- Submit
    async function submitQuiz() {
        clearInterval(timer);
        let correct = 0;
        const details = [];

        questions.forEach((q, i) => {
            const sel = document.querySelector(`input[name="q${i}"]:checked`);
            const val = sel ? parseInt(sel.value) : null;
            const correctIdx = Number(q.correct_option);
            if (val === correctIdx) { correct++; soundGood(); } else soundBad();
            details.push({
                qid: q.id || i,
                question: q.question,
                selected: val,
                correct: correctIdx,
                options: [q.option1, q.option2, q.option3, q.option4]
            });
        });

        // whoami
        let user = { logged: false, id: null, username: 'Anonymous' };
        try { user = await getJSON(API.whoami); } catch (e) { /* ignore */ }
        window.userName = user.logged ? user.username : 'Anonymous';

        // post score (fire-and-forget)
        const payload = { user_id: user.logged ? user.id : null, user: window.userName, quiz_id: currentQuiz, total_questions: questions.length, correct_answers: correct };
        postJSON(API.submit, payload).catch(() => { });

        // store last result
        lastResultStore.correct = correct;
        lastResultStore.total = questions.length;
        lastResultStore.details = details;
        lastResultStore.username = window.userName;

        // show results UI
        showResultUI(correct, questions.length, details);
    }

    // ---- Show Result UI (reads from args but also updates lastResultStore)
    function showResultUI(correct, total, details) {
        // hide quiz
        $('#quizCard') && ($('#quizCard').style.display = 'none');

        const card = $('#resultCard');
        if (!card) return;
        card.style.display = 'block';

        const pct = total ? Math.round((correct / total) * 100) : 0;
        const resultTextEl = $('#resultText');
        if (resultTextEl) resultTextEl.innerHTML = `<p class="lead">You scored <strong>${correct}</strong> / <strong>${total}</strong> (<strong style="color:#ffcc00">${pct}%</strong>)</p>`;

        // chart — destroy previous
        try { if (resultChart) { resultChart.destroy(); resultChart = null; } } catch (e) { }

        const canvas = $('#resultChart');
        if (canvas && canvas.getContext) {
            // set size just in case
            canvas.width = 260; canvas.height = 260;
            const ctx = canvas.getContext('2d');
            resultChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Correct', 'Wrong'],
                    datasets: [{ data: [correct, Math.max(total - correct, 0)], backgroundColor: ['#28a745', '#6c757d'] }]
                },
                options: { cutout: '70%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        }

        // ensure the "Review" and "Take Another Quiz" buttons are present and bound
        // If the DOM already contains those controls, leave them; otherwise keep existing controls in markup.
        bindResultButtons();
    }

    // ---- Bind result buttons (re-run after any DOM replacement)
    function bindResultButtons() {
        // review button might exist in markup
        const reviewBtn = $('#reviewBtn');
        if (reviewBtn) {
            reviewBtn.onclick = (ev) => { ev && ev.preventDefault(); showReviewUI(); };
        }

        // again button might exist in markup
        const againBtn = $('#againBtn');
        if (againBtn) {
            againBtn.onclick = (ev) => { ev && ev.preventDefault(); resetToStart(); };
        }

        // If you have a link for leaderboard, it can stay as an <a> in DOM.
    }

    // ---- Show Review UI (builds review from lastResultStore)
    function showReviewUI() {
        const card = $('#resultCard');
        if (!card) return;

        const details = lastResultStore.details || [];
        // build review markup
        let html = `<h3 class="text-yellow mb-3">Answer Review</h3>`;
        details.forEach((d, i) => {
            html += `<div style="background:#0f0f0f;border:1px solid #222;padding:12px;border-radius:8px;margin-bottom:12px;">
        <h5 style="color:#ffcc00">Q${i + 1}. ${escapeHtml(d.question)}</h5>`;
            d.options.forEach((opt, idx) => {
                const id = idx + 1;
                const isCorrect = id === d.correct;
                const selectedWrong = d.selected === id && !isCorrect;
                const style = isCorrect ? 'background:#073b07;color:#d4ffd4;border:1px solid #155515;padding:8px;border-radius:6px;margin-bottom:6px;' :
                    selectedWrong ? 'background:#3b0707;color:#ffd4d4;border:1px solid #551111;padding:8px;border-radius:6px;margin-bottom:6px;' :
                        'background:#1a1a1a;color:#cfcfcf;border:1px solid #333;padding:8px;border-radius:6px;margin-bottom:6px;';
                const symbol = isCorrect ? ' ✅' : selectedWrong ? ' ✖' : '';
                html += `<div style="${style}">${escapeHtml(opt || '')}${symbol}</div>`;
            });
            html += `</div>`;
        });

        // we put a stable "Back to Results" button with ID 'backToResultsBtn'
        html += `<button id="backToResultsBtn" class="btn btn-warning w-100 mt-3">Back to Results</button>`;

        // replace resultCard content with review
        card.innerHTML = html;

        // bind the back button reliably
        const backBtn = $('#backToResultsBtn');
        if (backBtn) {
            backBtn.onclick = (ev) => {
                ev && ev.preventDefault();
                // restore results from lastResultStore (guaranteed stable)
                const r = lastResultStore;
                // render results UI again (this will also rebind Review/Again)
                showResultUI(r.correct, r.total, r.details);
                // scroll to results card smoothly
                setTimeout(() => { card.scrollIntoView({ behavior: 'smooth' }); }, 50);
            };
        }

        // ensure review view is visible
        card.style.display = 'block';
        card.scrollIntoView({ behavior: 'smooth' });
    }

    // ---- Reset to start (Take Another Quiz)
    function resetToStart() {
        clearInterval(timer);
        // restore result card default markup if you want consistent buttons
        const card = $('#resultCard');
        if (!card) return;
        card.innerHTML = `
      <h3>Your Result</h3>
      <div id="resultText"></div>
      <canvas id="resultChart" style="width:260px;height:260px;display:block;margin:12px auto;"></canvas>
      <div class="d-grid gap-2">
        <button id="reviewBtn" class="btn btn-warning mt-2">Review Answers</button>
        <button id="againBtn" class="btn btn-outline-warning mt-2">Take Another Quiz</button>
      </div>
    `;
        // re-bind the buttons now that markup exists
        bindResultButtons();

        // show start list
        $('#quizCard') && ($('#quizCard').style.display = 'none');
        card.style.display = 'none';
        $('#startCard') && ($('#startCard').style.display = 'block');

        // reload quizzes so user can select another
        loadQuizzesUI();
    }

    // ---- Initial binding & load
    function initialBind() {
        const s = $('#submitBtn'); if (s) s.addEventListener('click', (e) => { e.preventDefault(); submitQuiz(); });
        bindResultButtons(); // bind if those elements exist in initial markup
    }

    // start
    document.addEventListener('DOMContentLoaded', () => { initialBind(); loadQuizzesUI(); });

})();
