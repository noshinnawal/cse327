<?php
session_start();
require 'db.php';

function is_logged_in() {
    return isset($_SESSION['institution']);
}

function current_institution() {
    return $_SESSION['institution'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function authenticate($name, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name, password_hash, status FROM institutions WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();

    if ($row && $row['status'] === 'pending') {
        return 'pending';
    }

    if ($row && $row['status'] === 'active' && password_verify($password, $row['password_hash'])) {
        return $row['name'];
    }

    return null;
}

function active_institutions() {
    global $pdo;
    $stmt = $pdo->query("SELECT name FROM institutions WHERE status = 'active' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
