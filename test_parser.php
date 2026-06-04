<?php
$tmpPath = 'test_local.docx';
$rawText = '';
$zip = new ZipArchive;
if ($zip->open($tmpPath) === TRUE) {
    if (($index = $zip->locateName('word/document.xml')) !== false) {
        $data = $zip->getFromIndex($index);
        $data = str_replace(['</w:p>', '</w:tc>'], ["\n", "\t"], $data);
        $rawText = strip_tags($data);
    }
    $zip->close();
}
echo "RAW TEXT EXTRACTED:\n";
echo $rawText;
