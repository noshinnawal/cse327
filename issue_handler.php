<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $student_name = trim($_POST['student_name'] ?? '');
        $degree = trim($_POST['degree'] ?? '');
        $issuance_date = $_POST['issuance_date'] ?? '';
        $institution = current_institution();

        if (empty($student_name) || empty($degree) || empty($issuance_date)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing metadata.']);
            exit;
        }

        $tmp_path = $_FILES['certificate']['tmp_name'];
        $hash = hash_file('sha256', $tmp_path);

        try {
            $stmt = $pdo->prepare("INSERT INTO certificates (hash, student_name, degree, institution, issuance_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$hash, $student_name, $degree, $institution, $issuance_date]);

            // Immediately delete the uploaded file
            unlink($tmp_path);

            echo json_encode(['status' => 'success', 'message' => 'Certificate issued successfully.', 'hash' => $hash]);
        } catch (\PDOException $e) {
            // Cleanup on error
            @unlink($tmp_path);

            if ($e->getCode() == 23000) {
                echo json_encode(['status' => 'error', 'message' => 'Certificate already exists.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File upload error.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>