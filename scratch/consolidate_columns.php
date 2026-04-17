<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed");

$mapping = [
    'phone_number' => ['phone', 'telephone_number', 'phone'], // Target => Sources
    'address' => ['place_of_stay_address'],
    'landmark' => ['land_mark'],
    'hometown' => ['home_town'],
    'job_title' => ['staff_role'],
];

foreach ($mapping as $target => $sources) {
    foreach ($sources as $source) {
        $conn->query("UPDATE staff_profiles SET $target = $source WHERE ($target IS NULL OR $target = '') AND ($source IS NOT NULL AND $source != '')");
        echo "Merged $source into $target\n";
    }
}

// Drop redundant columns
$to_drop = [
    'phone', 'telephone_number', 'place_of_stay_address', 'land_mark', 'home_town', 
    'staff_role', 'languages_spoken', 'bank_account_details'
];

foreach ($to_drop as $col) {
    if ($conn->query("ALTER TABLE staff_profiles DROP COLUMN $col")) {
        echo "Dropped $col\n";
    } else {
        echo "Could not drop $col (might not exist)\n";
    }
}

echo "Column consolidation complete.\n";
?>
