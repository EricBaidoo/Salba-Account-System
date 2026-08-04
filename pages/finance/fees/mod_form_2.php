<?php
$content = file_get_contents('bulk_unassign_fee_form.php');
// Remove Due Date field
$content = preg_replace('/<div>\s*<label[^>]*>Due Date.*?<\/div>/is', '', $content);
// Remove Notes field
$content = preg_replace('/<div>\s*<label[^>]*>Notes \(Optional\).*?<\/div>/is', '', $content);
file_put_contents('bulk_unassign_fee_form.php', $content);
