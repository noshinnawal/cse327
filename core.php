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

function ledger_insert($pdo, $hash, $student_name, $degree, $institution, $issuance_date)
{
    $stmt = $pdo->prepare('INSERT INTO certificates (hash, student_name, degree, institution, issuance_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$hash, $student_name, $degree, $institution, $issuance_date]);
    return $hash;
}

function ledger_find_by_hash($pdo, $hash)
{
    $stmt = $pdo->prepare('SELECT id, student_name, degree, institution, issuance_date FROM certificates WHERE hash = ?');
    $stmt->execute([$hash]);
    return $stmt->fetch();
}

function ledger_delete($pdo, $id, $institution)
{
    $stmt = $pdo->prepare('DELETE FROM certificates WHERE id = ? AND institution = ?');
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

    $sql = 'SELECT id, student_name, degree, issuance_date, created_at, hash FROM certificates WHERE institution = ?';
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
function audit_log($pdo, $institution, $action, $hash = null)
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_log (institution, action, hash) VALUES (?, ?, ?)');
        $stmt->execute([$institution, $action, $hash]);
        return true;
    } catch (\PDOException $e) {
        error_log('audit_log: ' . $e->getMessage());
        return false;
    }
}
