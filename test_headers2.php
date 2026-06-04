<?php
$ch = curl_init('http://localhost/ACCOUNTING/pages/teacher/download_word_template');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $header_size);
echo "HEADERS:\n" . $header;
