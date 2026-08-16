<?php

require_once 'auth.php';
require_once 'core.php';
header('Content-Type: application/json');

$pdo = DbConnection::getInstance()->getPdo();

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

if (!csrf_validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing CSRF token.']);
    exit;
}

$id = (int) $_POST['id'];

try {
    $stmt = $pdo->prepare('SELECT document_hash FROM certificates WHERE id = ? AND institution = ?');
    $stmt->execute([$id, current_institution()]);
    $document_hash = $stmt->fetchColumn();

    if (ledger_delete($pdo, $id, current_institution())) {
        audit_log($pdo, current_institution(), 'delete', $document_hash ?: null);
        echo json_encode(['status' => 'success', 'message' => 'Certificate deleted from the ledger.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Certificate not found or owned by another institution.']);
    }
} catch (\PDOException $e) {
    error_log('delete_handler: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal error occurred. Please try again.']);
}
