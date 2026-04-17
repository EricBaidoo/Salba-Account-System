<?php
/**
 * Top Navigation Bar — SALBA Montessori 
 * Used for Teacher and Supervisor portals when sidebar is hidden.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$user_role = $_SESSION['role'] ?? 'staff';
$user_name = $_SESSION['username'] ?? 'User';

// We now rely on BASE_URL from config via db_connect.php
$root_path = BASE_URL;
?>
<header class="w-full bg-white border-b border-gray-100 px-4 md:px-6 py-4 sticky top-0 z-50 shadow-sm flex items-center justify-between">
    <div class="flex items-center gap-4">
        <!-- Mobile Toggle (Only for Admin pages where sidebar exists) -->
        <?php if ($user_role === 'admin'): ?>
            <button onclick="toggleSidebar()" class="lg:hidden w-10 h-10 flex items-center justify-center text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                <i class="fas fa-bars text-xl"></i>
            </button>
        <?php endif; ?>

        <?php 
        $logo_link = BASE_URL . "index";
        if ($user_role === 'facilitator') $logo_link = BASE_URL . "pages/teacher/dashboard";
        if ($user_role === 'supervisor') $logo_link = BASE_URL . "pages/supervisor/dashboard";
        if ($user_role === 'admin') $logo_link = BASE_URL . "pages/administration/dashboard";
        ?>
        <a href="<?= $logo_link ?>" class="flex items-center gap-2 md:gap-3 group">
            <div class="w-8 h-8 md:w-10 md:h-10 bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-lg md:rounded-xl flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                <i class="fas fa-graduation-cap text-xs md:text-base"></i>
            </div>
            <div class="hidden xs:block">
                <span class="font-black text-gray-900 tracking-tighter text-base md:text-lg leading-none">SALBA</span>
                <div class="text-[9px] md:text-[10px] text-indigo-500 font-bold uppercase tracking-widest leading-none mt-1"><?= ucfirst($user_role) ?> Portal</div>
            </div>
        </a>
    </div>

    <div class="flex items-center gap-3 md:gap-6">
        <?php 
        $hub_link = BASE_URL . "index";
        if ($user_role === 'facilitator') $hub_link = BASE_URL . "pages/teacher/dashboard";
        if ($user_role === 'supervisor') $hub_link = BASE_URL . "pages/supervisor/dashboard";
        if ($user_role === 'admin') $hub_link = BASE_URL . "pages/administration/dashboard";
        ?>
        <a href="<?= $hub_link ?>" class="flex items-center gap-2 text-[10px] md:text-sm font-bold text-gray-600 hover:text-indigo-600 transition-colors bg-gray-50 px-3 md:px-4 py-1.5 md:py-2 rounded-full border border-gray-100">
            <i class="fas fa-house"></i>
            <span class="hidden sm:inline">Dashboard Hub</span>
        </a>
        
        <div class="h-6 w-px bg-gray-200 hidden xs:block"></div>

        <div class="flex items-center gap-2 md:gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-[10px] md:text-xs font-black text-gray-900 leading-none"><?= htmlspecialchars($user_name) ?></div>
                <div class="text-[8px] md:text-[10px] text-gray-400 font-bold uppercase mt-1">Logged In</div>
            </div>
            <a href="<?= BASE_URL ?>logout" class="w-8 h-8 md:w-10 md:h-10 bg-red-50 text-red-500 rounded-lg md:rounded-xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-sm" title="Logout">
                <i class="fas fa-sign-out-alt text-xs md:text-base"></i>
            </a>
        </div>
    </div>
</header>
