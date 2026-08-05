<?php
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

$download_pdf = (($_GET['download'] ?? '') === 'pdf');

$css = '
<style>
    body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; color: #333333; line-height: 1.4; background: #f1f5f9; margin: 0; padding: 20px; }
    .serif { font-family: "Times", "Times New Roman", serif; }
    
    .document-wrapper { max-width: 800px; margin: 0 auto 30px auto; background: #fff; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 8px; }
    
    .header-table { width: 100%; margin-bottom: 10px; border-collapse: collapse; border: none; }
    .header-table td { border: none; }
    .logo-cell { width: 80px; vertical-align: middle; }
    .school-logo { width: 70px; height: auto; max-height: 70px; border: none; }
    .school-info-cell { text-align: left; padding-left: 15px; vertical-align: middle; }
    .school-name { font-size: 18pt; font-weight: bold; color: #111827; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 0.5px; }
    .school-motto { font-size: 9pt; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin: 0; font-weight: normal; }
    
    .divider { border-bottom: 1.5px solid #4f46e5; margin-bottom: 20px; }
    
    .doc-title { text-align: center; font-size: 15pt; font-weight: bold; color: #111827; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; }
    
    .meta-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; border: none; }
    .meta-table td { padding: 6px 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; vertical-align: middle; width: 33.33%; }
    .meta-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; font-weight: bold; margin-bottom: 2px; }
    .meta-value { font-size: 10pt; font-weight: bold; color: #111827; }
    
    .instructions-box { background-color: #ffffff; border: 1px solid #e5e7eb; border-left: 3px solid #4f46e5; padding: 10px 15px; margin-bottom: 20px; color: #4b5563; font-size: 9.5pt; font-style: italic; line-height: 1.5; }
    
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .items-table th { background-color: #f3f4f6; color: #374151; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; padding: 8px 10px; text-align: left; border: 1px solid #e5e7eb; border-bottom: 2px solid #d1d5db; }
    .items-table th.center { text-align: center; }
    .items-table th.right { text-align: right; }
    .items-table td { padding: 8px 10px; border: 1px solid #e5e7eb; color: #1f2937; vertical-align: middle; font-size: 9.5pt; }
    .items-table td.sn { width: 30px; text-align: center; font-weight: bold; color: #9ca3af; }
    .items-table td.item-name { font-weight: bold; color: #111827; }
    .items-table td.qty { text-align: right; font-weight: bold; font-size: 10.5pt; color: #4f46e5; }
    
    .footer-notes { margin-bottom: 30px; }
    .footer-notes-title { font-size: 9pt; font-weight: bold; color: #4b5563; text-transform: uppercase; margin-bottom: 8px; }
    .footer-notes ul { list-style: none; padding: 0; margin: 0; }
    .footer-notes li { margin-bottom: 5px; font-size: 9pt; color: #6b7280; }
    .footer-notes li::before { content: "- "; color: #9ca3af; }
    
    /* Toolbar for HTML preview */
    .toolbar { max-width: 800px; margin: 0 auto 20px auto; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .btn { display: inline-block; padding: 10px 20px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; cursor: pointer; text-decoration: none; border: none; }
    .btn-primary { background: #4f46e5; color: #fff; }
    .btn-ghost { background: #f3f4f6; color: #4b5563; }
    
    @media print {
        body { background: #fff; padding: 0; }
        .toolbar { display: none !important; }
        .document-wrapper { box-shadow: none; padding: 0; margin: 0; border-radius: 0; page-break-after: always; }
    }
</style>
';

// If PDF download is requested
if ($download_pdf) {
    $autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        http_response_code(500);
        echo 'mPDF library is not installed. Run: composer require mpdf/mpdf';
        exit;
    }
    require_once $autoload;

    // Redefine CSS for mPDF to remove browser background/shadows
    $mpdf_css = str_replace([
        'background: #f1f5f9; margin: 0; padding: 20px;',
        'max-width: 800px; margin: 0 auto 30px auto; background: #fff; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 8px;'
    ], [
        '',
        ''
    ], $css);

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_top' => 12,
        'margin_bottom' => 12,
        'margin_left' => 12,
        'margin_right' => 12,
    ]);
    
    $file_suffix = preg_replace('/[^a-z0-9]+/i', '_', $selected_class);
    $pdf_filename = 'Stationery_List_' . $file_suffix . '.pdf';
    
    $html = '
    <div class="document-wrapper">
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="logo-cell">
                    ' . ($logo_path ? '<img src="' . htmlspecialchars($logo_path) . '" alt="Logo" class="school-logo">' : '') . '
                </td>
                <td class="school-info-cell">
                    <div class="school-name serif">' . htmlspecialchars($school_name) . '</div>
                    <div class="school-motto">Official Stationery Requirements</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="doc-title serif">' . htmlspecialchars($print_title) . '</div>

        <table class="meta-table" cellpadding="0" cellspacing="0">
            <tr>
                <td><div class="meta-label">Class Level</div><div class="meta-value">' . strtoupper(htmlspecialchars($selected_class)) . '</div></td>
                <td><div class="meta-label">Academic Year</div><div class="meta-value">' . htmlspecialchars($selected_year) . '</div></td>
            </tr>
        </table>
        
        ' . ($print_instruction ? '<div class="instructions-box serif">' . nl2br(htmlspecialchars($print_instruction)) . '</div>' : '') . '

        <table class="items-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="sn">#</th>
                    <th>Item Description</th>
                    <th class="right">Required Qty</th>
                </tr>
            </thead>
            <tbody>';
            
    foreach ($items as $idx => $item) {
        $rowClass = ($idx % 2 === 1) ? 'alt-row' : '';
        $html .= '<tr class="' . $rowClass . '">
            <td class="sn">' . ($idx + 1) . '</td>
            <td class="item-name">' . htmlspecialchars($item['item_name']) . '</td>
            <td class="qty">' . htmlspecialchars($item['quantity']) . '</td>
        </tr>';
    }
            
    $html .= '</tbody>
        </table>';
        
    if ($print_footer_1 || $print_footer_2 || $print_footer_3) {
        $html .= '<div class="footer-notes">
            <div class="footer-notes-title">Important Notes</div>
            <ul>';
        if ($print_footer_1) $html .= '<li>' . htmlspecialchars($print_footer_1) . '</li>';
        if ($print_footer_2) $html .= '<li>' . htmlspecialchars($print_footer_2) . '</li>';
        if ($print_footer_3) $html .= '<li>' . htmlspecialchars($print_footer_3) . '</li>';
        $html .= '</ul></div>';
    }

    $html .= '</div>';

    $mpdf->WriteHTML($mpdf_css, \Mpdf\HTMLParserMode::HEADER_CSS);
    $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);
    
    // Download directly to browser
    $mpdf->Output($pdf_filename, \Mpdf\Output\Destination::DOWNLOAD);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Preview - Stationery List</title>
    <?= $css ?>
</head>
<body>

<div class="toolbar">
    <div>
        <a href="manage.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>" class="btn btn-ghost">← Back</a>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn btn-ghost">Preview Print</button>
        <a href="print_list.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>&download=pdf" class="btn btn-primary">Download PDF</a>
    </div>
</div>

<div class="document-wrapper">
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="logo-cell">
                <?php if ($logo_path): ?><img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" class="school-logo"><?php endif; ?>
            </td>
            <td class="school-info-cell">
                <div class="school-name serif"><?= htmlspecialchars($school_name) ?></div>
                <div class="school-motto">Official Stationery Requirements</div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="doc-title serif"><?= htmlspecialchars($print_title) ?></div>

    <table class="meta-table" cellpadding="0" cellspacing="0">
        <tr>
            <td><div class="meta-label">Class Level</div><div class="meta-value"><?= strtoupper(htmlspecialchars($selected_class)) ?></div></td>
            <td><div class="meta-label">Academic Year</div><div class="meta-value"><?= htmlspecialchars($selected_year) ?></div></td>
        </tr>
    </table>
    
    <?php if ($print_instruction): ?>
    <div class="instructions-box serif"><?= nl2br(htmlspecialchars($print_instruction)) ?></div>
    <?php endif; ?>

    <table class="items-table" cellpadding="0" cellspacing="0">
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
</div>

</body>
</html>
