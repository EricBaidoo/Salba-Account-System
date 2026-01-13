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
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

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

// Sort by student name
usort($student_balances, function($a, $b) {
    return strcmp($a['student_name'], $b['student_name']);
});

// Calculate totals
$total_owing = array_sum(array_column($student_balances, 'net_balance'));
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

// Check if mPDF is available
if (!file_exists('../vendor/autoload.php')) {
    die('PDF library not found. Please ensure composer dependencies are installed.');
}

require_once '../vendor/autoload.php';

use Mpdf\Mpdf;

// Create PDF object
$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

// Build HTML content
$html = '
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0 0 5px 0; font-size: 24px; color: #2c5282; }
        .header p { margin: 2px 0; font-size: 11px; color: #666; }
        .filters { background: #f0f0f0; padding: 10px; margin-bottom: 15px; font-size: 10px; border-radius: 3px; }
        .summary { margin-bottom: 15px; font-size: 11px; }
        .summary-item { display: inline-block; margin-right: 30px; }
        .summary-label { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        thead { background-color: #2c5282; color: white; }
        th { padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; font-size: 11px; }
        td { padding: 8px 10px; border: 1px solid #ddd; font-size: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .amount { text-align: right; font-family: monospace; }
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>';

$html .= '
    <div class="header">
        <h1>' . htmlspecialchars($school_name) . '</h1>
        <p>Student Balances Report</p>
        <p>Printed on ' . date('M j, Y \a\t g:i A') . '</p>
    </div>';

// Add filter information
$html .= '<div class="filters">
    <strong>Term:</strong> ' . htmlspecialchars($selected_term) . ' | 
    <strong>Academic Year:</strong> ' . htmlspecialchars($display_academic_year);
if ($class_filter !== 'all') {
    $html .= ' | <strong>Class:</strong> ' . htmlspecialchars($class_filter);
}
$html .= ' | <strong>Status:</strong> ' . ucfirst($status_filter) . '
</div>';

// Add summary statistics
$html .= '<div class="summary">
    <div class="summary-item"><span class="summary-label">Total Students:</span> ' . count($student_balances) . '</div>
    <div class="summary-item"><span class="summary-label">Total Outstanding:</span> GH₵' . number_format($total_owing, 2) . '</div>
</div>';

// Build table
$html .= '
    <table>
        <thead>
            <tr>
                <th style="text-align: left; width: 70%;">Student Name</th>
                <th style="text-align: right; width: 30%;">Amount Owing (GH₵)</th>
            </tr>
        </thead>
        <tbody>';

foreach ($student_balances as $student) {
    $outstanding = max(0, (float)($student['total_fees'] ?? 0) - (float)($student['total_payments'] ?? 0));
    $html .= '<tr>
        <td>' . htmlspecialchars($student['student_name']) . '</td>
        <td class="amount">' . number_format($outstanding, 2) . '</td>
    </tr>';
}

$html .= '
        </tbody>
    </table>
    <div class="footer">
        <p>This is an automatically generated report. For inquiries, contact the accounting office.</p>
    </div>
</body>
</html>';

$mpdf->WriteHTML($html);

// Output PDF
$filename = 'student_balances_' . date('Y-m-d_His') . '.pdf';
$mpdf->Output($filename, 'D');
?>
