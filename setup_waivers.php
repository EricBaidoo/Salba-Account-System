<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Waivers & Scholarships Setup Log</h2>";

$sql1 = "CREATE TABLE IF NOT EXISTS scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql2 = "CREATE TABLE IF NOT EXISTS student_scholarships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    status ENUM('active', 'revoked') DEFAULT 'active',
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE
)";

// Insert system fee for discounts
$sql3 = "INSERT INTO fees (name, amount, fee_type, description) 
         SELECT 'Waivers & Scholarships', 0.00, 'fixed', 'System-level fee to apply automatic discounts'
         WHERE NOT EXISTS (SELECT 1 FROM fees WHERE name = 'Waivers & Scholarships')";

$conn->query($sql1);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating scholarships table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Scholarships table created/verified.</div>";
}

$conn->query($sql2);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating student_scholarships table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Student_scholarships table created/verified.</div>";
}

$conn->query($sql3);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error inserting system fee: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Waivers & Scholarships system fee created/verified.</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Setup script completed execution.</h3>";
echo "</div>";
?>
