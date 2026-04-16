<?php
include 'includes/db_connect.php';
$res = $conn->query("DESCRIBE users");
echo "Table: users\n";
while ($row = $res->fetch_row()) {
    echo implode(' | ', $row) . "\n";
}
?>
