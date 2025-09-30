<?php
// Update students with class 'JHS 1' to 'Basic 7' for consistency
include '../includes/db_connect.php';

$sql = "UPDATE students SET class = 'Basic 7' WHERE class = 'JHS 1'";
if ($conn->query($sql) === TRUE) {
    echo "<div style='color:green;'>All students with class 'JHS 1' have been updated to 'Basic 7'.</div>";
} else {
    echo "<div style='color:red;'>Error updating students: " . $conn->error . "</div>";
}
?>
