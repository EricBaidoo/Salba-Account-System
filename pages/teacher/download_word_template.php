<?php
ob_start();
error_reporting(0);
session_start();

require '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in()) {
    ob_end_clean();
    die("Unauthorized access.");
}

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

// Define Styles
$phpWord->addFontStyle('HeaderStyle', ['bold' => true, 'size' => 16, 'color' => '1F2937', 'name' => 'Arial']);
$phpWord->addFontStyle('LabelStyle', ['bold' => true, 'size' => 10, 'color' => '4B5563', 'name' => 'Arial']);
$phpWord->addFontStyle('ValueStyle', ['size' => 10, 'color' => '111827', 'name' => 'Arial']);
$phpWord->addParagraphStyle('PStyle', ['spaceAfter' => 100]);

$section = $phpWord->addSection();

// Add Header
$section->addText("GES LESSON NOTE TEMPLATE", 'HeaderStyle', ['alignment' => Jc::CENTER]);
$section->addText("Instructions: Fill in the right column. Do not change the labels in the left column.", ['italic' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$tableStyle = [
    'borderSize' => 6,
    'borderColor' => 'D1D5DB',
    'cellMargin' => 80
];
$phpWord->addTableStyle('NoteTable', $tableStyle);
$table = $section->addTable('NoteTable');

$fields = [
    'Week Ending' => 'e.g. 2026-05-15',
    'Day' => 'e.g. Monday',
    'Subject' => 'e.g. ABACUS',
    'Class' => 'e.g. Basic 1',
    'Class Size' => '45',
    'Duration' => 'e.g. 60 mins',
    'Strand' => 'e.g. Number',
    'Sub-Strand' => 'e.g. Fractions',
    'Content Standard' => 'e.g. B1.1.1.1',
    'Indicator' => 'e.g. B1.1.1.1.1',
    'Lesson Number' => '1',
    'Performance Indicator' => 'Learners can identify...',
    'Core Competencies' => 'Communication, Collaboration',
    'References' => 'Mathematics Curriculum p.12',
    'TLM' => 'Flashcards, Counters',
    'New Words' => 'Numerator, Denominator',
    'Starter Activities' => 'Phase 1: Warm up with a song...',
    'Starter Resources' => 'Audio player',
    'Starter Duration' => '10 mins',
    'Learning Activities' => 'Phase 2: Use counters to show...',
    'Learning Resources' => 'Counters, Worksheets',
    'Assessment' => 'Can they group items into halves?',
    'Learning Duration' => '40 mins',
    'Reflection Activities' => 'Phase 3: Wrap up with Q&A...',
    'Reflection Resources' => 'None',
    'Reflection Duration' => '10 mins',
    'Homework' => 'Complete Page 5 in Workbook'
];

foreach ($fields as $label => $placeholder) {
    $table->addRow();
    $table->addCell(3000, ['bgColor' => 'F3F4F6'])->addText($label, 'LabelStyle');
    $table->addCell(6000)->addText($placeholder, 'ValueStyle');
}

// Set Headers for Download (Simple HTML-to-Word)
$filename = "GES_Lesson_Note_Template_" . date('Ymd') . ".doc";

ob_end_clean();
header('Content-Type: application/msword');
header('Content-Disposition: attachment; filename="' . $filename . '"');

?>
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
    table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
    td { border: 1px solid #ccc; padding: 8px; vertical-align: top; }
    .label { background-color: #eee; font-weight: bold; width: 30%; }
</style>
</head>
<body>
    <h2 style='text-align:center;'>GES LESSON NOTE TEMPLATE</h2>
    <p style='text-align:center;'>Instructions: Fill in the right column. Do not change labels.</p>
    <table>
        <?php
        $fields = [
            'Week Number' => 'e.g. 1',
            'Week Ending' => '2026-05-15',
            'Day' => 'Monday',
            'Subject' => 'ABACUS',
            'Class' => 'Basic 1',
            'Class Size' => '45',
            'Duration' => '60 mins',
            'Strand' => 'Number',
            'Sub-Strand' => 'Fractions',
            'Content Standard' => 'B1.1.1.1',
            'Indicator' => 'B1.1.1.1.1',
            'Lesson Number' => '1',
            'Performance Indicator' => 'Learners can identify...',
            'Core Competencies' => 'Communication, Collaboration',
            'References' => 'Mathematics Curriculum p.12',
            'TLM' => 'Flashcards, Counters',
            'New Words' => 'Numerator, Denominator',
            'Starter Activities' => 'Phase 1: Warm up...',
            'Starter Resources' => 'Audio player',
            'Starter Duration' => '10 mins',
            'Learning Activities' => 'Phase 2: Main lesson...',
            'Learning Resources' => 'Counters',
            'Assessment' => 'Can they group items?',
            'Learning Duration' => '40 mins',
            'Reflection Activities' => 'Phase 3: Wrap up...',
            'Reflection Resources' => 'None',
            'Reflection Duration' => '10 mins',
            'Homework' => 'Page 5 Workbook'
        ];
        foreach ($fields as $label => $val) {
            echo "<tr><td class='label'>$label</td><td>$val</td></tr>";
        }
        ?>
    </table>
</body>
</html>
<?php
exit;
