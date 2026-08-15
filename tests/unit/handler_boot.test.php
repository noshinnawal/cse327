<?php

/**
 * Boot tests: every handler must be requirable without a fatal error.
 *
 * Regression guard for the verify_handler bug where csrf_validate() was
 * called before csrf.php had been loaded — static analysis cannot detect
 * a missing require in the include chain, so we prove it at runtime.
 *
 * Each handler boots in its own subprocess: a fatal error (undefined
 * function, missing file) exits with code 255, a clean boot exits 0.
 */

require_once __DIR__ . '/../helpers.php';

function boot_handler($handler)
{
    $command = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../boot_handler.php') . ' ' . escapeshellarg($handler) . ' 2>&1';
    exec($command, $output, $code);
    return [$code, $output];
}

function test_NFR06_issue_handler_boots_cleanly()
{
    [$code, $output] = boot_handler('issue_handler.php');
    assert_true($code === 0, 'issue_handler.php boots without fatal error (exit ' . $code . ')');
}

function test_NFR06_verify_handler_boots_cleanly()
{
    [$code, $output] = boot_handler('verify_handler.php');
    assert_true($code === 0, 'verify_handler.php boots without fatal error (exit ' . $code . ')');
}

function test_NFR06_delete_handler_boots_cleanly()
{
    [$code, $output] = boot_handler('delete_handler.php');
    assert_true($code === 0, 'delete_handler.php boots without fatal error (exit ' . $code . ')');
}
