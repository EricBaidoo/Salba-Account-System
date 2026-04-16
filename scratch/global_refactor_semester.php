<?php
$root = 'c:/xampp/htdocs/ACCOUNTING';
$exclude = ['vendor', '.git', 'node_modules', 'scratch'];

function replaceInDir($dir, $exclude) {
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it);
    
    foreach ($files as $file) {
        $path = $file->getRealPath();
        $relativePath = str_replace($dir . DIRECTORY_SEPARATOR, '', $path);
        
        $skip = false;
        foreach ($exclude as $ex) {
            if (strpos($relativePath, $ex) === 0 || strpos($relativePath, DIRECTORY_SEPARATOR . $ex) !== false) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) continue;
        if ($file->isDir()) continue;
        
        $content = file_get_contents($path);
        $original = $content;
        
        // Terminology Replacements
        $content = str_replace('First Term', 'First Semester', $content);
        $content = str_replace('Second Term', 'Second Semester', $content);
        $content = str_replace('Third Term', 'Third Semester', $content);
        
        // Case-sensitive replacements
        // Note: We need to be careful with "term" as it might be part of other words like "determined"
        // But in most cases in this codebase, standalone "term" refers to academic term.
        // We'll use word boundaries where possible or just direct replace if confident.
        
        $content = str_replace('Current Active Term', 'Current Active Semester', $content);
        $content = str_replace('Weeks per Term', 'Weeks per Semester', $content);
        
        // Specific SQL patterns
        $content = str_replace("term", "semester", $content);
        $content = str_replace("Term", "Semester", $content);
        
        // Revert some accidentally broken words? 
        // "Determined" -> "Desemesterined" (Problematic)
        // Let's use preg_replace with word boundaries for lowercase semester
        
        $content = preg_replace('/\bterm\b/', 'semester', $original);
        $content = preg_replace('/\bTerm\b/', 'Semester', $content);
        
        // Specific labels
        $content = str_replace('First Term', 'First Semester', $content);
        $content = str_replace('Second Term', 'Second Semester', $content);
        $content = str_replace('Third Term', 'Third Semester', $content);
        
        // Functions
        $content = str_replace('getCurrentTerm', 'getCurrentSemester', $content);
        $content = str_replace('getAvailableTerms', 'getAvailableSemesters', $content);
        
        if ($content !== $original) {
            file_put_contents($path, $content);
            echo "Updated: $relativePath\n";
        }
    }
}

replaceInDir($root, $exclude);
echo "Refactoring Complete.\n";
?>
