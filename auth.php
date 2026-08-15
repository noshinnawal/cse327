<?php

require_once 'db.php';
require_once 'core.php';
require_once 'csrf.php';

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const LOGIN_MAX_FAILED_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS    = 900;

function is_logged_in()
{
    return isset($_SESSION['institution']);
}

function current_institution()
{
    return $_SESSION['institution'] ?? null;
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    // Idle timeout — force re-authentication after 30 minutes of inactivity.
    $idleLimit = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Authenticates an institution.
 *
 * Returns:
 *  - the institution name on success,
 *  - 'pending' for accounts awaiting admin approval,
 *  - 'locked' while the account is under a brute-force lockout,
 *  - null on unknown institution or wrong password.
 */
function authenticate($name, $password)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT name, password_hash, status, failed_attempts, locked_until FROM institutions WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ($row['status'] === 'pending') {
        return 'pending';
    }

    $lockedUntil = $row['locked_until'] ?? null;
    if ($lockedUntil !== null && strtotime($lockedUntil) > time()) {
        return 'locked';
    }
    if ($lockedUntil !== null) {
        // Lock expired — reset counters.
        $pdo->prepare('UPDATE institutions SET failed_attempts = 0, locked_until = NULL WHERE name = ?')->execute([$name]);
    }

    if (password_verify($password, $row['password_hash'])) {
        $pdo->prepare('UPDATE institutions SET failed_attempts = 0, locked_until = NULL WHERE name = ?')->execute([$name]);
        audit_log($pdo, $name, 'login');
        return $row['name'];
    }

    $attempts = ((int)($row['failed_attempts'] ?? 0)) + 1;
    $lockUntil = null;
    if ($attempts >= LOGIN_MAX_FAILED_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_SECONDS);
        $attempts = 0;
    }
    $pdo->prepare('UPDATE institutions SET failed_attempts = ?, locked_until = ? WHERE name = ?')
        ->execute([$attempts, $lockUntil, $name]);
    audit_log($pdo, $name, 'login_failed');
    return null;
}

function active_institutions()
{
    global $pdo;
    $stmt = $pdo->query("SELECT name FROM institutions WHERE status = 'active' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
