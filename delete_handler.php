<?php
require 'auth.php';
require 'db.php';
require 'core.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];

    try {
        if (ledger_delete($pdo, $id, current_institution())) {
            echo json_encode(['status' => 'success', 'message' => 'Certificate deleted from the ledger.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Certificate not found or owned by another institution.']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>