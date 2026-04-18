<?php
/**
 * SIMPLIFIED DATABASE UPDATE SCRIPT
 * Run this online at: smis.e7techlab.com/sql/update_audit_system.php
 */

// 1. Manually include the connection file with an absolute-friendly path
$conn_file = __DIR__ . '/../includes/db_connect.php';

if (file_exists($conn_file)) {
    require_once $conn_file;
} else {
    die("FATAL ERROR: Could not find db_connect.php at $conn_file. Please check your file upload location.");
}

echo "<h2>System Audit Table Migration</h2>";

// 2. The SQL to create our missing table
$sql = "CREATE TABLE IF NOT EXISTS system_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (action),
    INDEX (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// 3. Execute
if ($conn->query($sql)) {
    echo "<p style='color:green; font-weight:bold;'>SUCCESS: 'system_audit_logs' table is now ready!</p>";
    echo "<p>Your dashboard and logging system should now be fully functional.</p>";
} else {
    echo "<p style='color:red;'>DATABASE ERROR: " . $conn->error . "</p>";
}

echo "<hr><a href='../index.php'>Return to Dashboard</a>";
?>
