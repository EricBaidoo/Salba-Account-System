<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['teacher', 'facilitator', 'admin'])) {
    header('Location: ../../login');
    exit;
}

$user_id = $_SESSION['user_id'];
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

// Filters
$week_f = intval($_GET['week'] ?? 0);
$class_f = $_GET['class'] ?? '';
$search_f = trim($_GET['search'] ?? '');

$where = "l.teacher_id = $user_id";
if ($week_f) $where .= " AND l.week_number = $week_f";
if ($class_f) $where .= " AND l.class_name = '" . $conn->real_escape_string($class_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $where .= " AND l.class_name LIKE '%$s%'";
}

// Fetch Teacher's Allocated Classes for Filter
$current_academic_year = getAcademicYear($conn);
$teacher_classes_res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $user_id AND year = '$current_academic_year' ORDER BY class_name ASC");
$teacher_classes = [];
if ($teacher_classes_res) {
    while($r = $teacher_classes_res->fetch_assoc()) $teacher_classes[] = $r['class_name'];
}

// Stats (Filtered)
$stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$st_res = $conn->query("SELECT status, COUNT(*) as count FROM weekly_reports l WHERE $where GROUP BY status");
if ($st_res) {
    while($r = $st_res->fetch_assoc()) $stats[$r['status']] = $r['count'];
}

// Expected vs Submitted calculations
$current_week = 1;
if (function_exists('getWeekNumberForDate')) {
    $current_week = getWeekNumberForDate($conn, date('Y-m-d'));
}
$teacher_classes_count = count($teacher_classes);
$cumulative_expected = $teacher_classes_count * $current_week;
$cumulative_submitted = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE teacher_id = $user_id AND status != 'draft'")->fetch_row()[0] ?? 0;

