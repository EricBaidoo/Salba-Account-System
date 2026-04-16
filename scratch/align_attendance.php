<?php
include 'includes/db_connect.php';
include 'includes/system_settings.php';

$current_semester = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

echo "Current Active Semester: $current_semester\n";
echo "Current Academic Year: $current_year\n";

// Update all attendance records to match the current semester/year for anything marked recently
// This fixes any mismatches caused by late renaming or session updates.
$stmt = $conn->prepare("UPDATE attendance SET semester = ?, academic_year = ? WHERE attendance_date BETWEEN '2026-04-01' AND '2026-05-01'");
$stmt->bind_param("ss", $current_semester, $current_year);
$stmt->execute();

echo "Aligned " . $stmt->affected_rows . " attendance records to current academic session metadata.\n";
?>
