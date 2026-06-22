<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !has_role(['supervisor', 'admin'])) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header('Location: staff_appraisals.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch specific appraisal details
$det_q = $conn->query("
    SELECT a.*, sp.full_name, sp.photo_path 
    FROM appraisals a 
    JOIN staff_profiles sp ON a.teacher_id = sp.user_id 
    WHERE a.id = $id AND a.status != 'draft_teacher'
");
$appraisal = $det_q->fetch_assoc();

if (!$appraisal) {
    header('Location: staff_appraisals.php');
    exit;
}

// Fetch scores
$scores_q = $conn->query("SELECT * FROM appraisal_scores WHERE appraisal_id = $id");
$scores = [];
while($s = $scores_q->fetch_assoc()) {
    $scores[$s['section_name']][$s['criteria']] = [
        'teacher' => $s['teacher_score'],
        'appraiser' => $s['appraiser_score']
    ];
}

$checklist_answers = json_decode($appraisal['observation_checklist'] ?? '[]', true);
// Helper to get checked status
function is_checked($checklist, $index, $value) {
    return (isset($checklist[$index]['answer']) && $checklist[$index]['answer'] === $value) ? 'checked' : '';
}

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

$checklist_items = [
    'Lesson note available', 'Teaching aids used effectively', 'Lesson objectives clearly stated',
    'Learners actively participated', 'Teacher demonstrated subject mastery', 'Classroom was orderly',
    'Learners understood lesson content', 'Questions were encouraged', 'Assessment conducted during lesson',
    'Teacher managed time effectively'
];

$is_read_only = ($appraisal['status'] === 'completed');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate Appraisal | Evaluator Hub</title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .form-input {
            width: 60px; text-align: center; font-weight: bold; border-radius: 0.5rem; border: 1px solid #e2e8f0; padding: 0.5rem; outline: none; transition: all 0.3s;
        }
        .form-input:focus:not(:disabled) { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        .form-input:disabled { background-color: #f8fafc; color: #64748b; cursor: not-allowed; }
        .checklist-cb {
            width: 1.25rem; height: 1.25rem; border-radius: 0.25rem; border: 2px solid #cbd5e1; cursor: pointer; accent-color: #4f46e5;
        }
        .checklist-cb:disabled { cursor: not-allowed; opacity: 0.6; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-700 via-indigo-600 to-purple-600 shadow-md">
            <div class="max-w-4xl mx-auto px-4 py-8">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-3 text-white/80 text-sm font-semibold mb-2">
                            <a href="staff_appraisals.php" class="hover:text-white transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to Ledger</a>
                            <i class="fas fa-chevron-right text-[10px]"></i>
                            <span class="text-white">Evaluate</span>
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                            <i class="fas fa-clipboard-check opacity-80"></i> <?= $is_read_only ? 'View' : 'Evaluate' ?> Appraisal
                        </h1>
                        <p class="text-indigo-100 mt-2 text-sm">Review self-scores and complete the Evaluator evaluation for <?= htmlspecialchars($appraisal['appraisal_month']) ?>.</p>
                    </div>
                    <?php if($is_read_only): ?>
                        <div class="bg-white/10 border border-white/20 backdrop-blur-sm px-4 py-2 rounded-xl text-white text-sm font-bold shadow-sm">
                            <i class="fas fa-lock mr-2 text-indigo-300"></i> Read Only
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 py-8 mb-12">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                <div class="bg-slate-900 px-6 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <?php if($appraisal['photo_path']): ?>
                            <img src="../../<?= htmlspecialchars($appraisal['photo_path']) ?>" class="w-14 h-14 rounded-full object-cover shadow-sm border-2 border-slate-700">
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-full bg-white/10 flex items-center justify-center text-2xl text-white font-bold backdrop-blur-sm border-2 border-slate-700">
                                <?= strtoupper(substr($appraisal['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="text-xl font-black text-white"><?= htmlspecialchars($appraisal['full_name']) ?></h2>
                            <p class="text-xs font-semibold text-slate-400 mt-1 uppercase tracking-widest">Staff Self-Score Submitted</p>
                        </div>
                    </div>
                    <?php if($appraisal['status'] === 'pending_supervisor'): ?>
                        <span class="bg-amber-500/20 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider inline-flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Pending Review
                        </span>
                    <?php else: ?>
                        <span class="bg-slate-800 text-slate-300 border border-slate-700 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider">
                            Already Evaluated
                        </span>
                    <?php endif; ?>
                </div>

                <form action="<?= BASE_URL ?>pages/api/appraisal/submit.php" method="POST" class="p-6 md:p-8">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="supervisor_submit">
                    <input type="hidden" name="appraisal_id" value="<?= $id ?>">

                    <?php if($is_read_only): ?>
                    <div class="bg-blue-50 text-blue-800 p-4 rounded-xl text-sm mb-8 flex gap-3 border border-blue-100">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                        <div>
                            <strong>View Mode:</strong> You have already submitted your evaluation for this appraisal. It is currently locked and cannot be edited while awaiting or possessing final Admin sign-off.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Section Scoring -->
                    <?php foreach($form_structure as $sec_key => $sec_data): ?>
                    <div class="mb-10 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-slate-50 px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">SECTION <?= $sec_key ?>: <?= strtoupper($sec_data['title']) ?></h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($sec_data['items'] as $criteria => $c_data): 
                                $max_score = is_array($c_data) ? $c_data['max'] : $c_data;
                                $desc = is_array($c_data) ? $c_data['desc'] : '';
                                
                                $t_score = $scores[$sec_key][$criteria]['teacher'] ?? '-';
                                $s_score = $scores[$sec_key][$criteria]['appraiser'] ?? $t_score; // Default to teacher's self-score
                            ?>
                            <div class="px-5 py-4 flex flex-col md:flex-row items-start md:items-center justify-between hover:bg-slate-50 transition-colors gap-4 border-b border-slate-50 last:border-0">
                                <div class="flex-1 pr-4">
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($criteria) ?></p>
                                    <?php if($desc): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($desc) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="shrink-0 flex items-center gap-6">
                                    <!-- Staff Score Display -->
                                    <div class="text-center w-12 border-r border-slate-200 pr-6">
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-1">Staff</div>
                                        <div class="text-sm font-bold text-slate-600">
                                            <?= $t_score ?> <span class="text-xs text-slate-300">/<?= $max_score ?></span>
                                        </div>
                                    </div>
                                    <!-- Evaluator Input -->
                                    <div class="text-center">
                                        <div class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest mb-1">Evaluator</div>
                                        <div class="flex items-center gap-2">
                                            <input type="number" 
                                                name="scores[<?= $sec_key ?>][<?= htmlspecialchars($criteria) ?>][appraiser_score]" 
                                                class="form-input text-indigo-700 section-<?= $sec_key ?>-input" 
                                                min="0" max="<?= $max_score ?>" step="1" 
                                                value="<?= htmlspecialchars((string)$s_score) ?>"
                                                <?= $is_read_only ? 'disabled' : 'required' ?>
                                                oninput="calcTotal()">
                                            <span class="text-slate-300 font-bold text-xs">/ <?= $max_score ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="bg-indigo-50/30 px-5 py-4 border-t border-slate-100 flex justify-end items-center gap-6">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Subtotal</span>
                            <div class="font-black text-indigo-700 text-xl w-24 text-right">
                                <span id="total-<?= $sec_key ?>">0</span> <span class="text-sm text-indigo-300">/ <?= $sec_data['max'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Classroom Observation Checklist -->
                    <h3 class="text-lg font-black text-slate-800 mb-4 border-b border-slate-200 pb-2 mt-12">Classroom Observation Checklist</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mb-12">
                        <?php foreach($checklist_items as $index => $item): ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100 shadow-sm">
                            <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($item) ?></span>
                            <div class="flex gap-4 shrink-0">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="checklist[<?= $index ?>][answer]" value="yes" class="checklist-cb" <?= is_checked($checklist_answers, $index, 'yes') ?> <?= $is_read_only ? 'disabled' : 'required' ?>>
                                    <span class="text-xs font-bold text-emerald-600 group-hover:text-emerald-700">Yes</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="radio" name="checklist[<?= $index ?>][answer]" value="no" class="checklist-cb border-rose-300" <?= is_checked($checklist_answers, $index, 'no') ?> <?= $is_read_only ? 'disabled' : 'required' ?>>
                                    <span class="text-xs font-bold text-rose-600 group-hover:text-rose-700">No</span>
                                </label>
                                <input type="hidden" name="checklist[<?= $index ?>][question]" value="<?= htmlspecialchars($item) ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Qualitative Assessment -->
                    <h3 class="text-lg font-black text-slate-800 mb-4 border-b border-slate-200 pb-2">Qualitative Assessment</h3>
                    <div class="space-y-6 mb-10">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Staff's Strengths</label>
                            <textarea name="strengths" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-4 border outline-none disabled:bg-slate-50 disabled:text-slate-600" placeholder="List 1-3 key strengths..." <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['strengths'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Areas for Improvement</label>
                            <textarea name="areas_for_improvement" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-4 border outline-none disabled:bg-slate-50 disabled:text-slate-600" placeholder="Identify areas requiring attention..." <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['areas_for_improvement'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Targets for Next Month (SMART)</label>
                            <textarea name="targets" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-4 border outline-none disabled:bg-slate-50 disabled:text-slate-600" placeholder="Specific, Measurable, Achievable, Relevant, Time-bound targets..." <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['targets'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">CPD & Support Required</label>
                            <textarea name="cpd_support" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-4 border outline-none disabled:bg-slate-50 disabled:text-slate-600" placeholder="What specific training or resources does the teacher need?" <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['cpd_support'] ?? '') ?></textarea>
                        </div>
                        <div class="bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                            <label class="block text-sm font-bold text-indigo-900 mb-2">Appraiser Overall Comments</label>
                            <textarea name="appraiser_comments" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm p-4 border outline-none disabled:bg-slate-50 disabled:text-slate-600" placeholder="Final summary remarks..." <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['appraiser_comments'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="bg-indigo-50 p-6 rounded-2xl border border-indigo-100 flex flex-col sm:flex-row justify-between items-center gap-6 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center text-indigo-500 shadow-sm text-2xl"><i class="fas fa-calculator"></i></div>
                            <div>
                                <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-0.5">Calculated Score</div>
                                <div class="font-black text-3xl text-indigo-900"><span id="grand-total">0</span> <span class="text-sm text-indigo-400">/ 100</span></div>
                            </div>
                        </div>
                        <?php if($appraisal['status'] === 'pending_admin'): ?>
                        <div class="px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-500 font-bold text-sm shadow-sm flex items-center gap-2 mb-4 sm:mb-0 w-full justify-center sm:w-auto">
                            <i class="fas fa-check-circle text-emerald-500"></i> Already Submitted. Modifications will override previous evaluation.
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!$is_read_only): ?>
                        <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                            <?= $appraisal['status'] === 'pending_admin' ? 'Update & Resubmit' : 'Submit to School Admin' ?> <i class="fas fa-arrow-right"></i>
                        </button>
                        <?php else: ?>
                        <div class="px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-500 font-bold text-sm shadow-sm flex items-center gap-2 w-full justify-center sm:w-auto">
                            <i class="fas fa-check-circle text-emerald-500"></i> Appraisal Completed & Finalized
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <script>
        function calcTotal(section) {
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

        // Initialize totals
        window.onload = function() {
            ['A','B','C','D','E','F'].forEach(sec => calcTotal(sec));
        };
    </script>
</body>
</html>
