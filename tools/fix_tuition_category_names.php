<?php
// Auto-fix: Update Tuition Fee category names in fee_amounts to match Level names in classes
include __DIR__ . '/../includes/db_connect.php';

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

// Map old category names to new Level names
$mapping = [
    'pre_school' => 'Pre-School',
    'lower_basic' => 'Lower Basic',
    'upper_basic' => 'Upper Basic',
    'junior_high' => 'Junior High',
];
$fixed = 0;
foreach ($mapping as $old => $new) {
    $update = $conn->prepare("UPDATE fee_amounts SET category = ? WHERE fee_id = ? AND category = ?");
    $update->bind_param("sis", $new, $fee_id, $old);
    $update->execute();
    if ($update->affected_rows > 0) $fixed++;
    $update->close();
}
if ($fixed > 0) {
    echo "<div style='color:green;'>Updated $fixed Tuition Fee category names to match Level names.</div>";
} else {
    echo "<div style='color:orange;'>No category names needed updating, or they were already correct.</div>";
}
?>
