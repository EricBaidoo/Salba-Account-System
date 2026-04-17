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

// Only Admin keeps the sidebar.
if ($user_role === 'admin') {
    include __DIR__ . '/sidebar_admin_modern.php';
} else {
    // Non-admins use their Hub dashboards
    return;
}
?>
