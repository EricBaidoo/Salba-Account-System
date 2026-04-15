<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$res = $conn->query("SELECT name FROM classes ORDER BY name");
$classes = [];
if($res) {
    while($row = $res->fetch_assoc()) {
        $classes[] = $row['name'];
    }
} else {
    // Fallback if table doesn't exist
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL ORDER BY class");
    while($row = $res->fetch_assoc()) {
        $classes[] = $row['class'];
    }
}

echo "Total Classes: " . count($classes) . "\n";
echo "Class Names:\n";
foreach($classes as $c) echo "- $c\n";

// Infer levels (common levels: Basic, JHS, Nursery, etc.)
$levels = [];
foreach($classes as $c) {
    // Extract everything before the first number or just grouping by first word
    if (preg_match('/^([a-zA-Z\s]+)/', $c, $matches)) {
        $levels[] = trim($matches[1]);
    }
}
$levels = array_unique($levels);
echo "\nInferred Levels: " . count($levels) . "\n";
foreach($levels as $l) echo "- $l\n";
?>
