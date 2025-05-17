<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ .'../../2fa/TotpAuthenticator.php';

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return "$protocol$host$basePath";
}

$error = '';
$username = $_SESSION['username'];


$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$mainuser = $stmt->fetch();

$tfa = new TotpAuthenticator();
if($mainuser['twofa_secret']!=''){
    $secret = $mainuser['twofa_secret'];
}else{
    $secret = $tfa->getSecret();
}


 
$issuer = 'Formbuilder';

$stmt = $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE username = ?");
$stmt->execute([$secret, $username]);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    $code = $_POST['code']; // From user
    if ($tfa->verifyCode($secret, $code)) {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if($user['username']!=''){
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
            $_SESSION['is_external'] = $user['is_external'] ?? 0;
            header('Location: '.getBaseUrl().'/dashboard.php');


        }
        
    } else {
        $error =  "Invalid code.";
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

                        
                    <?php
                    
                    $user = $_SESSION['username'];
                    $otpauth = 'otpauth://totp/' . rawurlencode($issuer . ':' . $user) .
                            '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);

                    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauth);


                    ?>
                    
                    <div class="text-center">
                        <h4>Scan this QR with Google Authenticator:</h4>
                        <img src="<?= $qrUrl ?>" alt="QR Code">
                        <p>Or manually enter this secret: <b><?= $secret ?></b></p>
                    </div>


                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control" required autofocus>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                        <div class="text-end mt-2">
                            <a href="login.php">Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
