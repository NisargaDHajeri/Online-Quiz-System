<?php
// admin/dashboard.php
session_start();
include '../php/database.php'; // adjust path if needed

// Admin guard
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Small helper
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Handle POST actions: add/edit/delete for quizzes and questions
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ADD QUIZ
    if ($action === 'add_quiz') {
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        if ($title !== '') {
            $stmt = $conn->prepare("INSERT INTO quizzes (title, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $title, $desc);
            $stmt->execute();
            $messages[] = "Quiz added.";
        } else $messages[] = "Quiz title required.";
    }

    // EDIT QUIZ
    if ($action === 'edit_quiz') {
        $qid = intval($_POST['quiz_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($qid && $title !== '') {
            $stmt = $conn->prepare("UPDATE quizzes SET title=?, description=? WHERE id=?");
            $stmt->bind_param('ssi', $title, $desc, $qid);
            $stmt->execute();
            $messages[] = "Quiz updated.";
        } else $messages[] = "Invalid quiz or title.";
    }

    // DELETE QUIZ
    if ($action === 'delete_quiz') {
        $qid = intval($_POST['quiz_id'] ?? 0);
        if ($qid) {
            // This will cascade delete questions/scores if FK set; otherwise delete explicitly
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE id=?");
            $stmt->bind_param('i', $qid);
            $stmt->execute();
            $messages[] = "Quiz deleted.";
        }
    }

    // ADD QUESTION
    if ($action === 'add_question') {
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $o1 = trim($_POST['option1'] ?? '');
        $o2 = trim($_POST['option2'] ?? '');
        $o3 = trim($_POST['option3'] ?? '');
        $o4 = trim($_POST['option4'] ?? '');
        $correct = intval($_POST['correct_option'] ?? 0);
        if ($quiz_id && $question && $o1 && $o2 && $correct >= 1 && $correct <= 4) {
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question, option1, option2, option3, option4, correct_option) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('isssssi', $quiz_id, $question, $o1, $o2, $o3, $o4, $correct);
            $stmt->execute();
            $messages[] = "Question added.";
        } else $messages[] = "Missing fields for question. Ensure at least two options and correct option set.";
    }

    // EDIT QUESTION
    if ($action === 'edit_question') {
        $qid = intval($_POST['qid'] ?? 0);
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $o1 = trim($_POST['option1'] ?? '');
        $o2 = trim($_POST['option2'] ?? '');
        $o3 = trim($_POST['option3'] ?? '');
        $o4 = trim($_POST['option4'] ?? '');
        $correct = intval($_POST['correct_option'] ?? 0);
        if ($qid && $quiz_id && $question && $o1 && $o2 && $correct >= 1 && $correct <= 4) {
            $stmt = $conn->prepare("UPDATE questions SET quiz_id=?, question=?, option1=?, option2=?, option3=?, option4=?, correct_option=? WHERE id=?");
            $stmt->bind_param('isssssii', $quiz_id, $question, $o1, $o2, $o3, $o4, $correct, $qid);
            $stmt->execute();
            $messages[] = "Question updated.";
        } else $messages[] = "Invalid question data.";
    }

    // DELETE QUESTION
    if ($action === 'delete_question') {
        $qid = intval($_POST['qid'] ?? 0);
        if ($qid) {
            $stmt = $conn->prepare("DELETE FROM questions WHERE id=?");
            $stmt->bind_param('i', $qid);
            $stmt->execute();
            $messages[] = "Question deleted.";
        }
    }
}

// Fetch data for dashboard
// Counts: quizzes, questions, users, attempts
$totalQuizzes = $conn->query("SELECT COUNT(*) AS c FROM quizzes")->fetch_assoc()['c'] ?? 0;
$totalQuestions = $conn->query("SELECT COUNT(*) AS c FROM questions")->fetch_assoc()['c'] ?? 0;
$totalUsers = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0;
$totalAttempts = $conn->query("SELECT COUNT(*) AS c FROM scores")->fetch_assoc()['c'] ?? 0;

