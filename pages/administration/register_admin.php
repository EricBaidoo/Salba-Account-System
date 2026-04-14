<?php
// Run this ONCE to create the first admin user, then delete or secure this file!
include '../../includes/db_connect.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->bind_param("ss", $username, $password);
    if ($stmt->execute()) {
        echo "<div class='p-4 bg-green-100 text-green-700 rounded border border-green-200'>Admin user created. You can now login.</div>";
    } else {
        echo "<div class='p-4 bg-red-100 text-red-700 rounded border border-red-200'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
        <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="max-w-7xl mx-auto mt-5 max-w-400">
        <h2>Register Admin</h2>
        <form action="register_admin.php" method="POST">
            <div class="mb-">
                <label for="username" class="block text-sm font-medium mb-">Username</label>
                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="username" name="username" required>
            </div>
            <div class="mb-">
                <label for="password" class="block text-sm font-medium mb-">Password</label>
                <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded" id="password" name="password" required>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Register</button>
        </form>
    </div>
    </body>
</html>
