<?php
$dirs = ['includes', 'pages', 'ajax'];
$replacements = [
    'getCurrentTerm' => 'getCurrentSemester',
    'getAvailableTerms' => 'getAvailableSemesters',
    'term_helpers.php' => 'semester_helpers.php',
    'Term:' => 'Semester:', // UI labels
    'Term' => 'Semester',   // General labels
];

function refactorDir($dir, $replacements) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = "$dir/$file";
        if (is_dir($path)) {
            refactorDir($path, $replacements);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            $newContent = $content;
            foreach ($replacements as $old => $new) {
                $newContent = str_replace($old, $new, $newContent);
            }
            if ($newContent !== $content) {
                file_put_contents($path, $newContent);
                echo "Refactored: $path\n";
            }
        }
    }
}

foreach ($dirs as $dir) {
    echo "Refactoring directory: $dir...\n";
    refactorDir($dir, $replacements);
}
echo "Refactoring complete.\n";
?>
