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
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die('Security Check Failed: Invalid or missing CSRF token.');
    }
    $text = $_POST['pasted_text'];
    
    $keywords = [
        'week_num' => ['Week Number', 'Week #', 'Week'],
        'week_ending' => ['Week Ending', 'Date Ending', 'Date'],
        'day' => ['Day'],
        'subject' => ['Subject'],
        'duration' => ['Duration'],
        'strand' => ['Strand'],
        'sub_strand' => ['Sub-Strand', 'Sub Strand', 'Topic'],
        'class' => ['Class'],
        'class_size' => ['Class Size'],
        'content_standard' => ['Content Standard'],
        'indicator' => ['Learning Indicator', 'Indicator'],
        'lesson_num' => ['Lesson Number', 'Lesson #', 'Lesson'],
        'perf_ind' => ['Performance Indicator'],
        'core_comp' => ['Core Competencies', 'Core Comp'],
        'refs' => ['References', 'Reference', 'Refs'],
        'tlm' => ['Teaching/ Learning Resources', 'Teaching Materials', 'TLM'],
        'new_words' => ['New Words', 'Keywords'],
        's_act' => ['Starter Activities', 'Phase 1: Starter', 'Phase I', 'Phase 1'],
        's_res' => ['Starter Resources'],
        's_dur' => ['Starter Duration'],
        'l_act' => ['Learning Activities', 'Phase 2: New Learning', 'Phase 2: Main', 'Phase 2'],
        'l_res' => ['Learning Resources'],
        'l_ass' => ['Assessment', 'Evaluation'],
        'l_dur' => ['Learning Duration'],
        'r_act' => ['Reflection Activities', 'Phase 3: Reflection', 'Phase 3'],
        'r_res' => ['Reflection Resources'],
        'r_dur' => ['Reflection Duration'],
        'homework' => ['Homework', 'Assignment']
    ];

    // Split merged columns horizontally on the same line if they start with a keyword followed by a colon
    $allMatches = [];
    foreach ($keywords as $key => $matches) {
        foreach ($matches as $m) {
            $quoted = preg_quote($m, '/');
            if (strcasecmp($m, 'Strand') === 0) {
                $quoted = '(?<!Sub\s)(?<!Sub\-)' . $quoted;
            } elseif (strcasecmp($m, 'Indicator') === 0) {
                $quoted = '(?<!Learning\s)(?<!Performance\s)' . $quoted;
            } elseif (strcasecmp($m, 'Duration') === 0) {
                $quoted = '(?<!Starter\s)(?<!Learning\s)(?<!Reflection\s)' . $quoted;
            }
            $allMatches[] = $quoted;
        }
    }
    usort($allMatches, function($a, $b) { return strlen($b) - strlen($a); });
    $matchesRegex = implode('|', $allMatches);
    $text = preg_replace('/(\s+)(?=(?:' . $matchesRegex . '):)/i', "\n", $text);

    $lines = explode("\n", $text);
    
    $allData = [];
    $currentData = [];
    $singleLineKeys = ['class', 'subject', 'duration', 'strand', 'sub_strand', 'lesson_num', 'week_num', 'week_ending', 'day', 'class_size', 's_dur', 'l_dur', 'r_dur'];
    $currentKey = null;
    $cellIndex = null; // Track table column index

    // Flatten keywords and sort by length descending to match longest specific phrases first
    $flatKeywords = [];
    foreach ($keywords as $key => $matches) {
        foreach ($matches as $m) {
            $flatKeywords[] = ['key' => $key, 'match' => $m];
        }
    }
    usort($flatKeywords, function($a, $b) { return strlen($b['match']) - strlen($a['match']); });

    foreach ($lines as $line) {
        // Detect leading tabs before trimming
        $leadingTabs = 0;
        if (preg_match('/^(\s*)\t/', $line, $tabMatches)) {
            $leadingTabs = substr_count($tabMatches[0], "\t");
        }

        $line = trim($line);
        if (empty($line)) continue;

        // Smart transitional check for row-by-row horizontal tables (Prepositions notes formatting)
        $is_day_line = false;
        if (preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)\b/i', $line, $day_matches)) {
            $day_name = ucfirst(strtolower($day_matches[1]));
            
            // Push previous day's plan if exists and has some activities
            if (!empty($currentData) && (!empty($currentData['s_act']) || !empty($currentData['l_act']) || !empty($currentData['r_act']))) {
                $allData[] = $currentData;
                
                // Keep shared metadata for next day
                $sharedKeys = ['class', 'subject', 'duration', 'strand', 'sub_strand', 'lesson_num', 'week_num', 'week_ending', 'class_size', 'refs', 'tlm', 'new_words', 'content_standard', 'indicator', 'perf_ind', 'core_comp'];
                $newData = [];
                foreach ($sharedKeys as $sk) {
                    $newData[$sk] = $currentData[$sk] ?? '';
                }
                $currentData = $newData;
            }
            
            $currentData['day'] = $day_name;
            $cellIndex = 0; // Reset cell index since day is first cell (column 0)
            $currentKey = 's_act'; // Default activity key
            $is_day_line = true;
            
            $remaining = trim(substr($line, strlen($day_matches[0])), ": \t-");
            if (!empty($remaining)) {
                $line = $remaining;
            } else {
                continue;
            }
        }
        
        // Handle tab-based cell transitions
        if ($leadingTabs > 0 && $cellIndex !== null) {
            $cellIndex += $leadingTabs;
            if ($cellIndex === 1) {
                $currentKey = 's_act';
            } elseif ($cellIndex === 2) {
                $currentKey = 'l_act';
            } elseif ($cellIndex === 3) {
                $currentKey = 'r_act';
            }
        }

        // Only use regex transitions if we are NOT in tab-based parsing or if tab index doesn't restrict it
        if ($leadingTabs === 0 && !$is_day_line) {
            if (preg_match('/^[A-E]\.\s*(?:ORAL|READING|GRAMMAR|WRITING|CONVENTIONS|EXPRESSION|COMPREHENSION|VOCABULARY|SPELLING)/i', $line)) {
                $currentKey = 'l_act';
            }
            
            if (preg_match('/^(?:Give\s+learners\s+task|Give\s+pupils\s+task|Give\s+learners\s+a\s+task|Give\s+pupils\s+a\s+task)/i', $line)) {
                $currentKey = 'r_act';
            }
        }

        // Handle lines that have "Key: Value" or "Key - Value" natively
        $separator = '';
        if (strpos($line, ':') !== false) {
            $separator = ':';
        } elseif (strpos($line, "\t") !== false) {
            $separator = "\t";
        }
        
        $labelToTest = $line;
        $valueToSet = '';
        
        if ($separator) {
            $parts = explode($separator, $line, 2);
            $labelToTest = trim($parts[0]);
            $valueToSet = trim($parts[1]);
        }
            
        $matched = false;

        foreach ($flatKeywords as $fk) {
            $key = $fk['key'];
            $m = $fk['match'];
            
            if ($separator) {
                // If there is a separator, check if the label starts with the keyword (case-insensitive)
                $regex = '/^' . preg_quote($m, '/') . '\b/i';
                if (!preg_match('/\w$/', $m)) {
                    $regex = '/^' . preg_quote($m, '/') . '/i';
                }
                $isMatch = preg_match($regex, $labelToTest);
            } else {
                // If there is NO separator, match the entire line exactly (case-insensitive)
                $isMatch = (strcasecmp($labelToTest, $m) === 0);
            }
            
            if ($isMatch) {
                // If starting a new Phase 1/Starter and we already have activities, push the current block
                if ($key === 's_act' && !empty($currentData) && (!empty($currentData['l_act']) || !empty($currentData['r_act']))) {
                    $allData[] = $currentData;
                    
                    // Keep shared metadata for next lesson
                    $sharedKeys = ['class', 'subject', 'duration', 'strand', 'sub_strand', 'lesson_num', 'week_num', 'week_ending', 'class_size', 'refs', 'tlm', 'new_words', 'content_standard', 'indicator', 'perf_ind', 'core_comp'];
                    $newData = [];
                    foreach ($sharedKeys as $sk) {
                        $newData[$sk] = $currentData[$sk] ?? '';
                    }
                    $currentData = $newData;
                }
                
                $currentKey = $key;
                
                // If the line had a colon and a value, use it. Otherwise, if it was just "Subject English", try to extract.
                if (empty($valueToSet) && strlen($labelToTest) > strlen($m)) {
                    $valueToSet = trim(substr($labelToTest, strlen($m)), ": \t-");
                }
                
                if (!empty($valueToSet)) {
                    if (in_array($currentKey, $singleLineKeys)) {
                        $currentData[$currentKey] = $valueToSet;
                    } else {
                        $currentData[$currentKey] = empty($currentData[$currentKey]) ? $valueToSet : $currentData[$currentKey] . "\n" . $valueToSet;
                    }
                }
                $matched = true;
                break;
            }
        }
        
        // If this line did NOT match any keyword, but we have an active $currentKey, 
        // append it to the current key's value. This catches multi-line descriptions and table cell values.
        if (!$matched && $currentKey !== null) {
            if (in_array($currentKey, $singleLineKeys)) {
                $currentData[$currentKey] = empty($currentData[$currentKey]) ? $line : $currentData[$currentKey] . ' ' . $line;
            } else {
                $currentData[$currentKey] = empty($currentData[$currentKey]) ? $line : $currentData[$currentKey] . "\n" . $line;
            }
        }
    }

    if (!empty($currentData)) {
        $allData[] = $currentData;
    }

    // Filter out dummy/invalid plans
    $filteredData = [];
    foreach ($allData as $data) {
        $activitiesCount = 0;
        if (!empty(trim($data['s_act'] ?? ''))) $activitiesCount++;
        if (!empty(trim($data['l_act'] ?? ''))) $activitiesCount++;
        if (!empty(trim($data['r_act'] ?? ''))) $activitiesCount++;
        
        if ($activitiesCount < 2) {
            continue;
        }
        
        $totalActivitiesLen = strlen(trim($data['s_act'] ?? '')) + strlen(trim($data['l_act'] ?? '')) + strlen(trim($data['r_act'] ?? ''));
        if ($totalActivitiesLen < 150) {
            continue;
        }
        
        // Clean day of week if it's not valid
        $day_val = trim($data['day'] ?? '');
        if (!empty($day_val) && !preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)/i', $day_val)) {
            $data['day'] = '';
        }
        
        $filteredData[] = $data;
    }
    $allData = $filteredData;

    if (!empty($allData)) {
        // Helper to deduplicate repeated words or phrases
        $clean_repeated = function($str) {
            $str = trim($str);
            $clean_str = preg_replace('/\s+/', ' ', $str);
            $words = explode(' ', $clean_str);
            $w_count = count($words);
            if ($w_count > 1) {
                for ($len = 1; $len <= floor($w_count / 2); $len++) {
                    if ($w_count % $len === 0) {
                        $slice = array_slice($words, 0, $len);
                        $phrase = implode(' ', $slice);
                        $repeats = $w_count / $len;
                        $reconstructed = implode(' ', array_fill(0, $repeats, $phrase));
                        if (strcasecmp($clean_str, $reconstructed) === 0) {
                            return $phrase;
                        }
                    }
                }
            }
            return $str;
        };

        // PRE-VALIDATE CLASS AND SUBJECT
        foreach ($allData as &$data) {
            // Ensure all keys exist
            foreach ($keywords as $k => $matches) {
                if (!isset($data[$k])) $data[$k] = '';
            }

            // Clean up single line keys
            foreach ($singleLineKeys as $slk) {
                $data[$slk] = $clean_repeated($data[$slk]);
            }

            $class_name = trim($data['class'] ?? '');
            if (empty($class_name)) {
                $class_name = mb_substr(implode(" ", $data), 0, 1000);
            }

            if (!empty($class_name)) {
                $wordToNum = [
                    'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 
                    'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9'
                ];
                foreach ($wordToNum as $word => $num) {
                    $class_name = preg_replace('/\b' . $word . '\b/i', $num, $class_name);
                }

                if (preg_match('/(?:Basic|Class|B)\s*([1-9])/i', $class_name, $matches)) {
                    $class_name = 'Basic ' . $matches[1];
                } elseif (preg_match('/(?:KG)\s*([1-2])/i', $class_name, $matches)) {
                    $class_name = 'KG ' . $matches[1];
                } elseif (preg_match('/(?:Nursery|N)\s*([1-2])/i', $class_name, $matches)) {
                    $class_name = 'Nursery ' . $matches[1];
                } elseif (preg_match('/^([1-9])$/', trim($class_name), $matches)) {
                    $class_name = 'Basic ' . $matches[1];
                }
                
                $c_res = $conn->query("SELECT name FROM classes WHERE name LIKE '%" . $conn->real_escape_string($class_name) . "%' OR '" . $conn->real_escape_string($class_name) . "' LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
                if ($c_res && $c_res->num_rows > 0) {
                    $data['class'] = $c_res->fetch_assoc()['name'];
                } else {
                    $short_c = mb_substr($class_name, 0, 30) . (mb_strlen($class_name) > 30 ? '...' : '');
                    redirect('lesson_plans', 'error', "Invalid Class Name detected in pasted text: '" . htmlspecialchars($short_c) . "'. Please check your text and ensure it exactly matches an official class name.");
                }
            } else {
                redirect('lesson_plans', 'error', "Missing Class Name in pasted text. Please ensure 'Class:' is clearly labeled.");
            }
            
            $subject_name = trim($data['subject'] ?? '');
            if (empty($subject_name)) {
                $subject_name = mb_substr(implode(" ", $data), 0, 1000);
            }

            if (!empty($subject_name)) {
                $s_res = $conn->query("SELECT name FROM subjects WHERE name LIKE '%" . $conn->real_escape_string($subject_name) . "%' OR '" . $conn->real_escape_string($subject_name) . "' LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
                if ($s_res && $s_res->num_rows > 0) {
                    $data['subject'] = $s_res->fetch_assoc()['name'];
                } else {
                    $short_s = mb_substr($subject_name, 0, 30) . (mb_strlen($subject_name) > 30 ? '...' : '');
                    redirect('lesson_plans', 'error', "Invalid Subject Name detected in pasted text: '" . htmlspecialchars($short_s) . "'. Please check your text and ensure it exactly matches an official subject name.");
                }
            } else {
                redirect('lesson_plans', 'error', "Missing Subject Name in pasted text. Please ensure 'Subject:' is clearly labeled.");
            }
        }
        unset($data);

        // Define saveAsDraft helper inside paste script if not present
        if (!function_exists('saveAsDraftPaste')) {
            function saveAsDraftPaste($conn, $uid, $d, $term, $year) {
                $c_class = substr($d['class'] ?? '', 0, 50);
                $c_dur = substr($d['duration'] ?? '', 0, 20);
                $c_strand = substr($d['strand'] ?? '', 0, 255);
                $c_substrand = substr($d['sub_strand'] ?? '', 0, 255);
                $c_csize = intval($d['class_size'] ?? 0);
                $c_lesnum = substr($d['lesson_num'] ?? '', 0, 20);
                
                $topic = $c_substrand;
                $week_num = intval($d['week_num'] ?? 1); 
                
                $c_s_dur = substr($d['s_dur'] ?? '', 0, 20);
                $c_l_dur = substr($d['l_dur'] ?? '', 0, 20);
                $c_r_dur = substr($d['r_dur'] ?? '', 0, 20);

                $parsed_date = strtotime($d['week_ending'] ?? '');
                $c_week_ending = $parsed_date ? date('Y-m-d', $parsed_date) : null;

                $c_day = substr($d['day'] ?? '', 0, 20);
                if (!empty($c_day)) {
                    if (!preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)/i', $c_day)) {
                        $c_day = '';
                    } else {
                        preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)/i', $c_day, $day_matches);
                        $c_day = ucfirst(strtolower($day_matches[1]));
                    }
                }
                
                if (empty($c_day) && $parsed_date) {
                    $c_day = date('l', $parsed_date);
                }

                $subject_name = trim($d['subject'] ?? '');
                $subject_id = 0;
                if ($subject_name) {
                    $res = $conn->query("SELECT id FROM subjects WHERE name LIKE '%" . $conn->real_escape_string($subject_name) . "%' LIMIT 1");
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
                $obj = ''; 
                
                $stmt->bind_param(
                    "isiisssssssisssssssssssssssssssss",
                    $uid, $c_class, $subject_id, $week_num, $topic, $obj,
                    $c_week_ending, $c_day, $c_dur, $c_strand, $c_substrand, $c_csize,
                    $d['content_standard'], $d['indicator'], $c_lesnum, $d['perf_ind'], $d['core_comp'],
                    $d['refs'], $d['tlm'], $d['new_words'], $d['s_act'], $d['s_res'],
                    $d['l_act'], $d['l_res'], $d['l_ass'],
                    $d['r_act'], $d['r_res'], $d['homework'], $term, $year,
                    $c_s_dur, $c_l_dur, $c_r_dur
                );
                
                if ($stmt->execute()) {
                    return $conn->insert_id;
                }
                return false;
            }
        }

        // SAVE ALL EXTRACTED DATA
        $imported_count = 0;
        $last_id = 0;
        foreach ($allData as $data) {
            $id = saveAsDraftPaste($conn, $uid, $data, $current_term, $current_year);
            if ($id) {
                $imported_count++;
                $last_id = $id;
            }
        }

        if ($imported_count > 0) {
            if ($imported_count === 1 && $last_id > 0) {
                redirect("lesson_plans?edit=$last_id", 'success', "Successfully imported pasted lesson note! Please review and click Submit.");
            } else {
                redirect('lesson_portfolio', 'success', "Successfully imported $imported_count lesson note(s) as draft.");
            }
        } else {
            redirect('lesson_plans', 'error', "Error saving pasted content: " . $conn->error);
        }
    } else {
        redirect('lesson_plans', 'error', "Could not find any lesson data in the pasted text. Ensure you follow the Label: Value format.");
    }
    exit;
}
?>
