<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$audience = $_GET['audience'] ?? '';
$recipients = [];

// Determine SQL condition based on audience
$condition = "";
if ($audience === 'all_parents') {
    $condition = "status = 'active'";
} elseif ($audience === 'nursery_parents') {
    $condition = "status = 'active' AND class LIKE '%Nursery%'";
} elseif ($audience === 'primary_parents') {
    $condition = "status = 'active' AND (class LIKE '%Primary%' OR class LIKE '%Basic%')";
} elseif ($audience === 'jhs_parents') {
    $condition = "status = 'active' AND class LIKE '%JHS%'";
} elseif ($audience === 'debtors') {
    // For simplicity, just return active students if we don't have the exact debtors logic here yet
    $condition = "status = 'active'";
} else {
    echo json_encode(['recipients' => []]);
    exit;
}

header('Content-Type: application/json');
try {
    $query = "SELECT first_name, last_name, class, parent_contact FROM students WHERE $condition LIMIT 100";
    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty(trim($row['parent_contact'] ?? ''))) {
                $recipients[] = [
                    'student_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'class' => $row['class'] ?? '',
                    'contact' => $row['parent_contact']
                ];
            }
        }
    } else {
        throw new Exception($conn->error);
    }
    echo json_encode(['recipients' => $recipients]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'query' => $query]);
}
