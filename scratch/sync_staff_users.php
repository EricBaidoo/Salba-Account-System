<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed");

// 1. Reset orphaned user_id links
$conn->query("UPDATE staff_profiles SET user_id = NULL");
echo "Reset existing user links.\n";

// 2. Fetch all staff
$res = $conn->query("SELECT id, full_name, staff_code, email FROM staff_profiles");
$staff_list = $res->fetch_all(MYSQLI_ASSOC);

$default_password = password_hash('SalbaStaff@2026', PASSWORD_DEFAULT);
$created_count = 0;

foreach ($staff_list as $staff) {
    // Generate username: use staff_code if starts with 'SMIS', otherwise sanitize name
    $username = $staff['staff_code'];
    if (empty($username) || strpos($username, 'SMIS') === false) {
        $name_parts = explode(' ', strtolower($staff['full_name']));
        $username = ($name_parts[0] ?? 'staff') . '.' . ($name_parts[1] ?? $staff['id']);
    }
    
    // Check if username already exists in users (to avoid duplicates)
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $username .= $staff['id'];
    }

    // Insert into users
    $role = 'staff'; // Default role
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $username, $default_password, $role);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        // Update staff profile with new user_id
        $conn->query("UPDATE staff_profiles SET user_id = $new_user_id WHERE id = {$staff['id']}");
        $created_count++;
        echo "Created user: $username for {$staff['full_name']}\n";
    } else {
        echo "Failed to create user for {$staff['full_name']}: " . $conn->error . "\n";
    }
}

echo "Successfully synchronized $created_count staff members.\n";
?>
