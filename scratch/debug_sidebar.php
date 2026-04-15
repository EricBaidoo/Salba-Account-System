<?php
$script_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
echo "Script Dir: " . $script_dir . "\n";
echo "Pos Academics: " . strpos($script_dir, '/academics') . "\n";
echo "Current Page: " . basename($_SERVER['PHP_SELF']) . "\n";
?>
