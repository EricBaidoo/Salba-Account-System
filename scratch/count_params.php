<?php
$content = file_get_contents('pages/administration/staff/edit_staff.php');

// Count placeholders in the UPDATE query
if (preg_match('/UPDATE staff_profiles SET(.*?)\"/s', $content, $m)) {
    $placeholders = substr_count($m[1], '?');
    echo "Placeholders in SET: $placeholders\n";
}
if (preg_match('/WHERE id=\?\s*\"/s', $content, $m)) {
    echo "Placeholder in WHERE: 1\n";
}

// Count type characters
if (preg_match('/\"(s+i)\"/', $content, $m)) {
    echo "Type string length: " . strlen($m[1]) . "\n";
    echo "Type string: " . $m[1] . "\n";
}

// Count variables in bind_param
if (preg_match('/bind_param\((.*?)\);/s', $content, $m)) {
    // This is tricky because of the multi-line nature.
    // Let's just count '$' signs in the arguments bar the first one.
    $args = $m[1];
    $vars_count = substr_count($args, '$');
    echo "Variables starting with $: $vars_count\n";
}
