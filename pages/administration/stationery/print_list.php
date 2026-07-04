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
$download_pdf   = (($_GET['download'] ?? '') === 'pdf');
$school_name    = getSystemSetting($conn, 'school_name', 'School');
$school_logo    = getSystemSetting($conn, 'school_logo', '');

// Printout settings (editable from settings.php)
$print_title       = getSystemSetting($conn, 'stationery_print_title',       'STATIONERY LIST');
$print_instruction = getSystemSetting($conn, 'stationery_print_instruction', 'Dear Parent / Guardian, kindly ensure your child/ward reports with the items listed below. All items should be labelled with the student\'s name. Thank you for your cooperation.');
$print_footer_1    = getSystemSetting($conn, 'stationery_print_footer_1',    'Items must be brought on or before the first week of the term.');
$print_footer_2    = getSystemSetting($conn, 'stationery_print_footer_2',    'All items should be neatly labelled with your child\'s full name and class.');
$print_footer_3    = getSystemSetting($conn, 'stationery_print_footer_3',    'For inquiries, please contact the class teacher or school administration.');

$logo_public_path = $school_logo ? '../../../' . ltrim($school_logo, '/') : '../../../assets/img/salba_logo.jpg';
$logo_file_path = $school_logo
    ? realpath(__DIR__ . '/../../../' . ltrim($school_logo, '/'))
    : realpath(__DIR__ . '/../../../assets/img/salba_logo.jpg');
$logo_path = ($download_pdf && $logo_file_path) ? $logo_file_path : $logo_public_path;

if (!$selected_class || !$selected_year) {
    header('Location: manage.php'); exit;
}

$sc = $conn->real_escape_string($selected_class);
$sy = $conn->real_escape_string($selected_year);

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

