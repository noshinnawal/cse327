<?php
session_start();

$institutions = [
    'North South University' => 'nosh327',
    'Brac University' => 'brac327',
];

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
    global $institutions;
    if (isset($institutions[$name]) && $institutions[$name] === $password) {
        return $name;
    }
    return null;
}
?>