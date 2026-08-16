# Local Blockchain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a local, purely chronological blockchain in MySQL by linking certificates using a cryptographic hash chain.

**Architecture:** We will modify the `certificates` table to support `previous_hash`, `record_hash`, and `is_revoked`. Each new certificate will calculate its `record_hash` using the previous row's `record_hash`. Handlers will be updated to verify the chain and perform soft deletes.

**Tech Stack:** PHP 8, MySQL/SQLite

## Global Constraints

- No external dependencies, cloud services, or libraries.
- Must retain drop-in deployment with XAMPP (vanilla PHP + MySQL).
- Follow PSR-12 coding standards.

---

### Task 1: Schema Updates and Renaming `hash` to `document_hash`

**Files:**
- Modify: `schema.sql:1-9`
- Modify: `tests/fixtures/schema.sqlite.sql:1-9`
- Modify: `core.php`
- Modify: `issue_handler.php`
- Modify: `verify_handler.php`
- Modify: `delete_handler.php`
- Modify: `view_certs.php`
- Modify: `index.php`
- Modify: `tests/integration/ledger.test.php`
- Modify: `tests/unit/hash.test.php` (if applicable)

**Interfaces:**
- Consumes: N/A
- Produces: Base schema and renamed variables across the app. `ledger_insert`, `ledger_find_by_document_hash`, `ledger_search`, and `audit_log` will now use `document_hash`.

- [ ] **Step 1: Write the failing test for schema structure**

Modify `tests/integration/ledger.test.php` to use `document_hash` and include the new schema columns:

