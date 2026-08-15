<?php

require_once __DIR__ . '/../helpers.php';

function test_FR02_active_correct_password()
{
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq('North South University', authenticate('North South University', 'nosh327'), 'active institution with correct password logs in');
}

function test_FR02_wrong_password_rejected()
{
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq(null, authenticate('North South University', 'wrong-password'), 'wrong password is rejected');
}

function test_FR01_pending_account_cannot_login()
{
    $GLOBALS['pdo'] = boot_sqlite();
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare("INSERT INTO institutions (name, password_hash, location, email, website, rep_name, rep_title, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute(['Test University', 'x', 'Dhaka', 'test@example.com', 'https://test.example', 'Rep', 'Registrar']);
    assert_eq('pending', authenticate('Test University', 'anything'), 'pending account returns pending marker');
}

function test_FR02_unknown_institution()
{
    $GLOBALS['pdo'] = boot_sqlite();
    assert_eq(null, authenticate('No Such University', 'nosh327'), 'unknown institution returns null');
}

function test_FR07_five_failures_lock_account()
{
    $GLOBALS['pdo'] = boot_sqlite();
    for ($i = 0; $i < 4; $i++) {
        assert_eq(null, authenticate('North South University', 'wrong-' . $i), 'early failures return null');
    }
    assert_eq(null, authenticate('North South University', 'wrong-5'), 'the attempt that triggers the lock still returns null');
    assert_eq('locked', authenticate('North South University', 'nosh327'), 'correct password is rejected while the account is locked');
}

function test_FR07_lock_expires_and_login_succeeds()
{
    $GLOBALS['pdo'] = boot_sqlite();
    $pdo = $GLOBALS['pdo'];
    for ($i = 0; $i < 5; $i++) {
        authenticate('North South University', 'wrong-' . $i);
    }
    assert_eq('locked', authenticate('North South University', 'nosh327'), 'account is locked immediately after five failures');

    $pdo->exec("UPDATE institutions SET locked_until = '2000-01-01 00:00:00' WHERE name = 'North South University'");
    assert_eq('North South University', authenticate('North South University', 'nosh327'), 'correct password works again after the lock expires');
}

function test_FR07_failed_attempts_reset_after_success()
{
    $GLOBALS['pdo'] = boot_sqlite();
    authenticate('North South University', 'wrong-1');
    authenticate('North South University', 'wrong-2');
    assert_eq('North South University', authenticate('North South University', 'nosh327'), 'correct password still works before the lock threshold');
    assert_eq(null, authenticate('North South University', 'wrong-again'), 'a later failure starts from a reset counter');
    assert_eq('North South University', authenticate('North South University', 'nosh327'), 'account is not locked after the reset');
}
