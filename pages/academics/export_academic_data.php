<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login'); exit;
}

$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);

// Get class-wise performance for current semester
$class_performance = $conn->prepare("
    SELECT s.class, 
           COUNT(DISTINCT g.student_id) as students_graded,
           AVG(CASE WHEN g.out_of > 0 THEN (g.marks / g.out_of) * 100 ELSE 0 END) as avg_grade_pct
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE g.semester = ? AND g.year = ?
    GROUP BY s.class
    ORDER BY s.class
");

$filename = "Academic_Performance_Data_" . date('Y_m_d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Header
fputcsv($output, ['Institutional Level (Class)', 'Active Census Graded', 'Scholastic Mean (%)', 'Academic Cycle']);

if($class_performance) {
    $class_performance->bind_param("ss", $current_term, $academic_year);
    $class_performance->execute();
    $res = $class_performance->get_result();
    while($row = $res->fetch_assoc()) {
        fputcsv($output, [
            strtoupper($row['class']),
            $row['students_graded'],
            number_format($row['avg_grade_pct'], 2),
            $current_term . ' ' . $academic_year
        ]);
    }
    $class_performance->close();
}

fclose($output);
exit;
?>
