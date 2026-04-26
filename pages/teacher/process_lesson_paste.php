<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'facilitator') {
    header('Location: ../../login'); exit;
}

$uid = $_SESSION['user_id'];
$current_term = getSystemSetting($conn, 'current_semester', '1');
$current_year = getSystemSetting($conn, 'current_academic_year', date('Y') . '/' . (date('Y')+1));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pasted_text'])) {
    $text = $_POST['pasted_text'];
    $lines = explode("\n", $text);
    
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

    $currentData = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Support Colons or Tabs as separators
        $separator = '';
        if (strpos($line, ':') !== false) $separator = ':';
        elseif (strpos($line, "\t") !== false) $separator = "\t";
        
        if ($separator) {
            $parts = explode($separator, $line, 2);
            $label = trim($parts[0], ": \t\n\r\0\x0B");
            $value = trim($parts[1]);
        } else {
            // If no separator, the line might just start with a keyword
            $label = $line;
            $value = ''; // We'll hope the next line has the value or ignore
        }
            
        foreach ($keywords as $key => $matches) {
            foreach ($matches as $m) {
                if (stripos($label, $m) !== false) {
                    // If we only have a label and no value, try to find the value in the rest of the line
                    if (empty($value)) {
                        $value = trim(str_ireplace($m, '', $label), ": \t");
                    }
                    $currentData[$key] = $value;
                    break;
                }
            }
        }
    }

    if (!empty($currentData) && count($currentData) > 5) {
        // Ensure all keys exist
        foreach ($keywords as $key => $matches) {
            if (!isset($currentData[$key])) $currentData[$key] = '';
        }

        // Subject ID resolution
        $subject_name = trim($currentData['subject']);
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
        $week_num = intval($currentData['week_num'] ?? 1); 
        $topic = $currentData['sub_strand'];
        $obj = ''; 
        
        $stmt->bind_param(
            "isiisssssssisssssssssssssssssssss",
            $uid, $currentData['class'], $subject_id, $week_num, $topic, $obj,
            $currentData['week_ending'], $currentData['day'], $currentData['duration'], $currentData['strand'], $currentData['sub_strand'], $currentData['class_size'],
            $currentData['content_standard'], $currentData['indicator'], $currentData['lesson_num'], $currentData['perf_ind'], $currentData['core_comp'],
            $currentData['refs'], $currentData['tlm'], $currentData['new_words'], $currentData['s_act'], $currentData['s_res'],
            $currentData['l_act'], $currentData['l_res'], $currentData['l_ass'],
            $currentData['r_act'], $currentData['r_res'], $currentData['homework'], $current_term, $current_year,
            $currentData['s_dur'], $currentData['l_dur'], $currentData['r_dur']
        );
        
        if ($stmt->execute()) {
            redirect('lesson_plans', 'success', "Successfully imported pasted lesson note as draft.");
        } else {
            redirect('lesson_plans', 'error', "Error saving pasted content: " . $conn->error);
        }
    } else {
        redirect('lesson_plans', 'error', "Could not find any lesson data in the pasted text. Ensure you follow the Label: Value format.");
    }
    exit;
}
?>
