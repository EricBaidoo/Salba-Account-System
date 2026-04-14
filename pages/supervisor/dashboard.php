<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'academic_supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen p-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Welcome Back, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p class="text-lg text-gray-600 mb-8">Academic Supervisor Overview</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <i class="fas fa-file-signature text-4xl text-green-500 mb-4"></i>
                <h2 class="text-xl font-bold">Review Lesson Plans</h2>
                <p class="text-gray-500 mb-4">Approve or reject weekly lesson plans submitted by teachers.</p>
                <a href="lesson_plans.php" class="text-blue-600 font-bold hover:underline">Go to Approvals &rarr;</a>
            </div>
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                <i class="fas fa-scroll text-4xl text-purple-500 mb-4"></i>
                <h2 class="text-xl font-bold">Manage Transcripts</h2>
                <p class="text-gray-500 mb-4">Review global student transcripts and add supervisor remarks.</p>
                <a href="../academics/transcripts.php" class="text-blue-600 font-bold hover:underline">Go to Transcripts &rarr;</a>
            </div>
        </div>
    </main>
</body>
</html>
