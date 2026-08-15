<?php

/**
 * CLI helper: boots a single handler in a clean PHP process.
 * Used by handler_boot.test.php so a handler's exit() cannot kill the suite.
 *
 * Deliberately does NOT load tests/helpers.php: that pulls in auth.php
 * (which loads csrf.php) and would mask a handler with a missing require.
 * Only the DB env vars are set, mirroring what the handler's own include
 * chain (db.php -> core.php -> ...) needs to connect to SQLite.
 */

error_reporting(E_ALL);

putenv('DB_DSN=sqlite::memory:');
putenv('DB_USER=');
putenv('DB_PASS=');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'x';
$_FILES = [];
$_POST = [];

require __DIR__ . '/../' . ($argv[1] ?? '');
