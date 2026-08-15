<?php

require_once __DIR__ . '/../helpers.php';

function test_auth_active_correct_password() {
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq('North South University', authenticate('North South University', 'nosh327'), 'active institution with correct password logs in');
}

function test_auth_wrong_password_rejected() {
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq(null, authenticate('North South University', 'wrong-password'), 'wrong password is rejected');
}

function test_auth_pending_account_cannot_login() {
    $GLOBALS['pdo'] = boot_sqlite();
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare("INSERT INTO institutions (name, password_hash, location, email, website, rep_name, rep_title, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute(['Test University', 'x', 'Dhaka', 'test@example.com', 'https://test.example', 'Rep', 'Registrar']);
    assert_eq('pending', authenticate('Test University', 'anything'), 'pending account returns pending marker');
}

function test_auth_unknown_institution() {
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq(null, authenticate('No Such University', 'nosh327'), 'unknown institution returns null');
}