```php
function test_FR03_FR04_issue_then_verify_same_pdf()
{
    $pdo = boot_sqlite();
    $pdf = temp_upload('FULL-CERTIFICATE-CONTENT');
    $doc_hash = pdf_hash($pdf);
    // Updated signature: now we expect record_hash back
    $record_hash = ledger_insert($pdo, $doc_hash, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01');

    $found = ledger_find_by_document_hash($pdo, $doc_hash);
    assert_true($found !== false, 'issued certificate is found by its document hash');
    assert_true(array_key_exists('previous_hash', $found), 'schema includes previous_hash');
    assert_true(array_key_exists('record_hash', $found), 'schema includes record_hash');
    assert_true(array_key_exists('is_revoked', $found), 'schema includes is_revoked');
    unlink($pdf);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL due to missing `ledger_find_by_document_hash` or missing columns.

- [ ] **Step 3: Write minimal implementation**

Update `schema.sql` and `tests/fixtures/schema.sqlite.sql`:
```sql
CREATE TABLE certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT, /* Use MySQL syntax for schema.sql */
    document_hash VARCHAR(64) NOT NULL UNIQUE,
    previous_hash VARCHAR(64) DEFAULT NULL,
    record_hash VARCHAR(64) NOT NULL,
    is_revoked BOOLEAN NOT NULL DEFAULT 0,
    student_name VARCHAR(255) NOT NULL,
    degree VARCHAR(255) NOT NULL,
    institution VARCHAR(255) NOT NULL,
    issuance_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```
*(Make sure to use `AUTO_INCREMENT` in `schema.sql` and `AUTOINCREMENT` in `schema.sqlite.sql`)*

Update `core.php` to rename `hash` to `document_hash` in all SQL queries, function arguments, and arrays. Rename `ledger_find_by_hash` to `ledger_find_by_document_hash`. For `ledger_insert`, temporarily set `previous_hash` and `record_hash` to static dummy strings in the `INSERT` query just to pass the schema check, and return the `record_hash`.

Update all `tests/*.test.php` replacing `ledger_find_by_hash` with `ledger_find_by_document_hash` and `hash` with `document_hash` in tests.

Update `issue_handler.php`, `verify_handler.php`, `view_certs.php`, `index.php`, `delete_handler.php` to use `document_hash` instead of `hash`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS (All schema checks pass, all handlers continue to work despite dummy hashes)

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat(schema): Rename hash to document_hash and add blockchain columns"
```

---

### Task 2: Implement Blockchain Insert Logic

**Files:**
- Modify: `core.php`
- Modify: `tests/integration/ledger.test.php`

**Interfaces:**
- Consumes: The `document_hash`, `student_name`, `degree`, `institution`, `issuance_date` provided to `ledger_insert`.
- Produces: A properly hashed and linked row in the database where `previous_hash` points to the prior row's `record_hash`, and `record_hash` is `SHA256(document_hash + previous_hash + metadata)`.

- [ ] **Step 1: Write the failing test**

Add to `tests/integration/ledger.test.php`:

```php
function test_blockchain_linking_on_insert()
{
    $pdo = boot_sqlite();
    $hash1 = pdf_hash(temp_upload('CERT1'));
    $hash2 = pdf_hash(temp_upload('CERT2'));

    // First block (Genesis)
    $record_hash1 = ledger_insert($pdo, $hash1, 'Student 1', 'Deg 1', 'Inst', '2026-01-01');
    $found1 = ledger_find_by_document_hash($pdo, $hash1);
    assert_eq(null, $found1['previous_hash'], 'genesis block has no previous hash');
    $expected_rec1 = hash('sha256', $hash1 . 'Student 1Deg 12026-01-01');
    assert_eq($expected_rec1, $found1['record_hash'], 'record hash mathematically verified');

    // Second block
    $record_hash2 = ledger_insert($pdo, $hash2, 'Student 2', 'Deg 2', 'Inst', '2026-01-02');
    $found2 = ledger_find_by_document_hash($pdo, $hash2);
    assert_eq($record_hash1, $found2['previous_hash'], 'second block points to first block');
    $expected_rec2 = hash('sha256', $hash2 . $record_hash1 . 'Student 2Deg 22026-01-02');
    assert_eq($expected_rec2, $found2['record_hash'], 'second block record hash is correct');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL because `ledger_insert` is returning our dummy hashes from Task 1.

- [ ] **Step 3: Write minimal implementation**

Update `ledger_insert` in `core.php`:
```php
function ledger_insert($pdo, $document_hash, $student_name, $degree, $institution, $issuance_date)
{
    // 1. Get previous record_hash
    $stmt = $pdo->query('SELECT record_hash FROM certificates ORDER BY id DESC LIMIT 1');
    $last_row = $stmt->fetch();
    $previous_hash = $last_row ? $last_row['record_hash'] : null;

    // 2. Calculate new record_hash
    $data_to_hash = $document_hash . ($previous_hash ?? '') . $student_name . $degree . $issuance_date;
    $record_hash = hash('sha256', $data_to_hash);

    // 3. Insert
    $stmt = $pdo->prepare('INSERT INTO certificates (document_hash, previous_hash, record_hash, student_name, degree, institution, issuance_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$document_hash, $previous_hash, $record_hash, $student_name, $degree, $institution, $issuance_date]);
    
    return $record_hash;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add core.php tests/integration/ledger.test.php
git commit -m "feat(ledger): Implement cryptographic linking on certificate insertion"
```

---

### Task 3: Implement Verification Link Checking

**Files:**
- Modify: `core.php`
- Modify: `tests/integration/ledger.test.php`
- Modify: `verify_handler.php`

**Interfaces:**
- Consumes: A document hash provided by the user.
- Produces: A verification function `ledger_verify_chain($pdo, $document_hash)` that returns `['valid' => bool, 'is_revoked' => bool, 'certificate' => array|null, 'error' => string|null]`.

- [ ] **Step 1: Write the failing test**

Add to `tests/integration/ledger.test.php`:

```php
function test_blockchain_verification()
{
    $pdo = boot_sqlite();
    $hash1 = pdf_hash(temp_upload('CERT1'));
    $hash2 = pdf_hash(temp_upload('CERT2'));

    ledger_insert($pdo, $hash1, 'Student 1', 'Deg 1', 'Inst', '2026-01-01');
    ledger_insert($pdo, $hash2, 'Student 2', 'Deg 2', 'Inst', '2026-01-02');

    $result1 = ledger_verify_chain($pdo, $hash1);
    assert_true($result1['valid'], 'first block is valid');

    $result2 = ledger_verify_chain($pdo, $hash2);
    assert_true($result2['valid'], 'second block is valid');

    // Tamper with the first block's metadata to break the chain
    $pdo->exec("UPDATE certificates SET degree = 'HACKED' WHERE document_hash = '$hash1'");
    $tampered_result = ledger_verify_chain($pdo, $hash1);
    assert_eq(false, $tampered_result['valid'], 'tampered metadata invalidates the record hash');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL because `ledger_verify_chain` doesn't exist.

- [ ] **Step 3: Write minimal implementation**

Add to `core.php`:
```php
function ledger_verify_chain($pdo, $document_hash)
{
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE document_hash = ?');
    $stmt->execute([$document_hash]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['valid' => false, 'is_revoked' => false, 'certificate' => null, 'error' => 'Certificate not found in ledger.'];
    }
    
    if ($row['is_revoked']) {
        return ['valid' => false, 'is_revoked' => true, 'certificate' => $row, 'error' => 'This certificate has been revoked.'];
    }

    // Integrity Check 1: Record Hash
    $data_to_hash = $row['document_hash'] . ($row['previous_hash'] ?? '') . $row['student_name'] . $row['degree'] . $row['issuance_date'];
    $calculated_hash = hash('sha256', $data_to_hash);
    if ($calculated_hash !== $row['record_hash']) {
        return ['valid' => false, 'is_revoked' => false, 'certificate' => null, 'error' => 'Blockchain integrity error: Record hash mismatch.'];
    }

    // Integrity Check 2: Link to previous row (if not genesis)
    if ($row['previous_hash'] !== null) {
        $stmt_prev = $pdo->prepare('SELECT record_hash FROM certificates WHERE id < ? ORDER BY id DESC LIMIT 1');
        $stmt_prev->execute([$row['id']]);
        $prev_row = $stmt_prev->fetch();
        if (!$prev_row || $prev_row['record_hash'] !== $row['previous_hash']) {
            return ['valid' => false, 'is_revoked' => false, 'certificate' => null, 'error' => 'Blockchain integrity error: Broken chain link.'];
        }
    }

    return ['valid' => true, 'is_revoked' => false, 'certificate' => $row, 'error' => null];
}
```

Update `verify_handler.php` to use `ledger_verify_chain($pdo, $doc_hash)` instead of checking if `$found` is false manually. Update the UI JSON response to use `$result['valid']` and `$result['error']`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add core.php tests/integration/ledger.test.php verify_handler.php
git commit -m "feat(ledger): Add chain verification and link checking"
```

---

### Task 4: Implement Soft Deletion (Revocation)

**Files:**
- Modify: `core.php`
- Modify: `delete_handler.php`
- Modify: `view_certs.php`
- Modify: `tests/integration/ledger.test.php`

**Interfaces:**
- Consumes: A certificate ID and institution.
- Produces: Updates `is_revoked = 1` instead of deleting the row.

- [ ] **Step 1: Write the failing test**

Update `test_FR05_delete_own_certificate` in `tests/integration/ledger.test.php`:

```php
function test_FR05_delete_own_certificate()
{
    $pdo = boot_sqlite();
    $hash = seed_certificate($pdo, 'Alice Rahman', 'BSc in CSE', 'North South University', '2026-06-01', 'X');
    $id = $pdo->lastInsertId();

    assert_true(ledger_revoke($pdo, $id, 'North South University'), 'owner can revoke its certificate');
    $result = ledger_verify_chain($pdo, $hash);
    assert_true($result['is_revoked'], 'revoked certificate is marked as revoked in verification');
    assert_eq(false, $result['valid'], 'revoked certificate is not valid');
}
```

*(Note: Ensure `test_FR05_cannot_delete_another_institutions_certificate` and `test_FR05_reissue_same_pdf_after_delete` are also updated to use `ledger_revoke` instead of `ledger_delete`. The `reissue_same_pdf_after_delete` test should pass because the unique constraint on `document_hash` will fail if reissued unless we drop the UNIQUE constraint. Actually, soft deletes mean we CANNOT reissue the same PDF hash without violating UNIQUE constraint unless we remove UNIQUE from `document_hash` in Task 1! If reissuing same PDF is a strict requirement, we must remove `UNIQUE` from `document_hash` in `schema.sql` during Task 1. Ensure `UNIQUE` is removed from `document_hash` in Task 1 if `reissue` test fails.)*

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FAIL because `ledger_revoke` is undefined.

- [ ] **Step 3: Write minimal implementation**

In `core.php`, rename `ledger_delete` to `ledger_revoke`:
```php
function ledger_revoke($pdo, $id, $institution)
{
    $stmt = $pdo->prepare('UPDATE certificates SET is_revoked = 1 WHERE id = ? AND institution = ?');
    $stmt->execute([$id, $institution]);
    return $stmt->rowCount() > 0;
}
```

In `delete_handler.php`, change the call from `ledger_delete` to `ledger_revoke`.
In `view_certs.php`, change references of "Delete" to "Revoke", and display the revoked status in the table if `is_revoked == 1`.
Update `ledger_search` in `core.php` to include `is_revoked`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/run.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat(ledger): Convert hard deletes to soft revocations to maintain blockchain integrity"
```
