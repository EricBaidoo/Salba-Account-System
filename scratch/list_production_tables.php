<?php
$file = 'sql/production_ready_utf8.sql';
$content = file_get_contents($file);
preg_match_all('/CREATE TABLE `([^`]+)`/', $content, $matches);
echo "Tables in production_ready_utf8.sql:\n";
print_r($matches[1]);
?>
