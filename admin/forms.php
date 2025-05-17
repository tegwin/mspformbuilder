<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$assigneduser = '';
$is_admin= '';
if($_SESSION['user_id']){
    $assigneduser = $_SESSION['user_id'];
    $is_admin = $_SESSION['is_admin'];
}
// Fetch forms
if ($is_admin == 1) {
    // Admin: show all forms
    $stmt = $pdo->prepare("SELECT * FROM forms ORDER BY created_at DESC");
    $stmt->execute();
} else {
    // Non-admin: show only public or assigned forms
    $stmt = $pdo->prepare("
        SELECT * FROM forms 
        WHERE is_public = 1 
           OR (is_public = 0 AND FIND_IN_SET(:assigneduser, assigneduser)) 
        ORDER BY created_at DESC
    ");
    $stmt->execute([
        'assigneduser' => $assigneduser
    ]);
}




$forms = $stmt->fetchAll();

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = $user['is_admin'];
// $role = $user['role'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Forms - Form Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">All Forms</h1>
        <a href="new_form.php" class="btn btn-success">➕ Create New Form</a>
    </div>

    <?php if (count($forms) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Form Name</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td><?= htmlspecialchars($form['form_name']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($form['created_at'])) ?></td>
                            <td>

                            <?php if($is_admin == '1'){ ?>
                                <a href="edit_form.php?id=<?= $form['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="clone_form.php?id=<?= $form['id'] ?>" class="btn btn-sm btn-secondary">Clone</a>
                            <?php  } ?>
                                <a href="../forms/form.php?form_id=<?= $form['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                            <?php if($is_admin == '1'){ ?>
                                <a href="delete_form.php?id=<?= $form['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this form?');">Delete</a>
                            <?php  } ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No forms created yet.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
