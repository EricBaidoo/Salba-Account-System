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
<header class="w-full bg-white/95 backdrop-blur-md border-b border-gray-100 px-4 md:px-10 py-3 md:py-4 sticky top-0 z-50 shadow-sm flex items-center justify-between transition-all duration-300">
    <div class="flex items-center gap-4">
        <!-- Mobile Toggle (Only for Admin pages where sidebar exists) -->
        <?php if ($user_role === 'admin'): ?>
            <button onclick="toggleSidebar()" class="lg:hidden w-11 h-11 flex items-center justify-center text-slate-600 hover:bg-slate-50/80 rounded-xl transition-all active:scale-95 shadow-sm border border-slate-100">
                <i class="fas fa-bars text-xl"></i>
            </button>
        <?php endif; ?>

        <?php 
        $logo_link = BASE_URL . "index";
        if ($user_role === 'facilitator') $logo_link = BASE_URL . "pages/teacher/dashboard";
        if ($user_role === 'supervisor') $logo_link = BASE_URL . "pages/supervisor/dashboard";
        if ($user_role === 'admin') $logo_link = BASE_URL . "pages/administration/dashboard";
        ?>
        <a href="<?= $logo_link ?>" class="flex items-center gap-2 md:gap-4 group">
            <div class="w-9 h-9 md:w-12 md:h-12 bg-white rounded-xl md:rounded-2xl flex items-center justify-center overflow-hidden shadow-lg shadow-indigo-500/10 group-hover:scale-105 transition-all border border-slate-100">
                <img src="<?= BASE_URL . getSystemLogo($conn) ?>" alt="System Logo" class="w-full h-full object-contain">
            </div>
            <div class="hidden xs:block">
                <span class="font-black text-slate-900 tracking-tighter text-base md:text-2xl leading-none">SALBA</span>
                <div class="text-[8px] md:text-[10px] text-indigo-500 font-extrabold uppercase tracking-[0.2em] leading-none mt-1"><?= ucfirst($user_role) ?> Portal</div>
            </div>
        </a>
    </div>

    <div class="flex items-center gap-2 md:gap-8">
        <?php 
        $hub_link = BASE_URL . "index";
        if ($user_role === 'facilitator') $hub_link = BASE_URL . "pages/teacher/dashboard";
        if ($user_role === 'supervisor') $hub_link = BASE_URL . "pages/supervisor/dashboard";
        if ($user_role === 'admin') $hub_link = BASE_URL . "pages/administration/dashboard";
        ?>
        <a href="<?= $hub_link ?>" class="flex items-center gap-2 text-[11px] md:text-sm font-black text-slate-600 hover:text-indigo-600 transition-all bg-slate-50/80 px-3 md:px-6 py-2 md:py-2.5 rounded-xl border border-slate-100 hover:border-indigo-100 group shadow-sm">
            <i class="fas fa-house-chimney group-hover:-translate-y-0.5 transition-transform text-xs md:text-base"></i>
            <span class="hidden md:inline">Dashboard Hub</span>
        </a>
        
        <div class="h-8 w-px bg-slate-100 hidden sm:block"></div>

        <div class="flex items-center gap-3 md:gap-5">
            <div class="text-right hidden sm:block">
                <div class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-0.5">Welcome Back</div>
                <div class="text-xs md:text-sm font-black text-slate-900 tracking-tight leading-none"><?= htmlspecialchars($user_name) ?></div>
            </div>
            
            <!-- User Profile Avatar/Icon Display on Mobile -->
            <div class="sm:hidden w-9 h-9 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center text-slate-400 shadow-sm">
                <i class="fas fa-user-circle text-lg"></i>
            </div>

            <a href="<?= BASE_URL ?>logout" class="w-10 h-10 md:w-11 md:h-11 bg-red-50 text-red-500 rounded-xl md:rounded-[1rem] flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-md shadow-red-100 group" title="Secure Logout">
                <i class="fas fa-power-off text-sm md:text-lg group-hover:rotate-90 transition-transform"></i>
            </a>
        </div>
    </div>
</header>
