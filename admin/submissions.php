<?php
require_once __DIR__ . '/../includes/db.php';

session_start();
require_once __DIR__ . '/../includes/auth.php';
require_login();

// Fetch all submissions from the database along with the associated form names
$stmt = $pdo->prepare("SELECT submissions.*, forms.form_name FROM submissions LEFT JOIN forms ON submissions.form_id = forms.id ORDER BY submissions.submitted_at DESC");
$stmt->execute();
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle the deletion of selected submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_selected'])) {
    if (!empty($_POST['selected_submissions'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_submissions']));
        $pdo->exec("DELETE FROM submissions WHERE id IN ($ids)");
        header("Location: submissions.php?deleted=1");
        exit;
    }
}

// Handle individual submission deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: submissions.php?deleted=1");
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
    <title>Manage Submissions - Form Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Manage Submissions</h1>

    <!-- Dismissible success message -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Submissions deleted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Export buttons -->
    <div class="mb-3">
        <a href="../export_excel.php" class="btn btn-success">Export All to Excel</a>
        <a href="../export_csv.php" class="btn btn-info">Export All to CSV</a>
    </div>

    <!-- Bulk delete form -->
    <form method="POST" action="submissions.php">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        
                        <?php if($is_admin=='1'){?>
                        <th>
                            <input type="checkbox" id="select-all">
                        </th>
                        <?php } ?>

                        <th>Form Name</th>
                        <th>Form Data</th>
                        <th>Submission Date</th>
                        <?php if($is_admin=='1'){?>
                        <th>Actions</th>
                        <?php } ?>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            
                            <?php if($is_admin=='1'){?>
                            <td>
                                <input type="checkbox" name="selected_submissions[]" value="<?= $submission['id'] ?>">
                            </td>
                            <?php } ?>

                            <td><?= htmlspecialchars($submission['form_name']) ?></td>
                            <td><?= htmlspecialchars($submission['entry_data']) ?></td>
                            <td><?= date('Y-m-d H:i:s', strtotime($submission['submitted_at'])) ?></td>

                            <?php if($is_admin=='1'){?>
                            <td>
                                <a href="submissions.php?delete=1&id=<?= $submission['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this submission?');">Delete</a>
                            </td>
                            <?php } ?>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if($is_admin=='1'){?>
        <button type="submit" name="delete_selected" class="btn btn-danger">Delete Selected</button>
        <?php } ?>

    </form>
</div>

<script>
    // Select all checkboxes
    document.getElementById('select-all').addEventListener('change', function (e) {
        var checkboxes = document.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = e.target.checked;
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
