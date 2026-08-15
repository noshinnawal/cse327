<?php

function pdf_hash($path) {
    return hash_file('sha256', $path);
}

function ledger_insert($pdo, $hash, $student_name, $degree, $institution, $issuance_date) {
    $stmt = $pdo->prepare("INSERT INTO certificates (hash, student_name, degree, institution, issuance_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$hash, $student_name, $degree, $institution, $issuance_date]);
    return $hash;
}

function ledger_find_by_hash($pdo, $hash) {
    $stmt = $pdo->prepare("SELECT id, student_name, degree, institution, issuance_date FROM certificates WHERE hash = ?");
    $stmt->execute([$hash]);
    return $stmt->fetch();
}

function ledger_delete($pdo, $id, $institution) {
    $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ? AND institution = ?");
    $stmt->execute([$id, $institution]);
    return $stmt->rowCount() > 0;
}

function ledger_search($pdo, $institution, $q = '', $sort = 'newest') {
    $allowed_sort = [
        'newest' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'name' => 'student_name ASC',
        'date' => 'issuance_date DESC',
    ];
    $order = $allowed_sort[$sort] ?? 'created_at DESC';

    $sql = "SELECT id, student_name, degree, issuance_date, created_at, hash FROM certificates WHERE institution = ?";
    $params = [$institution];
    if ($q !== '') {
        $sql .= " AND (student_name LIKE ? OR degree LIKE ?)";
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY " . $order;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
