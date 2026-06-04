<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/weekly.*report.*\.pdf/i', RegexIterator::GET_MATCH);
foreach($files as $file) {
    echo $file[0] . "\n";
}

$files2 = new RegexIterator($ite, '/.*report.*\.pdf/i', RegexIterator::GET_MATCH);
foreach($files2 as $file) {
    echo "Report file: " . $file[0] . "\n";
}
