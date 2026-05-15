<?php include 'php/database.php'; session_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>QuizHub</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body {
        background: linear-gradient(to bottom, #0e0e0e, #000);
        color: white;
        font-family: Arial, sans-serif;
        min-height: 100vh;
    }

    /* Navbar */
    #mainNav {
        background: #000;
        padding: 15px 30px;
        box-shadow: 0 0 10px rgba(255, 255, 0, 0.2);
    }

    .text-yellow {
        color: #ffcc00 !important;
        font-weight: bold;
        font-size: 22px;
    }

    /* Card Center */
    .welcome-card {
        background: #111;
        border: 1px solid #2a2a2a;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        max-width: 700px;
        margin: 80px auto;
        box-shadow: 0 0 20px rgba(255, 255, 0, 0.15);
    }

    h1 {
        font-size: 38px;
        margin-bottom: 15px;
    }

    .btn-warning {
        background: #ffcc00;
        border: none;
        padding: 12px 25px;
        font-size: 17px;
        font-weight: bold;
        border-radius: 6px;
    }

    .btn-warning:hover {
        background: #ffdb4d;
    }

    .btn-outline-warning {
        border-width: 2px;
        font-size: 17px;
        padding: 12px 25px;
        border-radius: 6px;
    }
</style>
</head>

<body>

<nav class="navbar" id="mainNav">
    <div class="container-fluid">
        <a class="navbar-brand text-yellow" href="index.php">QuizHub</a>

        <div class="ms-auto">
            <?php if(isset($_SESSION['user'])): ?>
                <span class="text-yellow me-3">Hello, <?php echo htmlspecialchars($_SESSION['user']['username']); ?></span>
                <a class="btn btn-dark btn-sm" href="logout.php">Logout</a>
            <?php else: ?>
                <a class="btn btn-dark btn-sm me-2" href="login.php">Login</a>
                <a class="btn btn-warning btn-sm" href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="welcome-card">
    <h1>Welcome to QuizHub</h1>
    <p class="lead">Black & Yellow theme. Create quizzes and track results.</p>

    <div class="mt-4">
        <a class="btn btn-warning me-3" href="quiz.php">Take Quiz</a>
        <a class="btn btn-outline-warning" href="admin/login.php">Admin Panel</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
