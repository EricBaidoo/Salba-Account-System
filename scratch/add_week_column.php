<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$sql = "ALTER TABLE attendance ADD COLUMN week_number INT DEFAULT 1 AFTER status";
if ($conn->query($sql) === TRUE) {
    echo "Column 'week_number' added successfully\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
$conn->close();
?>
