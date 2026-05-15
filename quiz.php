<?php include 'php/database.php'; session_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Take Quiz — QuizHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
  --bg: #0b0b0b;
  --card: #111;
  --card-2: #1a1a1a;
  --muted: #bdbdbd;
  --accent: #ffcc00;
  --accent-2: #ffd633;
  --success: #073b07;
  --danger: #3b0707;
}

/* Body */
body {
    background: var(--bg);
    color: #fff;
    font-family: Inter, Arial, sans-serif;
}

/* Navbar */
.navbar-brand { color: var(--accent); font-weight:700; font-size:1.25rem; }

/* Card basics */
.card {
  background: linear-gradient(180deg,var(--card), #0f0f0f);
  border: 1px solid #222;
  border-radius: 10px;
}

/* Start Card */
#startCard h3 { color: var(--accent); font-weight:700; }
#quizzes .list-group-item {
  background: var(--card-2);
  border: 1px solid #2b2b2b;
  border-radius:8px;
  margin-bottom:10px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
#quizzes .quiz-meta { color: var(--muted); font-size:0.95rem; }

/* Buttons */
.btn-warning {
  background: var(--accent);
  color:#111;
  font-weight:700;
  border:none;
}
.btn-warning:hover { background: var(--accent-2); }

/* Quiz Card */
#quizTitle { color: var(--accent); font-weight:700; font-size:1.15rem; }
.qblock {
  background: #131313;
  border:1px solid #222;
  padding:16px;
  border-radius:8px;
  margin-bottom:14px;
}
.qblock h5 { color: var(--accent); font-weight:600; margin-bottom:10px; }

/* Option row styling */
.optionRow {
  display:flex;
  align-items:center;
  gap:12px;
  padding:10px;
  border-radius:8px;
  margin-bottom:8px;
  background:#171717;
  border:1px solid #222;
  cursor:pointer;
  color:#ddd;
}
.optionRow:hover{ border-color: #333; transform:translateY(-1px); }
.optionRow input[type="radio"] { transform:scale(1.2); accent-color: var(--accent); }

/* Result card */
#resultCard { padding:18px; }
#resultChart { max-width: 280px; max-height:280px; display:block; margin:12px auto; }

/* Review answer rows */
.review-option {
  padding:10px;
  border-radius:6px;
  margin-bottom:8px;
  border:1px solid #2a2a2a;
  color:#ddd;
}

.review-correct { background: #073b07; color:#dfffdc; border-color:#155515; }
.review-wrong { background: #3b0707; color:#ffdcdc; border-color:#551111; }
.review-neutral { background:#171717; color:#ddd; border-color:#222; }

@media (max-width:720px){
  #resultChart{ max-width:200px; max-height:200px; }
  .optionRow{ font-size:14px; }
}

.text-muted-2 { color: #a9a9a9; font-size:0.93rem; }
</style>
</head>
<body>

<nav class="navbar px-3 mb-3">
  <a class="navbar-brand" href="index.php">QuizHub</a>
</nav>

<div class="container py-3">

  <!-- Start card -->
  <div id="startCard" class="card p-4 mb-3">
    <h3>Select a Quiz</h3>
    <div id="quizzes" class="list-group mt-3"></div>
  </div>

  <!-- Quiz screen -->
  <div id="quizCard" class="card p-4 mb-3" style="display:none;">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div>
        <h4 id="quizTitle" class="m-0"></h4>
        <div id="quizDesc" class="text-muted-2"></div>
      </div>
      <div class="text-muted-2">Time Left: <span id="timeLeft">--:--</span></div>
    </div>

    <hr style="border-color:#222">
    <form id="quizForm">
      <div id="questionsContainer"></div>
      <button type="button" id="submitBtn" class="btn btn-warning mt-3 w-100">Submit Quiz</button>
    </form>
  </div>

  <!-- Results -->
  <div id="resultCard" class="card p-4 mb-3" style="display:none;">
    <h3>Your Result</h3>
    <div id="resultText" class="text-muted-2 mb-2"></div>
    <canvas id="resultChart"></canvas>

    <div class="mt-3 d-grid gap-2">
      <button id="reviewBtn" class="btn btn-warning">Review Answers</button>
      <button id="againBtn" class="btn btn-outline-warning">Take Another Quiz</button>
      <a href="leaderboard.php" class="btn btn-dark">View Leaderboard</a>
    </div>
  </div>

  <!-- REVIEW SECTION (MISSING EARLIER — NOW FIXED) -->
  <div id="review-section" class="card p-4 mb-3" style="display:none;">
    <h3>Review Answers</h3>
    <div id="reviewContainer" class="mt-3"></div>

    <div class="mt-3 d-grid gap-2">
      <button id="backToResultsBtn" class="btn btn-warning">Back to Results</button>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="quiz_script.js"></script>
</body>
</html>
