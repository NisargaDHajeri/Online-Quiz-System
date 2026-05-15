<?php 
session_start(); 
include '../php/database.php'; 

$error=''; 
if($_SERVER['REQUEST_METHOD']==='POST'){
    $u = strtoupper(trim($_POST['username'] ?? ''));
    $p = $_POST['password'] ?? '';
    $stmt=$conn->prepare('SELECT id,username,password FROM admin WHERE username=?');
    $stmt->bind_param('s',$u);
    $stmt->execute();
    $res=$stmt->get_result();
    if($row=$res->fetch_assoc()){
        if(password_verify($p,$row['password'])){
            $_SESSION['admin']=$u;
            header('Location: dashboard.php');
            exit;
        } else $error='Invalid credentials';
    } else $error='Invalid credentials';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    body{
        background: linear-gradient(to bottom, #1a1a1a, #000);
        color: white;
        min-height: 100vh;
        display:flex;
        justify-content:center;
        align-items:center;
        font-family: Arial, sans-serif;
    }

    .login-box{
        background:#121212;
        padding:45px 40px;
        border-radius:12px;
        width:420px;
        box-shadow:0 0 18px rgba(255,255,0,0.3);
        border:1px solid #3a3a3a;
        text-align:center;
        animation: fadeIn 0.6s ease-in-out;
    }

    @keyframes fadeIn{
        from{ opacity:0; transform:scale(0.95); }
        to{ opacity:1; transform:scale(1); }
    }

    h2{
        font-size:32px;
        margin-bottom:25px;
        color:#ffcc00;
        font-weight:bold;
    }

    .form-control{
        background:#1c1c1c !important;
        border:1px solid #777 !important;
        color:#fff !important;
        height:52px;
        font-size:18px;
        border-radius:8px;
        padding-left:14px;
    }

    .form-control::placeholder{
        color:#c9c9c9 !important;
    }

    .form-control:focus{
        border-color:#ffcc00 !important;
        box-shadow:0 0 6px #ffcc00 !important;
    }

    .btn-warning{
        background:#ffcc00 !important;
        border:none;
        width:100%;
        font-size:20px;
        padding:14px;
        border-radius:8px;
        font-weight:bold;
        margin-top:10px;
        color:#000;
    }

    .btn-warning:hover{
        background:#ffd633 !important;
    }

    #togglePassword{
        position:absolute;
        right:14px;
        top:14px;
        cursor:pointer;
        color:#ffcc00;
        font-size:20px;
    }
</style>
</head>

<body>

<div class="login-box">
    <h2>Admin Login</h2>

    <?php if($error): ?>
        <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">

        <input name="username" class="form-control mb-3" placeholder="Enter Admin Username" required>

        <div class="position-relative mb-3">
            <input name="password" id="passwordField" type="password" class="form-control" placeholder="Enter Admin Password" required>
            <span id="togglePassword">👁️</span>
        </div>

        <button class="btn btn-warning">Login</button>

    </form>

</div>

<script>
const passwordField = document.getElementById("passwordField");
const togglePassword = document.getElementById("togglePassword");

togglePassword.addEventListener("click", () => {
    if(passwordField.type === "password"){
        passwordField.type = "text";
        togglePassword.textContent = "🙈";
    } else {
        passwordField.type = "password";
        togglePassword.textContent = "👁️";
    }
});
</script>

</body>
</html>
