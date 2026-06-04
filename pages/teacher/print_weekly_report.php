<?php
session_start();
require_once '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login'); exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) die("Invalid Report ID.");

$p = $conn->query("
    SELECT l.*, COALESCE(sp.full_name, u.username) as teacher_full_name
    FROM weekly_reports l 
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE l.id = $id LIMIT 1
")->fetch_assoc();

if (!$p) die("Report not found.");

if ($_SESSION['role'] === 'facilitator' && $_SESSION['user_id'] != $p['teacher_id']) {
    die("Access denied.");
}

$school_name    = getSystemSetting($conn, 'school_name', '');
$school_address = getSystemSetting($conn, 'school_address', '');
$school_phone   = getSystemSetting($conn, 'school_phone', '');
$school_email   = getSystemSetting($conn, 'school_email', '');

$v = fn($k) => htmlspecialchars($p[$k] ?? '-');
$n = fn($k) => nl2br(htmlspecialchars($p[$k] ?? '-'));

$topics_covered = $p['topics_covered'] ?? '';
$assessments_conducted = $p['assessments_conducted'] ?? '';
$overall_performance = $p['overall_performance'] ?? '';
$struggling_students = $p['struggling_students'] ?? '';
$general_behavior = $p['general_behavior'] ?? '';
$discipline_issues = $p['discipline_issues'] ?? '';
$attendance_concerns = $p['attendance_concerns'] ?? '';
$parents_contacted = $p['parents_contacted'] ?? '';
$challenges_faced = $p['challenges_faced'] ?? '';
$support_required = explode(',', $p['support_required'] ?? '');
$next_week_focus = $p['next_week_focus'] ?? '';

if (isset($_GET['view']) && $_GET['view'] == 'html') {
    $render_type = 'html';
} else {
    $render_type = 'pdf';
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Report - <?= $v('class_name') ?></title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; line-height: 1.4; color: #000; margin: 0; padding: 0; }
        .print-container { width: 100%; border: none; }
        
        .school-header { text-align: center; margin-bottom: 25px; }
        .school-name { font-size: 22px; font-weight: 800; color: #1e293b; margin: 10px 0 0; line-height: 1.2; }
        .school-info { font-size: 12px; color: #475569; margin: 5px 0 0; font-weight: normal; }
        .header-title { text-align: center; text-transform: uppercase; margin-bottom: 20px; }
        .header-title h1 { margin: 0; font-size: 18px; text-decoration: underline; font-weight: bold; }
        .header-title h2 { margin: 3px 0 0; font-size: 13px; letter-spacing: 1px; }

        .box-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 20px; }
        .box-table th { border: 1px solid #000; padding: 8px 10px; background-color: #f8fafc; font-weight: bold; text-align: left; }
        .box-table td { border: 1px solid #000; padding: 8px 10px; vertical-align: top; }
        
        .label { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-right: 5px; color: #334155; }
        .content { font-weight: normal; }

        h3 { font-size: 13px; font-weight: 800; text-transform: uppercase; margin: 20px 0 8px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; color: #0f172a; }

        .footer { margin-top: 50px; width: 100%; page-break-inside: avoid; }
        .footer-table { width: 100%; }
        .footer-table td { border: none; padding-top: 10px; width: 50%; vertical-align: top; }
        .sig-line { border-top: 1px solid #000; width: 80%; display: block; margin-top: 30px; padding-top: 5px; text-align: center; font-weight: bold; }
    </style>
</head>
<body style="<?= ($render_type == 'html') ? 'background-color: #f1f5f9; padding-bottom: 60px;' : '' ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if ($render_type == 'html'): ?>
        <div class="web-only-nav" style="background: #f8fafc; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;">
            <div style="font-weight: bold; color: #1e293b; font-size: 14px;">Structured Web Preview</div>
            <div style="display: flex; gap: 10px;">
                <a href="?id=<?= $id ?>" class="btn-action" style="background: #0d9488; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
                <button onclick="window.close()" class="btn-action" style="background: #64748b; color: #fff; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 12px;">
                    Close Preview
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="print-container" style="<?= ($render_type == 'html') ? 'max-width: 950px; margin: 40px auto; padding: 40px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-radius: 12px;' : '' ?>">
        <div class="school-header">
            <?php 
                $logo_path = '../../assets/img/salba_logo.jpg';
                if (file_exists($logo_path)): 
            ?>
                <img src="<?= $logo_path ?>" style="width: 120px; height: auto; display: block; margin: 0 auto;">
            <?php endif; ?>
            <div class="school-name"><?= strtoupper($school_name) ?></div>
        </div>

        <div class="header-title">
            <h2 style="font-size: 14px; margin-bottom: 5px;">TERM <?= strtoupper($v('academic_term')) ?> (<?= $v('academic_year') ?>)</h2>
            <h1>WEEKLY PERFORMANCE REPORT</h1>
            <h2>WEEK <?= $v('week_number') ?> ENDING <?= $p['week_ending'] ? date('jS M, Y', strtotime($p['week_ending'])) : '-' ?></h2>
        </div>

        <table class="box-table">
            <tr>
                <td style="width: 50%;"><span class="label">Teacher:</span><br><span class="content" style="font-size: 14px; font-weight: bold;"><?= $v('teacher_full_name') ?></span></td>
                <td style="width: 50%;"><span class="label">Class:</span><br><span class="content" style="font-size: 14px; font-weight: bold;"><?= $v('class_name') ?></span></td>
            </tr>
        </table>

        <h3>1. Academic Coverage & Performance</h3>
        <table class="box-table">
            <tr>
                <th colspan="2">Topics Covered (Summary)</th>
            </tr>
            <tr>
                <td colspan="2"><?= nl2br(htmlspecialchars($topics_covered)) ?></td>
            </tr>
            <tr>
                <th colspan="2">Assessments Conducted & General Performance</th>
            </tr>
            <tr>
                <td colspan="2"><?= nl2br(htmlspecialchars($assessments_conducted)) ?></td>
            </tr>
            <tr>
                <td style="width: 50%"><span class="label">Overall Class Performance:</span><br><span style="font-size: 13px; font-weight: bold;"><?= htmlspecialchars($overall_performance) ?></span></td>
                <td style="width: 50%"><span class="label">Struggling Students & Intervention:</span><br><?= nl2br(htmlspecialchars($struggling_students)) ?></td>
            </tr>
        </table>

        <h3>2. Classroom Management & Behavior</h3>
        <table class="box-table">
            <tr>
                <td colspan="2"><span class="label">General Class Behavior:</span><br><?= nl2br(htmlspecialchars($general_behavior)) ?></td>
            </tr>
            <tr>
                <td style="width: 50%"><span class="label">Discipline Issues & Actions Taken:</span><br><?= nl2br(htmlspecialchars($discipline_issues)) ?></td>
                <td style="width: 50%"><span class="label">Attendance Concerns:</span><br><?= nl2br(htmlspecialchars($attendance_concerns)) ?></td>
            </tr>
        </table>

        <h3>3. Parent Engagement</h3>
        <table class="box-table">
            <tr>
                <td><span class="label">Parents Contacted This Week (Who and Why?):</span><br><?= nl2br(htmlspecialchars($parents_contacted)) ?></td>
            </tr>
        </table>

        <h3>4. Teacher's Challenges & Needs</h3>
        <table class="box-table">
            <tr>
                <td colspan="2"><span class="label">Challenges Faced:</span><br><?= nl2br(htmlspecialchars($challenges_faced)) ?></td>
            </tr>
            <tr>
                <td style="width: 50%"><span class="label">Support / Resources Required:</span><br><?= htmlspecialchars(implode(', ', array_filter(array_map('trim', $support_req)))) ?></td>
                <td style="width: 50%"><span class="label">Focus For Next Week:</span><br><?= nl2br(htmlspecialchars($next_week_focus)) ?></td>
            </tr>
        </table>
        
        <?php if(!empty($p['supervisor_comments'])): ?>
        <table class="box-table">
            <tr>
                <td style="background-color: #fef2f2; border-color: #fca5a5;">
                    <span class="label" style="color: #dc2626;">Supervisor Remarks:</span><br>
                    <span style="color: #991b1b; font-weight: bold; font-style: italic;">"<?= $n('supervisor_comments') ?>"</span>
                </td>
            </tr>
        </table>
        <?php endif; ?>

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td><div class="sig-line">Class Teacher's Signature</div></td>
                    <td><div class="sig-line" style="width: 100%;">Principal's / Headteacher's / Supervisor's Signature</div></td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
<?php
if ($render_type == 'pdf') {
    $html = ob_get_clean();
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'default_font' => 'Helvetica'
    ]);
    $mpdf->SetTitle('Weekly Report - ' . $p['class_name']);
    $mpdf->WriteHTML($html);
    $filename = 'Weekly_Report_Week' . $p['week_number'] . '_' . str_replace(' ', '_', $p['class_name']) . '.pdf';
    $mpdf->Output($filename, 'I');
}
?>
