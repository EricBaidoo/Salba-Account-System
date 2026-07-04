<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/db_connect.php';
include '../../../includes/auth_check.php';
require_finance_access();
include '../../../includes/system_settings.php';
include '../../../includes/payment_receipt_functions.php';

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if ($payment_id <= 0) die('Invalid payment ID.');
$context = getPaymentReceiptContext($conn, $payment_id, '../../../');
if (!$context) die('Payment not found.');

while (ob_get_level() > 0) {
    ob_end_clean();
}

$html = buildPaymentReceiptPdfHtml($context);

$previous_display_errors = ini_get('display_errors');
$previous_error_reporting = error_reporting();
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

try {
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A6', 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0, 'margin_header' => 0, 'margin_footer' => 0]);
    $mpdf->WriteHTML($html);
    $mode = isset($_GET['download']) && $_GET['download'] === '1' ? 'D' : 'I';
    $mpdf->Output('Receipt_' . ($context['receipt_no'] ?: ('PAYMENT-' . $payment_id)) . '.pdf', $mode);
} finally {
    ini_set('display_errors', (string)$previous_display_errors);
    error_reporting($previous_error_reporting);
}
