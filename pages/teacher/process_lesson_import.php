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
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
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
        } elseif ($ext === 'docx' || $ext === 'rtf' || $ext === 'doc') {
            $reader = ($ext === 'docx') ? 'Word2007' : (($ext === 'rtf') ? 'RTF' : 'HTML');
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpPath, $reader);
            $sections = $phpWord->getSections();
            $allData = [];
            $keywords = [
                'week_num' => ['Week Number', 'Week #', 'Week'],
                'week_ending' => ['Week Ending', 'Date Ending'],
                'day' => ['Day'],
                'subject' => ['Subject'],
                'duration' => ['Duration'],
                'strand' => ['Strand'],
                'sub_strand' => ['Sub-Strand', 'Sub Strand', 'Topic'],
                'class' => ['Class'],
                'class_size' => ['Class Size'],
                'content_standard' => ['Content Standard'],
                'indicator' => ['Indicator'],
                'lesson_num' => ['Lesson Number', 'Lesson #'],
                'perf_ind' => ['Performance Indicator'],
                'core_comp' => ['Core Competencies', 'Core Comp'],
                'refs' => ['References', 'Refs'],
                'tlm' => ['TLM', 'Teaching Materials'],
                'new_words' => ['New Words', 'Keywords'],
                's_act' => ['Starter Activities', 'Phase 1'],
                's_res' => ['Starter Resources'],
                's_dur' => ['Starter Duration'],
                'l_act' => ['Learning Activities', 'Phase 2'],
                'l_res' => ['Learning Resources'],
                'l_ass' => ['Assessment'],
                'l_dur' => ['Learning Duration'],
                'r_act' => ['Reflection Activities', 'Phase 3'],
                'r_res' => ['Reflection Resources'],
                'r_dur' => ['Reflection Duration'],
                'homework' => ['Homework', 'Assignment']
            ];
            $allData = [];
            
            // Check if it's our HTML-based .doc file
            $rawContent = file_get_contents($tmpPath);
            if ($ext === 'doc' && strpos($rawContent, '<html') !== false) {
                // Manual HTML Table Parser (Safe Fallback)
                preg_match_all('/<tr><td[^>]*>(.*?)<\/td><td[^>]*>(.*?)<\/td><\/tr>/is', $rawContent, $matches, PREG_SET_ORDER);
                if (!empty($matches)) {
                    $currentData = [];
                    foreach ($matches as $m) {
                        $label = trim(strip_tags($m[1]), ": \t\n\r\0\x0B");
                        $value = trim(strip_tags($m[2]));
                        foreach ($keywords as $key => $kMatches) {
                            foreach ($kMatches as $km) {
                                if (stripos($label, $km) !== false) {
                                    $currentData[$key] = $value;
                                    break;
                                }
                            }
                        }
                    }
                    if (count($currentData) > 3) $allData[] = $currentData;
                }
            }

            // If manual parsing didn't find anything, use PhpWord
            if (empty($allData)) {
                $reader = ($ext === 'docx') ? 'Word2007' : (($ext === 'rtf') ? 'RTF' : 'HTML');
                $phpWord = @\PhpOffice\PhpWord\IOFactory::load($tmpPath, $reader);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                            $currentData = [];
                            foreach ($element->getRows() as $row) {
                                $cells = $row->getCells();
                                if (count($cells) >= 2) {
                                    $labelCell = trim(getWordText($cells[0]), ": \t\n\r\0\x0B");
                                    $valueCell = trim(getWordText($cells[1]));
                                    foreach ($keywords as $key => $mKeys) {
                                        foreach ($mKeys as $mk) {
                                            if (stripos($labelCell, $mk) !== false) {
                                                $currentData[$key] = $valueCell;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            if (count($currentData) > 3) $allData[] = $currentData;
                        }
                    }
                }
            }

            foreach ($allData as $currentData) {
                if (!empty($currentData) && count($currentData) > 5) {
                    foreach ($keywords as $key => $matches) {
                        if (!isset($currentData[$key])) $currentData[$key] = '';
                    }
                    if (saveAsDraft($conn, $uid, $currentData, $current_term, $current_year)) {
                        $imported_count++;
                    }
                }
            }
        }
        
        if ($imported_count > 0) {
            redirect('lesson_plans', 'success', "Successfully imported $imported_count lesson note(s) as draft.");
        } else {
            redirect('lesson_plans', 'error', "No notes were imported. Ensure you follow the template.");
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
    $week_num = intval($d['week_num'] ?? 1); 
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

function getWordText($element) {
    $text = '';
    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        $text = $element->getText();
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        foreach ($element->getElements() as $child) {
            $text .= getWordText($child);
        }
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $child) {
            $text .= getWordText($child);
        }
    } elseif (method_exists($element, 'getText')) {
        $text = $element->getText();
    }
    return $text;
}
?>
