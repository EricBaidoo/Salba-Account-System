<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');

$queries = [
    "ALTER TABLE subjects DROP KEY unique_subject",
    "ALTER TABLE subjects DROP KEY idx_level",
    "ALTER TABLE subjects DROP COLUMN academic_level",
    "ALTER TABLE subjects ADD UNIQUE KEY unique_subject_code (code)"
];

foreach ($queries as $q) {
    if ($conn->query($q) === TRUE) {
        echo "Success: $q\n";
    } else {
        echo "Error running '$q': " . $conn->error . "\n";
    }
}
?>
