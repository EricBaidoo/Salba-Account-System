<?php
include_once 'includes/auth_functions.php';
include_once 'includes/db_connect.php';
include_once 'includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: includes/login.php');
    exit;
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$user_role = $_SESSION['role'] ?? 'staff';

if ($user_role === 'teacher') {
    header('Location: pages/teacher/check_in.php');
    exit;
} elseif ($user_role === 'academic_supervisor') {
    header('Location: pages/supervisor/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/sidebar.php'; ?>

    <main class="ml-72 p-8">
        <div class="max-w-5xl">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p class="text-lg text-gray-600 mb-8"><?php echo htmlspecialchars($school_name); ?> - Management System Dashboard</p>

            <h2 class="text-2xl font-bold text-gray-800 mb-6">Available Modules</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if ($user_role === 'admin'): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow hover:border-blue-300">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-cogs w-8 h-8 text-blue-600 mr-3"></i>
                        <h3 class="text-xl font-bold text-gray-900">Administration</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Manage students, staff, system settings, and user roles across the platform</p>
                    <a href="pages/administration/dashboard.php" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-700 font-semibold">
                        <span>Go to Administration Module</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow hover:border-green-300">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-wallet w-8 h-8 text-green-600 mr-3"></i>
                        <h3 class="text-xl font-bold text-gray-900">Finance</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Handle fees, payments, budgets, expenses, and generate comprehensive financial reports</p>
                    <a href="pages/finance/dashboard.php" class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 font-semibold">
                        <span>Go to Finance Module</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow hover:border-purple-300">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-book w-8 h-8 text-purple-600 mr-3"></i>
                        <h3 class="text-xl font-bold text-gray-900">Academics</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Manage grades, attendance records, class rosters, transcripts, and academic analytics</p>
                    <a href="pages/academics/dashboard.php" class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-700 font-semibold">
                        <span>Go to Academics Module</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow hover:border-orange-300">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-envelope w-8 h-8 text-orange-600 mr-3"></i>
                        <h3 class="text-xl font-bold text-gray-900">Communication</h3>
                    </div>
                    <p class="text-gray-600 mb-4">Send announcements, manage messages, and maintain effective communication channels</p>
                    <a href="pages/communication/dashboard.php" class="inline-flex items-center gap-2 text-orange-600 hover:text-orange-700 font-semibold">
                        <span>Go to Communication Module</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>