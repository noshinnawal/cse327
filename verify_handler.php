<?php

header('Content-Type: application/json');
require_once 'db.php';
require_once 'core.php';
require_once 'auth.php';

$pdo = DbConnection::getInstance()->getPdo();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!csrf_validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing CSRF token.']);
    exit;
}

$uploadError = validate_upload($_FILES['certificate'] ?? null);
if ($uploadError !== null) {
    echo json_encode(['status' => 'error', 'message' => $uploadError]);
    exit;
}

$tmp_path = $_FILES['certificate']['tmp_name'];
$hash = pdf_hash($tmp_path);

// Immediately delete the uploaded file
unlink($tmp_path);

try {
    $result = ledger_find_by_hash($pdo, $hash);
    audit_log($pdo, null, 'verify', $hash);

    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Certificate verified.',
            'data' => certificate_present($result),
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Certificate not valid: this document does not match the ledger. It may have been tampered with, altered, or removed by the issuing institution.',
        ]);
    }
} catch (\PDOException $e) {
    error_log('verify_handler: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal error occurred. Please try again.']);
}
