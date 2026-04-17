<?php
$dir = __DIR__;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$filesToModify = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getBasename() !== 'refactor_terms.php') {
        $filesToModify[] = $file->getPathname();
    }
}

$replacements = [
    'term_budget' => 'semester_budget',
    'term_invoice' => 'semester_invoice',
    'term_bills' => 'semester_bills',
    "['term']" => "['semester']",
    '["term"]' => '["semester"]',
    '$_GET[\'term\']' => '$_GET[\'semester\']',
    '$_POST[\'term\']' => '$_POST[\'semester\']',
    'sf.term' => 'sf.semester',
    "WHERE term " => "WHERE semester ",
    "AND term " => "AND semester ",
    "term = ?" => "semester = ?",
    "term = '" => "semester = '",
    '$term ' => '$semester ',
    '$term=' => '$semester=',
    '$term;' => '$semester;',
    '$term)' => '$semester)',
    '$term,' => '$semester,',
];

foreach ($filesToModify as $path) {
    $content = file_get_contents($path);
    $original = $content;
    
    foreach ($replacements as $search => $replace) {
        // use regex if it contains backslash for variables
        if (strpos($search, '\\') !== false) {
             $content = preg_replace('/' . $search . '/', stripslashes($replace), $content);
        } else {
             $content = str_replace($search, $replace, $content);
        }
    }
    
    // Also capital Semester Budget
    $content = str_replace('Semester Budget', 'Semester Budget', $content);
    $content = str_replace('Semester Invoice', 'Semester Bill', $content);
    $content = str_replace('Semester Bill', 'Semester Bill', $content);
    
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: " . basename($path) . "\n";
    }
}
echo "Done.";
