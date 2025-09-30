<?php
// Diagnostic: Find missing Tuition Fee category/level amounts for all student levels
include '../includes/db_connect.php';

// Get Tuition Fee ID
$fee_name = 'Tuition Fee';
$fee_id = null;
$res = $conn->query("SELECT id FROM fees WHERE name = '$fee_name' AND fee_type = 'category' LIMIT 1");
if ($row = $res->fetch_assoc()) {
    $fee_id = $row['id'];
}
if (!$fee_id) {
    echo "<div style='color:red;'>No Tuition Fee (category-based) found.</div>";
    exit;
}

// Get all Levels in use by students
$levels = [];
$res = $conn->query("SELECT DISTINCT c.Level FROM students s JOIN classes c ON s.class = c.name WHERE c.Level IS NOT NULL AND c.Level != ''");
while ($row = $res->fetch_assoc()) {
    $levels[] = $row['Level'];
}

// Get all categories configured for this fee
$configured = [];
$res = $conn->query("SELECT category FROM fee_amounts WHERE fee_id = $fee_id");
while ($row = $res->fetch_assoc()) {
    $configured[] = $row['category'];
}

// Find missing
$missing = array_diff($levels, $configured);
if (empty($missing)) {
    echo "<div style='color:green;'>All student categories/levels have a Tuition Fee amount configured.</div>";
} else {
    echo "<div style='color:red;'>Missing Tuition Fee amount for these categories/levels:</div><ul>";
    foreach ($missing as $cat) {
        echo "<li>" . htmlspecialchars($cat) . "</li>";
    }
    echo "</ul>";
}
?>
