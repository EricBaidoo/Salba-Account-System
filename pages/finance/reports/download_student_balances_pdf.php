<?php
require_once '../../../vendor/autoload.php';
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

$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);
$school_name    = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '');

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

$total_students = count($student_balances);
$sum_fees = array_sum(array_column($student_balances, 'total_fees'));
$sum_paid = array_sum(array_column($student_balances, 'total_payments'));
$sum_due  = array_sum(array_column($student_balances, 'net_balance'));

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 0.5625rem; margin: 0; color: #1e293b; }
    .header { text-align: center; border-bottom: 0.125rem solid #1e293b; padding-bottom: 0.75rem; margin-bottom: 1rem; }
    .school-name { font-size: 1rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.0625rem; }
    .school-address { font-size: 0.5rem; color: #64748b; margin-top: 0.125rem; }
    .report-title { font-size: 0.6875rem; font-weight: 900; text-align: center; margin: 0.625rem 0 0.25rem; text-transform: uppercase; letter-spacing: 0.125rem; }
    .meta { text-align: center; font-size: 0.5rem; color: #64748b; margin-bottom: 0.875rem; }
    table.stats-table { width: 100%; border-collapse: collapse; margin-bottom: 0.875rem; }
    table.stats-table td { width: 25%; text-align: center; padding: 0.5rem; border: 0.0625rem solid #e2e8f0; }
    .stat-label { font-size: 0.4375rem; font-weight: 900; text-transform: uppercase; color: #94a3b8; }
    .stat-value { font-size: 0.8125rem; font-weight: 900; margin-top: 0.125rem; }
    table.ledger { width: 100%; border-collapse: collapse; }
    table.ledger th { background: #f1f5f9; font-size: 0.4375rem; font-weight: 900; text-transform: uppercase; padding: 0.4375rem 0.3125rem; text-align: left; border-bottom: 0.125rem solid #cbd5e1; }
    table.ledger td { padding: 0.4375rem 0.3125rem; font-size: 0.5rem; border-bottom: 0.0625rem solid #f1f5f9; vertical-align: middle; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .badge { display: inline-block; padding: 0.0625rem 0.3125rem; border-radius: 0.1875rem; font-size: 0.4375rem; font-weight: 900; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-emerald { background: #d1fae5; color: #065f46; }
    .badge-slate { background: #f1f5f9; color: #64748b; }
    .text-rose { color: #e11d48; }
    .text-emerald { color: #059669; }
    .tfoot-row td { font-weight: 900; background: #f8fafc; border-top: 0.125rem solid #cbd5e1; }
    .bar-wrap { width: 3.125rem; height: 0.3125rem; background: #e2e8f0; border-radius: 0.1875rem; display: inline-block; vertical-align: middle; }
    .bar-fill { height: 0.3125rem; border-radius: 0.1875rem; }
    .inactive-tag { font-size: 0.375rem; background: #f1f5f9; color: #94a3b8; padding: 0.0625rem 0.25rem; border-radius: 0.1875rem; margin-left: 0.1875rem; }
    .footer { margin-top: 1.25rem; text-align: center; font-size: 0.4375rem; font-weight: 900; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.125rem; }
</style>
</head>
<body>
    <div class="header">
        <div class="school-name"><?= htmlspecialchars($school_name) ?></div>
        <?php if($school_address): ?><div class="school-address"><?= htmlspecialchars($school_address) ?></div><?php endif; ?>
    </div>
    <div class="report-title">Student Balances Report</div>
    <div class="meta">
        Semester: <strong><?= htmlspecialchars($selected_term) ?></strong> &nbsp;&middot;&nbsp;
        Year: <strong><?= htmlspecialchars($display_academic_year) ?></strong> &nbsp;&middot;&nbsp;
        Class: <strong><?= $class_filter !== 'all' ? htmlspecialchars($class_filter) : 'All Classes' ?></strong> &nbsp;&middot;&nbsp;
        Status: <strong><?= ucfirst($status_filter) ?></strong> &nbsp;&middot;&nbsp;
        Generated: <strong><?= date('M j, Y H:i') ?></strong>
    </div>

    <table class="stats-table">
        <tr>
            <td><div class="stat-label">Total Students</div><div class="stat-value"><?= $total_students ?></div></td>
            <td><div class="stat-label">Total Fees</div><div class="stat-value">&#8373;<?= number_format($sum_fees, 2) ?></div></td>
            <td><div class="stat-label">Total Paid</div><div class="stat-value">&#8373;<?= number_format($sum_paid, 2) ?></div></td>
            <td><div class="stat-label">Outstanding</div><div class="stat-value text-rose">&#8373;<?= number_format($sum_due, 2) ?></div></td>
        </tr>
    </table>

    <table class="ledger">
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Class</th>
                <th class="text-right">Total Fees (&#8373;)</th>
                <th class="text-right">Total Paid (&#8373;)</th>
                <th class="text-right">Balance (&#8373;)</th>
                <th class="text-center">% Paid</th>
                <th class="text-center">Pending</th>
                <th class="text-center">Paid Fees</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1; $grand_fees = 0; $grand_paid_t = 0; $grand_bal = 0;
            foreach ($student_balances as $s):
                $outstanding = max(0, (float)($s['net_balance'] ?? 0));
                $tf = (float)($s['total_fees'] ?? 0);
                $tp = (float)($s['total_payments'] ?? 0);
                $percent = round($s['paid_percent']);
                $pending_cnt = intval($s['pending_assignments'] ?? 0);
                $paid_cnt    = intval($s['paid_assignments']    ?? 0);
                $is_inactive = ($s['student_status'] ?? '') === 'inactive';
                $bar_color   = $percent >= 100 ? '#10b981' : ($percent >= 50 ? '#6366f1' : '#f43f5e');
                $grand_fees += $tf; $grand_paid_t += $tp; $grand_bal += $outstanding;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td>
                    <?= htmlspecialchars($s['student_name']) ?>
                    <?php if($is_inactive): ?><span class="inactive-tag">Inactive</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($s['class']) ?></td>
                <td class="text-right"><?= number_format($tf, 2) ?></td>
                <td class="text-right text-emerald"><?= number_format($tp, 2) ?></td>
                <td class="text-right <?= $outstanding > 0 ? 'text-rose' : 'text-emerald' ?>">
                    <?= $outstanding > 0 ? number_format($outstanding, 2) : 'Paid Up' ?>
                </td>
                <td class="text-center">
                    <div class="bar-wrap"><div class="bar-fill" style="width:<?= $percent ?>%;background:<?= $bar_color ?>;"></div></div>
                    <span style="margin-left:0.1875rem;"><?= $percent ?>%</span>
                </td>
                <td class="text-center"><span class="badge <?= $pending_cnt > 0 ? 'badge-amber' : 'badge-slate' ?>"><?= $pending_cnt ?></span></td>
                <td class="text-center"><span class="badge <?= $paid_cnt > 0 ? 'badge-emerald' : 'badge-slate' ?>"><?= $paid_cnt ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="tfoot-row">
                <td colspan="3">TOTALS (<?= $total_students ?> students)</td>
                <td class="text-right">&#8373;<?= number_format($grand_fees, 2) ?></td>
                <td class="text-right text-emerald">&#8373;<?= number_format($grand_paid_t, 2) ?></td>
                <td class="text-right text-rose">&#8373;<?= number_format($grand_bal, 2) ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <?= htmlspecialchars($school_name) ?> &middot; Student Balance Ledger &middot; <?= date('Y') ?>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4-L',
    'margin_left'   => 10,
    'margin_right'  => 10,
    'margin_top'    => 10,
    'margin_bottom' => 12,
]);
$mpdf->WriteHTML($html);
$mpdf->Output('Student_Balances_' . date('Y-m-d') . '.pdf', 'D');
