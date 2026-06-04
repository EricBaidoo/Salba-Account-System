<?php
ob_start();
error_reporting(0);
session_start();

require '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

// Define Styles
$phpWord->addFontStyle('HeaderStyle', ['bold' => true, 'size' => 16, 'color' => '0F766E', 'name' => 'Arial']); // Teal
$phpWord->addFontStyle('SubHeaderStyle', ['bold' => true, 'size' => 12, 'color' => '111827', 'name' => 'Arial']);
$phpWord->addFontStyle('LabelStyle', ['bold' => true, 'size' => 10, 'color' => '4B5563', 'name' => 'Arial']);
$phpWord->addFontStyle('ValueStyle', ['size' => 10, 'color' => '111827', 'name' => 'Arial']);

$section = $phpWord->addSection();

// Add Header
$section->addText("WEEKLY REPORT TEMPLATE", 'HeaderStyle', ['alignment' => Jc::CENTER]);
$section->addText("Instructions: Fill in the right column. Do not change the labels in the left column.", ['italic' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$tableStyle = [
    'borderSize' => 6,
    'borderColor' => 'D1D5DB',
    'cellMargin' => 80
];
$phpWord->addTableStyle('ReportTable', $tableStyle);

$fields = [
    // Core
    'Class' => 'e.g. Basic 1',
    'Week Number' => '1',
    'Week Ending Date' => '2026-05-15',
    
    // Academic Coverage
    'Topics Covered (Summary)' => 'e.g. Math: Fractions, English: Nouns. Completed successfully.',
    'Assessments Conducted' => 'e.g. Math Quiz on Wednesday, English Dictation on Friday.',
    'Overall Class Performance' => 'e.g. Good',
    'Struggling Students (Intervention)' => 'e.g. Kwame and Sarah are struggling with fractions.',
    
    // Classroom Management
    'General Class Behavior' => 'e.g. Very attentive this week.',
    'Discipline Issues & Actions' => 'e.g. Two students were disruptive, parents were notified.',
    'Attendance Concerns' => 'e.g. John Doe was absent for 3 consecutive days.',
    
    // Parents & Support
    'Parents Contacted This Week' => 'e.g. Called Mrs. Smith regarding John\'s absences.',
    'Challenges Faced' => 'e.g. Intermittent power outage affected the ICT practicals.',
    'Support / Resources Required' => 'e.g. Require new whiteboard markers and printing paper.',
    'Focus For Next Week' => 'e.g. Will conduct revision on Fractions.'
];

$table = $section->addTable('ReportTable');

foreach ($fields as $label => $placeholder) {
    $table->addRow();
    $table->addCell(4000, ['bgColor' => 'F3F4F6'])->addText($label, 'LabelStyle');
    $table->addCell(5000)->addText($placeholder, 'ValueStyle');
}

$tempFile = __DIR__ . '/temp_report_' . md5(uniqid()) . '.docx';
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($tempFile);

$wordData = file_get_contents($tempFile);
@unlink($tempFile);

while (ob_get_level()) {
    ob_end_clean();
}

$filename = "Weekly_Report_Template_" . date('Ymd') . ".docx";

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . strlen($wordData));

echo $wordData;
exit;
?>
