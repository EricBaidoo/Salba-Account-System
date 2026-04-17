<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SELECT * FROM staff_profiles LIMIT 1");
$row = $res->fetch_assoc();

$redundant_pairs = [
    ['phone', 'phone_number'],
    ['telephone_number', 'phone_number'],
    ['address', 'place_of_stay_address'],
    ['landmark', 'land_mark'],
    ['hometown', 'home_town'],
    ['job_title', 'staff_role'],
    ['bank_name', 'bank_account_details'],
];

foreach ($redundant_pairs as $pair) {
    $val1 = $row[$pair[0]] ?? 'N/A';
    $val2 = $row[$pair[1]] ?? 'N/A';
    echo "Pair: {$pair[0]} vs {$pair[1]} | Samples: '$val1' vs '$val2'\n";
}
?>