// Recent attempts
$recentAttempts = $conn->query("SELECT s.*, q.title AS quiz_title FROM scores s LEFT JOIN quizzes q ON s.quiz_id = q.id ORDER BY s.taken_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

// Leaderboard: best percentage per user (top 10)
$leaderboard = $conn->query("
    SELECT user_name, MAX(percentage) AS best_percentage, COUNT(*) AS attempts, MAX(taken_at) AS last_taken
    FROM scores
    GROUP BY user_name
    ORDER BY best_percentage DESC, attempts DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// Quiz list for selects
$quizList = $conn->query("SELECT id, title FROM quizzes ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);

// Questions listing (with search)
$searchQ = trim($_GET['q'] ?? '');
$filterQuiz = intval($_GET['quiz_filter'] ?? 0);

$qWhere = [];
if ($searchQ !== '') {
    $kw = $conn->real_escape_string('%' . $searchQ . '%');
    $qWhere[] = "(question LIKE '{$kw}' OR option1 LIKE '{$kw}' OR option2 LIKE '{$kw}' OR option3 LIKE '{$kw}' OR option4 LIKE '{$kw}')";
}
if ($filterQuiz) {
    $qWhere[] = "quiz_id = " . intval($filterQuiz);
}
$whereSQL = $qWhere ? 'WHERE ' . implode(' AND ', $qWhere) : '';
$questionsAll = $conn->query("SELECT q.*, qu.title AS quiz_title FROM questions q LEFT JOIN quizzes qu ON q.quiz_id = qu.id $whereSQL ORDER BY q.id DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

// Per-quiz statistics (attempts, avg, best)
$quizStats = $conn->query("
    SELECT qu.id, qu.title,
           COUNT(s.id) AS attempts,
           IFNULL(ROUND(AVG(s.percentage),2),0) AS avg_percentage,
           IFNULL(MAX(s.percentage),0) AS best_percentage
    FROM quizzes qu
    LEFT JOIN scores s ON qu.id = s.quiz_id
    GROUP BY qu.id
    ORDER BY qu.title ASC
")->fetch_all(MYSQLI_ASSOC);

// Recent users (last 20 who attempted)
$recentUsers = $conn->query("SELECT DISTINCT user_name FROM scores ORDER BY taken_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard — QuizHub</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{ --bg:#060606; --card:#0f0f0f; --accent:#ffcc00; --muted:#bdbdbd; }
  body{ background:var(--bg); color:#fff; font-family:Inter, Arial, sans-serif; min-height:100vh;}
  .app { display:flex; gap:20px; max-width:1300px; margin:20px auto; padding:10px; }
  .sidebar { width:260px; background: linear-gradient(#0b0b0b,#0e0e0e); border:1px solid #222; border-radius:10px; padding:18px; }
  .content { flex:1; }
  .nav-link { color:#ddd; display:block; padding:8px 10px; border-radius:6px; }
  .nav-link:hover, .nav-link.active { background:#111; color:var(--accent); text-decoration:none; }
  .card { background:linear-gradient(#111,#0e0e0e); border:1px solid #222; color:#eee; }
  .overview .card { padding:18px; border-radius:10px; }
  .muted { color:var(--muted); }
  .text-accent { color:var(--accent); font-weight:700; }
  .table thead { background:#111; }
  .small-muted { color:#9d9d9d; font-size:0.9rem; }
  .form-control, .form-select { background:#0c0c0c; color:#fff; border:1px solid #222; }
  .btn-warning { background:var(--accent); color:#111; border:none; font-weight:700; }
  .search-row { gap:8px; display:flex; align-items:center; }
  .leader-row { display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .leader-name { font-weight:700; color:#fff; }
  .medal-gold { color:#ffd700; } .medal-silver { color:#c0c0c0; } .medal-bronze { color:#cd7f32; }
  /* small screens */
  @media (max-width:880px){ .app{flex-direction:column; padding:8px;} .sidebar{width:100%;} }
</style>
</head>
<body>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center py-3">
    <h3 class="mb-0 text-accent">QuizHub — Admin</h3>
    <div>
      <span class="me-3 small-muted">Signed in as <strong><?php echo esc($_SESSION['admin']); ?></strong></span>
      <a href="logout.php" class="btn btn-sm btn-dark">Logout</a>
    </div>
  </div>

  <div class="app">
    <!-- SIDEBAR -->
    <aside class="sidebar">
      <nav class="mb-3">
        <a class="nav-link active" href="#overview" onclick="showSection('overview')">🏠 Overview</a>
        <a class="nav-link" href="#quizzes" onclick="showSection('quizzes')">🧾 Manage Quizzes</a>
        <a class="nav-link" href="#questions" onclick="showSection('questions')">❓ Manage Questions</a>
        <a class="nav-link" href="#stats" onclick="showSection('stats')">📊 Quiz Statistics</a>
        <a class="nav-link" href="#attempts" onclick="showSection('attempts')">🧑‍🎓 Users & Attempts</a>
        <a class="nav-link" href="#leaderboard" onclick="showSection('leaderboard')">🏆 Leaderboard</a>
        <a class="nav-link" href="#settings" onclick="showSection('settings')">⚙️ Settings</a>
      </nav>

      <div class="mt-3">
        <h6 class="small-muted">Quick Actions</h6>
        <div class="d-grid gap-2">
          <button class="btn btn-warning" onclick="showSection('quizzes'); document.getElementById('quiz-add-title').focus();">Add Quiz</button>
          <button class="btn btn-outline-warning" onclick="showSection('questions'); document.getElementById('question-add-text').focus();">Add Question</button>
        </div>
      </div>
    </aside>

    <!-- CONTENT -->
    <main class="content">

      <!-- top messages -->
      <?php if (!empty($messages)): ?>
        <div class="mb-3">
          <?php foreach ($messages as $m): ?>
            <div class="alert alert-success py-2"><?php echo esc($m); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- OVERVIEW -->
      <section id="overview-section">
        <div id="overview" class="">
          <div class="row overview mb-3">
            <div class="col-md-3 mb-2">
              <div class="card p-3">
                <div class="small-muted">Total Quizzes</div>
                <div class="h4 text-accent"><?php echo (int)$totalQuizzes; ?></div>
              </div>
            </div>
            <div class="col-md-3 mb-2">
              <div class="card p-3">
                <div class="small-muted">Total Questions</div>
                <div class="h4 text-accent"><?php echo (int)$totalQuestions; ?></div>
              </div>
            </div>
            <div class="col-md-3 mb-2">
              <div class="card p-3">
                <div class="small-muted">Total Users</div>
                <div class="h4 text-accent"><?php echo (int)$totalUsers; ?></div>
              </div>
            </div>
            <div class="col-md-3 mb-2">
              <div class="card p-3">
                <div class="small-muted">Total Attempts</div>
                <div class="h4 text-accent"><?php echo (int)$totalAttempts; ?></div>
              </div>
            </div>
          </div>

          <div class="card p-3 mb-3">
            <h5 class="mb-2">Recent Attempts (last 20)</h5>
            <div class="table-responsive">
              <table class="table table-dark table-striped">
                <thead>
                  <tr><th>User</th><th>Quiz</th><th>Score</th><th>Correct</th><th>Total Q</th><th>Taken At</th></tr>
                </thead>
                <tbody>
                  <?php foreach($recentAttempts as $r): ?>
                    <tr>
                      <td><?php echo esc($r['user_name']); ?></td>
                      <td><?php echo esc($r['quiz_title'] ?: '—'); ?></td>
                      <td><?php echo esc($r['percentage']); ?>%</td>
                      <td><?php echo esc($r['correct_answers']); ?></td>
                      <td><?php echo esc($r['total_questions']); ?></td>
                      <td><?php echo esc($r['taken_at']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- MANAGE QUIZZES -->
      <section id="quizzes-section" style="display:none">
        <div id="quizzes" class="card p-3 mb-3">
          <h4 class="mb-3">Manage Quizzes</h4>
          <div class="row g-3">
            <div class="col-md-5">
              <form method="post" class="card p-3">
                <h6>Add New Quiz</h6>
                <input id="quiz-add-title" name="title" class="form-control mb-2" placeholder="Quiz Title" required>
                <textarea name="description" class="form-control mb-2" placeholder="Short Description"></textarea>
                <input type="hidden" name="action" value="add_quiz">
                <button class="btn btn-warning w-100">Add Quiz</button>
              </form>
            </div>

            <div class="col-md-7">
              <h6>All Quizzes</h6>
              <div class="table-responsive card p-2">
                <table class="table table-dark table-hover">
                  <thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Actions</th></tr></thead>
                  <tbody>
                    <?php
                      $allQuizzes = $conn->query("SELECT * FROM quizzes ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
                      foreach($allQuizzes as $q):
                    ?>
                    <tr>
                      <td><?php echo esc($q['id']); ?></td>
                      <td><?php echo esc($q['title']); ?></td>
                      <td><?php echo esc($q['description']); ?></td>
                      <td>
                        <button class="btn btn-sm btn-outline-warning" onclick='openEditQuizModal(<?php echo json_encode($q); ?>)'>Edit</button>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Delete this quiz and its data?');">
                          <input type="hidden" name="action" value="delete_quiz">
                          <input type="hidden" name="quiz_id" value="<?php echo esc($q['id']); ?>">
                          <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- MANAGE QUESTIONS -->
      <section id="questions-section" style="display:none">
        <div class="card p-3 mb-3">
          <h4>Manage Questions</h4>
          <div class="row mb-3">
            <div class="col-md-5">
              <form method="post" class="card p-3">
                <h6>Add Question</h6>
                <select name="quiz_id" class="form-select mb-2" required>
                  <option value="">Select Quiz</option>
                  <?php foreach($quizList as $qq): ?>
                    <option value="<?php echo (int)$qq['id']; ?>"><?php echo esc($qq['title']); ?></option>
                  <?php endforeach; ?>
                </select>
                <textarea id="question-add-text" name="question" class="form-control mb-2" placeholder="Question" required></textarea>
                <input name="option1" class="form-control mb-2" placeholder="Option 1 (required)" required>
                <input name="option2" class="form-control mb-2" placeholder="Option 2 (required)" required>
                <input name="option3" class="form-control mb-2" placeholder="Option 3">
                <input name="option4" class="form-control mb-2" placeholder="Option 4">
                <input name="correct_option" type="number" min="1" max="4" class="form-control mb-2" placeholder="Correct Option (1-4)" required>
                <input type="hidden" name="action" value="add_question">
                <button class="btn btn-warning w-100">Add Question</button>
              </form>
            </div>

            <div class="col-md-7">
              <div class="d-flex mb-2 align-items-center">
                <div class="me-2 small-muted">Filter</div>
                <form method="get" class="d-flex w-100">
                  <input name="q" class="form-control me-2" placeholder="Search question/options" value="<?php echo esc($searchQ); ?>">
                  <select name="quiz_filter" class="form-select me-2">
                    <option value="0">All quizzes</option>
                    <?php foreach($quizList as $qq): ?>
                      <option value="<?php echo (int)$qq['id']; ?>" <?php if($filterQuiz == $qq['id']) echo 'selected'; ?>><?php echo esc($qq['title']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-dark">Search</button>
                </form>
              </div>

              <div class="table-responsive card p-2">
                <table class="table table-dark table-hover">
                  <thead><tr><th>ID</th><th>Quiz</th><th>Question</th><th>Correct</th><th>Actions</th></tr></thead>
                  <tbody>
                    <?php foreach($questionsAll as $qq): ?>
                      <tr>
                        <td><?php echo esc($qq['id']); ?></td>
                        <td><?php echo esc($qq['quiz_title'] ?? '—'); ?></td>
                        <td><?php echo esc($qq['question']); ?></td>
                        <td><?php echo esc($qq['correct_option']); ?></td>
                        <td>
                          <button class="btn btn-sm btn-outline-warning" onclick='openEditQuestionModal(<?php echo json_encode($qq); ?>)'>Edit</button>
                          <form method="post" style="display:inline-block" onsubmit="return confirm('Delete question?');">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="qid" value="<?php echo esc($qq['id']); ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- STATS -->
      <section id="stats-section" style="display:none">
        <div class="card p-3 mb-3">
          <h4>Quiz Statistics</h4>
          <div class="table-responsive">
            <table class="table table-dark table-striped">
              <thead><tr><th>Quiz</th><th>Attempts</th><th>Avg %</th><th>Best %</th></tr></thead>
              <tbody>
                <?php foreach($quizStats as $qs): ?>
                  <tr>
                    <td><?php echo esc($qs['title']); ?></td>
                    <td><?php echo esc($qs['attempts']); ?></td>
                    <td><?php echo esc($qs['avg_percentage']); ?>%</td>
                    <td><?php echo esc($qs['best_percentage']); ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- USERS & ATTEMPTS -->
      <section id="attempts-section" style="display:none">
        <div class="card p-3 mb-3">
          <h4>Users & Attempts</h4>
          <p class="small-muted">Recent attempts are visible in Overview. Use query below to fetch user's attempts history.</p>
          <div class="row">
            <div class="col-md-4 mb-3">
              <h6>Top Users / Quick lookup</h6>
              <ul class="list-unstyled">
                <?php foreach($recentUsers as $u): ?>
                  <li><a href="#" onclick="showUserAttempts('<?php echo esc($u['user_name']); ?>'); return false;"><?php echo esc($u['user_name']); ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="col-md-8">
              <div id="userAttemptsPanel">
                <h6 class="muted">Select a user to view their recent attempts</h6>
                <div id="userAttemptsResult"></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- LEADERBOARD -->
      <section id="leaderboard-section" style="display:none">
        <div class="card p-3 mb-3">
          <h4>Leaderboard Preview</h4>
          <div class="table-responsive">
            <table class="table table-dark">
              <thead><tr><th>Rank</th><th>User</th><th>Best %</th><th>Attempts</th><th>Last</th></tr></thead>
              <tbody>
                <?php $rank=1; foreach($leaderboard as $lb): ?>
                  <tr>
                    <td><?php echo $rank++; ?></td>
                    <td>
                      <a href="#" onclick="showUserAttempts('<?php echo esc($lb['user_name']); ?>'); return false;"><?php echo esc($lb['user_name']); ?></a>
                    </td>
                    <td><?php echo esc($lb['best_percentage']); ?>%</td>
                    <td><?php echo esc($lb['attempts']); ?></td>
                    <td><?php echo esc($lb['last_taken']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- SETTINGS -->
      <section id="settings-section" style="display:none">
        <div class="card p-3 mb-3">
          <h4>Settings</h4>
          <p class="small-muted">Security & Admin settings placeholder. You can add options like export CSV, change admin password, backup DB, set quiz defaults, etc.</p>

          <div class="row">
            <div class="col-md-6">
              <form onsubmit="alert('Not implemented in demo.'); return false;">
                <h6>Export</h6>
                <div class="mb-2">
                  <select class="form-select">
                    <option>Export recent attempts (CSV)</option>
                    <option>Export leaderboards (CSV)</option>
                  </select>
                </div>
                <button class="btn btn-dark">Export</button>
              </form>
            </div>
            <div class="col-md-6">
              <form onsubmit="alert('Not implemented in demo.'); return false;">
                <h6>Admin Controls</h6>
                <div class="mb-2">
                  <input class="form-control" placeholder="New admin username">
                </div>
                <button class="btn btn-warning">Add Admin</button>
              </form>
            </div>
          </div>

        </div>
      </section>

    </main>
  </div>
</div>

<!-- EDIT MODALS -->
<!-- Edit Quiz Modal -->
<div class="modal fade" id="editQuizModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Edit Quiz</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editQuizForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_quiz">
          <input type="hidden" name="quiz_id" id="editQuizId">
          <div class="mb-2">
            <label class="form-label">Title</label>
            <input id="editQuizTitle" name="title" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea id="editQuizDesc" name="description" class="form-control"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-warning" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Question Modal -->
<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header">
        <h5 class="modal-title">Edit Question</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editQuestionForm">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_question">
          <input type="hidden" name="qid" id="editQId">
          <div class="mb-2">
            <label class="form-label">Quiz</label>
            <select name="quiz_id" id="editQQuiz" class="form-select" required>
              <?php foreach($quizList as $qq): ?>
                <option value="<?php echo (int)$qq['id']; ?>"><?php echo esc($qq['title']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Question</label>
            <textarea id="editQText" name="question" class="form-control" required></textarea>
          </div>
          <div class="row g-2">
            <div class="col-md-6"><input id="editQO1" name="option1" class="form-control" placeholder="Option 1" required></div>
            <div class="col-md-6"><input id="editQO2" name="option2" class="form-control" placeholder="Option 2" required></div>
            <div class="col-md-6"><input id="editQO3" name="option3" class="form-control" placeholder="Option 3"></div>
            <div class="col-md-6"><input id="editQO4" name="option4" class="form-control" placeholder="Option 4"></div>
          </div>
          <div class="mt-2">
            <label class="form-label">Correct Option (1-4)</label>
            <input id="editQCorrect" name="correct_option" class="form-control" type="number" min="1" max="4" required>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-warning" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // UI routing between sections
  function showSection(id) {
    document.querySelectorAll('main .content > *').forEach(()=>{}); // no-op to satisfy linter
    const sections = {
      overview: document.getElementById('overview-section'),
      quizzes: document.getElementById('quizzes-section'),
      questions: document.getElementById('questions-section'),
      stats: document.getElementById('stats-section'),
      attempts: document.getElementById('attempts-section'),
      leaderboard: document.getElementById('leaderboard-section'),
      settings: document.getElementById('settings-section')
    };
    Object.keys(sections).forEach(k => {
      if (sections[k]) sections[k].style.display = (k === id) ? 'block' : 'none';
    });
    // make sidebar active
    document.querySelectorAll('.sidebar .nav-link').forEach(a => a.classList.remove('active'));
    const el = document.querySelector('.sidebar .nav-link[href="#'+id+'"]');
    if (el) el.classList.add('active');
    // Scroll top
    window.scrollTo({top:0,behavior:'smooth'});
  }

  // Edit quiz modal population
  function openEditQuizModal(obj) {
    const modalEl = new bootstrap.Modal(document.getElementById('editQuizModal'));
    document.getElementById('editQuizId').value = obj.id;
    document.getElementById('editQuizTitle').value = obj.title;
    document.getElementById('editQuizDesc').value = obj.description || '';
    modalEl.show();
  }

  // Edit question modal
  function openEditQuestionModal(obj) {
    const modalEl = new bootstrap.Modal(document.getElementById('editQuestionModal'));
    document.getElementById('editQId').value = obj.id;
    document.getElementById('editQQuiz').value = obj.quiz_id;
    document.getElementById('editQText').value = obj.question;
    document.getElementById('editQO1').value = obj.option1 || '';
    document.getElementById('editQO2').value = obj.option2 || '';
    document.getElementById('editQO3').value = obj.option3 || '';
    document.getElementById('editQO4').value = obj.option4 || '';
    document.getElementById('editQCorrect').value = obj.correct_option || 1;
    modalEl.show();
  }

  // Show user attempts (AJAX-ish using fetch to backend endpoint)
  async function showUserAttempts(userName) {
    const panel = document.getElementById('userAttemptsResult');
    panel.innerHTML = '<div class="small-muted">Loading...</div>';
    try {
      const res = await fetch('../api/get_user_attempts.php?user=' + encodeURIComponent(userName));
      if (!res.ok) throw new Error('Network');
      const data = await res.json();
      if (!Array.isArray(data)) throw new Error('Unexpected');
      if (data.length === 0) {
        panel.innerHTML = '<div class="small-muted">No attempts for ' + userName + '</div>';
        return;
      }
      let html = '<h6>Recent attempts for <strong>' + userName + '</strong></h6>';
      html += '<table class="table table-dark table-sm"><thead><tr><th>Quiz</th><th>%</th><th>Correct</th><th>Total</th><th>When</th></tr></thead><tbody>';
      data.forEach(r => {
        html += '<tr><td>' + (r.quiz_title || '-') + '</td><td>' + (r.percentage || 0) + '%</td><td>' + (r.correct_answers || 0) + '</td><td>' + (r.total_questions || 0) + '</td><td>' + (r.taken_at || '-') + '</td></tr>';
      });
      html += '</tbody></table>';
      panel.innerHTML = html;
      // scroll panel to view
      panel.scrollIntoView({behavior:'smooth'});
    } catch (err) {
      panel.innerHTML = '<div class="text-danger small-muted">Error fetching attempts.</div>';
      console.error(err);
    }
  }

  // Bind: when page loads show overview
  (function init(){
    showSection('overview');
  })();
</script>
</body>
</html>
