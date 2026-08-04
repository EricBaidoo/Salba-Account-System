<?php
ob_start();
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'data_entry'])) {
    header('Location: ../dashboard.php'); exit;
}

$selected_class = $_GET['class'] ?? '';
$selected_year  = $_GET['academic_year'] ?? '';
$school_name    = getSystemSetting($conn, 'school_name', 'School');
$school_logo    = getSystemSetting($conn, 'school_logo', '');

// Printout settings (editable from settings.php)
$print_title       = getSystemSetting($conn, 'stationery_print_title',       'STATIONERY LIST');
$print_instruction = getSystemSetting($conn, 'stationery_print_instruction', 'Dear Parent / Guardian, kindly ensure your child/ward reports with the items listed below. All items should be labelled with the student\'s name. Thank you for your cooperation.');
$print_footer_1    = getSystemSetting($conn, 'stationery_print_footer_1',    'Items must be brought on or before the first week of the term.');
$print_footer_2    = getSystemSetting($conn, 'stationery_print_footer_2',    'All items should be neatly labelled with your child\'s full name and class.');
$print_footer_3    = getSystemSetting($conn, 'stationery_print_footer_3',    'For inquiries, please contact the class teacher or school administration.');

$logo_file_path = $school_logo
    ? realpath(__DIR__ . '/../../../' . ltrim($school_logo, '/'))
    : realpath(__DIR__ . '/../../../assets/img/salba_logo.jpg');
$logo_path = $logo_file_path ?: '';

if (!$selected_class || !$selected_year) {
    header('Location: manage.php'); exit;
}

$sc = $conn->real_escape_string($selected_class);
$sy = $conn->real_escape_string($selected_year);

