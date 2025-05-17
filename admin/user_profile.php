<?php
require_once __DIR__ . '/../includes/db.php';

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_login();

$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/admin');



$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    logError("No user ID provided");
    die("No user ID provided.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin_user = $stmt->fetch();

    if (!$admin_user) {
        logError("User not found for ID: $user_id");
        die("User not found.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log all POST data for debugging
        // logError("POST Data: " . print_r($_POST, true));

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $logo_path = $admin_user['avatar_path'] ?? null;
        
        $twofa = $_POST['twofa'] ?? 0;
        

        // echo $_FILES['logo']['name'];
        if (!empty($_FILES['logo']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'svg'];
            if (in_array($ext, $allowed_exts)) {
                $safe_name = uniqid('logo_', true) . '.' . $ext;
                $upload_path = $upload_dir . $safe_name;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if (!empty($logo_path) && file_exists(__DIR__ . '/../' . $logo_path)) {
                        unlink(__DIR__ . '/../' . $logo_path);
                    }
                    $logo_path = 'uploads/' . $safe_name;
                }
            }
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update user details
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?,twofa_enabled = ?, avatar_path = ? WHERE id = ?");
        $stmt->execute([$name, $email,$twofa, $logo_path, $user_id]);

        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
        }

        // Commit transaction
        $pdo->commit();

        // Log successful update
        // logError("User updated successfully: User ID $user_id");

        // Redirect with success message
        header("Location: user_profile.php?updated=1");
        exit;
    }

   
} catch (Exception $e) {
    // Roll back transaction if it's active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    // logError("Error: " . $e->getMessage());

    // Display error
    die("An error occurred: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Edit User</h1>

    <form method="post" enctype="multipart/form-data">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">User Details</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars((string)$admin_user['name']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" disabled value="<?= htmlspecialchars((string)$admin_user['username']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars((string)$admin_user['email']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">More settings</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Add a avatar</label>
                         <input type="file" name="logo" class="form-control">
                        <?php if (!empty($admin_user['avatar_path'])): ?>
                            <div class="mt-3">
                                <img src="<?= htmlspecialchars($base_path . '/' . $admin_user['avatar_path']) ?>" alt="Logo" style="max-height: 80px;">
                            </div>
                        <?php endif; ?>
                        
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">2FA security</label>
                        <br>
                        <input type="checkbox"  <?=$admin_user['twofa_enabled']==1?'checked':''; ?> name="twofa" value="1"> Turn On<br>
                        <small>You have receive a code in your registered email every time you logged in.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <div>
                <button class="btn btn-success">Save Changes</button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>