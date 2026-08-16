<?php

/**
 * Ledger core — Facade (Lec 11) over the certificate database.
 *
 * Handlers depend on this single, simple interface instead of SQL,
 * keeping the front-end layer loosely coupled from storage details.
 */

/**
 * Hashing strategy (Behavioral pattern, Lec 12).
 *
 * The hash algorithm is encapsulated behind an interface so it can be
 * swapped (e.g., SHA-512) without touching any callers.
 */
interface HashStrategy
{
    public function hash(string $path): string;
}

final class Sha256HashStrategy implements HashStrategy
{
    public function hash(string $path): string
    {
        return hash_file('sha256', $path);
    }
}

/**
 * Factory method (Creational pattern, Lec 10) — returns the right
 * HashStrategy by name without callers knowing concrete classes.
 */
final class HashStrategyFactory
{
    public static function create(string $algorithm = 'sha256'): HashStrategy
    {
        if ($algorithm === 'sha256') {
            return new Sha256HashStrategy();
        }
        throw new \InvalidArgumentException("Unsupported hash algorithm: {$algorithm}");
    }
}

function pdf_hash($path)
{
    return HashStrategyFactory::create()->hash($path);
}

/**
 * Validates a PHP $_FILES upload entry.
 *
 * Returns null when the upload is acceptable, otherwise an error message.
 * Server-side enforcement (PDF magic bytes + size cap) — the browser's
 * `accept="application/pdf"` attribute is client-controlled and untrusted.
 */
function validate_upload($file, int $maxBytes = 5242880)
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'File upload failed. Please try again.';
    }
    if ($file['size'] === 0) {
        return 'The uploaded file is empty.';
    }
    if ($file['size'] > $maxBytes) {
        return 'The uploaded file exceeds the ' . intdiv($maxBytes, 1048576) . ' MB size limit.';
    }
    $handle = @fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        return 'Could not read the uploaded file.';
    }
    $head = fread($handle, 4);
    fclose($handle);
    if ($head !== '%PDF') {
        return 'Only PDF certificate files are accepted.';
    }
    return null;
}

/**
 * Shapes a ledger row for public display, HTML-escaping every field so
 * data cannot inject markup into the verification result (XSS defense).
 */
function certificate_present(array $row): array
{
    return [
        'student_name'  => htmlspecialchars((string)$row['student_name'], ENT_QUOTES),
        'degree'        => htmlspecialchars((string)$row['degree'], ENT_QUOTES),
        'institution'   => htmlspecialchars((string)$row['institution'], ENT_QUOTES),
        'issuance_date' => htmlspecialchars((string)$row['issuance_date'], ENT_QUOTES),
    ];
}

function ledger_insert($pdo, $document_hash, $student_name, $degree, $institution, $issuance_date)
{
    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare('SELECT id FROM certificates WHERE document_hash = ? AND is_revoked = 0 LIMIT 1');
        $check->execute([$document_hash]);
        if ($check->fetch()) {
            throw new \PDOException('Duplicate entry', 23000);
        }

        // 1. Get previous record_hash
        $stmt = $pdo->query('SELECT record_hash FROM certificates ORDER BY id DESC LIMIT 1');
        $last_row = $stmt->fetch();
        $previous_hash = $last_row ? $last_row['record_hash'] : null;

        // 2. Calculate new record_hash
        $payload = json_encode([
            'document_hash' => $document_hash,
            'previous_hash' => $previous_hash,
            'student_name' => $student_name,
            'degree' => $degree,
            'institution' => $institution,
            'issuance_date' => $issuance_date,
        ]);
        $record_hash = hash('sha256', $payload);

        // 3. Insert
        $stmt = $pdo->prepare('INSERT INTO certificates (document_hash, previous_hash, record_hash, student_name, degree, institution, issuance_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$document_hash, $previous_hash, $record_hash, $student_name, $degree, $institution, $issuance_date]);
        
        $pdo->commit();
        return $record_hash;
    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ledger_find_by_document_hash($pdo, $document_hash)
{
    $stmt = $pdo->prepare('SELECT id FROM certificates WHERE document_hash = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$document_hash]);
    $target = $stmt->fetch();

    if (!$target) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE id <= ? ORDER BY id ASC');
    $stmt->execute([$target['id']]);
    
    $expected_previous_hash = null;
    
    while ($row = $stmt->fetch()) {
        $payload = json_encode([
            'document_hash' => $row['document_hash'],
            'previous_hash' => $expected_previous_hash,
            'student_name' => $row['student_name'],
            'degree' => $row['degree'],
            'institution' => $row['institution'],
            'issuance_date' => $row['issuance_date'],
        ]);
        $expected_record_hash = hash('sha256', $payload);

        if ($expected_record_hash !== $row['record_hash']) {
            return false; // Chain is broken
        }

        if ($row['id'] === $target['id']) {
            return $row; // Found the requested document in a valid chain
        }

        $expected_previous_hash = $row['record_hash'];
    }

    return false;
}

function ledger_revoke($pdo, $id, $institution)
{
    $stmt = $pdo->prepare('UPDATE certificates SET is_revoked = 1 WHERE id = ? AND institution = ?');
    $stmt->execute([$id, $institution]);
    return $stmt->rowCount() > 0;
}

function ledger_search($pdo, $institution, $q = '', $sort = 'newest')
{
    $allowed_sort = [
        'newest' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'name' => 'student_name ASC',
        'date' => 'issuance_date DESC',
    ];
    $order = $allowed_sort[$sort] ?? 'created_at DESC';

    $sql = 'SELECT id, student_name, degree, issuance_date, created_at, document_hash, is_revoked FROM certificates WHERE institution = ?';
    $params = [$institution];
    if ($q !== '') {
        $sql .= ' AND (student_name LIKE ? OR degree LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= ' ORDER BY ' . $order;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Records an action in the audit log.
 *
 * Deliberately non-fatal: if logging fails (e.g., an unmigrated database),
 * the main flow must continue unaffected. Returns true on success.
 */
function audit_log($pdo, $institution, $action, $document_hash = null)
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_log (institution, action, document_hash) VALUES (?, ?, ?)');
        $stmt->execute([$institution, $action, $document_hash]);
        return true;
    } catch (\PDOException $e) {
        error_log('audit_log: ' . $e->getMessage());
        return false;
    }
}
