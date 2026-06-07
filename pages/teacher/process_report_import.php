<?php
session_start();
require '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login'); exit;
}

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

function getWordText($phpWord) {
    $text = '';
    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if (method_exists($element, 'getElements')) {
                foreach ($element->getElements() as $childElement) {
                    if (method_exists($childElement, 'getText')) {
                        $text .= $childElement->getText() . " ";
                    }
                }
            } elseif (method_exists($element, 'getText')) {
                $text .= $element->getText() . " ";
            }
        }
    }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['report_file'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die('Security Check Failed: Invalid or missing CSRF token.');
    }
    $file = $_FILES['report_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmpPath = $file['tmp_name'];
    
    // Core parameters from form
    $upload_class = $_POST['upload_class_name'] ?? '';
    $upload_week = intval($_POST['upload_week_number'] ?? 1);

    if (!$upload_class) {
        set_flash('error', 'Class must be selected for import.');
        header('Location: weekly_reports');
        exit;
    }

    try {
        $rawText = '';
        if ($ext === 'docx') {
            $zip = new ZipArchive;
            if ($zip->open($tmpPath) === TRUE) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $data = $zip->getFromIndex($index);
                    $data = str_replace(['</w:p>', '</w:tc>'], ["\n", "\t"], $data);
                    $rawText = strip_tags($data);
                }
                $zip->close();
            }
            if (empty(trim($rawText))) {
                $phpWord = @\PhpOffice\PhpWord\IOFactory::load($tmpPath, 'Word2007');
                if ($phpWord) $rawText = getWordText($phpWord);
            }
        }

        if (empty(trim($rawText))) {
            throw new Exception("Could not extract text from the file. Ensure it is a valid DOCX.");
        }

        // Extremely simple parser: look for Keyword: Value or Tab-separated or Next-line separated
        $lines = explode("\n", $rawText);
        $data = [];
        $currentKey = '';
        
        $keywords = [
            'Class' => 'class',
            'Week Number' => 'week_num',
            'Week Ending Date' => 'week_ending',
            
            // Academic Coverage
            'Topics Covered (Summary)' => 'topics_covered',
            'Assessments Conducted' => 'assessments_conducted',
            'Overall Class Performance' => 'overall_performance',
            'Struggling Students (Intervention)' => 'struggling_students',
            
            // Classroom Management
            'General Class Behavior' => 'general_behavior',
            'Discipline Issues & Actions' => 'discipline_issues',
            'Attendance Concerns' => 'attendance_concerns',
            
            // Parents & Support
            'Parents Contacted This Week' => 'parents_contacted',
            'Challenges Faced' => 'challenges_faced',
            'Support / Resources Required' => 'support_required',
            'Focus For Next Week' => 'next_week_focus'
        ];

        // Parse logic (Assuming Table structure where Label is followed by Value, separated by Tab or Newline)
        $tokens = [];
        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            foreach ($parts as $p) {
                if(trim($p) !== '') $tokens[] = trim($p);
            }
        }

        for ($i=0; $i<count($tokens); $i++) {
            $t = $tokens[$i];
            foreach ($keywords as $lbl => $key) {
                if (stripos($t, $lbl) === 0 || stripos($t, str_replace(' ', '', $lbl)) === 0) {
                    $val = trim(str_ireplace($lbl, '', $t));
                    if ($val === '' && isset($tokens[$i+1])) {
                        $next = $tokens[$i+1];
                        $isLabel = false;
                        foreach($keywords as $l => $k) { if(stripos($next, $l) === 0) $isLabel = true; }
                        if(!$isLabel) {
                            $val = $next;
                            $i++;
                        }
                    }
                    $data[$key] = trim(str_ireplace('e.g.', '', $val));
                }
            }
        }

        // Insert as DRAFT
        $stmt = $conn->prepare("INSERT INTO weekly_reports (
            teacher_id, class_name, week_ending, week_number, academic_term, academic_year, status,
            topics_covered, assessments_conducted, overall_performance, struggling_students,
            general_behavior, discipline_issues, attendance_concerns, parents_contacted,
            challenges_faced, support_required, next_week_focus
        ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $we = $data['week_ending'] ?? date('Y-m-d');
        if(!strtotime($we)) $we = date('Y-m-d');
        
        $v_top = $data['topics_covered'] ?? '';
        $v_ass = $data['assessments_conducted'] ?? '';
        $v_perf = $data['overall_performance'] ?? '';
        $v_str = $data['struggling_students'] ?? '';
        $v_gen = $data['general_behavior'] ?? '';
        $v_dis = $data['discipline_issues'] ?? '';
        $v_att = $data['attendance_concerns'] ?? '';
        $v_par = $data['parents_contacted'] ?? '';
        $v_cha = $data['challenges_faced'] ?? '';
        $v_sup = $data['support_required'] ?? '';
        $v_nex = $data['next_week_focus'] ?? '';

        $stmt->bind_param("ississssssssssssss", 
            $uid, $upload_class, $we, $upload_week, $current_term, $current_year,
            $v_top, $v_ass, $v_perf, $v_str, $v_gen, $v_dis, $v_att, $v_par, $v_cha, $v_sup, $v_nex
        );
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            header("Location: weekly_reports?edit=$new_id&draft_imported=1");
            exit;
        } else {
            throw new Exception("Database error saving draft: " . $conn->error);
        }

    } catch (Exception $e) {
        set_flash('error', $e->getMessage());
        header('Location: weekly_reports');
        exit;
    }
}
?>
