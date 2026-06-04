<?php
session_start();
$_SESSION['user_id'] = 7; // The teacher's ID
$_SESSION['role'] = 'staff'; // Or whatever it is

$_GET['edit'] = 45;

// Let's just capture the output
ob_start();
include 'pages/teacher/lesson_plans.php';
$output = ob_get_clean();

// Check if "Fractions" is in the output as a value!
if (strpos($output, 'value="Fractions"') !== false) {
    echo "SUCCESS: 'Fractions' found in value attribute!\n";
} else {
    echo "ERROR: 'Fractions' not found in value attribute!\n";
}

if (strpos($output, 'value="60 mins"') !== false) {
    echo "SUCCESS: '60 mins' found in value attribute!\n";
} else {
    echo "ERROR: '60 mins' not found in value attribute!\n";
}

// Print the duration input specifically
preg_match('/<input type="text" name="duration" [^>]*>/', $output, $matches);
if ($matches) echo "Duration input: " . $matches[0] . "\n";

preg_match('/<input type="text" name="topic"[^>]*>/', $output, $matches);
if ($matches) echo "Topic input: " . $matches[0] . "\n";
