<?php
// session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        // header('Location: /formbuilder/admin/login.php');
        header("Location: " . getBaseUrl() . "/login.php");
        exit;
    }
}

function require_admin() {
    if (!is_logged_in() || empty($_SESSION['is_admin'])) {
        // header('Location: /formbuilder/admin/login.php');
        header("Location: " . getBaseUrl() . "/login.php");
        exit;
    }
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return "$protocol$host$basePath";
}
?>
