<?php
include '../../includes/db_connect.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed);
    if ($stmt->execute()) {
        $success = 'User registered successfully.';
    } else {
        $error = 'Registration failed. Username may already exist.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="max-w-7xl mx-auto mt-5">
        <div class="flex flex-wrap justify-center">
            <div class="col-md-5">
                <div class="bg-white rounded shadow shadow">
                    <div class="bg-white rounded shadow-header text-center bg-success text-white">
                        <h4>Register</h4>
                    </div>
                    <div class="bg-white rounded shadow-body">
                        <?php if ($error): ?>
                            <div class="p-4 bg-red-100 text-red-700 rounded border border-red-200"> <?php echo $error; ?> </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="p-4 bg-green-100 text-green-700 rounded border border-green-200"> <?php echo $success; ?> </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-">
                                <label for="username" class="block text-sm font-medium mb-">Username</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="username" name="username" required>
                            </div>
                            <div class="mb-">
                                <label for="password" class="block text-sm font-medium mb-">Password</label>
                                <input type="password" class="w-full px-3 py-2 border border-gray-300 rounded" id="password" name="password" required>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 w-full">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

