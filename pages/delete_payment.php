<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
header('Content-Type: application/json');
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$payment_id = intval($input['payment_id'] ?? 0);
if ($payment_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}
$stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
$stmt->bind_param("i", $payment_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Payment deleted successfully', 'redirect' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
