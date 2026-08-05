<?php
/**
 * Stationery Helpers
 * Functions for generating stationery HTML for combined PDF/printing
 */

function generate_stationery_html($conn, $student_name, $selected_class, $selected_year, $is_pdf = false) {
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
    
    if (!$ir || $ir->num_rows === 0) {
        return false; // No stationery for this class
    }
    
    while ($i = $ir->fetch_assoc()) {
        $items[] = $i;
    }

    $school_name    = getSystemSetting($conn, 'school_name', 'School');
    $school_logo    = getSystemSetting($conn, 'school_logo', '');

    $print_title       = getSystemSetting($conn, 'stationery_print_title',       'STATIONERY LIST');
    $print_instruction = getSystemSetting($conn, 'stationery_print_instruction', 'Dear Parent / Guardian, kindly ensure your child/ward reports with the items listed below. All items should be labelled with the student\'s name. Thank you for your cooperation.');
    $print_footer_1    = getSystemSetting($conn, 'stationery_print_footer_1',    'Items must be brought on or before the first week of the term.');
    $print_footer_2    = getSystemSetting($conn, 'stationery_print_footer_2',    'All items should be neatly labelled with your child\'s full name and class.');
    $print_footer_3    = getSystemSetting($conn, 'stationery_print_footer_3',    'For inquiries, please contact the class teacher or school administration.');

    $logo_file_path = $school_logo
        ? realpath(__DIR__ . '/../' . ltrim($school_logo, '/'))
        : realpath(__DIR__ . '/../assets/img/salba_logo.jpg');
    $logo_path = $logo_file_path ?: '';

    $student_name = !empty($student_name) ? strtoupper(htmlspecialchars($student_name)) : '';

    $css_class = $is_pdf ? 'document-wrapper' : 'document-wrapper page-break-before';
    $wrapper_style = $is_pdf ? '' : 'style="page-break-before: always; max-width: 800px; margin: 0 auto; padding: 40px;"';

    $html = '
    <style>
        .page-break-before { page-break-before: always; break-before: page; }
        .stationery-wrapper { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; color: #333333; line-height: 1.4; }
        .stationery-wrapper .serif { font-family: "Times", "Times New Roman", serif; }
        
        .stationery-wrapper .header-table { width: 100%; margin-bottom: 10px; border-collapse: collapse; border: none; }
        .stationery-wrapper .header-table td { border: none; }
        .stationery-wrapper .logo-cell { width: 80px; vertical-align: middle; }
        .stationery-wrapper .school-logo { width: 70px; height: auto; max-height: 70px; border: none; }
        .stationery-wrapper .school-info-cell { text-align: left; padding-left: 15px; vertical-align: middle; }
        .stationery-wrapper .school-name { font-size: 18pt; font-weight: bold; color: #111827; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .stationery-wrapper .school-motto { font-size: 9pt; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin: 0; font-weight: normal; }
        
        .stationery-wrapper .divider { border-bottom: 1.5px solid #4f46e5; margin-bottom: 20px; }
        
        .stationery-wrapper .doc-title { text-align: center; font-size: 15pt; font-weight: bold; color: #111827; margin: 0 0 15px 0; text-transform: uppercase; letter-spacing: 1px; }
        
        .stationery-wrapper .meta-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; border: none; }
        .stationery-wrapper .meta-table td { padding: 6px 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; vertical-align: middle; width: 33.33%; }
        .stationery-wrapper .meta-label { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; font-weight: bold; margin-bottom: 2px; }
        .stationery-wrapper .meta-value { font-size: 10pt; font-weight: bold; color: #111827; }
        
        .stationery-wrapper .instructions-box { background-color: #ffffff; border: 1px solid #e5e7eb; border-left: 3px solid #4f46e5; padding: 10px 15px; margin-bottom: 20px; color: #4b5563; font-size: 9.5pt; font-style: italic; line-height: 1.5; }
        
        .stationery-wrapper .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .stationery-wrapper .items-table th { background-color: #f3f4f6; color: #374151; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; padding: 8px 10px; text-align: left; border: 1px solid #e5e7eb; border-bottom: 2px solid #d1d5db; }
        .stationery-wrapper .items-table th.center { text-align: center; }
        .stationery-wrapper .items-table th.right { text-align: right; }
        .stationery-wrapper .items-table td { padding: 8px 10px; border: 1px solid #e5e7eb; color: #1f2937; vertical-align: middle; font-size: 9.5pt; }
        .stationery-wrapper .items-table td.sn { width: 30px; text-align: center; font-weight: bold; color: #9ca3af; }
        .stationery-wrapper .items-table td.item-name { font-weight: bold; color: #111827; }
        .stationery-wrapper .items-table td.qty { text-align: right; font-weight: bold; font-size: 10.5pt; color: #4f46e5; }
        
        .stationery-wrapper .footer-notes { margin-bottom: 30px; }
        .stationery-wrapper .footer-notes-title { font-size: 9pt; font-weight: bold; color: #4b5563; text-transform: uppercase; margin-bottom: 8px; }
        .stationery-wrapper .footer-notes ul { list-style: none; padding: 0; margin: 0; }
        .stationery-wrapper .footer-notes li { margin-bottom: 5px; font-size: 9pt; color: #6b7280; }
        .stationery-wrapper .footer-notes li::before { content: "- "; color: #9ca3af; }
        .stationery-wrapper tr.alt-row td { background-color: #f8fafc; }
    </style>
    <div class="' . $css_class . ' stationery-wrapper" ' . $wrapper_style . '>
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
                ' . ($student_name ? '<td><div class="meta-label">Student Name</div><div class="meta-value">' . $student_name . '</div></td>' : '') . '
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
    
    return $html;
}
?>
