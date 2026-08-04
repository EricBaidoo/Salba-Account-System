<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['class'])) {
    $class = $conn->real_escape_string($_GET['class']);
    
    $query = "SELECT id, first_name, last_name, class FROM students WHERE class = '$class' AND status = 'active' ORDER BY first_name ASC, last_name ASC";
    $result = $conn->query($query);
    
    $students = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'students' => $students]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;
