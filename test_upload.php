<?php
$file_path = 'dummy_lesson.docx';
copy('test_local.docx', $file_path); // Use the existing dummy file

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/ACCOUNTING/pages/teacher/process_lesson_import.php');
curl_setopt($ch, CURLOPT_POST, 1);
$cfile = new CURLFile($file_path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'dummy_lesson.docx');
curl_setopt($ch, CURLOPT_POSTFIELDS, ['lesson_file' => $cfile]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);
// Provide a valid session ID from the browser
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=c565v7udas849v0lp96cdvrlq3"); // Let's just create a mock session in process_lesson_import
$response = curl_exec($ch);
print_r($response);
