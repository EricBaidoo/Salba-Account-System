<?php
include 'db_connect.php';
include 'student_balance_functions.php';
include 'auth_functions.php';
session_start();
if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['student_id'])) {
    echo json_encode(['error' => 'Student ID required']);
    exit;
}

$student_id = intval($_GET['student_id']);

try {
    $balance = getStudentBalance($conn, $student_id);
    $fees = getStudentOutstandingFees($conn, $student_id);
    
    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'fees' => $fees
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>