// Fetch Items
$items = [];
$ir = $conn->query("
    SELECT si.name as item_name, sa.quantity, sa.price, si.description as notes
    FROM stationery_assignments sa
    JOIN stationery_items si ON sa.item_id = si.id
    WHERE sa.class='$sc' AND sa.academic_year='$sy'
    ORDER BY sa.sort_order ASC, sa.id ASC
");
while ($i = $ir->fetch_assoc()) $items[] = $i;

if (empty($items)) {
    header('Location: manage.php?class='.urlencode($selected_class).'&academic_year='.urlencode($selected_year));
    exit;
}

// Fetch Students in this class
$students = [];
$sr = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
    FROM students 
    WHERE status='active' AND class='$sc' 
    ORDER BY first_name, last_name ASC
");
if ($sr) {
    while ($s = $sr->fetch_assoc()) $students[] = $s;
}

// If no students are found, we can at least print one generic sheet
if (empty($students)) {
    $students[] = ['full_name' => ''];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stationery List</title>
</head>
<body>
<?php foreach ($students as $index => $student): ?>
    <?php if ($index > 0): ?>
        <pagebreak />
    <?php endif; ?>
    <div class="document-wrapper">
        
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php if ($logo_path): ?>
                        <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" class="school-logo">
                    <?php endif; ?>
                </td>
                <td class="school-info-cell">
                    <h1 class="school-name serif"><?= htmlspecialchars($school_name) ?></h1>
                    <p class="school-motto">Official Stationery Requirements</p>
                </td>
            </tr>
        </table>

        <!-- Divider -->
        <div class="divider"></div>

        <!-- Title -->
        <h2 class="doc-title serif"><?= htmlspecialchars($print_title) ?></h2>

        <!-- Meta Grid -->
        <table class="meta-table">
            <tr>
                <?php if (!empty($student['full_name'])): ?>
                <td>
                    <div class="meta-box">
                        <span class="meta-label">Student Name</span>
                        <span class="meta-value"><?= strtoupper(htmlspecialchars($student['full_name'])) ?></span>
                    </div>
                </td>
                <td style="width: 2%;"></td>
                <?php endif; ?>
                
                <td>
                    <div class="meta-box">
                        <span class="meta-label">Class Level</span>
                        <span class="meta-value"><?= strtoupper(htmlspecialchars($selected_class)) ?></span>
                    </div>
                </td>
                <td style="width: 2%;"></td>
                
                <td>
                    <div class="meta-box">
                        <span class="meta-label">Academic Year</span>
                        <span class="meta-value"><?= htmlspecialchars($selected_year) ?></span>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Instructions -->
        <?php if ($print_instruction): ?>
        <div class="instructions-box serif">
            <?= nl2br(htmlspecialchars($print_instruction)) ?>
        </div>
        <?php endif; ?>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="sn">#</th>
                    <th>Item Description</th>
                    <th class="right">Required Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): 
                    $rowClass = ($idx % 2 === 1) ? 'alt-row' : '';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="sn"><?= $idx + 1 ?></td>
                    <td class="item-name"><?= htmlspecialchars($item['item_name']) ?></td>
                    <td class="qty"><?= htmlspecialchars($item['quantity']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Footer Notes -->
        <?php if ($print_footer_1 || $print_footer_2 || $print_footer_3): ?>
        <div class="footer-notes">
            <div class="footer-notes-title">Important Notes</div>
            <ul>
                <?php if ($print_footer_1): ?><li><?= htmlspecialchars($print_footer_1) ?></li><?php endif; ?>
                <?php if ($print_footer_2): ?><li><?= htmlspecialchars($print_footer_2) ?></li><?php endif; ?>
                <?php if ($print_footer_3): ?><li><?= htmlspecialchars($print_footer_3) ?></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <table class="signature-table">
            <tr>
                <td class="signature-cell">
                    <div class="signature-line">
                        <span class="signature-label">Class Teacher</span>
                    </div>
                </td>
                <td class="signature-cell">
                    <div class="signature-line">
                        <span class="signature-label">Head of School</span>
                    </div>
                </td>
            </tr>
        </table>

    </div>
<?php endforeach; ?>
</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF via mPDF immediately
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'mPDF library is not installed. Run: composer require mpdf/mpdf';
    exit;
}

require_once $autoload;

try {
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_left' => 15,
        'margin_right' => 15,
    ]);
    $mpdf->SetTitle('Stationery List - ' . $selected_class . ' - ' . $selected_year);
    
    // Core styles for mPDF
    $mpdf->WriteHTML('
        <style>
            body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.5; }
            .serif { font-family: "Times", "Times New Roman", serif; }
            
            .header-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
            .logo-cell { width: 100px; vertical-align: middle; }
            .school-logo { width: 90px; height: auto; max-height: 90px; }
            .school-info-cell { text-align: left; padding-left: 20px; vertical-align: middle; }
            .school-name { font-size: 24pt; font-weight: bold; color: #0f172a; margin: 0 0 5px 0; text-transform: uppercase; letter-spacing: -0.02em; }
            .school-motto { font-size: 10pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; margin: 0; font-weight: bold; }
            
            .divider { border-bottom: 3px solid #4f46e5; margin-bottom: 3px; }
            
            .doc-title { text-align: center; font-size: 20pt; font-weight: bold; color: #1e293b; margin: 25px 0; text-transform: uppercase; }
            
            .meta-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
            .meta-box { background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 20px; text-align: center; border-radius: 8px; }
            .meta-label { font-size: 8pt; color: #64748b; text-transform: uppercase; font-weight: bold; letter-spacing: 0.1em; margin-bottom: 4px; display: block; }
            .meta-value { font-size: 12pt; font-weight: bold; color: #0f172a; display: block; }
            
            .instructions-box { background-color: #f1f5f9; border-left: 4px solid #4f46e5; padding: 15px 20px; margin-bottom: 30px; color: #334155; font-size: 11pt; font-style: italic; line-height: 1.6; }
            
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
            .items-table th { background-color: #1e293b; color: #ffffff; font-size: 10pt; font-weight: bold; text-transform: uppercase; padding: 10px; text-align: left; }
            .items-table th.center { text-align: center; }
            .items-table th.right { text-align: right; }
            .items-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
            .items-table tr.alt-row td { background-color: #f8fafc; }
            .items-table td.sn { width: 30px; text-align: center; font-weight: bold; color: #94a3b8; }
            .items-table td.item-name { font-weight: bold; font-size: 11pt; color: #0f172a; }
            .items-table td.qty { text-align: right; font-weight: bold; font-size: 12pt; color: #4f46e5; }
            
            .footer-notes { margin-bottom: 40px; }
            .footer-notes-title { font-size: 10pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
            .footer-notes ul { list-style: none; padding: 0; margin: 0; }
            .footer-notes li { margin-bottom: 6px; font-size: 10pt; color: #475569; }
            
            .signature-table { width: 100%; border-collapse: collapse; margin-top: 50px; }
            .signature-cell { width: 50%; text-align: center; vertical-align: bottom; }
            .signature-line { width: 70%; margin: 0 auto; border-top: 1px solid #94a3b8; padding-top: 5px; }
            .signature-label { font-size: 10pt; font-weight: bold; color: #1e293b; text-transform: uppercase; }
        </style>
    ', \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    
    $filename = 'stationery_list_' . preg_replace('/[^a-z0-9]+/i', '_', $selected_class) . '_' . preg_replace('/[^0-9_\-]/', '', $selected_year) . '.pdf';
    
    // Output PDF directly to browser (inline viewing)
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'PDF generation failed: ' . $e->getMessage();
    exit;
}
