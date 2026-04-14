<?php
$current_page = basename($_SERVER['PHP_SELF']);
$script_dir   = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$pages_pos    = strpos($script_dir, '/pages');

$depth      = ($pages_pos !== false) ? (substr_count(trim(substr($script_dir, $pages_pos + 6), '/'), '/') + 1) : 0;
$base_path  = ($depth > 0) ? str_repeat('../', $depth) : 'pages/';
$root_path  = ($depth > 0) ? str_repeat('../', $depth + 1) : '';

if (!function_exists('nav_link')) {
    function nav_link($base, $target) { return $base . ltrim($target, '/'); }
}
if (!function_exists('nav_active')) {
    function nav_active($page, $current) {
        return ($page === $current) ? 'bg-indigo-50 text-indigo-700 font-semibold border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
    }
}
?>
<div class="fixed left-0 top-0 bottom-0 w-72 bg-white border-r border-gray-100 z-50 flex flex-col shadow-sm" id="sidebar">
    <div class="flex-shrink-0 flex items-center gap-3 px-5 py-5 border-b border-gray-100">
        <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl flex items-center justify-center text-white flex-shrink-0">
            <i class="fas fa-chalkboard-user text-sm"></i>
        </div>
        <div>
            <div class="font-bold text-gray-900 leading-tight">SALBA</div>
            <div class="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Teacher Portal</div>
        </div>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <div class="pt-2 pb-1 px-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5"><i class="fas fa-desktop"></i> Portal Main</span>
        </div>
        <a href="<?= nav_link($base_path, 'teacher/check_in.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= nav_active('check_in.php', $current_page) ?>">
            <i class="fas fa-location-dot w-4 text-center text-red-500"></i>
            <span class="text-sm">Daily Check-In (GPS)</span>
        </a>
        <div class="pt-4 pb-1 px-2 border-t border-gray-100 mt-2">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5"><i class="fas fa-book"></i> Academics</span>
        </div>
        <a href="<?= nav_link($base_path, 'teacher/attendance.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= nav_active('attendance.php', $current_page) ?>">
            <i class="fas fa-clipboard-user w-4 text-center text-blue-500"></i>
            <span class="text-sm">Class Attendance</span>
        </a>
        <a href="<?= nav_link($base_path, 'teacher/grades.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= nav_active('grades.php', $current_page) ?>">
            <i class="fas fa-star w-4 text-center text-yellow-500"></i>
            <span class="text-sm">My Gradebook</span>
        </a>
        <a href="<?= nav_link($base_path, 'teacher/lesson_plans.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= nav_active('lesson_plans.php', $current_page) ?>">
            <i class="fas fa-file-contract w-4 text-center text-green-500"></i>
            <span class="text-sm">Lesson Planning</span>
        </a>
        <a href="<?= nav_link($base_path, 'academics/transcripts.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all <?= nav_active('transcripts.php', $current_page) ?>">
            <i class="fas fa-scroll w-4 text-center text-purple-500"></i>
            <span class="text-sm">View Transcripts</span>
        </a>
    </nav>
    <div class="p-4 border-t border-gray-100">
        <a href="<?= nav_link($base_path, '../includes/logout.php') ?>" class="flex items-center gap-3 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors text-sm font-semibold">
            <i class="fas fa-sign-out-alt"></i> Logout Portal
        </a>
    </div>
</div>
