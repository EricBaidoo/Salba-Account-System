<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$current_year = getAcademicYear($conn);
$current_term = getCurrentTerm($conn);
$user_role = $_SESSION['role'] ?? 'staff';
$uid = $_SESSION['user_id'];

// Global Transcript Settings
$global_oa_weight = floatval(getSystemSetting($conn, 'term_oa_weight', 30));
$global_exam_weight = floatval(getSystemSetting($conn, 'term_exam_weight', 70));

// Process Supervisor / Teacher Remarks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_remarks'])) {
    $r_student = intval($_POST['r_student']);
    $r_attitude = $conn->real_escape_string($_POST['attitude'] ?? '');
    $r_conduct = $conn->real_escape_string($_POST['conduct'] ?? '');
    $r_talent = $conn->real_escape_string($_POST['talent'] ?? '');
    $r_tr = $conn->real_escape_string($_POST['teacher_remarks'] ?? '');
    $r_sr = $conn->real_escape_string($_POST['supervisor_remarks'] ?? '');
    
    $check = $conn->query("SELECT id FROM student_term_remarks WHERE student_id = $r_student AND academic_year = '$current_year' AND term = '$current_term'");
    if ($check->num_rows > 0) {
        $q = "UPDATE student_term_remarks SET attitude='$r_attitude', conduct='$r_conduct', talent_and_interest='$r_talent'";
        if ($user_role === 'teacher' || $user_role === 'admin') $q .= ", teacher_remarks='$r_tr', teacher_id=$uid";
        if ($user_role === 'academic_supervisor' || $user_role === 'admin') $q .= ", supervisor_remarks='$r_sr', supervisor_id=$uid";
        $q .= " WHERE student_id=$r_student AND academic_year='$current_year' AND term='$current_term'";
        $conn->query($q);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_term_remarks (student_id, academic_year, term, attitude, conduct, talent_and_interest, teacher_remarks, teacher_id, supervisor_remarks, supervisor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssisi", $r_student, $current_year, $current_term, $r_attitude, $r_conduct, $r_talent, $r_tr, $uid, $r_sr, $uid);
        $stmt->execute();
    }
    $success = "Remarks efficiently saved.";
}

// Get Students based on Role scope
$allocated_classes = [];
if ($user_role === 'admin' || $user_role === 'academic_supervisor') {
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
} else {
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
}
$selected_class = $_GET['class'] ?? ($allocated_classes[0] ?? '');

$students = [];
if ($selected_class) {
    $res = $conn->query("SELECT id, first_name, last_name FROM students WHERE class = '$selected_class' AND status='active'");
    while($r = $res->fetch_assoc()){
        $students[] = $r;
    }
}
$selected_student_id = $_GET['student'] ?? ($students[0]['id'] ?? '');
$student_data = null;
if ($selected_student_id) {
    foreach($students as $s) {
        if ($s['id'] == $selected_student_id) {
            $student_data = $s; break;
        }
    }
}

// Compile Transcript Engine (Ranking & Math)
$transcript_lines = [];
$student_remarks = null;

