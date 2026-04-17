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
if (!$id) die("Invalid Lesson Plan ID.");

$p = $conn->query("
    SELECT l.*, s.name as subject_name, COALESCE(sp.full_name, u.username) as teacher_full_name
    FROM lesson_plans l 
    JOIN subjects s ON l.subject_id = s.id 
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.staff_id = sp.id
    WHERE l.id = $id LIMIT 1
")->fetch_assoc();

if (!$p) die("Lesson plan not found.");

if ($_SESSION['role'] === 'facilitator' && $_SESSION['user_id'] != $p['teacher_id']) {
    die("Access denied.");
}

$school_name    = getSystemSetting($conn, 'school_name', '');
$school_address = getSystemSetting($conn, 'school_address', '');
$school_phone   = getSystemSetting($conn, 'school_phone', '');
$school_email   = getSystemSetting($conn, 'school_email', '');

$v = fn($k) => htmlspecialchars($p[$k] ?? '-');
$n = fn($k) => nl2br(htmlspecialchars($p[$k] ?? '-'));

// If view=html is passed, show HTML (useful for debugging CSS)
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
    <title>Lesson Note - <?= $v('topic') ?></title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .print-container { width: 100%; border: none; }
        
        .school-header { text-align: center; margin-bottom: 25px; }
        .school-name { font-size: 22px; font-weight: 800; color: #1e293b; margin: 10px 0 0; line-height: 1.2; }
        .school-info { font-size: 12px; color: #475569; margin: 5px 0 0; font-weight: normal; }
        .header-title { text-align: center; text-transform: uppercase; margin-bottom: 20px; }
        .header-title h1 { margin: 0; font-size: 18px; text-decoration: underline; font-weight: bold; }
        .header-title h2 { margin: 3px 0 0; font-size: 13px; letter-spacing: 1px; }

        .box-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
        .box-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
        
        .label { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-right: 5px; }
        .content { font-weight: normal; }
        .textarea-content { display: block; margin-top: 3px; font-weight: normal; font-size: 11px; }

        .phases-table { width: 100%; border-collapse: collapse; margin-top: 15px; border: 1.5px solid #000; }
        .phases-table th { border: 1px solid #000; padding: 8px; background-color: #f5f5f5; font-weight: bold; text-transform: uppercase; font-size: 10px; text-align: center; }
        .phases-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
        
        .phase-col { width: 18%; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .activities-col { width: 57%; }
        .resources-col { width: 25%; }

        .sub-header { font-weight: bold; text-decoration: underline; margin-bottom: 3px; display: block; margin-top: 8px; }

        .footer { margin-top: 40px; width: 100%; }
        .footer-table { width: 100%; }
        .footer-table td { border: none; padding-top: 10px; width: 50%; vertical-align: top; }
        .sig-line { border-top: 1px solid #000; width: 80%; display: block; margin-top: 30px; padding-top: 5px; text-align: center; font-weight: bold; }

        .btn-debug { position: fixed; top: 10px; left: 10px; background: #333; color: #fff; padding: 5px 10px; font-size: 10px; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body style="<?= ($render_type == 'html') ? 'background-color: #f1f5f9; padding-bottom: 60px;' : '' ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if ($render_type == 'html'): ?>
        <div class="web-only-nav" style="background: #f8fafc; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;">
            <div style="font-weight: bold; color: #1e293b; font-size: 14px;">Structured Web Preview</div>
            <div style="display: flex; gap: 10px;">
                <a href="?id=<?= $id ?>" class="btn-action" style="background: #ef4444; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-file-pdf"></i> Download PDF Version
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
            <div class="school-info">
                <?= $school_address ?><br>
                Phone: <?= $school_phone ?> | Email: <?= $school_email ?>
            </div>
        </div>

        <div class="header-title">
            <h2>SEMESTER <?= strtoupper($v('semester')) ?: 'THREE' ?></h2>
            <h1>WEEKLY LESSON NOTES</h1>
            <h2>WEEK <?= $v('week_number') ?></h2>
        </div>

        <table class="box-table">
            <tr>
                <td style="width: 35%;"><span class="label">Week Ending:</span><span class="content"><?= date('jS M, Y', strtotime($p['week_ending'] ?? '')) ?></span></td>
                <td style="width: 25%;"><span class="label">DAY:</span><span class="content"><?= strtoupper($v('day_of_week')) ?></span></td>
                <td style="width: 40%;"><span class="label">Subject:</span><span class="content"><?= $v('subject_name') ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Duration:</span><span class="content"><?= $v('duration') ?></span></td>
                <td colspan="2"><span class="label">Strand:</span><span class="content"><?= $v('strand') ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Class:</span><span class="content"><?= $v('class_name') ?></span></td>
                <td><span class="label">Class Size:</span><span class="content"><?= $v('class_size') ?></span></td>
                <td><span class="label">Facilitator:</span><span class="content"><?= htmlspecialchars($v('teacher_full_name')) ?></span></td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">Sub Strand:</span><span class="content"><?= $v('sub_strand') ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Content Standard:</span><br><span class="textarea-content"><?= $n('content_standard') ?></span></td>
                <td><span class="label">Indicator:</span><br><span class="textarea-content"><?= $n('indicator') ?></span></td>
                <td><span class="label">Lesson:</span><br><span class="content"><?= $v('lesson_number') ?></span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Performance Indicator:</span><br><span class="textarea-content"><?= $n('performance_indicator') ?></span></td>
                <td><span class="label">Core Competencies:</span><br><span class="textarea-content"><?= $n('core_competencies') ?></span></td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">References:</span><span class="content"><?= $v('references_materials') ?></span></td>
            </tr>
            <tr>
                <td colspan="3"><span class="label">New words:</span><span class="content"><?= $v('new_words') ?></span></td>
            </tr>
        </table>

        <table class="phases-table">
            <thead>
                <tr>
                    <th class="phase-col">Phase/Duration</th>
                    <th class="activities-col">Learners Activities</th>
                    <th class="resources-col">Resources</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="phase-col">PHASE 1: STARTER<br><?= $v('phase1_duration') ?></td>
                    <td class="activities-col"><?= $n('starter_activities') ?></td>
                    <td class="resources-col"><?= $n('starter_resources') ?></td>
                </tr>
                <tr>
                    <td class="phase-col">PHASE 2: NEW LEARNING<br><?= $v('phase2_duration') ?></td>
                    <td class="activities-col">
                        <?= $n('learning_activities') ?>
                        <?php if(!empty($p['learning_assessment'])): ?>
                            <span class="sub-header">Assessment</span>
                            <?= $n('learning_assessment') ?>
                        <?php endif; ?>
                    </td>
                    <td class="resources-col"><?= $n('learning_resources') ?></td>
                </tr>
                <tr>
                    <td class="phase-col">PHASE 3: REFLECTION<br><?= $v('phase3_duration') ?></td>
                    <td class="activities-col">
                        <?= $n('reflection_activities') ?>
                        <?php if(!empty($p['homework'])): ?>
                            <span class="sub-header">Homework</span>
                            <?= $n('homework') ?>
                        <?php endif; ?>
                    </td>
                    <td class="resources-col"><?= $n('reflection_resources') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td><div class="sig-line">Class Teacher's Signature</div></td>
                    <td><div class="sig-line" style="width: 100%;">Principal's / Headteacher's / Supervisor's Approval</div></td>
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
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    $mpdf->SetTitle('Lesson Note - ' . $p['topic']);
    $mpdf->SetAuthor($p['teacher_full_name']);
    $mpdf->WriteHTML($html);
    $filename = 'Lesson_Note_Week' . $p['week_number'] . '_' . str_replace(' ', '_', $p['subject_name']) . '.pdf';
    $mpdf->Output($filename, 'I'); // 'I' means inline in browser
}
?>

