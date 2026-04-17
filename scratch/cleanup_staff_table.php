<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed");

// 1. Drop all columns NOT in the final target list
$res = $conn->query("DESCRIBE staff_profiles");
$current_cols = [];
while ($row = $res->fetch_assoc()) {
    $current_cols[] = $row['Field'];
}

$target_cols = [
    'id', 'user_id', 'created_at', 'staff_code', 'staff_type', 'job_title', 'photo_path', 
    'first_appointment_date', 'full_name', 'gender', 'date_of_birth', 'marital_status', 
    'nationality', 'religion', 'languages_spoken', 'phone_number', 'address', 'landmark', 
    'hometown', 'emergency_contact', 'ghana_card_no', 'ssnit_number', 'highest_qualification', 
    'entry_qualification', 'bank_details', 'guarantor1_name', 'guarantor1_phone', 
    'guarantor1_address', 'guarantor2_name', 'guarantor2_phone', 'guarantor2_address', 
    'employment_status', 'department', 'updated_at', 'email'
];

foreach ($current_cols as $col) {
    if (!in_array($col, $target_cols)) {
        echo "Dropping legacy column: $col\n";
        $conn->query("ALTER TABLE staff_profiles DROP COLUMN $col");
    }
}

// 2. Final alignment (ensure types are correct)
$conn->query("ALTER TABLE staff_profiles MODIFY COLUMN emergency_contact TEXT");
$conn->query("ALTER TABLE staff_profiles MODIFY COLUMN bank_details TEXT");

echo "Table cleanup complete.\n";
?>
