<?php
$dir = __DIR__;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getPathname(), 'fix_') === false) {
        $path = $file->getPathname();
        $relativePath = str_replace(str_replace('/', '\\', $dir) . '\\', '', $path);
        
        $depth = substr_count($relativePath, '\\');
        
        if ($depth == 1) { 
            // files in subfolders, need 3 ../
            $content = file_get_contents($path);
            $new_content = preg_replace('/(require_once|require|include_once|include)\s*\(\s*[\'"]\\.\\.\\/\\.\\.\\/includes\\//i', '$1(\'../../../includes/', $content);
            $new_content = preg_replace('/(require_once|require|include_once|include)\s+[\'"]\\.\\.\\/\\.\\.\\/includes\\//i', '$1 \'../../../includes/', $new_content);

            if ($content !== $new_content) {
                file_put_contents($path, $new_content);
                echo "Fixed requires in: " . $relativePath . "\n";
            }
        }
    }
}
echo "Done.\n";
