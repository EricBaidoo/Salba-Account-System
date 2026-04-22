<?php
session_start();
require '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'facilitator') {
    header('Location: ../../login'); exit;
}

$uid = $_SESSION['user_id'];
$current_term = getSystemSetting($conn, 'current_semester', '1');
$current_year = getSystemSetting($conn, 'current_academic_year', date('Y') . '/' . (date('Y')+1));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lesson_file'])) {
    $file = $_FILES['lesson_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $tmpPath = $file['tmp_name'];
    
    $imported_count = 0;
    $errors = [];

    try {
        if ($ext === 'xlsx') {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            // Expected headers count is 26. Row 0 is headers.
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                if (empty(array_filter($r))) continue; // Skip empty rows
                
                $data = [
                    'week_ending' => $r[0] ?? null,
                    'day' => $r[1] ?? '',
                    'subject' => $r[2] ?? '',
                    'duration' => $r[3] ?? '',
                    'strand' => $r[4] ?? '',
                    'sub_strand' => $r[5] ?? '',
                    'class' => $r[6] ?? '',
                    'class_size' => intval($r[7] ?? 0),
                    'content_standard' => $r[8] ?? '',
                    'indicator' => $r[9] ?? '',
                    'lesson_num' => $r[10] ?? '',
                    'perf_ind' => $r[11] ?? '',
                    'core_comp' => $r[12] ?? '',
                    'refs' => $r[13] ?? '',
                    'tlm' => $r[14] ?? '',
                    'new_words' => $r[15] ?? '',
                    's_act' => $r[16] ?? '',
                    's_res' => $r[17] ?? '',
                    's_dur' => $r[18] ?? '',
                    'l_act' => $r[19] ?? '',
                    'l_res' => $r[20] ?? '',
                    'l_ass' => $r[21] ?? '',
                    'l_dur' => $r[22] ?? '',
                    'r_act' => $r[23] ?? '',
                    'r_res' => $r[24] ?? '',
                    'r_dur' => $r[25] ?? '',
                    'homework' => $r[26] ?? ''
                ];
                
                if (saveAsDraft($conn, $uid, $data, $current_term, $current_year)) {
                    $imported_count++;
                }
            }
        } elseif ($ext === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpPath);
            $sections = $phpWord->getSections();
            
            foreach ($sections as $section) {
                $elements = $section->getElements();
                foreach ($elements as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                        $rows = $element->getRows();
                        // Row 0 is header.
                        for ($i = 1; $i < count($rows); $i++) {
                            $cells = $rows[$i]->getCells();
                            $rowData = [];
                            foreach ($cells as $cell) {
                                $text = '';
                                $cellElements = $cell->getElements();
                                foreach ($cellElements as $ce) {
                                    if (method_exists($ce, 'getText')) $text .= $ce->getText();
                                }
                                $rowData[] = trim($text);
                            }
                            
                            if (empty(array_filter($rowData))) continue;

                            $data = [
                                'week_ending' => $rowData[0] ?? null,
                                'day' => $rowData[1] ?? '',
                                'subject' => $rowData[2] ?? '',
                                'duration' => $rowData[3] ?? '',
                                'strand' => $rowData[4] ?? '',
                                'sub_strand' => $rowData[5] ?? '',
                                'class' => $rowData[6] ?? '',
                                'class_size' => intval($rowData[7] ?? 0),
                                'content_standard' => $rowData[8] ?? '',
                                'indicator' => $rowData[9] ?? '',
                                'lesson_num' => $rowData[10] ?? '',
                                'perf_ind' => $rowData[11] ?? '',
                                'core_comp' => $rowData[12] ?? '',
                                'refs' => $rowData[13] ?? '',
                                'tlm' => $rowData[14] ?? '',
                                'new_words' => $rowData[15] ?? '',
                                's_act' => $rowData[16] ?? '',
                                's_res' => $rowData[17] ?? '',
                                's_dur' => $rowData[18] ?? '',
                                'l_act' => $rowData[19] ?? '',
                                'l_res' => $rowData[20] ?? '',
                                'l_ass' => $rowData[21] ?? '',
                                'l_dur' => $rowData[22] ?? '',
                                'r_act' => $rowData[23] ?? '',
                                'r_res' => $rowData[24] ?? '',
                                'r_dur' => $rowData[25] ?? '',
                                'homework' => $rowData[26] ?? ''
                            ];
                            
                            if (saveAsDraft($conn, $uid, $data, $current_term, $current_year)) {
                                $imported_count++;
                            }
                        }
                        break; // Only parse first table
                    }
                }
            }
        }
        
        if ($imported_count > 0) {
            header("Location: lesson_plans?msg=Successfully imported $imported_count lesson note(s) as draft.&type=success");
        } else {
            header("Location: lesson_plans?msg=No notes were imported. Ensure you follow the template.&type=error");
        }
        exit;

    } catch (Exception $e) {
        header("Location: lesson_plans?msg=Error processing file: " . $e->getMessage() . "&type=error");
        exit;
    }
}

function saveAsDraft($conn, $uid, $d, $term, $year) {
    // Resolve Subject ID from name if possible, otherwise use a placeholder or 0
    $subject_name = trim($d['subject']);
    $subject_id = 0;
    if ($subject_name) {
        $res = $conn->query("SELECT id FROM subjects WHERE name LIKE '%$subject_name%' LIMIT 1");
        if ($res && $res->num_rows > 0) $subject_id = $res->fetch_assoc()['id'];
    }

    $sql = "INSERT INTO lesson_plans 
            (teacher_id, class_name, subject_id, week_number, topic, objectives, 
             week_ending, day_of_week, duration, strand, sub_strand, class_size,
             content_standard, indicator, lesson_number, performance_indicator, core_competencies,
             `references`, tlm, new_words, starter_activities, starter_resources,
             learning_activities, learning_resources, learning_assessment,
             reflection_activities, reflection_resources, homework, semester, academic_year,
             phase1_duration, phase2_duration, phase3_duration, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";
    
    $stmt = $conn->prepare($sql);
    $week_num = 1; // Default
    $topic = $d['sub_strand'];
    $obj = ''; // Consolidated objectives
    
    $stmt->bind_param(
        "isiisssssssisssssssssssssssssssss",
        $uid, $d['class'], $subject_id, $week_num, $topic, $obj,
        $d['week_ending'], $d['day'], $d['duration'], $d['strand'], $d['sub_strand'], $d['class_size'],
        $d['content_standard'], $d['indicator'], $d['lesson_num'], $d['perf_ind'], $d['core_comp'],
        $d['refs'], $d['tlm'], $d['new_words'], $d['s_act'], $d['s_res'],
        $d['l_act'], $d['l_res'], $d['l_ass'],
        $d['r_act'], $d['r_res'], $d['homework'], $term, $year,
        $d['s_dur'], $d['l_dur'], $d['r_dur']
    );
    
    return $stmt->execute();
}
?>
