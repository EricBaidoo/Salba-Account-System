<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password, $role);
            $stmt->fetch();
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id']  = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role']     = $role;
                header('Location: ../index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Salba Montessori Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">

    <div class="login-card">

        <!-- Header -->
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1 class="login-title">Salba Montessori School</h1>
            <p class="login-subtitle">Management System Portal</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if ($error): ?>
            <div class="login-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="login-form-group">
                    <label class="login-label" for="username">
                        <i class="fas fa-user text-gray-400"></i> Username
                    </label>
                    <input
                        type="text"
                        class="login-input"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="login-form-group">
                    <label class="login-label" for="password">
                        <i class="fas fa-lock text-gray-400"></i> Password
                    </label>
                    <input
                        type="password"
                        class="login-input"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Salba Montessori School &middot; All rights reserved
        </div>

    </div>

</body>
</html>
