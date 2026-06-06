<?php
include 'includes/db_connect.php';

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="parents_data.sql"');

echo "-- ==========================================================\n";
echo "-- SALBA MONTESSORI - PARENTS DATA EXPORT\n";
echo "-- ==========================================================\n\n";

$res = $conn->query("SELECT * FROM parents");
if ($res && $res->num_rows > 0) {
    echo "INSERT INTO `parents` (`id`, `title`, `first_name`, `last_name`, `relationship`, `phone`, `email`, `address`, `is_primary`, `created_at`) VALUES \n";
    
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $id = intval($row['id']);
        $title = $row['title'] ? "'" . $conn->real_escape_string($row['title']) . "'" : "NULL";
        $first_name = "'" . $conn->real_escape_string($row['first_name']) . "'";
        $last_name = "'" . $conn->real_escape_string($row['last_name']) . "'";
        $relationship = $row['relationship'] ? "'" . $conn->real_escape_string($row['relationship']) . "'" : "NULL";
        $phone = $row['phone'] ? "'" . $conn->real_escape_string($row['phone']) . "'" : "NULL";
        $email = $row['email'] ? "'" . $conn->real_escape_string($row['email']) . "'" : "NULL";
        $address = $row['address'] ? "'" . $conn->real_escape_string($row['address']) . "'" : "NULL";
        $is_primary = intval($row['is_primary']);
        $created_at = $row['created_at'] ? "'" . $conn->real_escape_string($row['created_at']) . "'" : "CURRENT_TIMESTAMP";

        $rows[] = "($id, $title, $first_name, $last_name, $relationship, $phone, $email, $address, $is_primary, $created_at)";
    }
    
    echo implode(",\n", $rows) . ";\n";
} else {
    echo "-- No data found in parents table.\n";
}

// Check if there are any student_parents links
$res_links = $conn->query("SELECT * FROM student_parents");
if ($res_links && $res_links->num_rows > 0) {
    echo "\n\n-- ==========================================================\n";
    echo "-- STUDENT-PARENT LINKS\n";
    echo "-- ==========================================================\n\n";
    echo "INSERT INTO `student_parents` (`student_id`, `parent_id`, `relationship`, `is_primary`, `created_at`) VALUES \n";
    
    $link_rows = [];
    while ($row = $res_links->fetch_assoc()) {
        $student_id = intval($row['student_id']);
        $parent_id = intval($row['parent_id']);
        $relationship = $row['relationship'] ? "'" . $conn->real_escape_string($row['relationship']) . "'" : "NULL";
        $is_primary = intval($row['is_primary']);
        $created_at = $row['created_at'] ? "'" . $conn->real_escape_string($row['created_at']) . "'" : "CURRENT_TIMESTAMP";

        $link_rows[] = "($student_id, $parent_id, $relationship, $is_primary, $created_at)";
    }
    
    echo implode(",\n", $link_rows) . ";\n";
}
?>
