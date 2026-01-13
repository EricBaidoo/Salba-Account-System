<?php
// Direct connection with root credentials
$conn = new mysqli('localhost', 'root', '', 'u420775839_Salba_acc');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Expenses table structure:\n";
echo "========================\n";

$result = $conn->query('DESCRIBE expenses');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\nSample expense record:\n";
echo "=====================\n";
$sample = $conn->query('SELECT * FROM expenses LIMIT 1');
if ($sample && $sample->num_rows > 0) {
    $row = $sample->fetch_assoc();
    foreach ($row as $key => $value) {
        echo "$key: $value\n";
    }
} else {
    echo "No expenses found\n";
}
?>
