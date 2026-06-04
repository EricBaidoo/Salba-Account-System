<?php
require 'includes/db_connect.php';

$teachers = $conn->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 3");
$teacher_ids = [];
if ($teachers) {
    while ($r = $teachers->fetch_assoc()) { $teacher_ids[] = $r['id']; }
}

// If no teachers with role 'teacher', just get any users
if (empty($teacher_ids)) {
    $teachers = $conn->query("SELECT id FROM users LIMIT 3");
    while ($r = $teachers->fetch_assoc()) { $teacher_ids[] = $r['id']; }
}

$subjects = $conn->query("SELECT id FROM subjects LIMIT 3");
$subject_ids = [];
if ($subjects) {
    while ($r = $subjects->fetch_assoc()) { $subject_ids[] = $r['id']; }
}

if (empty($teacher_ids) || empty($subject_ids)) {
    die("No teachers or subjects found.");
}

$dummies = [
    ["class" => "BASIC 4", "week" => 2, "topic" => "Advanced Patterns"],
    ["class" => "BASIC 4", "week" => 2, "topic" => "Creative Writing"],
    ["class" => "BASIC 5", "week" => 2, "topic" => "Fractions and Decimals"],
    ["class" => "BASIC 6", "week" => 3, "topic" => "History of Independence"],
    ["class" => "BASIC 1", "week" => 1, "topic" => "Alphabet and Sounds"],
    ["class" => "BASIC 2", "week" => 1, "topic" => "Addition within 100"]
];

$inserted = 0;
foreach ($dummies as $i => $d) {
    $t_id = (int) $teacher_ids[$i % count($teacher_ids)];
    $s_id = (int) $subject_ids[$i % count($subject_ids)];
    $we = date('Y-m-d');
    $c = $conn->real_escape_string($d['class']);
    $w = (int) $d['week'];
    $t = $conn->real_escape_string($d['topic']);
    
    $sql = "INSERT INTO lesson_plans (
        teacher_id, subject_id, class_name, week_number, week_ending, 
        topic, status, day_of_week, duration, class_size, strand, 
        sub_strand, content_standard, indicator, phase1_duration, 
        starter_activities, phase2_duration, learning_activities, 
        phase3_duration, reflection_activities
    ) VALUES (
        $t_id, $s_id, '$c', $w, '$we', '$t', 'pending', 'Monday', '60 mins', '35', 
        'Sample Strand', 'Sample Sub Strand', 'Sample Standard', 'Sample Indicator', 
        '10 mins', 'Dummy Intro', '40 mins', 'Dummy Main', '10 mins', 'Dummy Summary'
    )";
    
    if ($conn->query($sql)) {
        $inserted++;
    } else {
        echo "Error on row $i: " . $conn->error . "<br>";
    }
}

echo "Success! Inserted $inserted dummy lesson plans.";
?>