function getReports($conn, $where, $status) {
    return $conn->query("
        SELECT l.* 
        FROM weekly_reports l 
        WHERE $where AND l.status = '$status' 
        ORDER BY l.created_at DESC
    ");
}

$drafts = getReports($conn, $where, 'draft');
$pending = getReports($conn, $where, 'pending');
$approved = getReports($conn, $where, 'approved');
$rejected = getReports($conn, $where, 'rejected');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporting Dashboard | Teacher Portal</title>
    <!-- Modern Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    animation: {
                        blob: "blob 7s infinite",
                    },
                    keyframes: {
                        blob: {
                            "0%": { transform: "translate(0px, 0px) scale(1)" },
                            "33%": { transform: "translate(30px, -50px) scale(1.1)" },
                            "66%": { transform: "translate(-20px, 20px) scale(0.9)" },
                            "100%": { transform: "translate(0px, 0px) scale(1)" }
                        },
                        shimmer: {
                            "100%": { transform: "translateX(100%)" }
                        }
                    }
                }
            }
        }
        function switchTab(tabId) {
            document.querySelectorAll('.portfolio-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            
            document.querySelectorAll('.portfolio-tab-btn').forEach(el => {
                el.classList.remove('border-teal-500', 'text-teal-600', 'bg-teal-50/50', 'shadow-sm');
                el.classList.add('border-transparent', 'text-slate-400', 'hover:text-slate-600', 'hover:bg-slate-50/50');
            });
            
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.remove('border-transparent', 'text-slate-400', 'hover:text-slate-600', 'hover:bg-slate-50/50');
            activeBtn.classList.add('border-teal-500', 'text-teal-600', 'bg-teal-50/50', 'shadow-sm');
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans selection:bg-teal-500 selection:text-white">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-24 md:pt-32 max-w-7xl mx-auto relative z-0">
        <!-- Abstract Background Blobs -->
        <div class="fixed top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-teal-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
            <div class="absolute top-[20%] right-[-10%] w-96 h-96 bg-indigo-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
            <div class="absolute bottom-[-20%] left-[20%] w-[30rem] h-[30rem] bg-rose-400/20 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>
        </div>

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-12">
            <div class="relative">
                <div class="absolute -left-6 top-1/2 -translate-y-1/2 w-1.5 h-12 bg-gradient-to-b from-teal-500 to-indigo-600 rounded-r-full hidden md:block"></div>
                <h1 class="text-5xl font-black tracking-tight text-slate-900">
                    Weekly <span class="text-transparent bg-clip-text bg-gradient-to-r from-teal-500 to-indigo-600">Reports</span>
                </h1>
                <p class="text-sm font-bold text-slate-500 uppercase tracking-[0.2em] mt-3 ml-1">Manage & Track Performance</p>
            </div>
            <a href="weekly_reports" class="group relative px-8 py-4 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] overflow-hidden transition-all hover:scale-105 hover:shadow-[0_10px_40px_-10px_rgba(15,23,42,0.5)] active:scale-95 flex items-center gap-3">
                <div class="absolute inset-0 bg-gradient-to-r from-teal-500 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <i class="fas fa-plus relative z-10 group-hover:rotate-90 transition-transform duration-500"></i> 
                <span class="relative z-10">Create Report</span>
            </a>
        </div>

        <!-- Progress Overview (Dark Glassmorphic) -->
        <div class="relative rounded-[2.5rem] p-8 md:p-10 mb-12 overflow-hidden shadow-2xl shadow-indigo-900/20 group">
            <div class="absolute inset-0 bg-slate-900"></div>
            <!-- Animated Gradient Background -->
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/50 via-slate-900 to-teal-900/50 opacity-80 group-hover:opacity-100 transition-opacity duration-700"></div>
            
            <div class="absolute -right-20 -top-20 opacity-5 pointer-events-none transition-transform duration-1000 group-hover:rotate-12 group-hover:scale-110">
                <i class="fas fa-chart-pie text-[20rem] text-white"></i>
            </div>
            
            <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-10">
                <div class="flex items-center gap-6 w-full lg:w-1/2">
                    <div class="w-20 h-20 bg-white/10 rounded-[1.5rem] flex items-center justify-center text-4xl text-teal-300 backdrop-blur-md border border-white/20 shadow-[0_0_30px_rgba(45,212,191,0.2)]">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div>
                        <h2 class="text-xs font-black text-teal-400 uppercase tracking-[0.2em] mb-2 drop-shadow-md">Semester Submission Goal</h2>
                        <div class="flex items-baseline gap-2">
                            <span class="text-5xl font-black text-white drop-shadow-lg"><?= $cumulative_submitted ?></span>
                            <span class="text-xl font-bold text-slate-400">/ <?= $cumulative_expected ?> Expected</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-2 font-medium">Based on <?= $teacher_classes_count ?> reports per week (up to Week <?= $current_week ?>)</div>
                    </div>
                </div>
                
                <div class="w-full lg:w-1/2 bg-white/5 backdrop-blur-sm p-6 rounded-3xl border border-white/10">
                    <div class="flex justify-between text-sm font-black text-white mb-4 tracking-wider">
                        <span class="uppercase">Overall Progress</span>
                        <span class="text-teal-400"><?= $cumulative_expected > 0 ? round(($cumulative_submitted / $cumulative_expected) * 100) : 0 ?>%</span>
                    </div>
                    <div class="w-full bg-slate-950/80 rounded-full h-4 ring-1 ring-white/10 p-1">
                        <div class="bg-gradient-to-r from-teal-400 via-indigo-400 to-purple-400 rounded-full h-full shadow-[0_0_20px_rgba(45,212,191,0.6)] relative overflow-hidden" style="width: <?= $cumulative_expected > 0 ? min(100, ($cumulative_submitted / $cumulative_expected) * 100) : 0 ?>%">
                            <div class="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite] -translate-x-full"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Drafts -->
            <div class="glass-card p-6 rounded-[2rem] flex flex-col gap-5 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(148,163,184,0.3)] transition-all duration-300 group">
                <div class="w-16 h-16 bg-slate-100 text-slate-600 rounded-[1.2rem] flex items-center justify-center text-2xl group-hover:bg-slate-800 group-hover:text-white transition-colors duration-300 shadow-inner">
                    <i class="fas fa-file-pen group-hover:scale-110 transition-transform"></i>
                </div>
                <div>
                    <div class="text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Drafts</div>
                    <div class="text-4xl font-black text-slate-800"><?= $stats['draft'] ?></div>
                </div>
            </div>
            <!-- Pending -->
            <div class="glass-card p-6 rounded-[2rem] flex flex-col gap-5 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(234,179,8,0.2)] transition-all duration-300 group">
                <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-[1.2rem] flex items-center justify-center text-2xl group-hover:bg-amber-500 group-hover:text-white transition-colors duration-300 shadow-inner">
                    <i class="fas fa-hourglass-half group-hover:rotate-180 transition-transform duration-700"></i>
                </div>
                <div>
                    <div class="text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Pending</div>
                    <div class="text-4xl font-black text-slate-800"><?= $stats['pending'] ?></div>
                </div>
            </div>
            <!-- Approved -->
            <div class="glass-card p-6 rounded-[2rem] flex flex-col gap-5 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(16,185,129,0.2)] transition-all duration-300 group">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-[1.2rem] flex items-center justify-center text-2xl group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300 shadow-inner">
                    <i class="fas fa-check-double group-hover:scale-110 transition-transform"></i>
                </div>
                <div>
                    <div class="text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Approved</div>
                    <div class="text-4xl font-black text-slate-800"><?= $stats['approved'] ?></div>
                </div>
            </div>
            <!-- Rejected -->
            <div class="glass-card p-6 rounded-[2rem] flex flex-col gap-5 hover:-translate-y-2 hover:shadow-[0_20px_40px_-15px_rgba(244,63,94,0.2)] transition-all duration-300 group">
                <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-[1.2rem] flex items-center justify-center text-2xl group-hover:bg-rose-500 group-hover:text-white transition-colors duration-300 shadow-inner">
                    <i class="fas fa-circle-exclamation group-hover:animate-pulse transition-transform"></i>
                </div>
                <div>
                    <div class="text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] mb-1">Rejected</div>
                    <div class="text-4xl font-black text-slate-800"><?= $stats['rejected'] ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="glass-card p-4 rounded-[2rem] mb-12 shadow-sm border border-white/60">
            <form class="flex flex-wrap items-center gap-4 w-full">
                <div class="relative flex-1 min-w-[200px]">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400"></i>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search by class name..." class="w-full pl-12 pr-4 py-4 bg-slate-50/50 border border-slate-200 rounded-[1.5rem] focus:bg-white focus:ring-4 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-bold placeholder:font-medium placeholder:text-slate-400">
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select name="class" class="pl-5 pr-10 py-4 bg-slate-50/50 border border-slate-200 rounded-[1.5rem] focus:bg-white focus:ring-4 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-bold appearance-none min-w-[140px] cursor-pointer">
                            <option value="">All Classes</option>
                            <?php foreach($teacher_classes as $tc): ?>
                                <option value="<?= htmlspecialchars($tc) ?>" <?= $class_f === $tc ? 'selected' : '' ?>><?= htmlspecialchars($tc) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select name="week" class="pl-5 pr-10 py-4 bg-slate-50/50 border border-slate-200 rounded-[1.5rem] focus:bg-white focus:ring-4 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all text-sm font-bold appearance-none min-w-[120px] cursor-pointer">
                            <option value="0">All Weeks</option>
                            <?php for($i=1; $i<=$total_weeks; $i++): ?>
                                <option value="<?= $i ?>" <?= $week_f == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <i class="fas fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-teal-500 to-indigo-500 text-white px-8 py-4 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] hover:shadow-[0_10px_20px_-10px_rgba(45,212,191,0.5)] hover:-translate-y-0.5 transition-all active:scale-95">
                    Filter
                </button>
                <?php if($week_f || $class_f || $search_f): ?>
                    <a href="report_portfolio" class="px-6 py-4 rounded-[1.5rem] text-[0.7rem] font-black text-rose-500 hover:bg-rose-50 uppercase tracking-[0.2em] transition-colors">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- PRIORITY ALERTS: Rejected Reports -->
        <?php if($rejected && $rejected->num_rows > 0): ?>
        <div class="mb-12">
            <h3 class="text-xs font-black text-rose-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-3 pl-2">
                <span class="relative flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                </span>
                Needs Immediate Revision
            </h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php while($p = $rejected->fetch_assoc()): ?>
                <div class="glass-card rounded-[2rem] p-6 md:p-8 border-l-4 border-l-rose-500 hover:shadow-xl hover:shadow-rose-500/10 transition-shadow relative overflow-hidden group">
                    <div class="absolute -right-6 -bottom-6 opacity-[0.03] group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-triangle-exclamation text-[10rem]"></i>
                    </div>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 relative z-10 gap-4">
                        <div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-rose-100/80 text-rose-600 rounded-lg text-[0.65rem] font-black uppercase tracking-widest border border-rose-200/50 mb-3">
                                Wk <?= $p['week_number'] ?>
                            </span>
                            <h4 class="text-2xl font-black text-slate-800"><?= htmlspecialchars($p['class_name']) ?></h4>
                        </div>
                        <a href="weekly_reports?edit=<?= $p['id'] ?>" class="h-12 px-6 bg-rose-500 text-white rounded-[1rem] flex items-center gap-3 text-[0.7rem] font-black uppercase tracking-widest hover:bg-rose-600 hover:shadow-[0_10px_20px_-10px_rgba(244,63,94,0.5)] transition-all active:scale-95">
                            <i class="fas fa-wrench text-lg"></i> Fix Report
                        </a>
                    </div>
                    <div class="p-5 bg-white/70 backdrop-blur-sm rounded-[1.5rem] border border-rose-100 relative z-10">
                        <div class="text-[0.6rem] font-black text-rose-500 uppercase tracking-[0.2em] mb-2 flex items-center gap-2"><i class="fas fa-comment-dots text-sm"></i> Supervisor Note</div>
                        <p class="text-sm font-bold text-slate-700 italic leading-relaxed">"<?= htmlspecialchars($p['supervisor_comments'] ?: 'No specific comments provided.') ?>"</p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Drafts -->
        <?php if($drafts && $drafts->num_rows > 0): ?>
        <div class="mb-12">
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-3 pl-2">
                <i class="fas fa-file-pen text-slate-400"></i> Active Drafts
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while($p = $drafts->fetch_assoc()): ?>
                <div class="glass-card rounded-[2rem] p-6 border border-slate-200/60 hover:-translate-y-1 hover:shadow-[0_20px_40px_-15px_rgba(148,163,184,0.2)] transition-all group">
                    <div class="flex justify-between items-center mb-6">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[0.65rem] font-black uppercase tracking-widest">
                            Wk <?= $p['week_number'] ?>
                        </span>
                        <div class="text-xs font-bold text-slate-400"><i class="far fa-clock"></i> <?= date('M j', strtotime($p['created_at'])) ?></div>
                    </div>
                    <h4 class="text-xl font-black text-slate-800 mb-6"><?= htmlspecialchars($p['class_name']) ?></h4>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-200/60">
                        <a href="weekly_reports?edit=<?= $p['id'] ?>" class="text-[0.7rem] font-black uppercase tracking-[0.15em] text-teal-600 hover:text-teal-700 flex items-center gap-2 group-hover:translate-x-1 transition-transform">
                            Continue Editing <i class="fas fa-arrow-right"></i>
                        </a>
                        <form method="POST" action="weekly_reports" onsubmit="return confirm('Delete this draft permanently?');">
                            <input type="hidden" name="report_id" value="<?= $p['id'] ?>">
                            <button type="submit" name="delete_report" class="w-10 h-10 rounded-[1rem] bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 flex items-center justify-center transition-colors">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Archive Tabs -->
        <div class="glass-card rounded-[2.5rem] overflow-hidden shadow-xl shadow-slate-200/50">
            
            <div class="flex border-b border-slate-200/50 bg-white/40">
                <button type="button" id="btn-tab-pending" onclick="switchTab('tab-pending')" class="portfolio-tab-btn flex-1 py-5 text-[0.7rem] font-black uppercase tracking-[0.2em] border-b-2 border-teal-500 text-teal-600 bg-teal-50/50 transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-hourglass-half"></i> Pending Review (<?= $stats['pending'] ?>)
                </button>
                <button type="button" id="btn-tab-approved" onclick="switchTab('tab-approved')" class="portfolio-tab-btn flex-1 py-5 text-[0.7rem] font-black uppercase tracking-[0.2em] border-b-2 border-transparent text-slate-400 hover:text-slate-600 hover:bg-slate-50/50 transition-all flex items-center justify-center gap-3">
                    <i class="fas fa-check-double"></i> Approved Archive (<?= $stats['approved'] ?>)
                </button>
            </div>

            <!-- Pending Review Tab -->
            <div id="tab-pending" class="portfolio-tab-content p-6 md:p-10 bg-white/60">
                <?php if($pending && $pending->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while($p = $pending->fetch_assoc()): ?>
                        <div class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm hover:shadow-lg hover:shadow-slate-200/50 transition-all relative group">
                            <div class="absolute top-5 right-5 flex items-center gap-2 px-3 py-1.5 bg-amber-50 text-amber-600 rounded-xl text-[0.6rem] font-black uppercase tracking-[0.2em] border border-amber-100">
                                <i class="fas fa-spinner fa-spin"></i> In Queue
                            </div>
                            <div class="mb-4 inline-block px-3 py-1 bg-slate-50 text-slate-500 rounded-lg text-[0.65rem] font-black uppercase tracking-widest border border-slate-100">Wk <?= $p['week_number'] ?></div>
                            <h4 class="text-xl font-black text-slate-800 mb-2"><?= htmlspecialchars($p['class_name']) ?></h4>
                            <div class="text-xs font-bold text-slate-400 mb-6 flex items-center gap-2">
                                <i class="fas fa-calendar-alt opacity-50"></i> Submitted: <?= date('M j, Y', strtotime($p['created_at'])) ?>
                            </div>
                            
                            <div class="flex items-center gap-3 pt-5 border-t border-slate-100">
                                <a href="print_weekly_report?id=<?= $p['id'] ?>&view=html" target="_blank" class="flex-1 py-3 bg-slate-50 text-slate-600 rounded-[1rem] text-center text-[0.65rem] font-black uppercase tracking-[0.2em] hover:bg-teal-50 hover:text-teal-600 transition-colors">
                                    Preview
                                </a>
                                <form method="POST" action="weekly_reports" class="flex-1" onsubmit="return confirm('Unsubmit this report back to drafts?');">
                                    <input type="hidden" name="report_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="unsubmit_report" class="w-full py-3 border-2 border-slate-100 text-slate-500 rounded-[1rem] text-center text-[0.65rem] font-black uppercase tracking-[0.2em] hover:border-amber-200 hover:bg-amber-50 hover:text-amber-600 transition-colors">
                                        Unsubmit
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="py-20 text-center">
                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300">
                            <i class="fas fa-inbox text-4xl"></i>
                        </div>
                        <h4 class="text-2xl font-black text-slate-800 mb-3 tracking-tight">All Caught Up!</h4>
                        <p class="text-sm font-medium text-slate-500">You don't have any reports waiting for review.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Tab -->
            <div id="tab-approved" class="portfolio-tab-content p-6 md:p-10 bg-white/60 hidden">
                <?php if($approved && $approved->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="py-5 px-6 text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] border-b-2 border-slate-100">Week / Class</th>
                                    <th class="py-5 px-6 text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] border-b-2 border-slate-100">Approved On</th>
                                    <th class="py-5 px-6 text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] border-b-2 border-slate-100">Supervisor Remarks</th>
                                    <th class="py-5 px-6 text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.2em] border-b-2 border-slate-100 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while($p = $approved->fetch_assoc()): ?>
                                <tr class="hover:bg-emerald-50/50 transition-colors group">
                                    <td class="py-6 px-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-emerald-100/50 text-emerald-600 rounded-[1rem] flex items-center justify-center font-black text-sm border border-emerald-200/50">
                                                W<?= $p['week_number'] ?>
                                            </div>
                                            <div class="font-black text-slate-800 text-lg"><?= htmlspecialchars($p['class_name']) ?></div>
                                        </div>
                                    </td>
                                    <td class="py-6 px-6 text-sm font-bold text-slate-500">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-check-circle text-emerald-400"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="py-6 px-6 max-w-sm">
                                        <?php if($p['supervisor_comments']): ?>
                                            <div class="bg-white px-4 py-3 rounded-xl border border-slate-100 shadow-sm">
                                                <p class="text-xs font-bold text-slate-600 italic leading-relaxed" title="<?= htmlspecialchars($p['supervisor_comments']) ?>">
                                                    "<?= htmlspecialchars($p['supervisor_comments']) ?>"
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs font-medium text-slate-300 italic px-4 py-2 bg-slate-50 rounded-lg">No remarks</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-6 px-6 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="print_weekly_report?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-12 h-12 rounded-[1.2rem] bg-white border border-slate-100 text-slate-500 hover:bg-teal-50 hover:text-teal-600 hover:border-teal-200 flex items-center justify-center transition-all shadow-sm" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="print_weekly_report?id=<?= $p['id'] ?>" target="_blank" class="w-12 h-12 rounded-[1.2rem] bg-white border border-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 flex items-center justify-center transition-all shadow-sm" title="Download PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="py-20 text-center">
                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300">
                            <i class="fas fa-archive text-4xl"></i>
                        </div>
                        <h4 class="text-2xl font-black text-slate-800 mb-3 tracking-tight">No Approved Reports Yet</h4>
                        <p class="text-sm font-medium text-slate-500">Once your reports are reviewed and approved, they will appear here in the archive.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </main>

    <!-- Mobile Create Button -->
    <a href="weekly_reports" class="md:hidden fixed bottom-6 right-6 w-16 h-16 bg-gradient-to-tr from-teal-500 to-indigo-600 text-white rounded-[1.5rem] flex items-center justify-center shadow-[0_10px_25px_rgba(45,212,191,0.4)] z-50 hover:scale-105 active:scale-95 transition-all">
        <i class="fas fa-plus text-xl"></i>
    </a>

</body>
</html>
