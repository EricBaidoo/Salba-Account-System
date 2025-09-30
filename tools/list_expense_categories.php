<?php
require_once __DIR__ . '/../includes/db_connect.php';

$result = $conn->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != ''");
if ($result && $result->num_rows > 0) {
    echo "Unique expense categories in 'expenses' table:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['category'] . "\n";
    }
} else {
    echo "No categories found in 'expenses' table.\n";
}