$pdf_query = http_build_query([
    'class' => $selected_class,
    'academic_year' => $selected_year,
    'download' => 'pdf',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery List — <?= htmlspecialchars($selected_class) ?> — <?= htmlspecialchars($selected_year) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #111827;
            padding: 28px 18px;
        }

        .page-wrapper {
            max-width: 860px;
            margin: 0 auto;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 13px;
            font-weight: 700;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #334e87; color: #fff; }
        .btn-ghost { background: #fff; color: #475569; border: 1px solid #d1d5db; }

        .print-card {
            background: #fff;
            border: 1px solid #d1d5db;
            overflow: hidden;
        }

        .school-header {
            text-align: center;
            padding: 16px 24px 8px;
        }
        .school-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .school-name {
            font-size: 38px;
            line-height: 1;
            font-weight: 900;
            color: #3f5285;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .school-meta {
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
        }

        .bill-title {
            margin: 8px 16px 6px;
            padding: 8px 12px;
            background: #425587;
            color: #fff;
            text-align: center;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .meta-grid {
            margin: 0 16px 8px;
            border-left: 2px solid #425587;
            border-right: 2px solid #425587;
            padding: 8px 10px 2px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 24px;
            font-size: 11px;
        }
        .meta-grid .label {
            color: #111827;
            font-weight: 700;
            text-transform: uppercase;
        }

        .instruction {
            margin: 4px 16px 10px;
            font-size: 10.5px;
            color: #475569;
            line-height: 1.5;
            font-style: italic;
        }

        .items-table-wrapper {
            margin: 0 16px 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        thead tr {
            background: #425587;
            color: #fff;
        }
        thead th {
            padding: 7px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        thead th.right { text-align: right; }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #d1d5db;
        }
        tbody td.sn {
            width: 36px;
            text-align: center;
            color: #6b7280;
            font-weight: 700;
        }
        tbody td.item-name {
            font-weight: 700;
            text-transform: uppercase;
        }
        tbody td.qty {
            text-align: right;
            font-weight: 800;
            color: #1f3a77;
        }

        .notes-wrap {
            margin: 12px 16px 16px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
        }
        .notes-title {
            padding: 7px 10px;
            border-bottom: 1px solid #94a3b8;
            color: #334e87;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .footer-note {
            padding: 8px 12px 10px;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.65;
        }

        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .toolbar { display: none !important; }
            .page-wrapper { max-width: none; margin: 0; }
            .print-card {
                border: 0;
                box-shadow: none;
            }
            .school-name {
                font-size: 34px;
            }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }

        @page { margin: 9mm; }
    </style>
</head>
<body>
<div class="page-wrapper">

    <?php if (!$download_pdf): ?>
    <!-- Toolbar (screen only) -->
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="manage.php?class=<?= urlencode($selected_class) ?>&academic_year=<?= urlencode($selected_year) ?>" class="btn btn-ghost">
                ← Back
            </a>
            <span style="font-size:13px; font-weight:700; color:#475569;">
                Stationery List — <strong><?= htmlspecialchars($selected_class) ?></strong>
                &nbsp;·&nbsp; <?= htmlspecialchars($selected_year) ?>
            </span>
        </div>
        <button onclick="window.print()" class="btn btn-primary">
            Preview / Print
        </button>
        <a href="print_list.php?<?= htmlspecialchars($pdf_query) ?>" class="btn btn-ghost">
            Download PDF
        </a>
    </div>
    <?php endif; ?>

    <!-- Printable card -->
    <div class="print-card">

        <div class="school-header">
            <img src="<?= htmlspecialchars($logo_path) ?>" alt="Logo" class="school-logo">
            <h1 class="school-name"><?= htmlspecialchars($school_name) ?></h1>
            <p class="school-meta">Stationery Requirements · Academic Year <?= htmlspecialchars($selected_year) ?></p>
        </div>

        <div class="bill-title">
            <?= htmlspecialchars($print_title) ?> - <?= strtoupper(htmlspecialchars($selected_class)) ?>
        </div>

        <div class="meta-grid">
            <div><span class="label">Class:</span> <?= strtoupper(htmlspecialchars($selected_class)) ?></div>
            <div><span class="label">Academic Year:</span> <?= htmlspecialchars($selected_year) ?></div>
            <div><span class="label">Date Issued:</span> <?= date('d M, Y') ?></div>
            <div><span class="label">Total Items:</span> <?= count($items) ?></div>
        </div>

        <!-- Instruction -->
        <?php if ($print_instruction): ?>
        <p class="instruction"><?= nl2br(htmlspecialchars($print_instruction)) ?></p>
        <?php endif; ?>

        <!-- Items Table -->
        <div class="items-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px; text-align:center">#</th>
                        <th>Item</th>
                        <th class="right">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="sn"><?= $idx + 1 ?></td>
                        <td class="item-name"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td class="qty"><?= htmlspecialchars($item['quantity']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($print_footer_1 || $print_footer_2 || $print_footer_3): ?>
        <div class="notes-wrap">
            <div class="notes-title">Note:</div>
            <div class="footer-note">
                <?php if ($print_footer_1): ?>1. <?= htmlspecialchars($print_footer_1) ?><br><?php endif; ?>
                <?php if ($print_footer_2): ?>2. <?= htmlspecialchars($print_footer_2) ?><br><?php endif; ?>
                <?php if ($print_footer_3): ?>3. <?= htmlspecialchars($print_footer_3) ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.print-card -->
</div><!-- /.page-wrapper -->
</body>
</html>
<?php
$html = ob_get_clean();

if ($download_pdf) {
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
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
        ]);
        $mpdf->SetTitle('Stationery List - ' . $selected_class . ' - ' . $selected_year);
        $mpdf->WriteHTML($html);
        $filename = 'stationery_list_' . preg_replace('/[^a-z0-9]+/i', '_', $selected_class) . '_' . preg_replace('/[^0-9_\-]/', '', $selected_year) . '.pdf';
        $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        $encoded = rawurlencode($filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . $encoded);
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfBinary;
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'PDF generation failed: ' . $e->getMessage();
        exit;
    }
}

echo $html;
