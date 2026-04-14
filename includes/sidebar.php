<?php
// Central Sidebar Router
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = $_SESSION['role'] ?? 'staff';

if ($user_role === 'teacher') {
    include __DIR__ . '/sidebar_teacher.php';
} elseif ($user_role === 'academic_supervisor') {
    include __DIR__ . '/sidebar_supervisor.php';
} else {
    include __DIR__ . '/sidebar_admin.php';
}
?>
