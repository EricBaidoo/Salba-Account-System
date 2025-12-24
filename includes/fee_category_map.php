<?php
// Helper: get all fee categories as id=>name
include_once '../includes/db_connect.php';
$category_map = [];
$res = $conn->query("SELECT id, name FROM fee_categories");
while ($row = $res->fetch_assoc()) {
    $category_map[$row['id']] = $row['name'];
}
?>