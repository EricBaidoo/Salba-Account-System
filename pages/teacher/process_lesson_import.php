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

// Keyword definitions used for matching headings across all formats
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
    'l_ass' => ['Assessment', 'Evaluation'],
    'l_dur' => ['Learning Duration'],
    'r_act' => ['Reflection Activities', 'Phase 3'],
    'r_res' => ['Reflection Resources'],
    'r_dur' => ['Reflection Duration'],
    'homework' => ['Homework', 'Assignment']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lesson_file'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die('Security Check Failed: Invalid or missing CSRF token.');
    }
    $file = $_FILES['lesson_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $tmpPath = $file['tmp_name'];
    
    $imported_count = 0;

    try {
        $allData = [];

        if ($ext === 'xlsx') {
            // EXCEL PROCESSING
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            if (count($rows) < 2) {
                throw new Exception("Excel file is empty or missing data rows.");
            }

            $headers = $rows[0];
            $colMap = [];
            
            // Map headers to internal keys
            foreach ($headers as $colIdx => $headerVal) {
                if (empty($headerVal)) continue;
                $headerStr = trim($headerVal);
                foreach ($keywords as $key => $matches) {
                    foreach ($matches as $m) {
                        if (stripos($headerStr, $m) === 0) {
                            $colMap[$key] = $colIdx;
                            break 2;
                        }
                    }
                }
            }

            // Relaxed Validation: Ensure at least some columns mapped
            if (count($colMap) < 3) {
                throw new Exception("Invalid Excel format. The file is missing recognized column headings. Please use the official template or standard headings.");
            }

            // Extract rows
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                if (empty(array_filter($r))) continue; // Skip empty rows
                
                $currentData = [];
                foreach ($keywords as $key => $m) {
                    $idx = $colMap[$key] ?? -1;
                    $currentData[$key] = ($idx >= 0 && isset($r[$idx])) ? trim((string)$r[$idx]) : '';
                }
                
                // Add if it has some data
                if (count(array_filter($currentData)) >= 2) {
                    $allData[] = $currentData;
                }
            }

        } elseif (in_array($ext, ['docx', 'rtf', 'doc', 'pdf'])) {
            // WORD OR PDF PROCESSING
            $rawText = '';

            if ($ext === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($tmpPath);
                $rawText = $pdf->getText();
            } else {
                // Word parsing fallback to extract raw text
                if ($ext === 'docx') {
                    $zip = new ZipArchive;
                    if ($zip->open($tmpPath) === TRUE) {
                        if (($index = $zip->locateName('word/document.xml')) !== false) {
                            $data = $zip->getFromIndex($index);
                            $data = str_replace(
                                ['</w:p>', '</w:tc>', '</w:tr>', '<w:br/>', '<w:br>', '<w:cr/>', '<w:cr>', '<w:tab/>'], 
                                ["\n", "\t", "\n", "\n", "\n", "\n", "\n", "\t"], 
                                $data
                            );
                            $rawText = strip_tags($data);
                        }
                        $zip->close();
                    }
                }
                
                // If docx manual extraction failed, or it's RTF/DOC, try PhpWord
                if (empty(trim($rawText))) {
                    $reader = ($ext === 'docx') ? 'Word2007' : (($ext === 'rtf') ? 'RTF' : 'HTML');
                    try {
                        $phpWord = @\PhpOffice\PhpWord\IOFactory::load($tmpPath, $reader);
                        if ($phpWord) {
                            $rawText = getWordText($phpWord);
                        }
                    } catch (Exception $e) {
                        // Suppress IOFactory crash
                    }
                }
                
                if (empty(trim($rawText)) && $ext === 'doc') {
                    $rawContent = file_get_contents($tmpPath);
                    $rawText = strip_tags(str_replace(['</tr>', '</td>', '<br>', '</p>'], "\n", $rawContent));
                }
                
                if (empty(trim($rawText)) && $ext === 'rtf') {
                    // Manual RTF extraction fallback
                    $content = file_get_contents($tmpPath);
                    $content = str_replace(['\par', '\row'], "\n", $content);
                    $content = str_replace(['\cell', '\tab'], "\t", $content);
                    $content = preg_replace('/\\\[a-zA-Z]+[0-9]*/', '', $content);
                    $content = preg_replace('/[{}]/', '', $content);
                    $rawText = trim($content);
                }
            }

            if (empty(trim($rawText))) {
                throw new Exception("Could not extract any text from the document. Please ensure it is not a scanned image.");
            }

            // Normalize tabs to newlines so table cells are treated as separate chunks of text
            $rawText = str_replace("\t", "\n", $rawText);
            $lines = explode("\n", $rawText);
            
            $currentData = [];
            $singleLineKeys = ['class', 'subject', 'duration', 'strand', 'sub_strand', 'lesson_num', 'week_num', 'week_ending', 'day', 'class_size', 's_dur', 'l_dur', 'r_dur'];
            $currentKey = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Handle lines that have "Key: Value" or "Key - Value" natively
                $separator = '';
                if (strpos($line, ':') !== false) $separator = ':';
                
                $labelToTest = $line;
                $valueToSet = '';
                
                if ($separator) {
                    $parts = explode($separator, $line, 2);
                    $labelToTest = trim($parts[0]);
                    $valueToSet = trim($parts[1]);
                }
                    
                $matched = false;
                
                // Flatten keywords and sort by length descending to match longest specific phrases first
                $flatKeywords = [];
                foreach ($keywords as $key => $matches) {
                    foreach ($matches as $m) {
                        $flatKeywords[] = ['key' => $key, 'match' => $m];
                    }
                }
                usort($flatKeywords, function($a, $b) { return strlen($b['match']) - strlen($a['match']); });

                foreach ($flatKeywords as $fk) {
                    $key = $fk['key'];
                    $m = $fk['match'];
                    
                    if (stripos($labelToTest, $m) === 0) {
                        $currentKey = $key;
                        
                        // If the line had a colon and a value, use it. Otherwise, if it was just "Subject English", try to extract.
                        if (empty($valueToSet) && strlen($labelToTest) > strlen($m)) {
                            $valueToSet = trim(substr($labelToTest, strlen($m)), ": \t-");
                        }
                        
                        if (!empty($valueToSet)) {
                            $glue = in_array($currentKey, $singleLineKeys) ? ' ' : "\n";
                            $currentData[$currentKey] = empty($currentData[$currentKey]) ? $valueToSet : $currentData[$currentKey] . $glue . $valueToSet;
                        }
                        $matched = true;
                        break;
                    }
                }
                
                // If this line did NOT match any keyword, but we have an active $currentKey, 
                // append it to the current key's value. This catches multi-line descriptions and table cell values.
                if (!$matched && $currentKey !== null) {
                    $glue = in_array($currentKey, $singleLineKeys) ? ' ' : "\n";
                    $currentData[$currentKey] = empty($currentData[$currentKey]) ? $line : $currentData[$currentKey] . $glue . $line;
                }
            }

            // Ensure all keys exist
            foreach ($keywords as $key => $matches) {
                if (!isset($currentData[$key])) $currentData[$key] = '';
            }

            // Relaxed Validation for Word/PDF
            $matchedFields = count(array_filter($currentData, fn($val) => trim($val) !== ''));
            if ($matchedFields < 3) {
                throw new Exception("Invalid Document Format. We couldn't recognize standard headings like 'Subject:', 'Class:', 'Topic:', or 'Phase 1:'. Please add standard labels to your text or use the Paste Tool.");
            }

            $allData[] = $currentData;
        } else {
            throw new Exception("Unsupported file type.");
        }

        // Helper to deduplicate repeated words or phrases (e.g. "English Language English Language" -> "English Language")
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
            // Clean up all single-line fields to deduplicate repeated phrases (e.g. "60mins 60mins" -> "60mins")
            $singleLineKeys = ['class', 'subject', 'duration', 'strand', 'sub_strand', 'lesson_num', 'week_num', 'week_ending', 'day', 'class_size', 's_dur', 'l_dur', 'r_dur'];
            foreach ($singleLineKeys as $slk) {
                if (isset($data[$slk])) {
                    $data[$slk] = $clean_repeated($data[$slk]);
                }
            }

            $class_name = trim($data['class'] ?? '');
            if (!empty($class_name)) {
                // Map short forms (e.g. B7, b7, B 7, b 7 to Basic 7)
                if (preg_match('/^[Bb]\s*([1-9])$/', $class_name, $matches)) {
                    $class_name = 'Basic ' . $matches[1];
                } elseif (preg_match('/^[Kk][Gg]\s*([1-2])$/', $class_name, $matches)) {
                    $class_name = 'KG ' . $matches[1];
                } elseif (preg_match('/^[Nn]ursery\s*([1-2])$/i', $class_name, $matches)) {
                    $class_name = 'Nursery ' . $matches[1];
                }
                
                $c_res = $conn->query("SELECT name FROM classes WHERE name LIKE '%" . $conn->real_escape_string($class_name) . "%' LIMIT 1");
                if ($c_res && $c_res->num_rows > 0) {
                    $data['class'] = $c_res->fetch_assoc()['name']; // Use exact official name
                } else {
                    throw new Exception("Invalid Class Name detected in document: '" . htmlspecialchars($class_name) . "'. Please check your document and ensure it exactly matches an official class name (e.g. Basic 1).");
                }
            } else {
                throw new Exception("Missing Class Name in document. Please ensure 'Class:' is clearly labeled.");
            }
            
            $subject_name = trim($data['subject'] ?? '');
            if (!empty($subject_name)) {
                $s_res = $conn->query("SELECT name FROM subjects WHERE name LIKE '%" . $conn->real_escape_string($subject_name) . "%' LIMIT 1");
                if ($s_res && $s_res->num_rows > 0) {
                    $data['subject'] = $s_res->fetch_assoc()['name']; // Use exact official name
                } else {
                    throw new Exception("Invalid Subject Name detected in document: '" . htmlspecialchars($subject_name) . "'. Please check your document and ensure it exactly matches an official subject name.");
                }
            } else {
                throw new Exception("Missing Subject Name in document. Please ensure 'Subject:' is clearly labeled.");
            }
        }
        unset($data);

        // SAVE ALL EXTRACTED DATA
        $last_id = 0;
        foreach ($allData as $data) {
            $id = saveAsDraft($conn, $uid, $data, $current_term, $current_year);
            if ($id) {
                $imported_count++;
                $last_id = $id;
            }
        }
        
        if ($imported_count > 0) {
            if ($imported_count === 1 && $last_id > 0) {
                redirect("lesson_plans?edit=$last_id", 'success', "Successfully imported lesson note! Please review and click Submit.");
            } else {
                redirect('lesson_portfolio', 'success', "Successfully imported $imported_count lesson note(s) as draft.");
            }
        } else {
            redirect('lesson_plans', 'error', "No lesson notes were imported. Ensure your file follows the standard template format.");
        }
        exit;

    } catch (Exception $e) {
        header("Location: lesson_plans?msg=" . urlencode($e->getMessage()) . "&type=error");
        exit;
    }
}

