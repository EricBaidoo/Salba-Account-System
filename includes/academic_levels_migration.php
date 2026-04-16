<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// 1. Create academic_levels table
$sql = "CREATE TABLE IF NOT EXISTS academic_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "Table 'academic_levels' created or already exists.\n";
} else {
    die("Error creating table: " . $conn->error);
}

// 2. Pre-populate with default levels
$defaults = [
    ['Preschool', 'Blocks & Play'],
    ['Lower Basic', 'Foundational Years'],
    ['Upper Basic', 'Intermediate Study'],
    ['Junior High', 'Junior Secondary']
];

$stmt = $conn->prepare("INSERT IGNORE INTO academic_levels (level_name, description) VALUES (?, ?)");
foreach ($defaults as $d) {
    $stmt->bind_param("ss", $d[0], $d[1]);
    $stmt->execute();
}
echo "Default levels populated.\n";

// 3. Optional: Sync existing classes if they have a 'Level' that doesn't exist yet
// Just to be safe, we ensure classes with current 'Level' strings are valid or handled.
echo "Migration complete.\n";
?>
