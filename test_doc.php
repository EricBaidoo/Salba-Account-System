<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

$section = $phpWord->addSection();

// Add Header
$section->addText("GES LESSON NOTE TEMPLATE", ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
$section->addText("Instructions: Fill in the right column. Do not change the labels in the left column.", ['italic' => true, 'size' => 10], ['alignment' => Jc::CENTER]);
$section->addTextBreak(1);

$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 50
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
    $table->addCell(3000)->addText($label, ['bold' => true]);
    $table->addCell(6000)->addText($placeholder);
}

$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'RTF');
$objWriter->save("test_local.rtf");
echo "Done RTF.";
