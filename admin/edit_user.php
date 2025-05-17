<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Function to log errors
function logError($message) {
    // $log_path = __DIR__ . '/user_edit_error_log.txt';
    // file_put_contents($log_path, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    logError("No user ID provided");
    die("No user ID provided.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $useradmin = $user;

    if (!$user) {
        logError("User not found for ID: $user_id");
        die("User not found.");
    }

    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log all POST data for debugging
        logError("POST Data: " . print_r($_POST, true));
        
        $name = $_POST['name'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'external';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $is_external = $_POST['role']=='external' ? 1 : 0;
        
        // Validate required fields
        if (empty($username)) {
            throw new Exception("Username is required");
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update user details
         $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, is_external = ?, is_admin = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role,$is_external, $is_admin, $user_id]);


        // Update password if provided
        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
        }

        // Commit transaction
        $pdo->commit();

        // Log successful update
        logError("User updated successfully: User ID $user_id");

        // Redirect with success message
        header("Location: users.php?updated=1");
        exit;
    }

    // Handle user deletion
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Log deletion
        logError("User deleted: User ID $user_id");
        
        header("Location: users.php?deleted=1");
        exit;
    }
} catch (Exception $e) {
    // Roll back transaction if it's active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the error
    logError("Error: " . $e->getMessage());

    // Display error
    die("An error occurred: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>



<div class="container py-5">
    <h1 class="mb-4">Edit User</h1>

    <form method="post">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h4 class="mb-0">User Details</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars((string)$useradmin['name']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars((string)$useradmin['username']) ?>" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars((string)$useradmin['email']) ?>" class="form-control">
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
                <h4 class="mb-0">User Permissions</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="internal" <?= $useradmin['role'] === 'internal' ? 'selected' : '' ?>>Internal</option>
                            <option value="external" <?= $useradmin['role'] === 'external' ? 'selected' : '' ?>>External</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="is_admin" class="form-check-input" id="adminCheck" <?= $useradmin['is_admin'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="adminCheck">Is Admin</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <div>
                <button class="btn btn-success">Save Changes</button>
            </div>
            <div>
                <a href="?id=<?= $user_id ?>&delete=1" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">🗑 Delete User</a>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>