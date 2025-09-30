<?php
// Auto-fix script: Map all students' classes to the correct Level in the classes table if possible
include '../includes/db_connect.php';

$sql = "SELECT s.id, s.class, c.Level FROM students s LEFT JOIN classes c ON s.class = c.name WHERE c.Level IS NULL OR c.Level = ''";
$result = $conn->query($sql);
$fixed = 0;
$not_found = [];

while ($row = $result->fetch_assoc()) {
    $student_id = $row['id'];
    $student_class = $row['class'];
    // Try to find a class name in classes table that matches (case-insensitive)
    $find_class = $conn->prepare("SELECT name, Level FROM classes WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $find_class->bind_param("s", $student_class);
    $find_class->execute();
    $class_result = $find_class->get_result();
    if ($class_row = $class_result->fetch_assoc()) {
        // If found, update students.class to match exactly
        $update = $conn->prepare("UPDATE students SET class = ? WHERE id = ?");
        $update->bind_param("si", $class_row['name'], $student_id);
        $update->execute();
        $update->close();
        $fixed++;
    } else {
        $not_found[] = $student_id . ' (' . $student_class . ')';
    }
    $find_class->close();
}

if ($fixed > 0) {
    echo "<div style='color:green;'>Fixed $fixed student(s) with class name mismatches.</div>";
}
if (!empty($not_found)) {
    echo "<div style='color:red;'>Could not find a matching class in classes table for student IDs: " . implode(', ', $not_found) . ". Please check manually.</div>";
}
if ($fixed === 0 && empty($not_found)) {
    echo "<div style='color:green;'>No issues found. All students are mapped to a valid class Level.</div>";
}
?>
