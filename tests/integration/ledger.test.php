<?php

require_once __DIR__ . '/../helpers.php';

function test_FR03_FR04_issue_then_verify_same_pdf()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('FULL-CERTIFICATE-CONTENT');
    $hash = pdf_hash($pdf);
    ledger_insert($pdo, $hash, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01');

    $found = ledger_find_by_hash($pdo, $hash);
    assert_true($found !== false, 'issued certificate is found by its hash');
    assert_eq('Alice Rahman', $found['student_name'], 'student name round-trips');
    assert_eq('BSc in CSE', $found['degree'], 'degree round-trips');
    assert_eq('North South University', $found['institution'], 'institution round-trips');
    assert_eq('2026-06-01', $found['issuance_date'], 'issuance date round-trips');
    unlink($pdf);
}

function test_FR04_tampered_document_does_not_verify()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('ORIGINAL-CERTIFICATE');
    $hash = pdf_hash($pdf);
    ledger_insert($pdo, $hash, 'Bob Hasan', 'MBA', 'Brac University', '2025-12-31');

    file_put_contents($pdf, 'ORIGINAL-CERTIFICATE-TAMPERED');
    $tampered_hash = pdf_hash($pdf);
    assert_true($tampered_hash !== $hash, 'tampering with the document changes its hash');
    assert_eq(false, ledger_find_by_hash($pdo, $tampered_hash), 'tampered document does not verify against the ledger');
    unlink($pdf);
}

function test_FR03_duplicate_issuance_rejected()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('DUPLICATE-CERTIFICATE');
    $hash = pdf_hash($pdf);
    ledger_insert($pdo, $hash, 'Carol Chowdhury', 'BBA', 'North South University', '2026-01-15');

    $e = assert_throws(function () use ($pdo, $hash) {
        ledger_insert($pdo, $hash, 'Carol Chowdhury', 'BBA', 'North South University', '2026-01-15');
    }, 'issuing the same PDF twice throws an exception');
    assert_eq('23000', (string) $e->getCode(), 'duplicate issuance violates the unique-hash constraint');
    unlink($pdf);
}

function test_FR05_search_matches_name_and_degree()
{
    $pdo = boot_sqlite();
    seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'A');
    seed_certificate($pdo, 'Bob Hasan', 'MBA', 'North South University', '2025-12-31', 'B');
    seed_certificate($pdo, 'Carol Chowdhury', 'BSc in CSE', 'Brac University', '2026-03-01', 'C');

    $byName = ledger_search($pdo, 'North South University', 'Alice');
    assert_eq(1, count($byName), 'search by student name returns the match');
    assert_eq('Alice Rahman', $byName[0]['student_name'], 'name search hits the right row');

    $byDegree = ledger_search($pdo, 'North South University', 'CSE');
    assert_eq(1, count($byDegree), 'degree search is scoped to the institution');

    $noMatch = ledger_search($pdo, 'North South University', 'zzz-nothing');
    assert_eq(0, count($noMatch), 'no match returns an empty list');

    $empty = ledger_search($pdo, 'North South University', '');
    assert_eq(2, count($empty), 'empty query returns all of the institution\'s certificates');
}

function test_FR05_sort_by_name_and_date()
{
    $pdo = boot_sqlite();
    seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-01-01', 'A');
    seed_certificate($pdo, 'Bob Hasan', 'MBA', 'North South University', '2026-02-01', 'B');
    seed_certificate($pdo, 'Carol Chowdhury', 'BBA', 'North South University', '2026-03-01', 'C');

    $byName = ledger_search($pdo, 'North South University', '', 'name');
    assert_eq(
        ['Alice Rahman', 'Bob Hasan', 'Carol Chowdhury'],
        array_column($byName, 'student_name'),
        'name sort is alphabetical'
    );

    $byDate = ledger_search($pdo, 'North South University', '', 'date');
    assert_eq(
        ['Carol Chowdhury', 'Bob Hasan', 'Alice Rahman'],
        array_column($byDate, 'student_name'),
        'date sort is newest issuance first'
    );

    $fallback = ledger_search($pdo, 'North South University', '', 'bogus-sort');
    assert_eq(3, count($fallback), 'unknown sort value falls back to the default without error');
}

function test_FR05_delete_own_certificate()
{
    $pdo = boot_sqlite();
    $hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'X');
    $id = $pdo->lastInsertId();

    assert_true(ledger_delete($pdo, $id, 'North South University'), 'owner can delete its certificate');
    assert_eq(false, ledger_find_by_hash($pdo, $hash), 'deleted certificate no longer verifies');
}

function test_FR05_cannot_delete_another_institutions_certificate()
{
    $pdo = boot_sqlite();
    $hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'X');
    $id = $pdo->lastInsertId();

    assert_eq(false, ledger_delete($pdo, $id, 'Brac University'), 'another institution cannot delete the certificate');
    assert_true(ledger_find_by_hash($pdo, $hash) !== false, 'certificate remains in the ledger');
}

function test_FR05_reissue_same_pdf_after_delete()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('REISSUED-CERTIFICATE');
    $hash = pdf_hash($pdf);

    ledger_insert($pdo, $hash, 'Dan Karim', 'MSc', 'North South University', '2026-05-05');
    $id = $pdo->lastInsertId();
    assert_true(ledger_delete($pdo, $id, 'North South University'), 'certificate is deleted');

    ledger_insert($pdo, $hash, 'Dan Karim', 'MSc', 'North South University', '2026-05-05');
    assert_true(ledger_find_by_hash($pdo, $hash) !== false, 'same PDF can be re-issued after deletion');
    unlink($pdf);
}
