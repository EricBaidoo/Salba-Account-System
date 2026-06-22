<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Waivers Column Fix Log</h2>";

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

if (!tableExists($conn, 'scholarships')) {
    echo "<div style='color: red; margin-bottom: 10px;'>❌ Table <strong>scholarships</strong> does not exist. Running full waivers setup first...</div>";
    
    // Create scholarships table
    $sql1 = "CREATE TABLE IF NOT EXISTS scholarships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        applies_to_fees VARCHAR(255) NULL DEFAULT '[]',
        discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
        discount_value DECIMAL(10,2) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql1)) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Created <strong>scholarships</strong> table.</div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error creating scholarships table: " . $conn->error . "</div>";
    }
} else {
    // Table exists, check column
    if (columnExists($conn, 'scholarships', 'applies_to_fees')) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Column <strong>applies_to_fees</strong> already exists in <strong>scholarships</strong> table.</div>";
    } else {
        // Drop applies_to_fee_id if it exists to clean up
        if (columnExists($conn, 'scholarships', 'applies_to_fee_id')) {
            // Find foreign key constraint first
            $db_name = DB_NAME;
            $fk_res = $conn->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = '$db_name' 
                  AND TABLE_NAME = 'scholarships' 
                  AND COLUMN_NAME = 'applies_to_fee_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            if ($fk_res && $fk_res->num_rows > 0) {
                $fk_name = $fk_res->fetch_assoc()['CONSTRAINT_NAME'];
                $conn->query("ALTER TABLE scholarships DROP FOREIGN KEY `$fk_name`");
            }
            $conn->query("ALTER TABLE scholarships DROP COLUMN `applies_to_fee_id`");
        }

        // Add applies_to_fees
        if ($conn->query("ALTER TABLE `scholarships` ADD COLUMN `applies_to_fees` VARCHAR(255) NULL DEFAULT '[]' AFTER `name`")) {
            echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully added column <strong>applies_to_fees</strong> to <strong>scholarships</strong> table.</div>";
        } else {
            echo "<div style='color: red; margin-bottom: 10px;'>❌ Error adding column: " . $conn->error . "</div>";
        }
    }
}

// Check student_scholarships table
if (!tableExists($conn, 'student_scholarships')) {
    $sql2 = "CREATE TABLE IF NOT EXISTS student_scholarships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        scholarship_id INT NOT NULL,
        status ENUM('active', 'revoked') DEFAULT 'active',
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql2)) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Created <strong>student_scholarships</strong> table.</div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error creating student_scholarships table: " . $conn->error . "</div>";
    }
} else {
    echo "<div style='color: green; margin-bottom: 10px;'>✅ Table <strong>student_scholarships</strong> exists and is verified.</div>";
}

// Verify Waivers system fee
$sql3 = "INSERT INTO fees (name, amount, fee_type, description) 
         SELECT 'Waivers & Scholarships', 0.00, 'fixed', 'System-level fee to apply automatic discounts'
         WHERE NOT EXISTS (SELECT 1 FROM fees WHERE name = 'Waivers & Scholarships')";
if ($conn->query($sql3)) {
    echo "<div style='color: green; margin-bottom: 10px;'>✅ System fee 'Waivers & Scholarships' verified.</div>";
} else {
    echo "<div style='color: red; margin-bottom: 10px;'>❌ Error verifying system fee: " . $conn->error . "</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Fix script completed execution.</h3>";
echo "</div>";
?>
