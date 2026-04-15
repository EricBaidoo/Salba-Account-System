<?php
include __DIR__ . '/../includes/db_connect.php';

$sql = "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'supervisor', 'facilitator', 'staff') DEFAULT 'staff'";
if ($conn->query($sql)) {
    echo "Roles ENUM updated successfully.\n";
} else {
    echo "Error updating Roles: " . $conn->error . "\n";
}

// Add is_active if it not exists
$conn->query("ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `role`") ;
// It might error if column exists, which is fine for a scratch script.

echo "Migration finished.\n";
?>
