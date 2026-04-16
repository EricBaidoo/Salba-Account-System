<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->query("RENAME TABLE student_term_remarks TO student_semester_remarks")) {
    echo "SUCCESS";
} else {
    echo "FAILED: " . $conn->error;
}
?>
