<?php

/**
 * MySQL integration suite (run by CI against a real MySQL service).
 *
 * Complements tests/run.php: the zero-dependency suite proves logic against
 * in-memory SQLite; this suite proves the same flows against MySQL so schema
 * drift between the two engines is caught before it reaches production.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/helpers.php';

$checks = 0;
$failures = [];

function check($condition, $label)
{
    global $checks, $failures;
    $checks++;
    if ($condition) {
        echo "  \xE2\x9C\x93 $label\n";
        return;
    }
    $failures[] = $label;
    echo "  \xE2\x9C\x97 $label\n";
}

try {
    $pdo = new PDO(
        'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1')
        . ';port=' . (getenv('DB_PORT') ?: '3306')
        . ';dbname=' . (getenv('DB_NAME') ?: 'nosh_softdev')
        . ';charset=utf8mb4',
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $GLOBALS['pdo'] = $pdo;

    echo "MySQL integration suite\n";
    echo str_repeat('-', 60) . "\n";

    // Unique content per run so the suite is idempotent (repeatable on a dirty DB).
    $unique = bin2hex(random_bytes(8));

    // 1. Real UNIQUE constraint semantics (SQLite and MySQL agree, but prove it).
    $pdf = temp_upload('%PDF-MYSQL-UNIQUE-TEST-' . $unique);
    $document_hash = pdf_hash($pdf);
    ledger_insert($pdo, $document_hash, 'MySQL Test', 'BSc', 'North South University', '2026-01-01');
    try {
        ledger_insert($pdo, $document_hash, 'MySQL Test', 'BSc', 'North South University', '2026-01-01');
        check(false, 'duplicate issuance rejected by MySQL UNIQUE(document_hash)');
    } catch (PDOException $e) {
        check($e->getCode() == 23000, 'duplicate issuance rejected by MySQL UNIQUE(document_hash)');
    }

    // 2. Verify round-trip.
    $found = ledger_find_by_document_hash($pdo, $document_hash);
    check($found !== false && $found['student_name'] === 'MySQL Test', 'find_by_hash round-trips on MySQL');

    // 3. Delete scoping (institution ownership).
    // Use the id from the SELECTed row: PDO::lastInsertId() is reset by any
    // intervening SELECT on MariaDB, so it must never be relied on here.
    $id = (int)$found['id'];
    check(ledger_delete($pdo, $id, 'Brac University') === false, 'cross-institution delete rejected on MySQL');
    check(ledger_delete($pdo, $id, 'North South University') === true, 'owner delete works on MySQL');
    check(ledger_find_by_document_hash($pdo, $document_hash) === false, 'deleted certificate no longer verifies on MySQL');

    // 4. Audit log against the real table.
    check(audit_log($pdo, 'North South University', 'issue', $document_hash) === true, 'audit_log insert works on MySQL');
    check((int)$pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn() >= 1, 'audit rows exist on MySQL');

    // 5. Auth + lockout columns work on MySQL.
    check(authenticate('North South University', 'nosh327') === 'North South University', 'seeded login works on MySQL');
    check(authenticate('North South University', 'wrong-password') === null, 'wrong password rejected on MySQL');
    $attempts = (int)$pdo->query("SELECT failed_attempts FROM institutions WHERE name = 'North South University'")->fetchColumn();
    check($attempts >= 1, 'failed_attempts incremented on MySQL (got ' . $attempts . ')');
    check(authenticate('North South University', 'nosh327') === 'North South University', 'success resets login on MySQL');

    // 6. Search + sort behave identically on MySQL.
    $sortA = temp_upload('%PDF-MYSQL-SORT-A-' . $unique);
    $sortB = temp_upload('%PDF-MYSQL-SORT-B-' . $unique);
    $aliceName = 'Alice Rahman-' . $unique;
    ledger_insert($pdo, pdf_hash($sortA), $aliceName, 'BSc in CSE', 'North South University', '2026-02-01');
    ledger_insert($pdo, pdf_hash($sortB), 'Bob Hasan', 'MBA', 'North South University', '2026-01-01');
    $byName = ledger_search($pdo, 'North South University', $aliceName, 'name');
    check(count($byName) === 1 && $byName[0]['student_name'] === $aliceName, 'search by name works on MySQL');
    $sorted = ledger_search($pdo, 'North South University', '', 'date');
    $names = array_column($sorted, 'student_name');
    $alicePos = array_search($aliceName, $names, true);
    $bobPos = array_search('Bob Hasan', $names, true);
    check(count($sorted) >= 2 && $alicePos !== false && $bobPos !== false && $alicePos < $bobPos, 'date sort works on MySQL');

    unlink($pdf);
    unlink($sortA);
    unlink($sortB);

    echo str_repeat('-', 60) . "\n";
    echo "$checks checks, " . count($failures) . " failures\n";
    exit(count($failures) > 0 ? 1 : 0);
} catch (Throwable $e) {
    echo '::error::unexpected failure: ' . str_replace("\n", '%0A', $e->getMessage()) . "\n";
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
