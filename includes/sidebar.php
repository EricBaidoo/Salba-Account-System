<?php
// Central Sidebar Router
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_role = $_SESSION['role'] ?? 'staff';

// Dashboards are Hubs now for non-admins. Only Admin keeps the sidebar.
if ($user_role !== 'admin') {
    return; // Exit silent and early
}

if ($user_role === 'facilitator') {
    include __DIR__ . '/sidebar_teacher.php';
} elseif ($user_role === 'supervisor') {
    include __DIR__ . '/sidebar_supervisor.php';
} else {
    include __DIR__ . '/sidebar_admin.php';
}
?>
