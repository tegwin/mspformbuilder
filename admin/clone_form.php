<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$form_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Step 1: Fetch original form
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$form_id]);
$original = $stmt->fetch();

if (!$original) {
    die("Form not found.");
}

// Step 2: Duplicate form
$new_name = $original['form_name'] . ' (Copy)';
$stmt = $pdo->prepare("INSERT INTO forms (form_name, webhook_url, created_at) VALUES (?, ?, NOW())");
$stmt->execute([$new_name, $original['webhook_url']]);
$new_form_id = $pdo->lastInsertId();

// Step 3: Duplicate fields
$stmt = $pdo->prepare("SELECT * FROM form_fields WHERE form_id = ?");
$stmt->execute([$form_id]);
$fields = $stmt->fetchAll();

foreach ($fields as $field) {
    $stmt = $pdo->prepare("INSERT INTO form_fields (form_id, field_type, label, name, required, options)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $new_form_id,
        $field['field_type'],
        $field['label'],
        $field['name'] . '_copy', // avoid name collision
        $field['required'],
        $field['options']
    ]);
}

// Step 4: Redirect to edit the clone
header("Location: edit_form.php?id=$new_form_id");
exit;
?>