if ($selected_class && $selected_student_id) {
    $class_scores = []; 
    
    // Map Configs to Array for robust matching
    $oa_types = []; $exam_types = [];
    $type_res = $conn->query("SELECT assessment_name, is_exam FROM assessment_configurations WHERE academic_year = '$current_year' AND term = '$current_term'");
    while($r = $type_res->fetch_assoc()) {
        if ($r['is_exam']) $exam_types[] = $r['assessment_name'];
        else $oa_types[] = $r['assessment_name'];
    }

    // Fetch all raw scaled grades for class
    $g_res = $conn->query("
        SELECT student_id, subject, marks, assessment_type 
        FROM grades 
        WHERE class_name = '$selected_class' AND term = '$current_term' AND year = '$current_year'
    ");
    
    while($row = $g_res->fetch_assoc()) {
        $sid = $row['student_id'];
        $sub = $row['subject'];
        $type = $row['assessment_type'];
        $m = floatval($row['marks']);
        
        if (!isset($class_scores[$sub][$sid])) {
            $class_scores[$sub][$sid] = ['oa_raw' => 0, 'ex_raw' => 0];
        }
        
        if (in_array($type, $exam_types)) {
            $class_scores[$sub][$sid]['ex_raw'] += $m; 
        } else if (in_array($type, $oa_types)) {
            $class_scores[$sub][$sid]['oa_raw'] += $m; 
        }
    }
    
    // Now extract specific student data and calculate POS
    if (isset($class_scores)) {
        foreach ($class_scores as $sub => $scores_array) {
            
            // First we must calculate everyone's Final Total to determine Positions
            $all_totals = [];
            foreach ($scores_array as $st_id => $st_data) {
                // The master math formula defined by the user
                $final_oa = ($st_data['oa_raw'] * ($global_oa_weight / 100));
                $final_ex = ($st_data['ex_raw'] * ($global_exam_weight / 100));
                $all_totals[$st_id] = $final_oa + $final_ex;
            }
            
            // Generate list of numbers, sort descending, grab unique positions
            $ranked_scores = array_values($all_totals);
            rsort($ranked_scores);
            
            if (isset($scores_array[$selected_student_id])) {
                $my_total = $all_totals[$selected_student_id];
                $pos = array_search($my_total, $ranked_scores) + 1;
                
                // Final Math Variables for injection into HTML
                $st_data = $scores_array[$selected_student_id];
                $final_oa = ($st_data['oa_raw'] * ($global_oa_weight / 100));
                $final_ex = ($st_data['ex_raw'] * ($global_exam_weight / 100));
                
                $grade = ''; $remark = '';
                if ($my_total >= 80)      { $grade = 'A'; $remark = 'Advance'; }
                elseif ($my_total >= 70)  { $grade = 'B'; $remark = 'Proficient'; }
                elseif ($my_total >= 60)  { $grade = 'C'; $remark = 'Basic'; }
                elseif ($my_total >= 50)  { $grade = 'D'; $remark = 'Pass'; }
                else                      { $grade = 'F'; $remark = 'Below Basic'; }
                
                $transcript_lines[] = [
                    'subject' => $sub,
                    'oa' => round($final_oa, 1),
                    'ex' => round($final_ex, 1),
                    'total' => round($my_total, 1),
                    'pos' => $pos,
                    'grade' => $grade,
                    'remark' => $remark
                ];
            }
        }
    }
    
    // Fetch Remarks
    $rem_res = $conn->query("SELECT * FROM student_term_remarks WHERE student_id = $selected_student_id AND academic_year = '$current_year' AND term = '$current_term'");
    if($rem_res->num_rows > 0) $student_remarks = $rem_res->fetch_assoc();
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcripts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; }
        
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            @page { size: A4; margin: 10mm; }
            body { 
                background: white; 
                color: black; 
                margin: 0; 
                padding: 0; 
                width: 210mm; 
                position: relative;
            }
            .ml-72 { margin-left: 0 !important; }
            .print-container { 
                box-shadow: none !important; 
                border: none !important; 
                width: 100% !important; 
                max-width: 100% !important; 
                padding: 0 !important;
                margin: 0 !important;
            }
            /* High contrast print borders */
            table { border-collapse: collapse; width: 100%; border: 2px solid black !important; }
            th, td { border: 2px solid black !important; padding: 6px 8px !important; color: black !important; -webkit-print-color-adjust: exact; }
            th { background-color: #f0f0f0 !important; }
            
            /* Logo printing */
            .print-header img { 
                width: 100px !important; 
                height: 100px !important;
            }
            .signature-line {
                border-top: 1px dashed black !important;
                margin-top: 50px;
                width: 200px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="bg-gray-100">

    <?php include '../../includes/sidebar_admin.php'; ?>
    <?php if ($_SESSION['role'] !== 'admin') include '../../includes/sidebar.php'; // fallback ?>

    <main class="ml-72 min-h-screen relative p-6 no-print transition-all duration-300">
        
        <div class="flex justify-between items-center bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6 border-l-4 border-l-red-500">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900"><i class="fas fa-file-pdf text-red-500 mr-2"></i> Official Transcripts</h1>
                <p class="text-sm text-gray-700 mt-1 font-bold tracking-wider">MASTER PRINT SPLIT: <span class="bg-blue-100 text-blue-800 px-2 rounded">OA MAX <?= $global_oa_weight ?>%</span> <span class="mx-1">+</span> <span class="bg-red-100 text-red-800 px-2 rounded">EXAM MAX <?= $global_exam_weight ?>%</span></p>
            </div>
            
            <?php if($user_role !== 'teacher'): ?>
                <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white font-bold py-3 px-6 rounded-lg shadow-md transition transform hover:scale-105 flex items-center gap-2">
                    <i class="fas fa-print"></i> Print Official Document
                </button>
            <?php else: ?>
                <span class="bg-red-50 text-red-600 border border-red-200 font-bold px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-lock mr-1"></i> Print Role Restricted
                </span>
            <?php endif; ?>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6 flex gap-4">
            <form method="GET" class="flex gap-4 w-full">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Target Class</label>
                    <select name="class" class="w-full px-4 py-2 border rounded font-medium" onchange="this.form.submit()">
                        <?php foreach($allocated_classes as $cl): ?>
                            <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Student</label>
                    <select name="student" class="w-full px-4 py-2 border rounded font-medium" onchange="this.form.submit()">
                        <?php foreach($students as $s): ?>
                            <option value="<?= htmlspecialchars($s['id']) ?>" <?= $selected_student_id == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if($student_data): ?>
            <!-- Digital Entry View for Remarks (No Print) -->
            <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-8">
                <h3 class="font-bold text-gray-800 border-b pb-3 mb-4"><i class="fas fa-pen-nib text-blue-500"></i> Pastoral Care & Remarks Digital Entry</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="save_remarks" value="1">
                    <input type="hidden" name="r_student" value="<?= $student_data['id'] ?>">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Attitude</label>
                        <input type="text" name="attitude" value="<?= htmlspecialchars($student_remarks['attitude'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Conduct</label>
                        <input type="text" name="conduct" value="<?= htmlspecialchars($student_remarks['conduct'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
                    </div>
                    <div class="col-span-full border-t pt-4 mt-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Talent & Interest</label>
                        <input type="text" name="talent" value="<?= htmlspecialchars($student_remarks['talent_and_interest'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
                    </div>

                    <?php if($user_role === 'teacher' || $user_role === 'admin'): ?>
                    <div class="col-span-full border-t border-gray-100 pt-4 mt-2 bg-blue-50 p-4 rounded mt-4">
                        <label class="block text-xs font-bold text-blue-800 uppercase mb-1"><i class="fas fa-chalkboard-user"></i> Class Teacher Remarks</label>
                        <input type="text" name="teacher_remarks" value="<?= htmlspecialchars($student_remarks['teacher_remarks'] ?? '') ?>" class="w-full border border-blue-200 p-2 rounded font-bold italic">
                    </div>
                    <?php endif; ?>

                    <?php if($user_role === 'academic_supervisor' || $user_role === 'admin'): ?>
                    <div class="col-span-full border-t border-gray-100 pt-4 mt-2 bg-red-50 p-4 rounded mt-4">
                        <label class="block text-xs font-bold text-red-800 uppercase mb-1"><i class="fas fa-user-tie"></i> Supervisor/Headmaster Remarks</label>
                        <input type="text" name="supervisor_remarks" value="<?= htmlspecialchars($student_remarks['supervisor_remarks'] ?? '') ?>" class="w-full border border-red-200 p-2 rounded font-bold italic text-red-900">
                    </div>
                    <?php endif; ?>

                    <div class="col-span-full mt-4 text-right">
                        <button type="submit" class="bg-gray-800 text-white font-bold py-2 px-6 rounded hover:bg-gray-900 border border-transparent">Save Data to Print Queue</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <!-- PRINTABLE A4 AREA -->
    <?php if($student_data): ?>
    <div class="print-only hidden print-container bg-white w-[210mm] mx-auto min-h-[297mm] p-[10mm] relative">
        
        <!-- Header -->
        <table class="w-full border-none mb-6">
            <tr>
                <td class="w-[120px] text-center border-none!" style="border:none !important">
                    <div class="w-[100px] h-[100px] border-4 border-black rounded-full mx-auto flex items-center justify-center font-bold text-lg">LOGO</div>
                </td>
                <td class="text-center align-middle border-none!" style="border:none !important; text-align: center;">
                    <h1 class="text-3xl font-extrabold uppercase m-0 leading-tight tracking-widest"><?= htmlspecialchars($school_name) ?></h1>
                    <p class="font-bold text-lg border-b border-t border-black py-1 mt-2 inline-block px-10">TERMINAL REPORT CARD</p>
                </td>
                <td class="w-[120px] text-center border-none!" style="border:none !important">
                    <div class="w-[100px] h-[100px] border-2 border-black bg-gray-100 mx-auto text-xs flex items-center justify-center text-gray-500">PHOTO<br>BOX</div>
                </td>
            </tr>
        </table>

        <!-- Student Meta Data -->
        <div class="mb-4">
            <table class="w-full text-sm font-bold border-2 border-black">
                <tr>
                    <td class="w-[15%] bg-gray-100">NAME OF PUPIL:</td>
                    <td class="w-[50%] uppercase text-lg border-r-2 border-black"><?= htmlspecialchars($student_data['first_name'].' '.$student_data['last_name']) ?></td>
                    <td class="w-[15%] bg-gray-100">CLASS:</td>
                    <td class="w-[20%] uppercase"><?= htmlspecialchars($selected_class) ?></td>
                </tr>
                <tr>
                    <td class="bg-gray-100">ACADEMIC YEAR:</td>
                    <td class="border-r-2 border-black"><?= htmlspecialchars($current_year) ?></td>
                    <td class="bg-gray-100">TERM:</td>
                    <td><?= htmlspecialchars($current_term) ?></td>
                </tr>
                <tr>
                    <td class="bg-gray-100">VACATION DATE:</td>
                    <td class="border-r-2 border-black text-gray-400 font-normal italic">Set by Admin</td>
                    <td class="bg-gray-100">NUMBER ON ROLL:</td>
                    <td><?= count($students) ?></td>
                </tr>
            </table>
        </div>

        <!-- Grade Grid -->
        <div class="mb-8">
            <table class="w-full text-center text-sm border-2 border-black">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="text-left align-middle border-2 border-black uppercase text-sm w-[25%] p-2">SUBJECTS / LEARNING AREA</th>
                        <th class="border-2 border-black leading-tight w-[10%] bg-gray-100">OA SCORE<br>(<?= $global_oa_weight ?>%)</th>
                        <th class="border-2 border-black leading-tight w-[10%] bg-gray-100">EXAMS SCORE<br>(<?= $global_exam_weight ?>%)</th>
                        <th class="border-2 border-black leading-tight w-[10%]">TOTAL SCORE<br>(100%)</th>
                        <th class="border-2 border-black leading-tight w-[5%] bg-gray-100">POS.</th>
                        <th class="border-2 border-black leading-tight w-[10%]">GRADE</th>
                        <th class="border-2 border-black text-left pl-3 w-[25%]">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($transcript_lines)): ?>
                        <?php foreach($transcript_lines as $l): ?>
                        <tr>
                            <td class="text-left font-bold uppercase p-2 border-2 border-black"><?= htmlspecialchars($l['subject']) ?></td>
                            <td class="font-bold border-2 border-black bg-gray-50"><?= $l['oa'] ?></td>
                            <td class="font-bold border-2 border-black bg-gray-50"><?= $l['ex'] ?></td>
                            <td class="font-extrabold border-2 border-black bg-gray-100 text-base"><?= $l['total'] ?></td>
                            <td class="font-bold italic border-2 border-black bg-gray-50"><?= htmlspecialchars($l['pos']) ?></td>
                            <td class="font-bold text-base border-2 border-black"><?= htmlspecialchars($l['grade']) ?></td>
                            <td class="text-left font-medium pl-3 border-2 border-black uppercase text-xs"><?= htmlspecialchars($l['remark']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="py-8 font-bold text-gray-400 uppercase italic border-2 border-black">NO ACADEMIC RECORDS FINALIZED</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Remarks Sandbox -->
        <div class="mb-8">
            <table class="w-full text-sm font-bold border-2 border-black border-collapse">
                <tr>
                    <td class="w-[20%] uppercase border-2 border-black bg-gray-100 p-2">ATTENDANCE:</td>
                    <td class="w-[30%] uppercase border-2 border-black p-2 font-normal">_______ OUT OF _______</td>
                    <td class="w-[20%] uppercase border-2 border-black bg-gray-100 p-2 text-right">CONDUCT:</td>
                    <td class="w-[30%] uppercase border-2 border-black p-2 font-medium italic"><?= htmlspecialchars($student_remarks['conduct'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="uppercase border-2 border-black bg-gray-100 p-2">ATTITUDE:</td>
                    <td colspan="3" class="uppercase border-2 border-black p-2 font-medium italic"><?= htmlspecialchars($student_remarks['attitude'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="uppercase border-2 border-black bg-gray-100 p-2" colspan="2">TALENT AND INTEREST IN EXTRA CO-CURRICULAR:</td>
                    <td colspan="2" class="uppercase border-2 border-black p-2 font-medium italic"><?= htmlspecialchars($student_remarks['talent_and_interest'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="uppercase border-2 border-black bg-gray-100 p-2 py-4">TEACHER'S REMARK:</td>
                    <td colspan="3" class="uppercase border-2 border-black p-2 font-bold text-base italic"><?= htmlspecialchars($student_remarks['teacher_remarks'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="uppercase border-2 border-black bg-gray-100 p-2 py-6">HEADMASTER / SUPERVISOR'S REMARK:</td>
                    <td colspan="3" class="uppercase border-2 border-black p-2 font-bold text-[18px] italic"><?= htmlspecialchars($student_remarks['supervisor_remarks'] ?? '') ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Signatures -->
        <div class="mt-12 flex justify-between px-10">
            <div>
                <div class="signature-line border-t border-black mb-1 w-48 mx-auto"></div>
                <div class="font-bold text-sm text-center uppercase tracking-wider">TEACHER'S SIGNATURE</div>
            </div>
            <div>
                <div class="signature-line border-t border-black mb-1 w-48 mx-auto"></div>
                <div class="font-bold text-sm text-center uppercase tracking-wider">HEADMASTER'S SIGNATURE</div>
            </div>
        </div>

    </div>
    <?php endif; ?>

</body>
</html>
