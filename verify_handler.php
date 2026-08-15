<?php
header('Content-Type: application/json');
require 'db.php';
require 'core.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $tmp_path = $_FILES['certificate']['tmp_name'];
        $hash = pdf_hash($tmp_path);

        // Immediately delete the uploaded file
        unlink($tmp_path);

        try {
            $result = ledger_find_by_hash($pdo, $hash);

            if ($result) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Certificate verified.',
                    'data' => $result
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Certificate not valid: this document does not match the ledger. It may have been tampered with, altered, or removed by the issuing institution.'
                ]);
            }
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File upload error.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>