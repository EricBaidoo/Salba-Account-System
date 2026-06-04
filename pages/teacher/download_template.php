<?php
$type = $_GET['type'] ?? 'excel';

if ($type === 'word') {
    $file = '../../assets/templates/GES_Lesson_Note_Template.rtf';
    $name = 'GES_Lesson_Note_Template.rtf';
    $mime = 'application/rtf';
} else {
    $file = '../../assets/templates/GES_Lesson_Note_Template.xlsx';
    $name = 'GES_Lesson_Note_Template.xlsx';
    $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
}

if (file_exists($file)) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    die("Template file not found.");
}
