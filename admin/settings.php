<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();


// Load current settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $stmt = $pdo->prepare("UPDATE settings SET site_name = ?, admin_email = ?, banner_color = ?, email_subject = ?, email_body = ?, smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_secure = ?, halo_url = ?, halo_client_id = ?, halo_client_secret = ? WHERE id = 1");
    $stmt->execute([
        $_POST['site_name'] ?? '',
        $_POST['admin_email'] ?? '',
        $_POST['banner_color'] ?? '#007bff',
        $_POST['email_subject'] ?? '',
        $_POST['email_body'] ?? '',
        $_POST['smtp_host'] ?? '',
        $_POST['smtp_port'] ?? '',
        $_POST['smtp_username'] ?? '',
        $_POST['smtp_password'] ?? '',
        $_POST['smtp_secure'] ?? '',
        $_POST['halo_url'] ?? '',
        $_POST['halo_client_id'] ?? '',
        $_POST['halo_client_secret'] ?? ''
    ]);


    // if($_POST['halo_url']!='' && $_POST['halo_client_id']!='' && $_POST['halo_client_secret']!=''){

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //     CURLOPT_URL => $_POST['halo_url'].'/auth/token',
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_ENCODING => '',
    //     CURLOPT_MAXREDIRS => 10,
    //     CURLOPT_TIMEOUT => 0,
    //     CURLOPT_FOLLOWLOCATION => true,
    //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //     CURLOPT_CUSTOMREQUEST => 'POST',
    //     CURLOPT_POSTFIELDS => 'grant_type=client_credentials&client_id='.$_POST['halo_client_id'].'&client_secret='.$_POST['halo_client_secret'].'&scope=all',
    //     CURLOPT_HTTPHEADER => array(
    //         'Content-Type: application/x-www-form-urlencoded',
    //         'Accept: application/json'
    //     ),
    //     ));

    //     $response = curl_exec($curl);

    //     curl_close($curl);
    //     echo $response;


    //         $assigneduser ='';
    //     if($_SESSION['user_id']){
    //         $assigneduser = $_SESSION['user_id'];
    //     }

    //     $responseData = json_decode($response, true);

    //     if ($responseData && isset($responseData['access_token'])) {

    //         $access_token = $responseData['access_token'];
    //         $refresh_token = $responseData['refresh_token'] ?? '';
    //         $id_token = $responseData['id_token'] ?? '';
    //         $created_at = date('Y-m-d H:i:s');
    //         $user_id = $assigneduser; // or dynamically set the user_id if available

    //        // Check if record for user_id exists
    //         $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM haloapi WHERE user_id = ?");
    //         $checkStmt->execute([$user_id]);
    //         $exists = $checkStmt->fetchColumn();

    //         if ($exists) {
    //             // Update existing record
    //             $updateStmt = $pdo->prepare("UPDATE haloapi SET access_token = ?, refresh_token = ?, id_token = ?, created_at = ? WHERE user_id = ?");
    //             $updateStmt->execute([$access_token, $refresh_token, $id_token, $created_at, $user_id]);
    //         } else {
    //             // Insert new record
    //             $insertStmt = $pdo->prepare("INSERT INTO haloapi (user_id, access_token, refresh_token, id_token, created_at) VALUES (?, ?, ?, ?, ?)");
    //             $insertStmt->execute([$user_id, $access_token, $refresh_token, $id_token, $created_at]);
    //         }

    //     }


    // }



    header('Location: settings.php?saved=1');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = $user['is_admin'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings - Form Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Settings</h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Settings updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="site-tab" data-bs-toggle="tab" data-bs-target="#site" type="button">Site Settings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">Email Settings</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button">SMTP Settings</button>
            </li>

            <!-- <li class="nav-item" role="presentation">
                <button class="nav-link" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#halo" type="button">Halo Connection</button>
            </li> -->
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="site">
                <div class="mb-3">
                    <label class="form-label">Site Name</label>
                    <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Banner Color</label>
                    <input type="color" name="banner_color" class="form-control form-control-color" value="<?= htmlspecialchars($settings['banner_color'] ?? '#007bff') ?>">
                </div>
            </div>

            <div class="tab-pane fade" id="email">
                <div class="mb-3">
                    <label class="form-label">Email Subject</label>
                    <input type="text" name="email_subject" class="form-control" value="<?= htmlspecialchars($settings['email_subject'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Body Template</label>
                    <textarea name="email_body" class="form-control" rows="6"><?= htmlspecialchars($settings['email_body'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="tab-pane fade" id="smtp">
                <div class="mb-3">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">SMTP Secure (tls/ssl)</label>
                    <input type="text" name="smtp_secure" class="form-control" value="<?= htmlspecialchars($settings['smtp_secure'] ?? '') ?>">
                </div>
            </div>

            <!-- <div class="tab-pane fade" id="halo">
                <div class="mb-3">
                    <label class="form-label">Halo Url</label>
                    <input type="text" name="halo_url" class="form-control" value="<?= htmlspecialchars($settings['halo_url'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Halo client id</label>
                    <input type="text" name="halo_client_id" class="form-control" value="<?= htmlspecialchars($settings['halo_client_id'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Halo client secret</label>
                    <input type="password" name="halo_client_secret" class="form-control" value="<?= htmlspecialchars($settings['halo_client_secret'] ?? '') ?>">
                </div>
            </div> -->

        </div>
        
        <?php if($is_admin=='1'){?>
        <div class="mt-4">
            <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
        </div>
        <?php } ?>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
