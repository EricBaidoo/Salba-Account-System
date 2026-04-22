<?php 
include '../../../includes/auth_functions.php';
if (!is_logged_in()) { header('Location: ../../../login'); exit; }
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';

$current_term = getCurrentSemester($conn);
$default_academic_year = getAcademicYear($conn);

$selected_term          = $_GET['semester']      ?? $current_term;
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$class_filter           = $_GET['class']         ?? 'all';
$status_filter          = $_GET['status']        ?? 'active';
$owing_filter           = $_GET['owing']         ?? 'all';
$percent_filter         = $_GET['percent']       ?? 'all';

// Pre-compute arrears
{
    $where = []; $params = []; $types = '';
    if ($status_filter && $status_filter !== 'all') { $where[] = "status = ?"; $params[] = $status_filter; $types .= 's'; }
    if ($class_filter  && $class_filter  !== 'all') { $where[] = "class = ?";  $params[] = $class_filter;  $types .= 's'; }
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

$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter, $selected_term, $selected_academic_year);

if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, fn($s) => $s['net_balance'] > 0);
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, fn($s) => $s['net_balance'] == 0);
}

foreach ($student_balances as &$s) {
    $tf = (float)($s['total_fees'] ?? 0);
    $tp = (float)($s['total_payments'] ?? 0);
    $s['paid_percent'] = ($tf > 0) ? min(100, ($tp / $tf) * 100) : (($tp > 0) ? 100 : 0);
}
unset($s);

if ($percent_filter !== 'all') {
    $student_balances = array_filter($student_balances, function($st) use ($percent_filter) {
        $p = $st['paid_percent'];
        if ($percent_filter === 'below50')  return $p < 50;
        if ($percent_filter === 'below75')  return $p < 75;
        if ($percent_filter === 'below100') return $p < 100;
        return true;
    });
}

usort($student_balances, fn($a, $b) => strcmp($a['student_name'], $b['student_name']));

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="student_balances_' . date('Y-m-d_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

// Header row
fputcsv($output, [
    'Student Name',
    'Class',
    'Status',
    'Total Fees (GH₵)',
    'Total Paid (GH₵)',
    'Balance (GH₵)',
    '% Paid',
    'Pending Assignments',
    'Paid Assignments',
    'Semester',
    'Academic Year',
]);

// Data rows
foreach ($student_balances as $student) {
    $tf = (float)($student['total_fees'] ?? 0);
    $tp = (float)($student['total_payments'] ?? 0);
    $outstanding = max(0, (float)($student['net_balance'] ?? 0));
    $percent = round($student['paid_percent']);
    fputcsv($output, [
        $student['student_name'],
        $student['class'],
        ucfirst($student['student_status'] ?? 'active'),
        number_format($tf, 2, '.', ''),
        number_format($tp, 2, '.', ''),
        number_format($outstanding, 2, '.', ''),
        $percent . '%',
        intval($student['pending_assignments'] ?? 0),
        intval($student['paid_assignments']    ?? 0),
        $selected_term,
        $selected_academic_year,
    ]);
}

fclose($output);
exit;