function saveAsDraft($conn, $uid, $d, $term, $year) {
    // Safety Truncations to prevent fatal DB crashes
    $c_class = substr($d['class'] ?? '', 0, 50);
    $c_day = substr($d['day'] ?? '', 0, 20);
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

    // Date Safe Parsing
    $parsed_date = strtotime($d['week_ending'] ?? '');
    $c_week_ending = $parsed_date ? date('Y-m-d', $parsed_date) : null;

    // Resolve Subject ID
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

function getWordText($element) {
    $text = '';
    
    if ($element instanceof \PhpOffice\PhpWord\PhpWord) {
        foreach ($element->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $elText = getWordText($el);
                if ($elText !== '') {
                    $text .= $elText;
                    if (substr($elText, -1) !== "\n") {
                        $text .= "\n";
                    }
                }
            }
        }
        return $text;
    }
    
    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        foreach ($element->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                $cellText = '';
                foreach ($cell->getElements() as $el) {
                    $elText = getWordText($el);
                    if ($elText !== '') {
                        $cellText .= $elText;
                        if (substr($elText, -1) !== "\n" && substr($elText, -1) !== "\t") {
                            $cellText .= "\n";
                        }
                    }
                }
                $text .= $cellText . "\t";
            }
            $text .= "\n";
        }
        return $text;
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        $text = $element->getText();
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextBreak) {
        $text = "\n";
    } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        foreach ($element->getElements() as $child) {
            $text .= getWordText($child);
        }
        $text .= "\n";
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $child) {
            $childText = getWordText($child);
            if ($childText !== '') {
                $text .= $childText;
                if (substr($childText, -1) !== "\n") {
                    $text .= "\n";
                }
            }
        }
    } elseif (method_exists($element, 'getText')) {
        $text = $element->getText() . "\n";
    }
    return $text;
}
?>
