<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
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
$show_price        = getSystemSetting($conn, 'stationery_print_show_price',  '0') === '1';
$show_notes        = getSystemSetting($conn, 'stationery_print_show_notes',  '1') === '1';
$show_sig          = getSystemSetting($conn, 'stationery_print_show_sig',    '1') === '1';

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
            color: #1e293b;
            padding: 32px 24px;
        }

        /* ── Screen wrapper ── */
        .page-wrapper {
            max-width: 720px;
            margin: 0 auto;
        }

        /* ── Toolbar (hidden when printing) ── */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .15s;
        }
        .btn:hover { opacity: .85; }
        .btn-primary { background: #1e40af; color: #fff; }
        .btn-ghost   { background: #fff; color: #475569; border: 1px solid #e2e8f0; }

        /* ── The printable card ── */
        .print-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            overflow: hidden;
        }

        /* ── School header ── */
        .school-header {
            background: #1e3a8a;
            color: #fff;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .school-header img {
            height: 56px;
            width: 56px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            padding: 4px;
            flex-shrink: 0;
        }
        .school-header-text h1 {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .school-header-text p {
            font-size: 11px;
            opacity: .8;
            margin-top: 2px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        /* ── Document title band ── */
        .doc-title-band {
            background: #dbeafe;
            border-top: 3px solid #3b82f6;
            border-bottom: 3px solid #3b82f6;
            padding: 10px 28px;
            text-align: center;
        }
        .doc-title-band h2 {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #1e3a8a;
        }
        .doc-title-band p {
            font-size: 11px;
            color: #1e40af;
            margin-top: 2px;
            font-weight: 700;
        }

        /* ── Instruction text ── */
        .instruction {
            padding: 14px 28px 6px;
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
            font-style: italic;
        }

        /* ── Items table ── */
        .items-table-wrapper {
            padding: 12px 28px 28px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        thead tr {
            background: #1e3a8a;
            color: #fff;
        }
        thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .1em;
        }
        thead th.right { text-align: right; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #eff6ff; }
        tbody td {
            padding: 9px 14px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        tbody td.sn {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            width: 36px;
            text-align: center;
        }
        tbody td.item-name {
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            font-size: 12px;
        }
        tbody td.qty {
            font-weight: 800;
            color: #1e3a8a;
            white-space: nowrap;
            text-align: right;
        }
        tbody td.notes-cell {
            font-size: 11px;
            color: #64748b;
            font-style: italic;
        }
        tfoot tr { background: #eff6ff; }
        tfoot td {
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: .05em;
            border-top: 2px solid #3b82f6;
        }

        /* ── Footer note ── */
        .footer-note {
            padding: 0 28px 24px;
            font-size: 10.5px;
            color: #94a3b8;
            line-height: 1.7;
        }

        /* ── Signature block ── */
        .sign-block {
            margin: 0 28px 28px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }
        .sign-line {
            flex: 1;
            border-top: 1.5px solid #cbd5e1;
            padding-top: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            text-align: center;
        }

        /* ── Print overrides ── */
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
            }
            .toolbar { display: none !important; }
            .page-wrapper { max-width: none; margin: 0; }
            .print-card {
                box-shadow: none;
                border-radius: 0;
            }
            tbody tr:hover { background: inherit; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; }
        }

        @page {
            margin: 1cm;
        }
    </style>
</head>
<body>
<div class="page-wrapper">

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
            🖨 Print / Save as PDF
        </button>
    </div>

    <!-- Printable card -->
    <div class="print-card">

        <!-- School Header -->
        <div class="school-header">
            <?php if ($school_logo): ?>
            <img src="../../../<?= htmlspecialchars(ltrim($school_logo, '/')) ?>" alt="Logo">
            <?php endif; ?>
            <div class="school-header-text">
                <h1><?= htmlspecialchars($school_name) ?></h1>
                <p>Stationery Requirements — Academic Year <?= htmlspecialchars($selected_year) ?></p>
            </div>
        </div>

        <!-- Document Title Band -->
        <div class="doc-title-band">
            <h2>📋 <?= htmlspecialchars($print_title) ?></h2>
            <p>Class: <?= htmlspecialchars($selected_class) ?></p>
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
                        <?php if ($show_price): ?><th class="right">Price</th><?php endif; ?>
                        <?php
                        $has_notes = $show_notes && array_filter($items, fn($i) => !empty($i['notes']));
                        if ($has_notes): ?>
                        <th>Notes</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cols = 3 + ($show_price ? 1 : 0) + ($has_notes ? 1 : 0);
                    foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="sn"><?= $idx + 1 ?></td>
                        <td class="item-name"><?= htmlspecialchars($item['item_name']) ?></td>
                        <td class="qty"><?= htmlspecialchars($item['quantity']) ?></td>
                        <?php if ($show_price): ?><td class="qty"><?= $item['price'] > 0 ? 'GH₵ '.number_format($item['price'],2) : '—' ?></td><?php endif; ?>
                        <?php if ($has_notes): ?>
                        <td class="notes-cell"><?= htmlspecialchars($item['notes'] ?? '') ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="<?= $cols ?>" style="text-align:right">
                            Total items: <?= count($items) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Signature Block -->
        <?php if ($show_sig): ?>
        <div class="sign-block">
            <div class="sign-line">Student Name</div>
            <div class="sign-line">Class</div>
            <div class="sign-line">Parent/Guardian Signature</div>
            <div class="sign-line">Date</div>
        </div>
        <?php endif; ?>

        <!-- Footer Note -->
        <?php if ($print_footer_1 || $print_footer_2 || $print_footer_3): ?>
        <p class="footer-note">
            <?php if ($print_footer_1): ?>★ <?= htmlspecialchars($print_footer_1) ?><br><?php endif; ?>
            <?php if ($print_footer_2): ?>★ <?= htmlspecialchars($print_footer_2) ?><br><?php endif; ?>
            <?php if ($print_footer_3): ?>★ <?= htmlspecialchars($print_footer_3) ?><?php endif; ?>
        </p>
        <?php endif; ?>

    </div><!-- /.print-card -->
</div><!-- /.page-wrapper -->
</body>
</html>
