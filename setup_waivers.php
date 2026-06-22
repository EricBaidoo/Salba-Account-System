<?php
require 'includes/db_connect.php';

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
if ($conn->error) echo "Error creating scholarships: " . $conn->error . "<br>";
$conn->query($sql2);
if ($conn->error) echo "Error creating student_scholarships: " . $conn->error . "<br>";
$conn->query($sql3);
if ($conn->error) echo "Error inserting system fee: " . $conn->error . "<br>";

echo "Waivers database setup complete.";
