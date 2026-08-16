<?php

require_once __DIR__ . '/../helpers.php';

function test_FR03_FR04_issue_then_verify_same_pdf()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('FULL-CERTIFICATE-CONTENT');
    $doc_hash = pdf_hash($pdf);
    // Updated signature: now we expect record_hash back
    $record_hash = ledger_insert($pdo, $doc_hash, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01');

    $found = ledger_find_by_document_hash($pdo, $doc_hash);
    assert_true($found !== false, 'issued certificate is found by its document hash');
    assert_eq('Alice Rahman', $found['student_name'], 'student name round-trips');
    assert_eq('BSc in CSE', $found['degree'], 'degree round-trips');
    assert_eq('North South University', $found['institution'], 'institution round-trips');
    assert_eq('2026-06-01', $found['issuance_date'], 'issuance date round-trips');
    assert_true(array_key_exists('previous_hash', $found), 'schema includes previous_hash');
    assert_true(array_key_exists('record_hash', $found), 'schema includes record_hash');
    assert_true(array_key_exists('is_revoked', $found), 'schema includes is_revoked');
    unlink($pdf);
}

function test_FR04_tampered_document_does_not_verify()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('ORIGINAL-CERTIFICATE');
    $document_hash = pdf_hash($pdf);
    ledger_insert($pdo, $document_hash, 'Bob Hasan', 'MBA', 'Brac University', '2025-12-31');

    file_put_contents($pdf, 'ORIGINAL-CERTIFICATE-TAMPERED');
    $tampered_document_hash = pdf_hash($pdf);
    assert_true($tampered_document_hash !== $document_hash, 'tampering with the document changes its hash');
    assert_eq(false, ledger_find_by_document_hash($pdo, $tampered_document_hash), 'tampered document does not verify against the ledger');
    unlink($pdf);
}

function test_FR03_duplicate_issuance_rejected()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('DUPLICATE-CERTIFICATE');
    $document_hash = pdf_hash($pdf);
    ledger_insert($pdo, $document_hash, 'Carol Chowdhury', 'BBA', 'North South University', '2026-01-15');

    $e = assert_throws(function () use ($pdo, $document_hash) {
        ledger_insert($pdo, $document_hash, 'Carol Chowdhury', 'BBA', 'North South University', '2026-01-15');
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
    $document_hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'X');
    $id = $pdo->lastInsertId();

    assert_true(ledger_delete($pdo, $id, 'North South University'), 'owner can delete its certificate');
    assert_eq(false, ledger_find_by_document_hash($pdo, $document_hash), 'deleted certificate no longer verifies');
}

function test_FR05_cannot_delete_another_institutions_certificate()
{
    $pdo = boot_sqlite();
    $document_hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'X');
    $id = $pdo->lastInsertId();

    assert_eq(false, ledger_delete($pdo, $id, 'Brac University'), 'another institution cannot delete the certificate');
    assert_true(ledger_find_by_document_hash($pdo, $document_hash) !== false, 'certificate remains in the ledger');
}

function test_FR05_reissue_same_pdf_after_delete()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('REISSUED-CERTIFICATE');
    $document_hash = pdf_hash($pdf);

    ledger_insert($pdo, $document_hash, 'Dan Karim', 'MSc', 'North South University', '2026-05-05');
    $id = $pdo->lastInsertId();
    assert_true(ledger_delete($pdo, $id, 'North South University'), 'certificate is deleted');

    ledger_insert($pdo, $document_hash, 'Dan Karim', 'MSc', 'North South University', '2026-05-05');
    assert_true(ledger_find_by_document_hash($pdo, $document_hash) !== false, 'same PDF can be re-issued after deletion');
    unlink($pdf);
}

function test_blockchain_linking_on_insert()
{
    $pdo = boot_sqlite();
    $hash1 = pdf_hash(temp_upload('CERT1'));
    $hash2 = pdf_hash(temp_upload('CERT2'));

    // First block (Genesis)
    $record_hash1 = ledger_insert($pdo, $hash1, 'Student 1', 'Deg 1', 'Inst', '2026-01-01');
    $found1 = ledger_find_by_document_hash($pdo, $hash1);
    assert_eq(null, $found1['previous_hash'], 'genesis block has no previous hash');
    $expected_rec1 = hash('sha256', json_encode([
        'document_hash' => $hash1,
        'previous_hash' => null,
        'student_name' => 'Student 1',
        'degree' => 'Deg 1',
        'institution' => 'Inst',
        'issuance_date' => '2026-01-01',
    ]));
    assert_eq($expected_rec1, $found1['record_hash'], 'record hash mathematically verified');

    // Second block
    $record_hash2 = ledger_insert($pdo, $hash2, 'Student 2', 'Deg 2', 'Inst', '2026-01-02');
    $found2 = ledger_find_by_document_hash($pdo, $hash2);
    assert_eq($record_hash1, $found2['previous_hash'], 'second block points to first block');
    $expected_rec2 = hash('sha256', json_encode([
        'document_hash' => $hash2,
        'previous_hash' => $record_hash1,
        'student_name' => 'Student 2',
        'degree' => 'Deg 2',
        'institution' => 'Inst',
        'issuance_date' => '2026-01-02',
    ]));
    assert_eq($expected_rec2, $found2['record_hash'], 'second block record hash is correct');
}

function test_FR04_blockchain_verification_checks_chain_integrity()
{
    $pdo = boot_sqlite();
    // 1. Setup a valid chain of 3 blocks
    $hash1 = pdf_hash(temp_upload('CERT1'));
    $hash2 = pdf_hash(temp_upload('CERT2'));
    $hash3 = pdf_hash(temp_upload('CERT3'));

    ledger_insert($pdo, $hash1, 'Student 1', 'Deg 1', 'Inst', '2026-01-01');
    ledger_insert($pdo, $hash2, 'Student 2', 'Deg 2', 'Inst', '2026-01-02');
    ledger_insert($pdo, $hash3, 'Student 3', 'Deg 3', 'Inst', '2026-01-03');

    // 2. Verify middle block works normally
    $found2 = ledger_find_by_document_hash($pdo, $hash2);
    assert_true($found2 !== false, 'middle block verifies normally');

    // 3. Tamper with the database to break the chain
    // (We change the metadata of the genesis block without updating its record_hash or subsequent previous_hash)
    $pdo->exec("UPDATE certificates SET degree = 'Hacked Degree' WHERE document_hash = '$hash1'");

    // 4. Verify should now fail (blockchain integrity broken)
    $found2_after_hack = ledger_find_by_document_hash($pdo, $hash2);
    assert_eq(false, $found2_after_hack, 'middle block fails verification because a previous block was tampered with');
}
