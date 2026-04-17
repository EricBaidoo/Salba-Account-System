<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

$targets = [172340.00, 21628.50, 138702.98];

$tables = ['payments', 'expenses', 'student_fees', 'fees', 'system_settings'];

foreach ($tables as $t) {
    echo "--- SCANNING $t ---\n";
    $res = $conn->query("SELECT * FROM $t");
    if (!$res) continue;
    while($row = $res->fetch_assoc()) {
        foreach($row as $k => $v) {
            foreach($targets as $target) {
                if (floatval($v) == $target) {
                    echo "MATCH in $t: Column $k = $v\n";
                    echo json_encode($row) . "\n\n";
                }
            }
        }
    }
}
echo "Done.\n";
?>
