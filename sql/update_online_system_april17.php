<?php
/**
 * DATABASE UPDATE SCRIPT - APRIL 17
 * Purpose: Synchronizes the online SMIS database with the new Staff and User schema.
 */

// 1. Connection Settings (Update these for your online server)
$host = 'localhost';
$user = 'root'; // Update with online DB user
$pass = 'root'; // Update with online DB pass
$db   = 'Salba_acc'; // Update with online DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<div style='color:red; font-family:sans-serif;'>Connection failed: " . $conn->connect_error . "</div>");
}

echo "<div style='font-family:sans-serif; padding:20px; border:1px solid #eee; border-radius:12px; max-width:600px; margin:40px auto;'>";
echo "<h2 style='color:#4f46e5;'>System Update Service</h2>";
echo "<p style='color:#64748b; font-size:14px;'>Applying schema updates and synchronizing personnel records...</p><hr style='border:none; border-top:1px solid #f1f5f9; margin:20px 0;'>";

function runQuery($conn, $sql, $message) {
    if ($conn->query($sql)) {
        echo "<div style='color:#10b981; margin-bottom:10px; font-size:13px;'>✅ $message</div>";
    } else {
        echo "<div style='color:#f43f5e; margin-bottom:10px; font-size:13px;'>❌ Error ($message): " . $conn->error . "</div>";
    }
}

// 1. Update Users Table
runQuery($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER role", "Added is_active column to users table");

// 2. Prepare Staff Profiles Table
runQuery($conn, "ALTER TABLE staff_profiles ADD COLUMN IF NOT EXISTS bank_details TEXT AFTER first_appointment_date", "Created bank_details column");
runQuery($conn, "ALTER TABLE staff_profiles ADD COLUMN IF NOT EXISTS emergency_contact TEXT AFTER bank_details", "Created emergency_contact column");

// 3. Migrate and Consolidate Data (Bank)
$conn->query("UPDATE staff_profiles SET bank_details = CONCAT('Bank: ', IFNULL(bank_name,''), ' | Acc: ', IFNULL(bank_account_no,''), ' | Branch: ', IFNULL(bank_branch,'')) WHERE bank_details IS NULL OR bank_details = ''");
echo "<div style='color:#10b981; margin-bottom:10px; font-size:13px;'>✅ Consolidated Bank Account records</div>";

// 4. Migrate and Consolidate Data (Emergency)
$conn->query("UPDATE staff_profiles SET emergency_contact = CONCAT('Name: ', IFNULL(emergency_name,''), ' | Phone: ', IFNULL(emergency_phone,'')) WHERE emergency_contact IS NULL OR emergency_contact = ''");
echo "<div style='color:#10b981; margin-bottom:10px; font-size:13px;'>✅ Consolidated Emergency Contact records</div>";

// 5. Clean up missing Roles
runQuery($conn, "UPDATE staff_profiles SET job_title = 'Class Teacher' WHERE (job_title IS NULL OR job_title = '' OR job_title = 'N/A') AND staff_type LIKE '%teaching%'", "Set default job titles for teaching staff");

// 6. Final Clean-Up: Drop Legacy Columns
$legacy_cols = ['bank_name', 'bank_account_no', 'bank_branch', 'emergency_name', 'emergency_phone', 'phone', 'telephone_number', 'landmark', 'place_of_stay_address'];
foreach ($legacy_cols as $col) {
    $check = $conn->query("SHOW COLUMNS FROM staff_profiles LIKE '$col'");
    if ($check->num_rows > 0) {
        $conn->query("ALTER TABLE staff_profiles DROP COLUMN $col");
    }
}
echo "<div style='color:#10b981; margin-bottom:10px; font-size:13px;'>✅ Removed redundant legacy columns</div>";

// 7. Verify Sync (Link users to staff if found by staff_id)
runQuery($conn, "UPDATE users u JOIN staff_profiles sp ON sp.id = u.staff_id SET sp.user_id = u.id WHERE sp.user_id IS NULL", "Verified Staff-to-User account synchronization");

echo "<hr style='border:none; border-top:1px solid #f1f5f9; margin:20px 0;'>";
echo "<div style='background:#f0fdf4; color:#166534; padding:15px; border-radius:10px; font-weight:bold; text-align:center;'>Update Complete! You can now safely delete this file.</div>";
echo "</div>";
?>
