<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Include PHPMailer
require_once __DIR__ . '/sendemail.php'; // Include sendemail

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return "$protocol$host$basePath";
}

$msg= '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$email) {
        echo "Invalid email address.";
        exit;
    }

    // Check user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $reset_token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiration = NOW() + INTERVAL 1 HOUR WHERE email = ?");
        $stmt->execute([$reset_token, $email]);

    

        $reset_link = getBaseUrl()."/reset_password.php?token=$reset_token";
        $subject = "Password Reset Request";
        $htmlBody = "Click the link below to reset your password:<br><br><a href='$reset_link'>$reset_link</a>";
        $plainBody = "Click the link below to reset your password:\n\n$reset_link";

        $stmt = $pdo->prepare("SELECT * FROM settings LIMIT 1");
        $stmt->execute();
        $smtpSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        $sent = sendCustomEmail($email, $subject, $htmlBody, $plainBody, $smtpSettings);


        if($sent){
            $msg = "If your email is registered, you'll receive a password reset link shortly.";
        }else{
            $err =  "Failed to send email. Please try again later.";
        }
    } else {
        $msg =  "If your email is registered, you'll receive a password reset link shortly.";
    }
}

?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Forgot Password</h1>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
        <a href="login.php" class="btn btn-secondary">Back</a>
    </form>


   <?php if (!empty($err)): ?>
    <div class="alert alert-error mt-4"><?php echo $err; ?></div>
<?php endif; ?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success mt-4"><?php echo $msg; ?></div>
<?php endif; ?>


</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
