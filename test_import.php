<?php
$_FILES['lesson_file'] = [
    'name' => 'test_local.docx',
    'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'tmp_name' => 'test_local.docx',
    'error' => 0,
    'size' => filesize('test_local.docx')
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['user_id' => 1, 'role' => 'facilitator']; // Dummy session

// We'll just include the process_lesson_import.php directly but prevent the redirect
// Actually, let's just copy the parsing logic to test it without redirecting.
$ext = 'docx';
$tmpPath = 'test_local.docx';
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
}

$keywords = [
    'subject' => ['Subject', 'Course Title'],
    'class' => ['Class', 'Grade', 'Level'],
    'week_ending' => ['Week Ending', 'Date'],
    'day' => ['Day'],
    'duration' => ['Duration', 'Time'],
    'strand' => ['Strand', 'Main Topic'],
    'sub_strand' => ['Sub-Strand', 'Topic', 'Sub Topic'],
    'class_size' => ['Class Size', 'No. on Roll', 'Roll'],
    'content_standard' => ['Content Standard'],
    'indicator' => ['Indicator'],
    'lesson_num' => ['Lesson', 'Lesson Number'],
    'perf_ind' => ['Performance Indicator', 'Specific Objective', 'Objectives'],
    'core_comp' => ['Core Competencies', 'Competencies'],
    'refs' => ['References', 'Reference'],
    'tlm' => ['TLM', 'Teaching/Learning Materials', 'Teaching Learning Materials'],
    'new_words' => ['New Words', 'Key Words', 'Keywords'],
    's_act' => ['Starter', 'Starter Activities', 'Phase 1'],
    's_res' => ['Starter Resources'],
    'l_act' => ['Learning', 'Learning Activities', 'Main Activities', 'Phase 2'],
    'l_res' => ['Learning Resources'],
    'l_ass' => ['Assessment', 'Evaluation'],
    'r_act' => ['Reflection', 'Reflection Activities', 'Plenary', 'Phase 3'],
    'r_res' => ['Reflection Resources'],
    'homework' => ['Homework', 'Assignment', 'Remarks']
];

$rawText = str_replace("\t", "\n", $rawText);
$lines = explode("\n", $rawText);

$currentData = [];
$currentKey = null;

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

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
    foreach ($keywords as $key => $matches) {
        if ($matched) break;
        foreach ($matches as $m) {
            if (stripos($labelToTest, $m) === 0) {
                $currentKey = $key;
                if (empty($valueToSet) && strlen($labelToTest) > strlen($m)) {
                    $valueToSet = trim(substr($labelToTest, strlen($m)), ": \t-");
                }
                if (!empty($valueToSet)) {
                    $currentData[$currentKey] = empty($currentData[$currentKey]) ? $valueToSet : $currentData[$currentKey] . "\n" . $valueToSet;
                }
                $matched = true;
                break;
            }
        }
    }
    
    if (!$matched && $currentKey !== null) {
        $currentData[$currentKey] = empty($currentData[$currentKey]) ? $line : $currentData[$currentKey] . "\n" . $line;
    }
}

echo "PARSED DATA:\n";
print_r($currentData);
