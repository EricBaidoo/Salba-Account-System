<?php
$file = 'sql/final_full_system_reconciled.sql';
$content = file_get_contents($file);

// Strip DEFINER
$content = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', '', $content);

// Ensure there is no CREATE DATABASE or USE statements that might break online imports
$content = preg_replace('/CREATE DATABASE \/\*!\d+ IF NOT EXISTS\*\/ `[^`]+`.*?;/i', '', $content);
$content = preg_replace('/USE `[^`]+`;/i', '', $content);

file_put_contents($file, $content);
echo "Cleaned file.";
?>
