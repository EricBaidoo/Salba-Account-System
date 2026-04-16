<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SHOW TABLES LIKE 'student_semester_remarks'");
if($res->num_rows > 0) {
    echo "EXISTS";
} else {
    echo "MISSING";
    // Check if old name exists
    $res2 = $conn->query("SHOW TABLES LIKE 'student_term_remarks'");
    if($res2->num_rows > 0) echo " (OLD NAME student_term_remarks EXISTS)";
}
?>
