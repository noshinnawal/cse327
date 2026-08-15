<?php

require_once __DIR__ . '/../helpers.php';

function test_FR09_csrf_token_is_stable_within_session()
{
    assert_eq(csrf_token(), csrf_token(), 'token is generated once per session and reused');
}

function test_FR09_csrf_token_is_64_hex_chars()
{
    $token = csrf_token();
    assert_eq(64, strlen($token), 'csrf token is 64 characters');
    assert_true(ctype_xdigit($token), 'csrf token is hexadecimal');
}

function test_FR09_csrf_validate_accepts_valid_token()
{
    assert_true(csrf_validate(csrf_token()), 'the session token validates');
}

function test_FR09_csrf_validate_rejects_forged_token()
{
    assert_eq(false, csrf_validate('forged-token'), 'an attacker-supplied token is rejected');
}

function test_FR09_csrf_validate_rejects_empty_and_non_string()
{
    assert_eq(false, csrf_validate(''), 'empty token is rejected');
    assert_eq(false, csrf_validate(null), 'null token is rejected');
    assert_eq(false, csrf_validate(['array']), 'non-string token is rejected');
}
