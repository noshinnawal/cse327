<?php

require_once __DIR__ . '/../helpers.php';

function test_FR10_certificate_present_escapes_xss_fields()
{
    $pdo = boot_sqlite();
    $document_hash = seed_certificate($pdo, '<script>alert(1)</script>', 'BSc & "Quoted"', 'North South University', '2026-06-01');
    $row = ledger_find_by_document_hash($pdo, $document_hash);
    $present = certificate_present($row);

    assert_true(strpos($present['student_name'], '<script>') === false, 'script tags are escaped out of the student name');
    assert_true(strpos($present['student_name'], '&lt;script&gt;') !== false, 'escaped student name contains the entity form');
    assert_true(strpos($present['degree'], '&amp;') !== false && strpos($present['degree'], '&quot;') !== false, 'degree escapes ampersands and quotes');
}

function test_FR06_audit_log_records_issue()
{
    $pdo = boot_sqlite();
    $document_hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01');
    assert_true(audit_log($pdo, 'North South University', 'issue', $document_hash), 'issue audit write succeeds');
    $rows = $pdo->query('SELECT action, document_hash, institution FROM audit_log')->fetchAll();
    assert_eq(1, count($rows), 'one audit row is recorded');
    assert_eq('issue', $rows[0]['action'], 'audit action is issue');
    assert_eq($document_hash, $rows[0]['document_hash'], 'audit row carries the certificate hash');
    assert_eq('North South University', $rows[0]['institution'], 'audit row carries the issuing institution');
}

function test_FR06_audit_log_records_verify_and_delete()
{
    $pdo = boot_sqlite();
    $document_hash = seed_certificate($pdo, 'Bob Hasan', 'MBA', 'Brac University', '2025-12-31');
    $id = $pdo->lastInsertId();

    assert_true(audit_log($pdo, null, 'verify', $document_hash), 'verify audit write succeeds');
    assert_true(ledger_delete($pdo, $id, 'Brac University'), 'certificate deleted');
    assert_true(audit_log($pdo, 'Brac University', 'delete', $document_hash), 'delete audit write succeeds');

    $actions = array_column($pdo->query('SELECT action FROM audit_log')->fetchAll(), 'action');
    assert_true(in_array('verify', $actions, true), 'audit contains the verify action');
    assert_true(in_array('delete', $actions, true), 'audit contains the delete action');
}

function test_FR06_audit_log_never_breaks_the_main_flow()
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE certificates (id INTEGER PRIMARY KEY AUTOINCREMENT, document_hash TEXT NOT NULL UNIQUE, previous_hash TEXT DEFAULT NULL, record_hash TEXT NOT NULL, is_revoked INTEGER NOT NULL DEFAULT 0, student_name TEXT NOT NULL, degree TEXT NOT NULL, institution TEXT NOT NULL, issuance_date TEXT NOT NULL)');

    // No audit_log table in this database — audit must fail silently.
    $document_hash = pdf_hash(temp_upload('X'));
    assert_eq(false, audit_log($pdo, 'X', 'issue', $document_hash), 'audit_log returns false when the table is missing');
    assert_eq('dummy_record_hash', ledger_insert($pdo, $document_hash, 'A', 'B', 'C', '2026-01-01'), 'the main ledger flow still works');
}
