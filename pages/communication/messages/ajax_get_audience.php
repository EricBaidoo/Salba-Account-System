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
    $condition = "s.status = 'active'";
} elseif ($audience === 'nursery_parents') {
    $condition = "s.status = 'active' AND s.class LIKE '%Nursery%'";
} elseif ($audience === 'primary_parents') {
    $condition = "s.status = 'active' AND (s.class LIKE '%Primary%' OR s.class LIKE '%Basic%')";
} elseif ($audience === 'jhs_parents') {
    $condition = "s.status = 'active' AND s.class LIKE '%JHS%'";
} elseif ($audience === 'debtors') {
    $condition = "s.status = 'active'";
} else {
    echo json_encode(['recipients' => []]);
    exit;
}

header('Content-Type: application/json');
try {
    $query = "
        SELECT s.first_name, s.last_name, s.class, 
               COALESCE(
                   (SELECT p.phone FROM parents p 
                    JOIN student_parents sp ON p.id = sp.parent_id 
                    WHERE sp.student_id = s.id 
                    ORDER BY sp.is_primary DESC, sp.id ASC LIMIT 1), 
                   s.parent_contact
               ) as parent_contact 
        FROM students s 
        WHERE $condition 
        LIMIT 100
    ";
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
