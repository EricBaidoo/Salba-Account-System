<?php
// Fetch categories from DB for use in forms
include '../includes/db_connect.php';
$category_res = $conn->query("SELECT * FROM fee_categories ORDER BY name ASC");
$fee_categories = [];
while ($row = $category_res->fetch_assoc()) {
    $fee_categories[$row['id']] = $row['name'];
}
?>