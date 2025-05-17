<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/sendemail.php'; // Include sendemail


function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return "$protocol$host$basePath";
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();


     if ($user && password_verify($password, $user['password'])) {

         if($user['twofa_enabled']==1){
                
                $_SESSION['username'] = $_POST['username'];
                    // $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    // $stmt = $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE username = ?");
                    // $stmt->execute([$code, $username]);

                    // $name = $user['name'];
                    // $email = $user['email'];
                    // $subject = "Login security code";
                    // $htmlBody = "Hi $name,<br>
                    // Your login security code: <strong>$code</strong>
                    // <br><br>
                    // Thanks,<br>
                    // MSP form builder
                    // ";
                    // $plainBody = " Your login security code: <strong>$code</strong>";
                    
                    // $stmt = $pdo->prepare("SELECT * FROM settings LIMIT 1");
                    // $stmt->execute();
                    // $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

                    // $sent = sendCustomEmail($email, $subject, $htmlBody, $plainBody, $smtpSettings);

                    // header('Location: /formbuilder/admin/2fa.php');
                    header("Location: " . getBaseUrl() . "/2fa.php");

            }else{
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
                $_SESSION['is_external'] = $user['is_external'] ?? 0;
        
                // Redirect to dashboard or external view
                // if (!empty($user['is_external'])) {
                //     header('Location: /formbuilder/forms/form_list.php');
                // } else {
                    // header('Location: /formbuilder/admin/dashboard.php');
                    header("Location: dashboard.php");

                // }
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    
   

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Form Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="text-center mb-4">🔐 Login</h3>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                        <div class="text-end mt-2">
                            <a href="forgot_password.php">Forgot Password?</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
