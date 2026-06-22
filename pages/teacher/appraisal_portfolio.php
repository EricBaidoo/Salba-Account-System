<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !has_role(['staff', 'facilitator'])) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Fetch existing appraisals
$appraisals_query = $conn->query("
    SELECT id, appraisal_month, academic_year, status, overall_score, performance_rating, updated_at 
    FROM appraisals 
    WHERE teacher_id = $uid 
    ORDER BY id DESC
");
$appraisals = [];
while ($row = $appraisals_query->fetch_assoc()) {
    $appraisals[] = $row;
}

// Define Form Structure
$form_structure = [
    'A' => [
        'title' => 'Professional Conduct',
        'max' => 20,
        'items' => [
            'Punctuality to School' => ['max' => 5, 'desc' => 'Consistently arrives on time and is ready for duty before the official start time.'],
            'Attendance and Reliability' => ['max' => 5, 'desc' => 'Regularly present; notifies administration in advance if absence is unavoidable.'],
            'Professional Appearance' => ['max' => 5, 'desc' => "Dresses neatly, modestly, and in accordance with the school's dress code."],
            'Professional Attitude and Conduct' => ['max' => 5, 'desc' => 'Maintains a positive, respectful demeanor with staff, students, and parents.']
        ]
    ],
    'B' => [
        'title' => 'Teaching Effectiveness',
        'max' => 30,
        'items' => [
            'Lesson Preparation and Planning' => ['max' => 5, 'desc' => 'Prepares comprehensive lesson plans that align with the curriculum.'],
            'Lesson Delivery' => ['max' => 10, 'desc' => 'Explains concepts clearly, uses appropriate pacing, and adapts to learner needs.'],
            'Subject Knowledge' => ['max' => 5, 'desc' => 'Demonstrates strong mastery and confidence in the subject matter.'],
            'Use of Teaching and Learning Materials' => ['max' => 5, 'desc' => 'Effectively integrates relevant visual aids and materials to enhance learning.'],
            'Learner Participation and Engagement' => ['max' => 5, 'desc' => 'Actively involves students through questions, discussions, and group work.']
        ]
    ],
    'C' => [
        'title' => 'Classroom Management',
        'max' => 15,
        'items' => [
            'Classroom Organization' => ['max' => 5, 'desc' => 'Keeps the learning environment tidy, safe, and conducive to learning.'],
            'Discipline Management' => ['max' => 5, 'desc' => 'Maintains order firmly but fairly, respecting the dignity of learners.'],
            'Effective Use of Instructional Time' => ['max' => 5, 'desc' => 'Starts lessons promptly and minimizes off-task behavior.']
        ]
    ],
    'D' => [
        'title' => 'Student Progress & Assessment',
        'max' => 15,
        'items' => [
            'Frequency & Quality of Assessments' => ['max' => 5, 'desc' => 'Regularly evaluates learners using appropriate methods (tests, quizzes, projects).'],
            'Marking, Feedback & Record Keeping' => ['max' => 5, 'desc' => 'Promptly grades work and provides constructive feedback to learners.'],
            'Uses Assessment Data to Differentiate Instruction' => ['max' => 5, 'desc' => 'Adjusts teaching strategies based on student performance results.']
        ]
    ],
    'E' => [
        'title' => 'Record Keeping & Administration',
        'max' => 10,
        'items' => [
            'Attendance Register Updated' => ['max' => 3, 'desc' => 'Keeps daily attendance logs accurate and up-to-date.'],
            'Assessment Records Maintained' => ['max' => 3, 'desc' => 'Accurately documents continuous assessment and exam scores.'],
            'Timely Submission of Reports' => ['max' => 4, 'desc' => 'Submits lesson notes, exam questions, and terminal reports before deadlines.']
        ]
    ],
    'F' => [
        'title' => 'Teamwork & Participation',
        'max' => 10,
        'items' => [
            'Cooperation with Colleagues' => ['max' => 3, 'desc' => 'Works collaboratively and shares resources with fellow staff members.'],
            'Participation in Meetings' => ['max' => 3, 'desc' => 'Attends and actively contributes to departmental and general staff meetings.'],
            'Support for School Activities' => ['max' => 4, 'desc' => 'Willingly assists in extracurricular activities, open days, and school events.']
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appraisal Portfolio | SALBA Montessori</title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
        }
        .form-input {
            width: 60px; text-align: center; font-weight: bold; border-radius: 0.5rem; border: 1px solid #e2e8f0; padding: 0.5rem; outline: none; transition: all 0.3s;
        }
        .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-700 via-indigo-600 to-purple-600 shadow-md">
            <div class="max-w-7xl mx-auto px-4 py-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                        <i class="fas fa-clipboard-user opacity-80"></i> Appraisal Portfolio
                    </h1>
                    <p class="text-indigo-100 mt-2 text-sm">Complete your monthly self-evaluation and track your professional growth.</p>
                </div>
                <button onclick="document.getElementById('new-appraisal-modal').classList.remove('hidden')" class="bg-white/20 hover:bg-white/30 border border-white/40 text-white px-5 py-2.5 rounded-lg font-bold text-sm transition-all shadow-lg backdrop-blur-sm flex items-center gap-2">
                    <i class="fas fa-plus"></i> New Appraisal
                </button>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8 min-h-screen">
            
            <?php $flash = get_flash(); foreach($flash as $msg): ?>
                <div class="mb-6 p-4 rounded-lg <?= $msg['type'] == 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?> flex items-center gap-3 shadow-sm">
                    <i class="fas <?= $msg['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
                    <span class="font-medium text-sm"><?= htmlspecialchars($msg['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <!-- Appraisals List -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h2 class="font-bold text-slate-800"><i class="fas fa-history text-indigo-500 mr-2"></i> Appraisal History</h2>
                </div>
                <?php if(empty($appraisals)): ?>
                    <div class="p-12 text-center text-slate-400">
                        <i class="fas fa-folder-open text-4xl mb-3 opacity-30"></i>
                        <p class="font-medium text-sm">No appraisals found.</p>
                        <p class="text-xs mt-1">Click 'New Appraisal' to start your first self-evaluation.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Month/Year</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Final Rating</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($appraisals as $a): 
                                $status_colors = [
                                    'draft_teacher' => 'bg-slate-100 text-slate-600',
                                    'pending_supervisor' => 'bg-amber-100 text-amber-700',
                                    'pending_admin' => 'bg-purple-100 text-purple-700',
                                    'completed' => 'bg-emerald-100 text-emerald-700'
                                ];
                                $status_labels = [
                                    'draft_teacher' => 'Draft (Pending Your Input)',
                                    'pending_supervisor' => 'Under Supervisor Review',
                                    'pending_admin' => 'Awaiting Admin Approval',
                                    'completed' => 'Finalized'
                                ];
                                $color = $status_colors[$a['status']] ?? 'bg-slate-100 text-slate-600';
                                $label = $status_labels[$a['status']] ?? 'Unknown';
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-800">
                                    <?= htmlspecialchars($a['appraisal_month']) ?>
                                    <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mt-0.5"><?= htmlspecialchars($a['academic_year']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $color ?>"><?= $label ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($a['status'] == 'completed'): ?>
                                        <div class="font-bold text-slate-800"><?= $a['overall_score'] ?>%</div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($a['performance_rating']) ?></div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs italic">Pending...</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if($a['status'] == 'draft_teacher' || $a['status'] == 'pending_supervisor'): ?>
                                        <button onclick="openEditModal(<?= $a['id'] ?>)" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition-colors mb-1"><?= $a['status'] == 'pending_supervisor' ? 'Modify Self-Score' : 'Complete Self-Score' ?> &rarr;</button>
                                    <?php endif; ?>
                                    <?php if($a['status'] != 'draft_teacher'): ?>
                                        <a href="view_appraisal.php?id=<?= $a['id'] ?>" class="text-slate-500 hover:text-slate-700 font-bold text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded transition-colors inline-block"><i class="fas fa-eye mr-1"></i> View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- NEW APPRAISAL / EDIT MODAL -->
    <div id="new-appraisal-modal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="document.getElementById('new-appraisal-modal').classList.add('hidden')"></div>
        <div class="absolute inset-4 md:inset-10 bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <form action="<?= BASE_URL ?>pages/api/appraisal/submit.php" method="POST" class="flex flex-col h-full">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="teacher_submit">
                <input type="hidden" name="appraisal_id" id="modal_appraisal_id" value="0">
                <input type="hidden" name="academic_year" value="<?= htmlspecialchars($current_year) ?>">

                <!-- Header -->
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center shrink-0">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 tracking-tight">Staff Self-Evaluation</h2>
                        <div class="flex items-center gap-3 mt-1">
                            <select name="appraisal_month" class="text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded px-2 py-1 outline-none">
                                <?php 
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                    $curr = date('F');
                                    foreach($months as $m) echo "<option value=\"$m " . date('Y') . "\" ".($m==$curr?'selected':'').">$m " . date('Y') . "</option>";
                                ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('new-appraisal-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-100 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto bg-white p-6 md:p-8">
                    
                    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-8 flex gap-4">
                        <i class="fas fa-info-circle text-indigo-500 text-xl mt-0.5"></i>
                        <div class="text-sm text-indigo-900">
                            <strong>Instructions:</strong> Please complete your self-scores for all sections below honestly. 
                            <br><span class="text-xs text-indigo-700 opacity-80 mt-1 block">5 = Outstanding | 4 = Very Good | 3 = Satisfactory | 2 = Needs Improvement | 1 = Unsatisfactory</span>
                        </div>
                    </div>

                    <?php foreach($form_structure as $sec_key => $sec_data): ?>
                    <div class="mb-10 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">SECTION <?= $sec_key ?>: <?= strtoupper($sec_data['title']) ?></h3>
                            <span class="text-xs font-bold text-slate-500 bg-white px-2 py-1 rounded shadow-sm">Max: <?= $sec_data['max'] ?></span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($sec_data['items'] as $criteria => $c_data): 
                                $max_score = is_array($c_data) ? $c_data['max'] : $c_data;
                                $desc = is_array($c_data) ? $c_data['desc'] : '';
                            ?>
                            <div class="px-5 py-4 flex flex-col md:flex-row items-start md:items-center justify-between hover:bg-slate-50/50 transition-colors gap-4">
                                <div class="pr-4 flex-1">
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($criteria) ?></p>
                                    <?php if($desc): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($desc) ?></p>
                                    <?php endif; ?>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Max Score: <?= $max_score ?></p>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    <input type="hidden" name="scores[<?= $sec_key ?>][<?= htmlspecialchars($criteria) ?>][max]" value="<?= $max_score ?>">
                                    <input type="number" 
                                           name="scores[<?= $sec_key ?>][<?= htmlspecialchars($criteria) ?>][score]" 
                                           class="form-input text-indigo-700 section-<?= $sec_key ?>-input" 
                                           min="0" max="<?= $max_score ?>" step="1" placeholder="-" 
                                           oninput="calcTotal('<?= $sec_key ?>', <?= $sec_data['max'] ?>)">
                                    <span class="text-slate-300 font-bold">/ <?= $max_score ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="bg-indigo-50/30 px-5 py-3 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Section Subtotal</span>
                            <div class="font-black text-indigo-700 text-lg">
                                <span id="total-<?= $sec_key ?>">0</span> <span class="text-sm text-indigo-300">/ <?= $sec_data['max'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>

                <!-- Footer -->
                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 shrink-0 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Self-Score</span>
                        <div class="font-black text-2xl text-slate-800"><span id="grand-total">0</span> <span class="text-sm text-slate-400">/ 100</span></div>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" onclick="document.getElementById('new-appraisal-modal').classList.add('hidden')" class="px-5 py-2.5 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-200 transition-colors">Cancel</button>
                        <button type="submit" class="px-6 py-2.5 rounded-lg text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2">
                            Submit to Evaluator <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function calcTotal(section, maxVal) {
            const inputs = document.querySelectorAll(`.section-${section}-input`);
            let sum = 0;
            inputs.forEach(inp => {
                let val = parseFloat(inp.value);
                if (!isNaN(val)) sum += val;
            });
            document.getElementById(`total-${section}`).innerText = sum;
            
            // Grand Total
            let grand = 0;
            ['A','B','C','D','E','F'].forEach(sec => {
                let s = parseFloat(document.getElementById(`total-${sec}`).innerText);
                if(!isNaN(s)) grand += s;
            });
            document.getElementById('grand-total').innerText = grand;
        }

        function openEditModal(appraisalId) {
            document.getElementById('modal_appraisal_id').value = appraisalId;
            // In a full implementation, you'd fetch the existing drafted scores via AJAX here and populate the inputs.
            // For now, it opens the form so they can re-enter or submit.
            document.getElementById('new-appraisal-modal').classList.remove('hidden');
        }
    </script>
</body>
</html>
