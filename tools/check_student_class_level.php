<?php
// Diagnostic script: Find students whose class is not mapped to a valid Level in the classes table
include '../includes/db_connect.php';
echo "<h2>Students with Unmapped or Missing Class Level</h2>";
$sql = "SELECT s.id, s.first_name, s.last_name, s.class, c.Level FROM students s LEFT JOIN classes c ON s.class = c.name WHERE c.Level IS NULL OR c.Level = ''";
$result = $conn->query($sql);
if ($result->num_rows === 0) {
    echo "<div style='color:green;'>All students are mapped to a valid class Level.</div>";
} else {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Class</th><th>Level (should not be empty)</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['first_name']) . "</td><td>" . htmlspecialchars($row['last_name']) . "</td><td>" . htmlspecialchars($row['class']) . "</td><td>" . htmlspecialchars($row['Level']) . "</td></tr>";
    }
    echo "</table>";
}
?>
