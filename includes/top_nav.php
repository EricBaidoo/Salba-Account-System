<?php
/**
 * Top Navigation Bar — SALBA Montessori 
 * Used for Teacher and Supervisor portals when sidebar is hidden.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$user_role = $_SESSION['role'] ?? 'staff';
$user_name = $_SESSION['username'] ?? 'User';

// Calculate relative path for root/logout
$script_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$pages_pos  = strpos($script_dir, '/pages');
if ($pages_pos !== false) {
    $relative_after_pages = trim(substr($script_dir, $pages_pos + 6), '/');
    $depth = $relative_after_pages === '' ? 0 : (substr_count($relative_after_pages, '/') + 1);
    $root_path = str_repeat('../', $depth + 1);
} else {
    $root_path = '';
}
?>
<header class="w-full bg-white border-b border-gray-100 px-6 py-4 sticky top-0 z-50 shadow-sm flex items-center justify-between">
    <div class="flex items-center gap-4">
        <?php 
        $logo_link = $root_path . "index.php";
        if ($user_role === 'teacher') $logo_link = $root_path . "pages/teacher/dashboard.php";
        if ($user_role === 'supervisor') $logo_link = $root_path . "pages/supervisor/dashboard.php";
        if ($user_role === 'admin') $logo_link = $root_path . "pages/administration/dashboard.php";
        ?>
        <a href="<?= $logo_link ?>" class="flex items-center gap-3 group">
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <span class="font-black text-gray-900 tracking-tighter text-lg leading-none">SALBA</span>
                <div class="text-[10px] text-indigo-500 font-bold uppercase tracking-widest leading-none mt-1"><?= ucfirst($user_role) ?> Portal</div>
            </div>
        </a>
    </div>

    <div class="flex items-center gap-6">
        <?php 
        $hub_link = $root_path . "index.php";
        if ($user_role === 'teacher') $hub_link = $root_path . "pages/teacher/dashboard.php";
        if ($user_role === 'supervisor') $hub_link = $root_path . "pages/supervisor/dashboard.php";
        if ($user_role === 'admin') $hub_link = $root_path . "pages/administration/dashboard.php";
        ?>
        <a href="<?= $hub_link ?>" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-indigo-600 transition-colors bg-gray-50 px-4 py-2 rounded-full border border-gray-100">
            <i class="fas fa-house"></i>
            <span>Dashboard Hub</span>
        </a>
        
        <div class="h-6 w-px bg-gray-200"></div>

        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-xs font-black text-gray-900 leading-none"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-[10px] text-gray-400 font-bold uppercase mt-1">Logged In</div>
            </div>
            <a href="<?= $root_path ?>logout.php" class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</header>
