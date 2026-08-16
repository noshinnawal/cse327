<?php

require_once 'auth.php';
require_once 'core.php';
header('Content-Type: application/json');

$pdo = DbConnection::getInstance()->getPdo();

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

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

$student_name = trim($_POST['student_name'] ?? '');
$degree = trim($_POST['degree'] ?? '');
$issuance_date = $_POST['issuance_date'] ?? '';
$institution = current_institution();

if (empty($student_name) || empty($degree) || empty($issuance_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing metadata.']);
    exit;
}

if (mb_strlen($student_name) > 255 || mb_strlen($degree) > 255) {
    echo json_encode(['status' => 'error', 'message' => 'Student name and degree must be at most 255 characters.']);
    exit;
}

$dateCheck = DateTime::createFromFormat('Y-m-d', $issuance_date);
if (!($dateCheck instanceof DateTime) || $dateCheck->format('Y-m-d') !== $issuance_date) {
    echo json_encode(['status' => 'error', 'message' => 'Issuance date must be a valid YYYY-MM-DD date.']);
    exit;
}

$tmp_path = $_FILES['certificate']['tmp_name'];
$document_hash = pdf_hash($tmp_path);

try {
    ledger_insert($pdo, $document_hash, $student_name, $degree, $institution, $issuance_date);
    audit_log($pdo, $institution, 'issue', $document_hash);

    // Immediately delete the uploaded file
    unlink($tmp_path);

    echo json_encode(['status' => 'success', 'message' => 'Certificate issued successfully.', 'document_hash' => $document_hash]);
} catch (\PDOException $e) {
    // Cleanup on error
    @unlink($tmp_path);

    if ($e->getCode() == 23000) {
        echo json_encode(['status' => 'error', 'message' => 'Certificate already exists.']);
    } else {
        error_log('issue_handler: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An internal error occurred. Please try again.']);
    }
}
