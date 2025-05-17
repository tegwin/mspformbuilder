<?php
// install.php - Fresh, fixed installer

session_start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['db_host'] ?? 'localhost';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    $site = $_POST['site_name'] ?? 'Form Builder';
    $admin = $_POST['admin_user'] ?? 'admin';
    $admin_pass = password_hash($_POST['admin_pass'] ?? 'admin123', PASSWORD_DEFAULT);

    $configContent = "<?php\nreturn [\n" .
        "    'host' => '" . addslashes($host) . "',\n" .
        "    'user' => '" . addslashes($user) . "',\n" .
        "    'pass' => '" . addslashes($pass) . "',\n" .
        "    'name' => '" . addslashes($name) . "'\n" .
        "];\n";

    // Write to config.php
    if (file_put_contents('config.php', $configContent)) {
        // echo "Configuration saved successfully!";
    } else {
        echo "Failed to write config.php. Please check file permissions.";
    }

    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Function to add column if missing
        function addColumnIfMissing($pdo, $table, $column, $definition) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE `$table` ADD `$column` $definition");
            }
        }

        // Create settings table (base definition)
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT(11) NOT NULL PRIMARY KEY
        )");

        // Add expected columns to settings table if missing
        $settingsColumns = [
            'site_name' => "VARCHAR(255) DEFAULT NULL",
            'admin_email' => "VARCHAR(255) DEFAULT NULL",
            'banner_color' => "VARCHAR(20) DEFAULT '#007bff'",
            'logo_path' => "VARCHAR(255) DEFAULT NULL",
            'email_subject' => "VARCHAR(255) DEFAULT NULL",
            'email_body' => "TEXT DEFAULT NULL",
            'email_to' => "VARCHAR(255) DEFAULT NULL",
            'smtp_host' => "VARCHAR(255) DEFAULT NULL",
            'smtp_port' => "INT(11) DEFAULT NULL",
            'smtp_username' => "VARCHAR(255) DEFAULT NULL",
            'smtp_password' => "VARCHAR(255) DEFAULT NULL",
            'smtp_secure' => "VARCHAR(255) DEFAULT NULL",
            'from_email' => "VARCHAR(255) DEFAULT NULL",
            'public_branding' => "TINYINT(1) DEFAULT 0",
            'public_logo' => "VARCHAR(255) DEFAULT NULL",
            'footer_text' => "VARCHAR(255) DEFAULT NULL",
            'footer_color' => "VARCHAR(7) DEFAULT '#000000'",
            'halo_url' => "VARCHAR(500) NOT NULL",
            'halo_client_id' => "MEDIUMTEXT NOT NULL",
            'halo_client_secret' => "MEDIUMTEXT NOT NULL"
        ];
        foreach ($settingsColumns as $column => $definition) {
            addColumnIfMissing($pdo, 'settings', $column, $definition);
        }

        // Insert default row (optional)
        $pdo->exec("INSERT INTO settings (site_name, admin_email, email_subject, email_body)
                    SELECT '$site', '', 'Submission from {form}', 'New submission from {form}:<br><br>{fields}'
                    WHERE NOT EXISTS (SELECT 1 FROM settings)");


        // USERS TABLE
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT(11) NOT NULL PRIMARY KEY
        )");

        $userColumns = [
            'name' => "VARCHAR(255) DEFAULT NULL",
            'email' => "VARCHAR(255) DEFAULT NULL",
            'username' => "VARCHAR(255) DEFAULT NULL",
            'password' => "VARCHAR(255) DEFAULT NULL",
            'is_admin' => "TINYINT(1) DEFAULT 0",
            'is_external' => "TINYINT(1) DEFAULT 0",
            'twofa_enabled' => "TINYINT(1) DEFAULT 0",
            'twofa_secret' => "VARCHAR(255) DEFAULT NULL",
            'avatar_path' => "VARCHAR(255) DEFAULT NULL",
            'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            'role' => "ENUM('internal','external') NOT NULL DEFAULT 'internal'",
            'reset_token' => "VARCHAR(255) DEFAULT NULL",
            'reset_token_expiration' => "DATETIME DEFAULT NULL"
        ];
        foreach ($userColumns as $column => $definition) {
            addColumnIfMissing($pdo, 'users', $column, $definition);
        }

        // Insert admin user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$admin]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password, is_admin) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute(['Admin User', 'admin@example.com', $admin, $admin_pass]);
        }


        // FORMS TABLE
        $pdo->exec("CREATE TABLE IF NOT EXISTS forms (
            id INT(11) NOT NULL PRIMARY KEY
        )");

        $formColumns = [
            'form_name' => "VARCHAR(255) DEFAULT NULL",
            'webhook_url' => "VARCHAR(255) DEFAULT NULL",
            'webhook_url_2' => "VARCHAR(255) DEFAULT NULL",
            'assigneduser' => "VARCHAR(100) NOT NULL",
            'is_public' => "TINYINT(1) DEFAULT 1",
            'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            'is_embeddable' => "TINYINT(1) DEFAULT 0",
            'is_wordpress_plugin' => "TINYINT(1) DEFAULT 0",
            'user_id' => "INT(100) NOT NULL"
        ];
        foreach ($formColumns as $column => $definition) {
            addColumnIfMissing($pdo, 'forms', $column, $definition);
        }


        // FORM_GROUP TABLE
        $pdo->exec("CREATE TABLE IF NOT EXISTS form_group (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY
        )");

        $formGroupColumns = [
            'name' => "VARCHAR(300) NOT NULL",
            'groupid' => "VARCHAR(300) NOT NULL"
        ];

        foreach ($formGroupColumns as $column => $definition) {
            addColumnIfMissing($pdo, 'form_group', $column, $definition);
        }


        // FORM FIELDS
        $pdo->exec("CREATE TABLE IF NOT EXISTS form_fields (
            id INT(11) NOT NULL PRIMARY KEY,
            form_id INT(11),
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
        )");

        $fieldColumns = [
            'field_type' => "VARCHAR(50) DEFAULT NULL",
            'label' => "VARCHAR(255) DEFAULT NULL",
            'name' => "VARCHAR(255) DEFAULT NULL",
            'required' => "TINYINT(1) DEFAULT NULL",
            'options' => "TEXT DEFAULT NULL",
            'dependency_field' => "VARCHAR(255) DEFAULT NULL",
            'dependency_value' => "VARCHAR(255) DEFAULT NULL",
            'halo_field' => "VARCHAR(100) DEFAULT NULL",
            'sort_order' => "INT(11) DEFAULT 0"
        ];
        foreach ($fieldColumns as $column => $definition) {
            addColumnIfMissing($pdo, 'form_fields', $column, $definition);
        }


        // SUBMISSIONS
        $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_id INT,
            entry_data LONGTEXT,
            user_ip VARCHAR(100),
            user_agent TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");


        // FORM ASSIGNMENTS
        $pdo->exec("CREATE TABLE IF NOT EXISTS form_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_id INT,
            user_id INT,
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $success = true;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }

    $shomsg = '';
    try{
            $targetFolder = trim($_POST['install_path']);

            // Default to current directory if empty
            if (empty($targetFolder)) {
                $targetFolder = '.';
            }

            // Normalize path
            $targetFolder = rtrim($targetFolder, '/');

            // Check if folder exists and is not empty
            if ($targetFolder !== '.' && folderNotEmpty($targetFolder)) {
                $shomsg = "<p style='color:red;'>Error: Folder <strong>$targetFolder</strong> already exists and is not empty. Installation aborted to avoid overwriting existing files.</p>";
            } else {
                // Create target folder if it doesn't exist
                if (!is_dir($targetFolder)) {
                    mkdir($targetFolder, 0755, true);
                }

                // List of files/folders to copy
                $itemsToCopy = [
                    '2fa', 'admin', 'assets', 'forms', 'includes', 'uploads', 'vendor',
                    'config.php', 'export_csv.php', 'export_excel.php', 'index.php',
                    'README.md', 'readme.txt', 'reset_password.php'
                ];

                foreach ($itemsToCopy as $item) {
                    $source = __DIR__ . '/' . $item;
                    $destination = $targetFolder . '/' . $item;

                    if (is_dir($source)) {
                        recursiveCopy($source, $destination);
                    } elseif (is_file($source)) {
                        copy($source, $destination);
                    }
                }

                // $shomsg = "<p style='color:green;'>Installation completed successfully in: <strong>$targetFolder</strong></p>";
            }

            // echo "<p style='color:green;'>Installation completed in: <strong>$targetFolder</strong></p>";
            
    }catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }


}

function recursiveCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;

        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";

        if (is_dir($srcPath)) {
            recursiveCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

function folderNotEmpty($dir) {
    return is_dir($dir) && count(scandir($dir)) > 2;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Installer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5">
    <h1 class="mb-4">Form Builder Installer</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php


                if($shomsg!=''){
                    echo $shomsg;
                }

            if($targetFolder!=''){
                echo "✅ Install complete! <a href='".$targetFolder."/admin/login.php'>Go to Login</a>";
            }else{
                echo "✅ Install complete! <a href='admin/login.php'>Go to Login</a>";

            }

            ?>
    </div>
    <?php elseif (!empty($errors)): ?>
        <div class="alert alert-danger">❌ Install failed: <?= htmlspecialchars($errors[0]) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">DB Host</label>
                <input type="text" name="db_host" class="form-control" value="localhost" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">DB Name</label>
                <input type="text" name="db_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">DB User</label>
                <input type="text" name="db_user" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">DB Password</label>
                <input type="password" name="db_pass" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Site Name</label>
                <input type="text" name="site_name" class="form-control" value="Form Builder">
            </div>
            <div class="col-md-6">
                <label class="form-label">Admin Username</label>
                <input type="text" name="admin_user" class="form-control" value="admin">
            </div>
            <div class="col-md-6">
                <label class="form-label">Admin Password</label>
                <input type="text" name="admin_pass" class="form-control" value="admin123">
            </div>

            <div class="col-md-6">
                <label class="form-label">Install to (relative path):</label>
                <input type="text" name="install_path" id="install_path" placeholder="Leave blank for current directory" class="form-control">
            </div>
        </div>
        <button class="btn btn-primary mt-4">Install Now</button>
    </form>









    
</div>
</body>
</html>
