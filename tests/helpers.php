<?php

// Route the app's db.php to an in-memory SQLite DB instead of MySQL.
putenv('DB_DSN=sqlite::memory:');
putenv('DB_USER=');
putenv('DB_PASS=');

require_once __DIR__ . '/../core.php';
require_once __DIR__ . '/../auth.php';

function boot_sqlite() {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(file_get_contents(__DIR__ . '/fixtures/schema.sqlite.sql'));
    return $pdo;
}

function seed_certificate($pdo, $student_name, $degree, $institution, $issuance_date, $content = 'PDF-CONTENT') {
    $path = temp_upload($content);
    $hash = pdf_hash($path);
    ledger_insert($pdo, $hash, $student_name, $degree, $institution, $issuance_date);
    unlink($path);
    return $hash;
}

function temp_upload($content = 'PDF-CONTENT') {
    $path = tempnam(sys_get_temp_dir(), 'cert_');
    file_put_contents($path, $content);
    return $path;
}
