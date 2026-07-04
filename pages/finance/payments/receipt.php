<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_check.php';
require_finance_access();
include '../../../includes/system_settings.php';
include '../../../includes/payment_receipt_functions.php';

$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if ($payment_id <= 0) die('Invalid Access Descriptor.');
$context = getPaymentReceiptContext($conn, $payment_id, '../../../');
if (!$context) die('Transaction Record Not Found.');

echo buildPaymentReceiptHtml($context, [
    'interactive' => true,
    'autoprint' => $autoprint,
    'download_url' => 'download_receipt_pdf.php?payment_id=' . $payment_id,
    'back_url' => 'view_payments.php',
    'logo_src' => $context['logo_public_path'],
]);
