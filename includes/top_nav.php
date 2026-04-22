<?php
/**
 * Top Navigation Bar — SALBA Montessori 
 * Used for Teacher and Supervisor portals when sidebar is hidden.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$user_role = $_SESSION['role'] ?? 'staff';
$user_name = $_SESSION['username'] ?? 'User';

// Ensure config is loaded for BASE_URL
if (!defined('BASE_URL')) {
    include_once __DIR__ . '/config.php';
}
include_once __DIR__ . '/system_settings.php';
$root_path = defined('BASE_URL') ? BASE_URL : '/';
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
                <div class="text-[0.5rem] md:text-[0.625rem] text-indigo-500 font-extrabold uppercase tracking-[0.2em] leading-none mt-1"><?= ucfirst($user_role) ?> Portal</div>
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
        <a href="<?= $hub_link ?>" class="flex items-center gap-2 text-[0.6875rem] md:text-sm font-black text-slate-600 hover:text-indigo-600 transition-all bg-slate-50/80 px-3 md:px-6 py-2 md:py-2.5 rounded-xl border border-slate-100 hover:border-indigo-100 group shadow-sm">
            <i class="fas fa-house-chimney group-hover:-translate-y-0.5 transition-transform text-xs md:text-base"></i>
            <span class="hidden md:inline">Dashboard Hub</span>
        </a>
        
        <div class="h-8 w-px bg-slate-100 hidden sm:block"></div>

        <div class="flex items-center gap-3 md:gap-5">
            <a href="<?= BASE_URL ?>pages/common/profile.php" class="flex items-center gap-3 md:gap-5 group/profile">
                <div class="text-right hidden sm:block">
                    <div class="text-[0.5625rem] font-black text-indigo-400 uppercase tracking-widest mb-0.5 opacity-70 group-hover/profile:opacity-100 transition-opacity">Welcome Back</div>
                    <div class="text-xs md:text-sm font-black text-slate-900 tracking-tight leading-none"><?= htmlspecialchars($user_name) ?></div>
                </div>
                
                <!-- User Profile Avatar/Icon Display -->
                <div class="w-9 h-9 md:w-11 md:h-11 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center text-slate-400 shadow-sm group-hover/profile:border-indigo-200 group-hover/profile:bg-indigo-50 group-hover/profile:text-indigo-500 transition-all">
                    <i class="fas fa-user-circle text-lg md:text-xl"></i>
                </div>
            </a>

            <a href="<?= BASE_URL ?>logout" class="w-10 h-10 md:w-11 md:h-11 bg-red-50 text-red-500 rounded-xl md:rounded-[1rem] flex items-center justify-center hover:bg-red-500 hover:text-white transition-all shadow-md shadow-red-100 group" title="Secure Logout">
                <i class="fas fa-power-off text-sm md:text-lg group-hover:rotate-90 transition-transform"></i>
            </a>
        </div>
    </div>
</header>

<?php
// Global Flash Message Display Logic
$flashes = get_flash();
if (!empty($flashes)): ?>
<div class="fixed top-24 right-4 md:right-10 z-[60] flex flex-col gap-3 pointer-events-none max-w-[90vw] md:max-w-md">
    <?php foreach ($flashes as $f): 
        $type = $f['type'] ?? 'info';
        $colorClass = ($type === 'success') ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800';
        $iconClass = ($type === 'success') ? 'fa-check-circle text-emerald-500' : 'fa-exclamation-circle text-rose-500';
    ?>
    <div class="pointer-events-auto flex items-center gap-4 px-6 py-4 rounded-2xl border <?= $colorClass ?> shadow-2xl animate-in fade-in slide-in-from-right-10 duration-500">
        <div class="w-10 h-10 rounded-xl bg-white/50 flex items-center justify-center shadow-sm">
            <i class="fas <?= $iconClass ?> text-xl"></i>
        </div>
        <div class="flex-1 pr-4">
            <p class="text-[0.5625rem] font-black uppercase tracking-[0.2em] opacity-50 mb-0.5"><?= strtoupper($type) ?></p>
            <p class="text-xs font-bold leading-tight"><?= htmlspecialchars($f['message']) ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-black transition-colors p-2">
            <i class="fas fa-times text-[0.625rem]"></i>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<script>
    // System-wide Auto-dismiss Logic
    setTimeout(() => {
        const toasts = document.querySelectorAll('.animate-in');
        toasts.forEach(t => {
            t.style.opacity = '0';
            t.style.transform = 'translateX(1.25rem)';
            t.style.transition = 'all 0.5s ease';
            setTimeout(() => t.remove(), 500);
        });
    }, 6000);
</script>
<?php endif; ?>

