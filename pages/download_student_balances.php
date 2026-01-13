<?php 
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/system_settings.php';
include '../includes/student_balance_functions.php';

// Get current term and academic year from system settings
$current_term = getCurrentTerm($conn);
$default_academic_year = getAcademicYear($conn);

// Allow manual term override via URL parameter
$selected_term = $_GET['term'] ?? $current_term;
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;

// Get filter parameters
$class_filter = $_GET['class'] ?? 'all';
$status_filter = $_GET['status'] ?? 'active';
$owing_filter = $_GET['owing'] ?? 'all';

// Ensure arrears assignment exists
{
    $where = [];
    $params = [];
    $types = '';
    if ($status_filter && $status_filter !== 'all') { $where[] = "status = ?"; $params[] = $status_filter; $types .= 's'; }
    if ($class_filter && $class_filter !== 'all') { $where[] = "class = ?"; $params[] = $class_filter; $types .= 's'; }
    $sql = "SELECT id FROM students" . (empty($where) ? '' : (' WHERE ' . implode(' AND ', $where)));
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            ensureArrearsAssignment($conn, intval($row['id']), $selected_term, $selected_academic_year);
        }
    }
    $stmt->close();
}

// Get all student balances for the selected term/year
$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter, $selected_term, $selected_academic_year);

// Apply owing filter
if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] > 0;
    });
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] == 0;
    });
}

// Sort by student name by default
usort($student_balances, function($a, $b) {
    return strcmp($a['student_name'], $b['student_name']);
});

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="student_balances_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Set UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, array('Student Name', 'Amount Owing (GH₵)'));

// Write data rows
foreach ($student_balances as $student) {
    $outstanding = max(0, (float)($student['total_fees'] ?? 0) - (float)($student['total_payments'] ?? 0));
    fputcsv($output, array(
        $student['student_name'],
        number_format($outstanding, 2, '.', '')
    ));
}

fclose($output);
exit;
?>
