<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed");

// 1. Add the new combined columns safely
$res = $conn->query("SHOW COLUMNS FROM staff_profiles LIKE 'emergency_contact'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE staff_profiles ADD COLUMN emergency_contact TEXT AFTER marital_status");
}
$res = $conn->query("SHOW COLUMNS FROM staff_profiles LIKE 'bank_details'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE staff_profiles ADD COLUMN bank_details TEXT AFTER entry_qualification");
}

// 2. Migrate Emergency Contact data
// Format: "Name: [name] | Phone: [phone]"
$res = $conn->query("SELECT id, emergency_name, emergency_phone FROM staff_profiles");
while ($row = $res->fetch_assoc()) {
    $info = [];
    if (!empty($row['emergency_name'])) $info[] = "Name: " . $row['emergency_name'];
    if (!empty($row['emergency_phone'])) $info[] = "Phone: " . $row['emergency_phone'];
    $combined = implode(' | ', $info);
    
    if (!empty($combined)) {
        $stmt = $conn->prepare("UPDATE staff_profiles SET emergency_contact = ? WHERE id = ?");
        $stmt->bind_param("si", $combined, $row['id']);
        $stmt->execute();
    }
}
echo "Migrated Emergency Contacts.\n";

// 3. Migrate Bank Details
// Format: "[bank] | Acc: [acc] | Branch: [branch]"
$res = $conn->query("SELECT id, bank_name, bank_account_no, bank_branch FROM staff_profiles");
while ($row = $res->fetch_assoc()) {
    $info = [];
    if (!empty($row['bank_name'])) $info[] = $row['bank_name'];
    if (!empty($row['bank_account_no'])) $info[] = "Acc: " . $row['bank_account_no'];
    if (!empty($row['bank_branch'])) $info[] = "Branch: " . $row['bank_branch'];
    $combined = implode(' | ', $info);
    
    if (!empty($combined)) {
        $stmt = $conn->prepare("UPDATE staff_profiles SET bank_details = ? WHERE id = ?");
        $stmt->bind_param("si", $combined, $row['id']);
        $stmt->execute();
    }
}
echo "Migrated Bank Details.\n";
?>
