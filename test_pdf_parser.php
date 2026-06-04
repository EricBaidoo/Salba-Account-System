<?php
require 'vendor/autoload.php';

// Generate a PDF with a table
$mpdf = new \Mpdf\Mpdf();
$html = '
<table border="1">
    <tr>
        <td>Subject</td>
        <td>Mathematics</td>
    </tr>
    <tr>
        <td>Class</td>
        <td>Basic 5</td>
    </tr>
    <tr>
        <td>Duration</td>
        <td>60 mins</td>
    </tr>
</table>
';
$mpdf->WriteHTML($html);
$mpdf->Output('test_parser.pdf', 'F');

// Parse it
$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('test_parser.pdf');
$text = $pdf->getText();

echo "EXTRACTED TEXT:\n";
echo "-----------------\n";
echo $text;
?>
