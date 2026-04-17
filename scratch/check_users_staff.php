<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed");

echo "--- User Management ---\n";
$res = $conn->query("SELECT id, username, role, status FROM users LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | User: {$row['username']} | Role: {$row['role']} | Status: {$row['status']}\n";
    }
} else {
    echo "Error fetching users\n";
}

echo "\n--- Staff Profiles ---\n";
$res = $conn->query("SELECT id, first_name, last_name, employee_id, job_title FROM staff_profiles LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']} | Name: {$row['first_name']} {$row['last_name']} | Title: {$row['job_title']}\n";
    }
} else {
    echo "Error fetching staff profiles\n";
}
?>
