<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Load settings
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Automatically detect app base path
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/admin');


$is_admin = '';
$nonva = false;

if (isset($_SESSION['user_id'])) {
    $nonva = true;

    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $is_admin = $user['is_admin'];
    }
}
// $role = $user['role'];



?>

<style>
    .user-avatar-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

.user-avatar {
    max-height: 50px;
    max-width: 50px;
    border-radius: 50%;
    border: 2px solid #dee2e6;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    object-fit: cover;
}

.user-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

</style>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: <?= htmlspecialchars($settings['banner_color'] ?? '#007bff') ?>;">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= $base_path ?>/admin/dashboard.php">
            <?php if (!empty($settings['logo_path']) && file_exists(__DIR__ . '/../' . $settings['logo_path'])): ?>
                <img src="<?= htmlspecialchars($base_path . '/' . $settings['logo_path']) ?>" alt="Logo" style="height: 50px;">
            <?php else: ?>
                <span class="fs-4"><?= htmlspecialchars($settings['site_name'] ?? 'Form Builder') ?></span>
            <?php endif; ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarNavAltMarkup">
            <?php if($nonva){ ?>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="<?= $base_path ?>/admin/dashboard.php">Dashboard</a>
                <a class="nav-link" href="<?= $base_path ?>/admin/forms.php">Forms</a>
                <a class="nav-link" href="<?= $base_path ?>/admin/submissions.php">Submissions</a>
                <?php if($is_admin=='1'){?>
                <a class="nav-link" href="<?= $base_path ?>/admin/users.php">Users</a>
                <?php } ?>
                <a class="nav-link" href="<?= $base_path ?>/admin/settings.php">Settings</a>
                    <a class="nav-link" href="<?= $base_path ?>/admin/user_profile.php">Profile</a>
            </div>
            <?php } ?>

            <ul class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['username'])): ?>
                    <li  class="nav-item me-3">
                        <?php if (!empty($user['avatar_path'])): ?>
                            <div class="user-avatar-wrapper">
                                <img  class="user-avatar" src="<?= htmlspecialchars($base_path . '/' . $user['avatar_path']) ?>" alt="Logo">
                            </div>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item me-3">
                        <span class="text-white small">
                            Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <span class="badge bg-warning text-dark ms-2">Admin</span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endif; ?>
                
                <?php if($nonva){ ?>
                <li class="nav-item">
                    <a class="btn btn-outline-light btn-sm" href="<?= $base_path ?>/admin/logout.php">Logout</a>
                </li>
                <?php } ?>

            </ul>
        </div>
    </div>
</nav>
