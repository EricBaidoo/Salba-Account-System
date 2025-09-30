<?php
// Remove duplicate Tuition Fee entries, keeping only the most recent one
include __DIR__ . '/../includes/db_connect.php';

$fee_name = 'Tuition Fee';
// Find all Tuition Fee IDs, order by created_at DESC (keep the latest)
$res = $conn->query("SELECT id FROM fees WHERE name = '$fee_name' ORDER BY created_at DESC");
$ids = [];
while ($row = $res->fetch_assoc()) {
    $ids[] = $row['id'];
}
if (count($ids) <= 1) {
    echo "<div style='color:green;'>No duplicate Tuition Fee found.</div>";
    exit;
}
$keep_id = array_shift($ids); // keep the latest
$remove_ids = implode(',', $ids);
// Remove related fee_amounts and student_fees for duplicates
$conn->query("DELETE FROM fee_amounts WHERE fee_id IN ($remove_ids)");
$conn->query("DELETE FROM student_fees WHERE fee_id IN ($remove_ids)");
$conn->query("DELETE FROM fees WHERE id IN ($remove_ids)");
echo "<div style='color:green;'>Removed duplicate Tuition Fee(s), kept ID $keep_id.</div>";
?>
