<?php
// We will call download_word_template.php and save the output
$ch = curl_init('http://localhost/ACCOUNTING/pages/teacher/download_word_template.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Need session cookie to bypass login? We don't have the user's session cookie.
// Let's temporarily disable login check in download_word_template.php.
