<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !has_role('admin')) {
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
    SELECT a.*, sp.full_name as teacher_name, sp.photo_path as teacher_photo,
           sup.full_name as supervisor_name
    FROM appraisals a 
    JOIN staff_profiles sp ON a.teacher_id = sp.user_id 
    LEFT JOIN staff_profiles sup ON a.supervisor_id = sup.user_id
    WHERE a.id = $id AND a.status IN ('pending_admin', 'completed')
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
        'appraiser' => $s['appraiser_score'],
        'agreed' => $s['agreed_score']
    ];
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

$is_read_only = ($appraisal['status'] === 'completed');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize Appraisal | School Administrator</title>
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
        .form-input:focus:not(:disabled) { border-color: #059669; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .form-input:disabled { background-color: #f8fafc; color: #64748b; cursor: not-allowed; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Header -->
        <header class="bg-gradient-to-r from-slate-900 to-slate-800 shadow-md border-b border-slate-700">
            <div class="max-w-4xl mx-auto px-4 py-8">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-3 text-white/70 text-sm font-semibold mb-2">
                            <a href="staff_appraisals.php" class="hover:text-white transition-colors"><i class="fas fa-arrow-left mr-1"></i> Master Ledger</a>
                            <i class="fas fa-chevron-right text-[10px]"></i>
                            <span class="text-white">Sign-off</span>
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                            <i class="fas fa-gavel opacity-80 text-emerald-400"></i> <?= $is_read_only ? 'Archived' : 'Authorize' ?> Appraisal
                        </h1>
                        <p class="text-slate-400 mt-2 text-sm">Set final agreed scores and provide the official administrative sign-off.</p>
                    </div>
                    <?php if($is_read_only): ?>
                        <div class="bg-emerald-500/20 border border-emerald-500/30 px-4 py-2 rounded-xl text-emerald-400 text-sm font-bold shadow-sm flex items-center gap-2">
                            <i class="fas fa-check-double"></i> Fully Authorized
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 py-8 mb-12">
            
            <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                <div class="bg-white px-6 py-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <?php if($appraisal['teacher_photo']): ?>
                            <img src="../../<?= htmlspecialchars($appraisal['teacher_photo']) ?>" class="w-16 h-16 rounded-full object-cover shadow-sm border border-slate-200">
                        <?php else: ?>
                            <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-3xl text-slate-400 font-black border border-slate-200">
                                <?= strtoupper(substr($appraisal['teacher_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h2 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($appraisal['teacher_name']) ?></h2>
                            <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">
                                Evaluated By: <?= htmlspecialchars($appraisal['supervisor_name'] ?? 'Evaluator') ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Appraisal Period</div>
                        <div class="font-bold text-slate-800"><?= htmlspecialchars($appraisal['appraisal_month']) ?></div>
                        <div class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($appraisal['academic_year']) ?></div>
                    </div>
                </div>

                <form action="<?= BASE_URL ?>pages/api/appraisal/submit.php" method="POST" class="p-6 md:p-8 bg-slate-50">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="admin_submit">
                    <input type="hidden" name="appraisal_id" value="<?= $id ?>">

                    <?php if(!$is_read_only): ?>
                    <div class="bg-emerald-50 text-emerald-800 p-4 rounded-xl text-sm mb-8 flex gap-3 border border-emerald-200 shadow-sm">
                        <i class="fas fa-info-circle text-emerald-600 mt-0.5 text-lg"></i>
                        <div>
                            <strong>School Administrator Authority:</strong> You are setting the <em>Final Agreed Score</em>. By default, the inputs below are pre-filled with the Evaluator's score. You may adjust any score before saving the final record.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Section Scoring -->
                    <?php foreach($form_structure as $sec_key => $sec_data): ?>
                    <div class="mb-10 bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">SECTION <?= $sec_key ?>: <?= strtoupper($sec_data['title']) ?></h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($sec_data['items'] as $criteria => $c_data): 
                                $max_score = is_array($c_data) ? $c_data['max'] : $c_data;
                                $desc = is_array($c_data) ? $c_data['desc'] : '';
                                
                                $t_score = $scores[$sec_key][$criteria]['teacher'] ?? '-';
                                $s_score = $scores[$sec_key][$criteria]['appraiser'] ?? '-';
                                $a_score = $scores[$sec_key][$criteria]['agreed'] ?? $s_score; // Default to appraiser score
                            ?>
                            <div class="px-5 py-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-0">
                                <div class="flex-1 pr-4">
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($criteria) ?></p>
                                    <?php if($desc): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($desc) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="shrink-0 flex items-center gap-4">
                                    <!-- Staff Score Display -->
                                    <div class="text-center w-14 border-r border-slate-200 pr-4">
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-1">Staff</div>
                                        <div class="text-sm font-bold text-slate-600">
                                            <?= $t_score ?> <span class="text-xs text-slate-300">/<?= $max_score ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Evaluator Score Display -->
                                    <div class="text-center w-16 border-r border-slate-200 pr-4">
                                        <div class="text-[8px] font-bold text-indigo-400 uppercase tracking-widest mb-1">Evaluator</div>
                                        <div class="text-sm font-bold text-indigo-700">
                                            <?= $s_score ?> <span class="text-xs text-indigo-300">/<?= $max_score ?></span>
                                        </div>
                                    </div>

                                    <!-- Final Admin Input -->
                                    <div class="text-center w-20 pl-2">
                                        <div class="text-[8px] font-black text-emerald-500 uppercase tracking-widest mb-1">Final Agreed</div>
                                        <input type="number" 
                                            name="scores[<?= $sec_key ?>][<?= htmlspecialchars($criteria) ?>][agreed_score]" 
                                            class="form-input text-emerald-700 section-<?= $sec_key ?>-input" 
                                            min="0" max="<?= $max_score ?>" step="1" 
                                            value="<?= htmlspecialchars((string)$a_score) ?>"
                                            <?= $is_read_only ? 'disabled' : 'required' ?>
                                            oninput="calcTotal()">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Qualitative Feedback Summary (Read-Only) -->
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm mb-10">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 border-b border-slate-100 pb-2">Evaluator's Qualitative Notes</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase mb-1">Strengths</h4>
                                <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100"><?= nl2br(htmlspecialchars($appraisal['strengths'] ?? 'N/A')) ?></p>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase mb-1">Areas for Improvement</h4>
                                <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100"><?= nl2br(htmlspecialchars($appraisal['areas_for_improvement'] ?? 'N/A')) ?></p>
                            </div>
                            <div class="md:col-span-2">
                                <h4 class="text-[10px] font-bold text-indigo-400 uppercase mb-1">Evaluator Overall Comments</h4>
                                <p class="text-sm text-indigo-900 bg-indigo-50 p-3 rounded-lg border border-indigo-100"><?= nl2br(htmlspecialchars($appraisal['appraiser_comments'] ?? 'N/A')) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Final Remarks -->
                    <div class="bg-slate-900 p-6 rounded-xl border border-slate-800 shadow-lg mb-10 text-white">
                        <label class="block text-sm font-bold text-slate-300 mb-2 uppercase tracking-widest"><i class="fas fa-signature mr-2"></i> Official Administrator Remarks</label>
                        <p class="text-xs text-slate-500 mb-4">These comments will be visible to the staff and permanently archived with this appraisal.</p>
                        <textarea name="admin_comments" rows="3" class="w-full rounded-xl bg-slate-800 border-slate-700 shadow-inner focus:border-emerald-500 focus:ring-emerald-500 text-sm p-4 outline-none text-white disabled:opacity-70" placeholder="Enter final authorization remarks or decisions..." <?= $is_read_only ? 'disabled' : '' ?>><?= htmlspecialchars($appraisal['admin_comments'] ?? '') ?></textarea>
                    </div>

                    <!-- Action Bar -->
                    <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100 flex flex-col md:flex-row justify-between items-center gap-6 shadow-sm">
                        <div class="flex items-center gap-6">
                            <div class="text-center bg-white p-3 rounded-xl border border-emerald-200 shadow-sm min-w-[120px]">
                                <div class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1">Final Score</div>
                                <div class="font-black text-3xl text-emerald-700">
                                    <span id="grand-total">0</span><span class="text-sm text-emerald-400">%</span>
                                </div>
                                <input type="hidden" name="overall_score" id="overall_score_input" value="0">
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Rating Classification</div>
                                <div class="font-black text-xl text-slate-800 uppercase tracking-widest" id="performance_rating">Satisfactory</div>
                                <input type="hidden" name="performance_rating" id="rating_input" value="Satisfactory">
                            </div>
                        </div>
                        
                        <?php if(!$is_read_only): ?>
                        <button type="submit" class="w-full md:w-auto px-8 py-4 rounded-xl text-sm font-black uppercase tracking-widest text-white bg-slate-900 hover:bg-slate-800 shadow-lg transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-lock"></i> Finalize & Archive
                        </button>
                        <?php else: ?>
                        <div class="px-6 py-3 rounded-xl bg-slate-900 border border-slate-800 text-white font-bold text-sm shadow-sm flex items-center gap-2">
                            <i class="fas fa-archive text-emerald-400"></i> Record Archived
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <script>
        function calcTotal() {
            let grand = 0;
            ['A','B','C','D','E','F'].forEach(sec => {
                const inputs = document.querySelectorAll(`.section-${sec}-input`);
                inputs.forEach(inp => {
                    let val = parseFloat(inp.value);
                    if (!isNaN(val)) grand += val;
                });
            });
            
            // Assuming total max is 100 for percentage
            document.getElementById('grand-total').innerText = grand;
            document.getElementById('overall_score_input').value = grand;
            
            let rating = "Unsatisfactory";
            if (grand >= 90) rating = "Outstanding";
            else if (grand >= 75) rating = "Very Good";
            else if (grand >= 60) rating = "Satisfactory";
            else if (grand >= 50) rating = "Needs Improvement";

            const ratingEl = document.getElementById('performance_rating');
            ratingEl.innerText = rating;
            document.getElementById('rating_input').value = rating;

            // Colorize rating text
            ratingEl.className = "font-black text-xl uppercase tracking-widest";
            if (rating === "Outstanding") ratingEl.classList.add("text-emerald-600");
            else if (rating === "Very Good") ratingEl.classList.add("text-blue-600");
            else if (rating === "Satisfactory") ratingEl.classList.add("text-amber-600");
            else ratingEl.classList.add("text-rose-600");
        }

        // Initialize totals
        window.onload = function() {
            calcTotal();
        };
    </script>
</body>
</html>
