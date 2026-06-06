<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
include_once '../../includes/system_settings.php';
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

// Fetch Dynamic Form Schema
$weekly_report_defaults = [
    'topics_covered' => ['label' => 'Topics Covered (Summary)', 'helper' => 'Briefly list the specific topics or subtopics taught this week. What were the core learning objectives?', 'hidden' => false],
    'assessments_conducted' => ['label' => 'Assessments Conducted & General Performance', 'helper' => 'Did you give a quiz, test, or project? Mention the type of assessment and the general outcome.', 'hidden' => false],
    'overall_performance' => ['label' => 'Overall Class Performance', 'helper' => '', 'hidden' => false],
    'struggling_students' => ['label' => 'Struggling Students & Intervention Plans', 'helper' => 'List students who fell behind and the specific steps you are taking to help them.', 'hidden' => false],
    'excelling_students' => ['label' => 'Excelling Students & Enrichment', 'helper' => 'List students who mastered the content quickly and how you plan to challenge them further.', 'hidden' => false],
    'differentiation_strategies' => ['label' => 'Differentiation Strategies Used', 'helper' => 'How did you adapt the lesson for different learners? (e.g., visual aids, group work)', 'hidden' => false],
    'tlm_usage' => ['label' => 'Teaching & Learning Materials (TLMs)', 'helper' => 'What physical or digital tools did you use? (e.g., Smartboard, lab equipment, charts)', 'hidden' => false],
    'general_behavior' => ['label' => 'General Class Behavior', 'helper' => 'Describe the overall mood and engagement. Were they attentive, restless, or talkative?', 'hidden' => false],
    'discipline_issues' => ['label' => 'Discipline Issues & Actions Taken', 'helper' => 'Detail any behavioral incidents, who was involved, and the action taken.', 'hidden' => false],
    'attendance_concerns' => ['label' => 'Attendance Concerns', 'helper' => 'List students with frequent absences or persistent lateness. Is there a pattern?', 'hidden' => false],
    'parents_contacted' => ['label' => 'Parents Contacted This Week (Who and Why?)', 'helper' => 'Which parents did you speak to? Was it for a positive reason or a concern?', 'hidden' => false],
    'co_curricular_activities' => ['label' => 'Co-curricular Duties & Activities', 'helper' => 'What non-academic duties did you perform? (e.g., club meetings, break duty)', 'hidden' => false],
    'challenges_faced' => ['label' => 'Challenges Faced', 'helper' => 'What obstacles hindered your teaching? (e.g., time constraints, noisy environment)', 'hidden' => false],
    'self_reflection' => ['label' => 'Teacher Self-Reflection (What worked? What didn\'t?)', 'helper' => 'Think critically: What specific strategy worked perfectly? What needs a new approach?', 'hidden' => false],
    'support_required' => ['label' => 'Support / Resources Required', 'helper' => '', 'hidden' => false],
    'next_week_focus' => ['label' => 'Focus For Next Week', 'helper' => 'What are your primary goals? Are you starting a new topic or reviewing material?', 'hidden' => false]
];

$wr_schema_raw = getSystemSetting($conn, 'weekly_report_form_schema', '');
$wr_schema = $wr_schema_raw ? json_decode($wr_schema_raw, true) : $weekly_report_defaults;
foreach ($weekly_report_defaults as $k => $v) { if (!isset($wr_schema[$k])) $wr_schema[$k] = $v; }

// Load report for editing if requested
$edit_id = intval($_GET['edit'] ?? 0);
$edit_data = null;
if ($edit_id) {
    $res = $conn->query("SELECT * FROM weekly_reports WHERE id = $edit_id AND teacher_id = $uid AND status IN ('draft', 'pending', 'rejected') LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
    }
}

// Helper to get form values or JSON decoded values
function getVal($key, $default = '') {
    global $edit_data;
    return htmlspecialchars($_POST[$key] ?? $edit_data[$key] ?? $default);
}
function getJsonVal($key, $index, $subKey, $default = '') {
    global $edit_data;
    if (isset($_POST[$key][$index][$subKey])) {
        return htmlspecialchars($_POST[$key][$index][$subKey]);
    }
    if (isset($edit_data[$key])) {
        $arr = json_decode($edit_data[$key], true);
        return htmlspecialchars($arr[$index][$subKey] ?? $default);
    }
    return htmlspecialchars($default);
}

