<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Script started.\n";
$dir = new RecursiveDirectoryIterator('c:\xampp\htdocs\ACCOUNTING\pages\finance');
$iterator = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($files as $file) {
    $count++;
}
echo "Found $count files.\n";
?>
