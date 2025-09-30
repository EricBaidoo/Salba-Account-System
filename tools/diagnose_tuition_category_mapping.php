<?php
// Diagnostic: Show all Level/category values for Tuition Fee and highlight mismatches
include '../includes/db_connect.php';

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

// Get all Levels in use by students/classes
$levels = [];
$res = $conn->query("SELECT DISTINCT Level FROM classes WHERE Level IS NOT NULL AND Level != ''");
while ($row = $res->fetch_assoc()) {
    $levels[] = $row['Level'];
}

// Get all categories configured for this fee
$configured = [];
$res = $conn->query("SELECT category, amount FROM fee_amounts WHERE fee_id = $fee_id");
while ($row = $res->fetch_assoc()) {
    $configured[$row['category']] = $row['amount'];
}

// Show table
echo "<h2>Tuition Fee Category/Level Mapping</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Level (from classes)</th><th>Fee Amount (from fee_amounts)</th><th>Status</th></tr>";
foreach ($levels as $level) {
    $match = false;
    foreach ($configured as $cat => $amt) {
        if (strcmp(trim($level), trim($cat)) === 0) {
            echo "<tr><td>".htmlspecialchars($level)."</td><td>GH₵".number_format($amt,2)."</td><td style='color:green;'>OK</td></tr>";
            $match = true;
            break;
        }
    }
    if (!$match) {
        echo "<tr><td>".htmlspecialchars($level)."</td><td style='color:red;'>MISSING</td><td style='color:red;'>No matching category in fee_amounts</td></tr>";
    }
}
// Show extra categories in fee_amounts not in classes
foreach ($configured as $cat => $amt) {
    if (!in_array($cat, $levels, true)) {
        echo "<tr><td style='color:orange;'>Not in classes: ".htmlspecialchars($cat)."</td><td>GH₵".number_format($amt,2)."</td><td style='color:orange;'>Category not used by any class</td></tr>";
    }
}
echo "</table>";
?>
