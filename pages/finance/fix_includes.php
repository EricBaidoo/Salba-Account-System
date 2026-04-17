<?php
$dir = __DIR__; // C:.../finance (depth 2)
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getBasename() !== 'fix_includes.php') {
        $path = $file->getPathname();
        $relativePath = str_replace(str_replace('/', '\\', $dir) . '\\', '', $path);
        
        // Count directory separators to determine depth relative to /finance/
        $depth = substr_count($relativePath, '\\');
        
        if ($depth == 1) { // Example: budgets\term_budget.php
            $content = file_get_contents($path);
            $new_content = str_replace("include '../../includes/", "include '../../../includes/", $content);
            $new_content = str_replace("include_once '../../includes/", "include_once '../../../includes/", $new_content);
            $new_content = str_replace("header('Location: ../pages/login.php');", "header('Location: ../../../includes/login.php');", $new_content);
            $new_content = str_replace("header('Location: ../../includes/login.php');", "header('Location: ../../../includes/login.php');", $new_content);
            
            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                echo "Fixed includes in: " . $relativePath . "\n";
            }
        }
    }
}
echo "Include fixes applied.\n";