// Decode custom fields for editing
$custom_edit_data = [];
if ($edit_data && !empty($edit_data['custom_fields'])) {
    $custom_edit_data = json_decode($edit_data['custom_fields'], true) ?: [];
}

function getCustomVal($key) {
    global $custom_edit_data;
    return htmlspecialchars($_POST[$key] ?? $custom_edit_data[$key] ?? '');
}

// Fetch classes allocated to this teacher
$teacher_classes = [];
$tc_res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year' ORDER BY class_name ASC");
if ($tc_res) {
    while($r = $tc_res->fetch_assoc()) $teacher_classes[] = $r['class_name'];
}

// Fetch subjects
// $subjects = [];
// $sub_res = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
// if ($sub_res) {
//     while($r = $sub_res->fetch_assoc()) $subjects[] = $r;
// }

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_report']) || isset($_POST['save_draft']))) {
    $action_status = isset($_POST['save_draft']) ? 'draft' : 'pending';
    $report_id = intval($_POST['existing_report_id'] ?? 0);
    
    $class_name = trim($_POST['class_name'] ?? '');
    // $subject_id = intval($_POST['subject_id'] ?? 0);
    $week_ending = trim($_POST['week_ending'] ?? '');
    $week_number = intval($_POST['week_number'] ?? 1);
    
    $topics_covered = trim($_POST['topics_covered'] ?? '');
    $assessments_conducted = trim($_POST['assessments_conducted'] ?? '');
    $overall_performance = trim($_POST['overall_performance'] ?? '');
    $struggling_students = trim($_POST['struggling_students'] ?? '');
    
    $general_behavior = trim($_POST['general_behavior'] ?? '');
    $discipline_issues = trim($_POST['discipline_issues'] ?? '');
    $attendance_concerns = trim($_POST['attendance_concerns'] ?? '');
    
    $parents_contacted = trim($_POST['parents_contacted'] ?? '');
    $challenges_faced = trim($_POST['challenges_faced'] ?? '');
    $support_required = implode(',', $_POST['support_required'] ?? []);
    $next_week_focus = trim($_POST['next_week_focus'] ?? '');
    
    $differentiation_strategies = trim($_POST['differentiation_strategies'] ?? '');
    $excelling_students = trim($_POST['excelling_students'] ?? '');
    $tlm_usage = trim($_POST['tlm_usage'] ?? '');
    $self_reflection = trim($_POST['self_reflection'] ?? '');
    $co_curricular_activities = trim($_POST['co_curricular_activities'] ?? '');
    
    // Custom Fields
    $custom_data = [];
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'custom_') === 0) {
            $custom_data[$k] = trim($v);
        }
    }
    $custom_fields_json = json_encode($custom_data);

    if ($class_name) {
        if ($report_id > 0) {
            $stmt = $conn->prepare("UPDATE weekly_reports SET 
                class_name=?, week_ending=?, week_number=?, academic_term=?, academic_year=?, status=?,
                topics_covered=?, assessments_conducted=?, overall_performance=?, struggling_students=?,
                general_behavior=?, discipline_issues=?, attendance_concerns=?, parents_contacted=?,
                challenges_faced=?, support_required=?, next_week_focus=?,
                differentiation_strategies=?, excelling_students=?, tlm_usage=?, self_reflection=?, co_curricular_activities=?,
                custom_fields=?
                WHERE id=? AND teacher_id=?");
            $stmt->bind_param("ssissssssssssssssssssssii", 
                $class_name, $week_ending, $week_number, $current_term, $current_year, $action_status,
                $topics_covered, $assessments_conducted, $overall_performance, $struggling_students,
                $general_behavior, $discipline_issues, $attendance_concerns, $parents_contacted,
                $challenges_faced, $support_required, $next_week_focus,
                $differentiation_strategies, $excelling_students, $tlm_usage, $self_reflection, $co_curricular_activities,
                $custom_fields_json,
                $report_id, $uid
            );
            if ($stmt->execute()) {
                redirect('report_portfolio', 'success', "Report successfully " . ($action_status == 'draft' ? "saved as draft." : "submitted."));
            } else {
                set_flash('error', "Database error updating report: " . $conn->error);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO weekly_reports (
                teacher_id, class_name, week_ending, week_number, academic_term, academic_year, status,
                topics_covered, assessments_conducted, overall_performance, struggling_students,
                general_behavior, discipline_issues, attendance_concerns, parents_contacted,
                challenges_faced, support_required, next_week_focus,
                differentiation_strategies, excelling_students, tlm_usage, self_reflection, co_curricular_activities,
                custom_fields
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississssssssssssssssssss", 
                $uid, $class_name, $week_ending, $week_number, $current_term, $current_year, $action_status,
                $topics_covered, $assessments_conducted, $overall_performance, $struggling_students,
                $general_behavior, $discipline_issues, $attendance_concerns, $parents_contacted,
                $challenges_faced, $support_required, $next_week_focus,
                $differentiation_strategies, $excelling_students, $tlm_usage, $self_reflection, $co_curricular_activities,
                $custom_fields_json
            );
            if ($stmt->execute()) {
                redirect('report_portfolio', 'success', "Report successfully " . ($action_status == 'draft' ? "saved as draft." : "submitted."));
            } else {
                set_flash('error', "Database error inserting report.");
            }
        }
    } else {
        set_flash('error', "Class is required.");
    }
}

// Handle Delete/Unsubmit via Portfolio (we also process it here just in case)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_report'])) {
    $report_id = intval($_POST['report_id'] ?? 0);
    $conn->query("DELETE FROM weekly_reports WHERE id = $report_id AND teacher_id = $uid AND status IN ('draft', 'pending', 'rejected')");
    redirect('report_portfolio', 'success', "Report deleted.");
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsubmit_report'])) {
    $report_id = intval($_POST['report_id'] ?? 0);
    $conn->query("UPDATE weekly_reports SET status = 'draft' WHERE id = $report_id AND teacher_id = $uid AND status = 'pending'");
    redirect('report_portfolio', 'success', "Report unsubmitted to draft.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_id ? 'Edit' : 'Create' ?> Weekly Report | Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 pb-20">

    <?php include '../../includes/top_nav.php'; ?>

    <!-- Flash Messages (usually handled in top_nav but included here for direct alerts) -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div id="flash-banner" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 animate-bounce">
            <div class="px-6 py-3 rounded-full font-black text-sm uppercase tracking-widest shadow-2xl <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white' ?>">
                <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if(isset($_GET['draft_imported'])): ?>
        <div class="max-w-full mx-auto px-4 md:px-8 pt-24 mb-2">
            <div class="bg-teal-600 text-white p-5 rounded-2xl flex items-center justify-between shadow-xl shadow-teal-200 border-2 border-teal-500 animate-pulse">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl backdrop-blur-sm">
                        <i class="fas fa-magic"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-lg">Document Imported Successfully!</h3>
                        <p class="text-sm text-teal-100">Review your generated draft below before submitting.</p>
                    </div>
                </div>
                <button onclick="this.parentElement.style.display='none'" class="text-teal-200 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
        </div>
    <?php endif; ?>

    <main class="max-w-full mx-auto p-4 md:p-8 <?= !isset($_GET['draft_imported']) ? 'pt-24' : '' ?>">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <a href="report_portfolio" class="text-sm font-black text-slate-900 uppercase tracking-widest hover:text-teal-600 transition flex items-center gap-2 mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 class="text-4xl font-black text-gray-900 tracking-tight">
                    <?= $edit_id ? 'Edit Report' : 'New Report' ?> 
                    <?php if($edit_data && $edit_data['status'] == 'rejected'): ?>
                        <span class="ml-2 inline-flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-600 rounded-lg text-xs font-black uppercase tracking-widest align-middle">
                            <i class="fas fa-exclamation-triangle"></i> Needs Revision
                        </span>
                    <?php endif; ?>
                </h1>
            </div>
            
            <?php if(!$edit_id): ?>
            <!-- Import Button triggers modal -->
            <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-teal-50 text-teal-600 px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-600 hover:text-white transition shadow-sm border border-teal-100 flex items-center gap-2 group">
                <i class="fas fa-file-import group-hover:animate-bounce"></i> Import Word File
            </button>
            <?php endif; ?>
        </div>

        <?php if($edit_data && $edit_data['status'] == 'rejected' && !empty($edit_data['supervisor_comments'])): ?>
            <div class="mb-8 p-6 bg-red-50 border border-red-200 rounded-3xl relative overflow-hidden">
                <div class="absolute -right-4 -top-4 opacity-5 text-red-600"><i class="fas fa-comment-dots text-[8rem]"></i></div>
                <div class="relative z-10">
                    <div class="text-sm font-black text-red-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-user-tie"></i> Supervisor Remarks
                    </div>
                    <p class="text-red-900 font-bold italic leading-relaxed text-sm">"<?= nl2br(htmlspecialchars($edit_data['supervisor_comments'])) ?>"</p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="weekly_reports" id="reportForm" class="space-y-8">
            <input type="hidden" name="existing_report_id" value="<?= $edit_id ?>">
            
            <!-- Core Info -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-teal-500"></div>
                <h2 class="text-base font-black text-teal-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> Core Information
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Class <span class="text-red-500">*</span></label>
                        <select name="class_name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold">
                            <option value="">Select Class...</option>
                            <?php foreach($teacher_classes as $tc): ?>
                                <option value="<?= htmlspecialchars($tc) ?>" <?= getVal('class_name') === $tc ? 'selected' : '' ?>><?= htmlspecialchars($tc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Week Number <span class="text-red-500">*</span></label>
                        <select name="week_number" class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold">
                            <?php for($i=1; $i<=$total_weeks; $i++): ?>
                                <option value="<?= $i ?>" <?= getVal('week_number') == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Week Ending (Friday Date) <span class="text-red-500">*</span></label>
                        <input type="date" name="week_ending" value="<?= getVal('week_ending') ?>" required class="w-full px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold">
                    </div>
                </div>
            </div>

            <!-- 1. Academic Coverage & Performance -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                <h2 class="text-base font-black text-indigo-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    1. Academic Coverage & Performance
                </h2>
                <div class="space-y-6">
                    <?php if (!$wr_schema['topics_covered']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['topics_covered']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['topics_covered']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['topics_covered']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="topics_covered" required class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('topics_covered') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['assessments_conducted']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['assessments_conducted']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['assessments_conducted']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['assessments_conducted']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="assessments_conducted" required placeholder="If none, type 'None'" class="w-full h-12 p-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('assessments_conducted') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['overall_performance']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['overall_performance']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['overall_performance']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['overall_performance']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="overall_performance" required class="w-full h-16 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('overall_performance') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if (!$wr_schema['struggling_students']['hidden']): ?>
                        <div>
                            <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['struggling_students']['label']) ?> <span class="text-red-500">*</span></label>
                            <?php if(!empty($wr_schema['struggling_students']['helper'])): ?>
                                <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['struggling_students']['helper']) ?></p>
                            <?php endif; ?>
                            <textarea name="struggling_students" required placeholder="If none, type 'None'" class="w-full h-12 p-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('struggling_students') ?></textarea>
                        </div>
                        <?php endif; ?>

                        <?php if (!$wr_schema['excelling_students']['hidden']): ?>
                        <div>
                            <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['excelling_students']['label']) ?> <span class="text-red-500">*</span></label>
                            <?php if(!empty($wr_schema['excelling_students']['helper'])): ?>
                                <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['excelling_students']['helper']) ?></p>
                            <?php endif; ?>
                            <textarea name="excelling_students" required placeholder="If none, type 'None'" class="w-full h-12 p-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('excelling_students') ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$wr_schema['differentiation_strategies']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['differentiation_strategies']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['differentiation_strategies']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['differentiation_strategies']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="differentiation_strategies" required placeholder="If none, type 'None'" class="w-full h-12 p-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('differentiation_strategies') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['tlm_usage']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['tlm_usage']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['tlm_usage']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['tlm_usage']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="tlm_usage" required placeholder="E.g., Smartboard, physical models, online tools..." class="w-full h-16 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all resize-none"><?= getVal('tlm_usage') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Classroom Management & Behavior -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                <h2 class="text-base font-black text-blue-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    2. Classroom Management & Behavior
                </h2>
                <div class="space-y-6">
                    <?php if (!$wr_schema['general_behavior']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['general_behavior']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['general_behavior']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['general_behavior']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="general_behavior" required class="w-full h-20 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 outline-none transition-all resize-none"><?= getVal('general_behavior') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if (!$wr_schema['discipline_issues']['hidden']): ?>
                        <div>
                            <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['discipline_issues']['label']) ?> <span class="text-red-500">*</span></label>
                            <?php if(!empty($wr_schema['discipline_issues']['helper'])): ?>
                                <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['discipline_issues']['helper']) ?></p>
                            <?php endif; ?>
                            <textarea name="discipline_issues" required placeholder="If none, type 'None'" class="w-full h-20 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 outline-none transition-all resize-none"><?= getVal('discipline_issues') ?></textarea>
                        </div>
                        <?php endif; ?>

                        <?php if (!$wr_schema['attendance_concerns']['hidden']): ?>
                        <div>
                            <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['attendance_concerns']['label']) ?> <span class="text-red-500">*</span></label>
                            <?php if(!empty($wr_schema['attendance_concerns']['helper'])): ?>
                                <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['attendance_concerns']['helper']) ?></p>
                            <?php endif; ?>
                            <textarea name="attendance_concerns" required placeholder="If none, type 'None'" class="w-full h-20 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-500/10 outline-none transition-all resize-none"><?= getVal('attendance_concerns') ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. Parent Engagement & Co-Curricular -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                <h2 class="text-base font-black text-emerald-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    3. Engagement & Duties
                </h2>
                <div class="space-y-6">
                    <?php if (!$wr_schema['parents_contacted']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['parents_contacted']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['parents_contacted']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['parents_contacted']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="parents_contacted" required placeholder="If none, type 'None'" class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all resize-none"><?= getVal('parents_contacted') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['co_curricular_activities']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['co_curricular_activities']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['co_curricular_activities']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['co_curricular_activities']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="co_curricular_activities" required placeholder="If none, type 'None'" class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all resize-none"><?= getVal('co_curricular_activities') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. Challenges & Support -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
                <h2 class="text-base font-black text-rose-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    4. Teacher's Challenges & Needs
                </h2>
                <div class="space-y-6">
                    <?php if (!$wr_schema['challenges_faced']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['challenges_faced']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['challenges_faced']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['challenges_faced']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="challenges_faced" required placeholder="If none, type 'None'" class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-rose-500/10 outline-none transition-all resize-none"><?= getVal('challenges_faced') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['self_reflection']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['self_reflection']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['self_reflection']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['self_reflection']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="self_reflection" required placeholder="If none, type 'None'" class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-rose-500/10 outline-none transition-all resize-none"><?= getVal('self_reflection') ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$wr_schema['support_required']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2"><?= htmlspecialchars($wr_schema['support_required']['label']) ?></label>
                        <?php
                        $support_arr = array_map('trim', explode(',', getVal('support_required')));
                        $support_options = [
                            'Parent Engagement', 'Additional Teaching Materials', 'ICT Support',
                            'Classroom Discipline Intervention', 'Counselling Support', 'Extra Classes',
                            'Maintenance / Repairs', 'Administrative Support'
                        ];
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php foreach($support_options as $opt): ?>
                                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-rose-50 transition border border-transparent hover:border-rose-100">
                                    <input type="checkbox" name="support_required[]" value="<?= $opt ?>" <?= in_array($opt, $support_arr) ? 'checked' : '' ?> class="w-4 h-4 text-rose-600 rounded">
                                    <span class="text-sm font-bold text-slate-900"><?= $opt ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$wr_schema['next_week_focus']['hidden']): ?>
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($wr_schema['next_week_focus']['label']) ?> <span class="text-red-500">*</span></label>
                        <?php if(!empty($wr_schema['next_week_focus']['helper'])): ?>
                            <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($wr_schema['next_week_focus']['helper']) ?></p>
                        <?php endif; ?>
                        <textarea name="next_week_focus" required class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-rose-500/10 outline-none transition-all resize-none"><?= getVal('next_week_focus') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // Extract custom fields from schema
            $custom_schema_fields = [];
            foreach ($wr_schema as $key => $field) {
                if (strpos($key, 'custom_') === 0 && empty($field['hidden'])) {
                    $custom_schema_fields[$key] = $field;
                }
            }
            ?>
            <?php if (!empty($custom_schema_fields)): ?>
            <!-- Custom Fields Vault -->
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-fuchsia-500"></div>
                <h2 class="text-base font-black text-fuchsia-600 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                    <i class="fas fa-plus-circle"></i> Additional Details
                </h2>
                <div class="space-y-6">
                    <?php foreach ($custom_schema_fields as $key => $field): ?>
                        <div>
                            <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-1"><?= htmlspecialchars($field['label']) ?> <span class="text-red-500">*</span></label>
                            <?php if(!empty($field['helper'])): ?>
                                <p class="text-xs text-slate-500 italic mb-2"><?= htmlspecialchars($field['helper']) ?></p>
                            <?php endif; ?>
                            <textarea name="<?= htmlspecialchars($key) ?>" required class="w-full h-24 p-4 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-fuchsia-500/10 outline-none transition-all resize-none"><?= getCustomVal($key) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Floating Action Bar -->
            <div class="fixed bottom-0 left-0 md:left-64 right-0 p-4 bg-white/80 backdrop-blur-md border-t border-gray-200 flex justify-center gap-4 z-40 shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)]">
                <button type="submit" name="save_draft" formnovalidate class="px-8 py-3.5 bg-gray-800 text-white font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-gray-900 transition shadow-xl shadow-gray-200">
                    <i class="fas fa-save mr-2"></i> Save Draft
                </button>
                <button type="submit" name="submit_report" class="px-8 py-3.5 bg-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-indigo-700 transition shadow-xl shadow-indigo-200">
                    <i class="fas fa-paper-plane mr-2"></i> Submit for Review
                </button>
            </div>

        </form>
    </main>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-[scale-in_0.2s_ease-out]">
            <div class="p-6 md:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight flex items-center gap-3">
                        <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-lg"><i class="fas fa-file-import"></i></div>
                        Import Report
                    </h2>
                    <button onclick="document.getElementById('importModal').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-slate-600 hover:bg-gray-200 transition"><i class="fas fa-times"></i></button>
                </div>
                
                <p class="text-sm text-slate-600 font-medium mb-6">Select the Class for this report, then upload your completed Word document (.docx). The system will automatically extract your data.</p>
                
                <form action="process_report_import" method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Class <span class="text-red-500">*</span></label>
                        <select name="upload_class_name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:bg-white focus:border-teal-500 outline-none transition-all text-sm font-bold">
                            <option value="">Select Class...</option>
                            <?php foreach($teacher_classes as $tc): ?>
                                <option value="<?= htmlspecialchars($tc) ?>"><?= htmlspecialchars($tc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Removed Subject Dropdown -->
                    <div>
                        <label class="block text-sm font-black text-slate-900 uppercase tracking-widest mb-2">Week Number <span class="text-red-500">*</span></label>
                        <select name="upload_week_number" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl focus:bg-white focus:border-teal-500 outline-none transition-all text-sm font-bold">
                            <?php for($i=1; $i<=$total_weeks; $i++): ?>
                                <option value="<?= $i ?>">Week <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="border-2 border-dashed border-teal-200 rounded-3xl p-8 text-center bg-teal-50 hover:bg-teal-100 transition cursor-pointer relative mt-6 group">
                        <input type="file" name="report_file" accept=".docx" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-4xl text-teal-400 mb-3 group-hover:-translate-y-1 transition-transform"></i>
                        <p class="text-sm font-bold text-teal-800">Drag & Drop your .docx file here</p>
                        <p class="text-sm font-black text-teal-600/60 uppercase tracking-widest mt-1">or click to browse</p>
                    </div>
                    
                    <button type="submit" class="w-full mt-4 bg-teal-600 text-white py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-700 transition shadow-xl shadow-teal-100 flex justify-center items-center gap-2">
                        <i class="fas fa-cogs"></i> Process Document
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="download_report_template" class="text-sm font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-widest transition flex items-center justify-center gap-1">
                            <i class="fas fa-download"></i> Download Blank Template
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
