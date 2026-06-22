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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch appraisal ensuring it belongs to the logged in teacher
$query = $conn->query("
    SELECT a.*, sup.full_name as supervisor_name 
    FROM appraisals a 
    LEFT JOIN staff_profiles sup ON a.supervisor_id = sup.user_id 
    WHERE a.id = $id AND a.teacher_id = $uid
");
$appraisal = $query->fetch_assoc();

if (!$appraisal) {
    // Redirect if not found or unauthorized
    header('Location: appraisal_portfolio.php');
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

$status_colors = [
    'draft_teacher' => 'bg-slate-100 text-slate-600',
    'pending_supervisor' => 'bg-amber-100 text-amber-700',
    'pending_admin' => 'bg-purple-100 text-purple-700',
    'completed' => 'bg-emerald-100 text-emerald-700'
];
$status_labels = [
    'draft_teacher' => 'Draft',
    'pending_supervisor' => 'Under Review',
    'pending_admin' => 'Awaiting Admin Approval',
    'completed' => 'Finalized'
];
$status_color = $status_colors[$appraisal['status']] ?? 'bg-slate-100';
$status_label = $status_labels[$appraisal['status']] ?? 'Unknown';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appraisal | SALBA Montessori</title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <?php include '../../includes/top_nav.php'; ?>

    <div class="pt-16 md:pt-20">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-700 via-indigo-600 to-purple-600 shadow-md">
            <div class="max-w-4xl mx-auto px-4 py-8">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-3 text-white/80 text-sm font-semibold mb-2">
                            <a href="appraisal_portfolio.php" class="hover:text-white transition-colors">Portfolio</a>
                            <i class="fas fa-chevron-right text-[10px]"></i>
                            <span class="text-white">View Appraisal</span>
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                            <i class="fas fa-file-contract opacity-80"></i> Appraisal for <?= htmlspecialchars($appraisal['appraisal_month']) ?>
                        </h1>
                        <p class="text-indigo-100 mt-2 text-sm font-bold uppercase tracking-widest"><?= htmlspecialchars($appraisal['academic_year']) ?></p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-4 py-1.5 rounded-full text-xs font-bold <?= $status_color ?> shadow-sm">
                            <?= $status_label ?>
                        </span>
                        <?php if($appraisal['status'] == 'completed'): ?>
                            <div class="mt-3 text-white">
                                <div class="text-xs font-bold text-white/70 uppercase tracking-widest mb-0.5">Final Rating</div>
                                <div class="text-2xl font-black text-emerald-300"><?= $appraisal['overall_score'] ?>%</div>
                                <div class="text-sm font-bold text-white"><?= htmlspecialchars($appraisal['performance_rating']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 py-8 min-h-screen">
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                <div class="p-6 md:p-8">
                    
                    <div class="bg-indigo-50 text-indigo-800 p-4 rounded-xl text-sm mb-8 flex gap-3 border border-indigo-100">
                        <i class="fas fa-info-circle text-indigo-600 mt-0.5"></i>
                        <div>
                            <strong>Evaluation Record:</strong> This is a read-only view of your appraisal. 
                            <?php if($appraisal['status'] != 'completed'): ?>
                                <br><span class="opacity-80">Currently pending review. Only your submitted self-scores are fully visible. Final scores will appear once completed.</span>
                            <?php else: ?>
                                <br><span class="opacity-80">This appraisal has been finalized and securely archived. Evaluated by: <strong><?= htmlspecialchars($appraisal['supervisor_name'] ?? 'Supervisor') ?></strong></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Scores Section -->
                    <?php foreach($form_structure as $sec_key => $sec_data): ?>
                    <div class="mb-8 border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center">
                            <h3 class="font-bold text-slate-800 text-sm">SECTION <?= $sec_key ?>: <?= strtoupper($sec_data['title']) ?></h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach($sec_data['items'] as $criteria => $c_data): 
                                $max_score = is_array($c_data) ? $c_data['max'] : $c_data;
                                $desc = is_array($c_data) ? $c_data['desc'] : '';
                                
                                $t_score = $scores[$sec_key][$criteria]['teacher'] ?? '-';
                                $s_score = $scores[$sec_key][$criteria]['appraiser'] ?? '-';
                                $a_score = $scores[$sec_key][$criteria]['agreed'] ?? '-';
                                
                                // Hide supervisor/agreed scores if not completed, unless you want them visible earlier
                                if ($appraisal['status'] != 'completed') {
                                    $a_score = '-';
                                }
                            ?>
                            <div class="px-5 py-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div class="flex-1 pr-4">
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($criteria) ?></p>
                                    <?php if($desc): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($desc) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="shrink-0 flex items-center gap-6">
                                    <div class="text-center w-12">
                                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mb-1">Your Score</div>
                                        <div class="text-sm font-bold text-slate-600"><?= $t_score ?> <span class="text-xs text-slate-300">/ <?= $max_score ?></span></div>
                                    </div>
                                    <?php if($appraisal['status'] == 'completed'): ?>
                                    <div class="text-center w-12 border-l border-slate-100 pl-4">
                                        <div class="text-[8px] font-bold text-indigo-500 uppercase tracking-widest mb-1">Agreed</div>
                                        <div class="text-sm font-black text-indigo-700"><?= $a_score ?> <span class="text-xs text-indigo-300">/ <?= $max_score ?></span></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Qualitative Feedback -->
                    <?php if($appraisal['status'] == 'completed' || $appraisal['status'] == 'pending_admin'): ?>
                    <h3 class="text-lg font-black text-slate-800 mb-4 border-b border-slate-200 pb-2 mt-12">Qualitative Feedback & Support</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                        <div class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                            <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-widest mb-2"><i class="fas fa-arrow-up text-xs mr-1"></i> Strengths</h4>
                            <p class="text-sm text-slate-800"><?= nl2br(htmlspecialchars($appraisal['strengths'] ?? 'None provided')) ?></p>
                        </div>
                        <div class="bg-amber-50/50 p-4 rounded-xl border border-amber-100">
                            <h4 class="text-xs font-bold text-amber-600 uppercase tracking-widest mb-2"><i class="fas fa-hammer text-xs mr-1"></i> Areas for Improvement</h4>
                            <p class="text-sm text-slate-800"><?= nl2br(htmlspecialchars($appraisal['areas_for_improvement'] ?? 'None provided')) ?></p>
                        </div>
                        <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                            <h4 class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-2"><i class="fas fa-bullseye text-xs mr-1"></i> Targets</h4>
                            <p class="text-sm text-slate-800"><?= nl2br(htmlspecialchars($appraisal['targets'] ?? 'None provided')) ?></p>
                        </div>
                        <div class="bg-purple-50/50 p-4 rounded-xl border border-purple-100">
                            <h4 class="text-xs font-bold text-purple-600 uppercase tracking-widest mb-2"><i class="fas fa-book-open-reader text-xs mr-1"></i> CPD Support</h4>
                            <p class="text-sm text-slate-800"><?= nl2br(htmlspecialchars($appraisal['cpd_support'] ?? 'None provided')) ?></p>
                        </div>
                        <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100 md:col-span-2">
                            <h4 class="text-xs font-bold text-indigo-500 uppercase tracking-widest mb-2"><i class="fas fa-comment-dots text-xs mr-1"></i> Evaluator's Comments</h4>
                            <p class="text-sm text-indigo-900"><?= nl2br(htmlspecialchars($appraisal['appraiser_comments'] ?? 'None provided')) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Admin Final Remarks -->
                    <?php if($appraisal['status'] == 'completed' && !empty($appraisal['admin_comments'])): ?>
                    <div class="bg-slate-900 text-white p-5 rounded-xl shadow-md border border-slate-800">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2"><i class="fas fa-gavel text-xs mr-1"></i> Admin Final Remarks</h4>
                        <p class="text-sm text-slate-200"><?= nl2br(htmlspecialchars($appraisal['admin_comments'])) ?></p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="text-center mb-12">
                <a href="appraisal_portfolio.php" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-bold text-sm bg-white px-6 py-3 rounded-xl border border-slate-200 shadow-sm hover:shadow transition-all">
                    <i class="fas fa-arrow-left"></i> Back to Portfolio
                </a>
            </div>

        </main>
    </div>

</body>
</html>
