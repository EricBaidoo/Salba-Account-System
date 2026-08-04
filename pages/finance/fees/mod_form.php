<?php
$content = file_get_contents('bulk_unassign_fee_form.php');
$content = str_replace('Assign Fees', 'Bulk Unassign Fees', $content);
$content = str_replace('Assign <span', 'Bulk Unassign <span', $content);
$content = str_replace('Target students or classes for fee allocation.', 'Target classes or students to bulk expunge fees from.', $content);
$content = str_replace('assign_fee.php', 'bulk_unassign_fee.php', $content);
$content = str_replace('Assign Fee', 'Bulk Unassign Fees', $content);
$content = str_replace('2. Period & Deadlines', '2. Period Details', $content);
$content = str_replace('<option value="individual" <?= $preselected_student_id ? \'selected\' : \'\' ?>>Individual Student</option>', '<option value="whole_school">Whole School</option><option value="individual" <?= $preselected_student_id ? \'selected\' : \'\' ?>>Individual Student</option>', $content);
file_put_contents('bulk_unassign_fee_form.php', $content);
