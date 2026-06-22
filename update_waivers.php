<?php
require 'includes/db_connect.php';

$sql1 = "ALTER TABLE scholarships ADD COLUMN applies_to_fee_id INT NULL DEFAULT NULL AFTER name";
$sql2 = "ALTER TABLE scholarships ADD FOREIGN KEY (applies_to_fee_id) REFERENCES fees(id) ON DELETE SET NULL";

$conn->query($sql1);
if ($conn->error) echo "Error adding column: " . $conn->error . "<br>";
$conn->query($sql2);
if ($conn->error) echo "Error adding foreign key: " . $conn->error . "<br>";

echo "Database updated successfully.";
