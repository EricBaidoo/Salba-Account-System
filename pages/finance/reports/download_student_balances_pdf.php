<?php 
include '../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';
include '../../includes/student_balance_functions.php';

// Get current semester and academic year from system settings
$current_term = getCurrentSemester($conn);
$default_academic_year = getAcademicYear($conn);

// Allow manual semester override via URL parameter
$selected_term = $_GET['semester'] ?? $current_term;
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

// Get all student balances for the selected semester/year
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
    <strong>Semester:</strong> ' . htmlspecialchars($selected_term) . ' | 
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
                <th class="text-left w-70">Student Name</th>
                <th class="text-right w-30">Amount Owing (GH₵)</th>
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
