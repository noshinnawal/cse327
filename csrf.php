<?php

/**
 * CSRF protection (OWASP Top 10, Lec 3 security).
 *
 * Every state-changing request (login, register, issue, verify, delete)
 * must carry a session-bound token, validated with hash_equals to avoid
 * timing attacks.
 */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate($token)
{
    if (!is_string($token) || $token === '') {
        return false;
    }
    return hash_equals(csrf_token(), $token);
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}
