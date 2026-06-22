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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lesson_file'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die('Security Check Failed: Invalid or missing CSRF token.');
    }
    // Validate File Type
    $allowedExts = ['docx', 'xlsx', 'xls', 'pdf', 'rtf', 'doc', 'png', 'jpg', 'jpeg'];
    $file = $_FILES['lesson_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedExts)) {
        die('Invalid file type.');
    }
    
    $tmpPath = $file['tmp_name'];
    
    $isDirect = isset($_POST['direct_upload']) && $_POST['direct_upload'] == 1;
    if ($isDirect) {
        try {
            $rawText = '';
            if ($ext === 'xlsx' || $ext === 'xls') {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpPath);
                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $cellValues = [];
                        foreach ($row->getCellIterator() as $cell) {
                            $cellValues[] = (string)$cell->getValue();
                        }
                        $rawText .= implode(" ", $cellValues) . "\n";
                    }
                }
            } elseif ($ext === 'pdf') {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($tmpPath);
                $rawText = $pdf->getText();
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                try {
                    $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($tmpPath);
                    $rawText = $ocr->run();
                } catch (Exception $e) {
                    throw new Exception("OCR Failed. Please ensure Tesseract OCR is installed on the server. Error: " . $e->getMessage());
                }
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
                
                if (empty(trim($rawText))) {
                    $reader = ($ext === 'docx') ? 'Word2007' : (($ext === 'rtf') ? 'RTF' : (($ext === 'doc') ? 'MsDoc' : 'HTML'));
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
                    if (preg_match_all('/[a-zA-Z0-9\s,\.\-\(\):;!?\'"]{3,}/', $rawContent, $matches)) {
                        $rawText = implode("\n", $matches[0]);
                    }
                }
                
                if (empty(trim($rawText)) && $ext === 'rtf') {
                    $content = file_get_contents($tmpPath);
                    $content = str_replace(['\par', '\row'], "\n", $content);
                    $content = str_replace(['\cell', '\tab'], "\t", $content);
                    $content = preg_replace('/\\\[a-zA-Z]+[0-9]*/', '', $content);
                    $content = preg_replace('/[{}]/', '', $content);
                    $rawText = trim($content);
                }
            }

            if (empty(trim($rawText))) {
                if ($ext === 'pdf') {
                    throw new Exception("Could not extract any text from the PDF. If this is a scanned document or a photo converted to PDF, the system cannot read it because it does not have OCR capabilities. Please ensure the document is readable.");
                } else {
                    throw new Exception("Could not extract any text from the document. Please ensure it is not a scanned image or an empty file.");
                }
            }

            // Parse metadata
            $meta = [
                'class' => '',
                'subject' => '',
                'week_num' => '',
                'week_ending' => '',
                'day' => '',
                'duration' => '',
                'strand' => '',
                'sub_strand' => '',
                'class_size' => '',
                'lesson_num' => '',
                's_dur' => '',
                'l_dur' => '',
                'r_dur' => '',
                'content_standard' => '',
                'indicator' => '',
                'perf_ind' => '',
                'core_comp' => '',
                'refs' => '',
                'tlm' => '',
                'new_words' => '',
                's_act' => '',
                's_res' => '',
                'l_act' => '',
                'l_res' => '',
                'l_ass' => '',
                'r_act' => '',
                'r_res' => '',
                'homework' => ''
            ];

            // Normalize line splits with keywords colons
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
            $normalizedText = preg_replace('/(\s+)(?=(?:' . $matchesRegex . '):)/i', "\n", $rawText);

            $lines = explode("\n", $normalizedText);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Class
                if (empty($meta['class'])) {
                    foreach ($keywords['class'] as $kw) {
                        if (preg_match('/^' . preg_quote($kw, '/') . '[\s:-]+(.+)/i', $line, $m)) {
                            $meta['class'] = trim($m[1]);
                            break;
                        }
                    }
                }
                // Subject
                if (empty($meta['subject'])) {
                    foreach ($keywords['subject'] as $kw) {
                        if (preg_match('/^' . preg_quote($kw, '/') . '[\s:-]+(.+)/i', $line, $m)) {
                            $meta['subject'] = trim($m[1]);
                            break;
                        }
                    }
                }
                // Week Ending
                if (empty($meta['week_ending'])) {
                    foreach ($keywords['week_ending'] as $kw) {
                        if (preg_match('/^' . preg_quote($kw, '/') . '[\s:-]+(.+)/i', $line, $m)) {
                            $meta['week_ending'] = trim($m[1]);
                            break;
                        }
                    }
                }
                // Week Number
                if (empty($meta['week_num'])) {
                    foreach ($keywords['week_num'] as $kw) {
                        if (stripos($line, 'Week Ending') === 0 || stripos($line, 'WeekEnding') === 0) {
                            continue;
                        }
                        if (preg_match('/^' . preg_quote($kw, '/') . '[\s:-]+([0-9]+)/i', $line, $m)) {
                            $meta['week_num'] = trim($m[1]);
                            break;
                        }
                    }
                }
                // Sub-strand
                if (empty($meta['sub_strand'])) {
                    foreach ($keywords['sub_strand'] as $kw) {
                        if (preg_match('/^' . preg_quote($kw, '/') . '[\s:-]+(.+)/i', $line, $m)) {
                            $meta['sub_strand'] = trim($m[1]);
                            break;
                        }
                    }
                }
            }

            // Fallbacks for Class
            if (empty($meta['class'])) {
                if (preg_match('/(?:Basic|Class|B)\s*([1-9])/i', $rawText, $m)) {
                    $meta['class'] = 'Basic ' . $m[1];
                } elseif (preg_match('/KG\s*([1-2])/i', $rawText, $m)) {
                    $meta['class'] = 'KG ' . $m[1];
                } elseif (preg_match('/Nursery\s*([1-2])/i', $rawText, $m)) {
                    $meta['class'] = 'Nursery ' . $m[1];
                }
            }
            // Fallbacks for Week Number
            if (empty($meta['week_num'])) {
                if (preg_match('/Week\s*([0-9]+)/i', $rawText, $m)) {
                    $meta['week_num'] = $m[1];
                }
            }
            // Fallbacks for Week Ending
            if (empty($meta['week_ending'])) {
                if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $rawText, $m)) {
                    $meta['week_ending'] = $m[1];
                } elseif (preg_match('/\b(\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})\b/', $rawText, $m)) {
                    $meta['week_ending'] = $m[1];
                }
            }

            // Validate and Clean Class Name
            $class_name = trim($meta['class']);
            if (empty($class_name)) {
                $class_name = mb_substr($rawText, 0, 1000);
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
                    $meta['class'] = $c_res->fetch_assoc()['name'];
                } else {
                    $short_c = mb_substr($class_name, 0, 30) . (mb_strlen($class_name) > 30 ? '...' : '');
                    throw new Exception("Invalid Class Name detected in document: '" . htmlspecialchars($short_c) . "'. Please check your document and ensure it exactly matches an official class name (e.g. Basic 1).");
                }
            } else {
                throw new Exception("Missing Class Name in document. Please ensure 'Class:' is clearly labeled.");
            }

            // Validate and Clean Subject Name
            $subject_name = trim($meta['subject']);
            if (empty($subject_name)) {
                $subject_name = mb_substr($rawText, 0, 1000);
            }
            if (!empty($subject_name)) {
                $s_res = $conn->query("SELECT name FROM subjects WHERE name LIKE '%" . $conn->real_escape_string($subject_name) . "%' OR '" . $conn->real_escape_string($subject_name) . "' LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
                if ($s_res && $s_res->num_rows > 0) {
                    $meta['subject'] = $s_res->fetch_assoc()['name'];
                } else {
                    $short_s = mb_substr($subject_name, 0, 30) . (mb_strlen($subject_name) > 30 ? '...' : '');
                    throw new Exception("Invalid Subject Name detected in document: '" . htmlspecialchars($short_s) . "'. Please check your document and ensure it exactly matches an official subject name.");
                }
            } else {
                throw new Exception("Missing Subject Name in document. Please ensure 'Subject:' is clearly labeled.");
            }

            if (empty($meta['sub_strand'])) {
                $meta['sub_strand'] = 'General';
            }
            if (empty($meta['week_num'])) {
                $meta['week_num'] = 1;
            }

            // Save file
            $upload_dir = '../../uploads/lesson_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $unique_name = $uid . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._\-]/', '_', $file['name']);
            $dest_path = $upload_dir . $unique_name;
            if (!move_uploaded_file($tmpPath, $dest_path)) {
                throw new Exception("Failed to save uploaded file on the server.");
            }

            // Save to DB with 'pending' status
            $last_id = saveAsDraft($conn, $uid, $meta, $current_term, $current_year, 'pending', $unique_name);
            if ($last_id) {
                redirect('lesson_portfolio', 'success', "Successfully uploaded lesson note as direct attachment!");
            } else {
                throw new Exception("Failed to save the lesson note details to the database.");
            }
        } catch (Exception $e) {
            header("Location: lesson_plans?msg=" . urlencode($e->getMessage()) . "&type=error");
            exit;
        }
    }

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
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                try {
                    $ocr = new \thiagoalessio\TesseractOCR\TesseractOCR($tmpPath);
                    $rawText = $ocr->run();
                } catch (Exception $e) {
                    throw new Exception("OCR Failed. Please ensure Tesseract OCR is installed on the server. Error: " . $e->getMessage());
                }
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
                    $reader = ($ext === 'docx') ? 'Word2007' : (($ext === 'rtf') ? 'RTF' : (($ext === 'doc') ? 'MsDoc' : 'HTML'));
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
                    // Manual extraction fallback for binary .doc files
                    $rawContent = file_get_contents($tmpPath);
                    // strip_tags is extremely dangerous on binary as it treats random < as html tags and deletes all text until >
                    // We extract chunks of printable text (words and punctuation) that are at least 3 characters long
                    if (preg_match_all('/[a-zA-Z0-9\s,\.\-\(\):;!?\'"]{3,}/', $rawContent, $matches)) {
                        $rawText = implode("\n", $matches[0]);
                    }
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
                if ($ext === 'pdf') {
                    throw new Exception("Could not extract any text from the PDF. If this is a scanned document or a photo converted to PDF, the system cannot read it because it does not have OCR capabilities. Please upload the original Word document, a PDF saved directly from Word, or use the Paste Tool.");
                } else {
                    throw new Exception("Could not extract any text from the document. Please ensure it is not a scanned image or an empty file.");
                }
            }

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
            $rawText = preg_replace('/(\s+)(?=(?:' . $matchesRegex . '):)/i', "\n", $rawText);

            $lines = explode("\n", $rawText);
            
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

            // Enforce at least one valid lesson plan parsed
            if (empty($allData)) {
                throw new Exception("Invalid Document Format. We couldn't recognize standard headings like 'Subject:', 'Class:', 'Topic:', or 'Phase 1:'. Please add standard labels to your text or use the Paste Tool.");
            }
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
            if (empty($class_name)) {
                // Fallback: If class is missing, scan the first 1000 characters of the document
                $class_name = mb_substr(implode(" ", $data), 0, 1000);
            }

            if (!empty($class_name)) {
                // Map word numbers to digits (e.g. "Six" to "6")
                $wordToNum = [
                    'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 
                    'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9'
                ];
                foreach ($wordToNum as $word => $num) {
                    $class_name = preg_replace('/\b' . $word . '\b/i', $num, $class_name);
                }

                // Map short forms and extract from noisy strings (e.g. "B6.1.1" -> Basic 6)
                if (preg_match('/(?:Basic|Class|B)\s*([1-9])/i', $class_name, $matches)) {
                    $class_name = 'Basic ' . $matches[1];
                } elseif (preg_match('/(?:KG)\s*([1-2])/i', $class_name, $matches)) {
                    $class_name = 'KG ' . $matches[1];
                } elseif (preg_match('/(?:Nursery|N)\s*([1-2])/i', $class_name, $matches)) {
                    $class_name = 'Nursery ' . $matches[1];
                } elseif (preg_match('/^([1-9])$/', trim($class_name), $matches)) {
                    // If it's just a digit like "6", default to Basic 6
                    $class_name = 'Basic ' . $matches[1];
                }
                
                $c_res = $conn->query("SELECT name FROM classes WHERE name LIKE '%" . $conn->real_escape_string($class_name) . "%' OR '" . $conn->real_escape_string($class_name) . "' LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
                if ($c_res && $c_res->num_rows > 0) {
                    $data['class'] = $c_res->fetch_assoc()['name']; // Use exact official name
                } else {
                    // Truncate to first 30 chars for error message readability
                    $short_c = mb_substr($class_name, 0, 30) . (mb_strlen($class_name) > 30 ? '...' : '');
                    throw new Exception("Invalid Class Name detected in document: '" . htmlspecialchars($short_c) . "'. Please check your document and ensure it exactly matches an official class name (e.g. Basic 1).");
                }
            } else {
                throw new Exception("Missing Class Name in document. Please ensure 'Class:' is clearly labeled.");
            }
            
            $subject_name = trim($data['subject'] ?? '');
            if (empty($subject_name)) {
                // Fallback: If subject is missing, scan the first 1000 characters of the document
                $subject_name = mb_substr(implode(" ", $data), 0, 1000);
            }

            if (!empty($subject_name)) {
                $s_res = $conn->query("SELECT name FROM subjects WHERE name LIKE '%" . $conn->real_escape_string($subject_name) . "%' OR '" . $conn->real_escape_string($subject_name) . "' LIKE CONCAT('%', name, '%') ORDER BY LENGTH(name) DESC LIMIT 1");
                if ($s_res && $s_res->num_rows > 0) {
                    $data['subject'] = $s_res->fetch_assoc()['name']; // Use exact official name
                } else {
                    // Truncate for readability
                    $short_s = mb_substr($subject_name, 0, 30) . (mb_strlen($subject_name) > 30 ? '...' : '');
                    throw new Exception("Invalid Subject Name detected in document: '" . htmlspecialchars($short_s) . "'. Please check your document and ensure it exactly matches an official subject name.");
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

function saveAsDraft($conn, $uid, $d, $term, $year, $status = 'draft', $attachment = null) {
    // Safety Truncations to prevent fatal DB crashes
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

    // Date Safe Parsing
    $parsed_date = strtotime($d['week_ending'] ?? '');
    $c_week_ending = $parsed_date ? date('Y-m-d', $parsed_date) : null;

    $c_day = substr($d['day'] ?? '', 0, 20);
    // Safe validation of day (must start with standard weekday name, otherwise clear it to default to date-based resolver)
    if (!empty($c_day)) {
        if (!preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)/i', $c_day)) {
            $c_day = '';
        } else {
            // Normalize to proper day format (e.g. Monday)
            preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday)/i', $c_day, $day_matches);
            $c_day = ucfirst(strtolower($day_matches[1]));
        }
    }
    
    if (empty($c_day) && $parsed_date) {
        $c_day = date('l', $parsed_date);
    }

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
             phase1_duration, phase2_duration, phase3_duration, status, attachment) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $obj = ''; 
    
    $stmt->bind_param(
        "isiisssssssisssssssssssssssssssssss",
        $uid, $c_class, $subject_id, $week_num, $topic, $obj,
        $c_week_ending, $c_day, $c_dur, $c_strand, $c_substrand, $c_csize,
        $d['content_standard'], $d['indicator'], $c_lesnum, $d['perf_ind'], $d['core_comp'],
        $d['refs'], $d['tlm'], $d['new_words'], $d['s_act'], $d['s_res'],
        $d['l_act'], $d['l_res'], $d['l_ass'],
        $d['r_act'], $d['r_res'], $d['homework'], $term, $year,
        $c_s_dur, $c_l_dur, $c_r_dur, $status, $attachment
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
