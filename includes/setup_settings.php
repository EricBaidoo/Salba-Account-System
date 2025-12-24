<?php
// Run this file once to create the system_settings table
include 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(100)
)";

if ($conn->query($sql)) {
    echo "✓ system_settings table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert default settings
$defaults = [
    ['current_term', 'First Term', 'The active academic term for the entire system', 'System'],
    ['academic_year', '2024/2025', 'Current academic year', 'System'],
    ['school_name', 'Salba Montessori School', 'Official school name', 'System'],
    ['school_address', 'Accra, Ghana', 'School physical address', 'System'],
    ['school_phone', '+233 XX XXX XXXX', 'School contact number', 'System'],
    ['school_email', 'info@salbamontessori.edu.gh', 'School email address', 'System']
];

$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by) 
                        VALUES (?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = setting_value");

foreach ($defaults as $setting) {
    $stmt->bind_param("ssss", $setting[0], $setting[1], $setting[2], $setting[3]);
    if ($stmt->execute()) {
        echo "✓ Setting '{$setting[0]}' initialized<br>";
    } else {
        echo "Error: " . $stmt->error . "<br>";
    }
}

$stmt->close();
echo "<br><strong>Setup complete!</strong> <a href='../pages/system_settings.php'>Go to System Settings</a>";
?>
