<?php
$output = '';

$edit_id = 45;
$uid = 7;

include 'includes/db_connect.php';

$res = $conn->query("SELECT * FROM lesson_plans WHERE id = $edit_id AND teacher_id = $uid AND status IN ('draft', 'pending', 'rejected') LIMIT 1");
if ($res && $res->num_rows > 0) {
    $edit_data = $res->fetch_assoc();
} else {
    die("Data not found");
}

$v = fn($k) => htmlspecialchars($_POST[$k] ?? $edit_data[$k] ?? '');

echo "Topic: " . $v('topic') . "\n";
echo "Day: " . $v('day_of_week') . "\n";
echo "Strand: " . $v('strand') . "\n";
