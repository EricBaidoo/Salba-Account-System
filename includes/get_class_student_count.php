<?php
include 'db_connect.php';
include 'auth_functions.php';
session_start();
if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['class'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$class = $_GET['class'];
$class = ($class === 'KG2' || $class === 'KG 2') ? 'KG 2' : (($class === 'KG1' || $class === 'KG 1') ? 'KG 1' : $class);
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE class = ? AND status = 'active'");
$stmt->bind_param("s", $class);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode(['count' => $row['count']]);

$stmt->close();
$conn->close();
?>