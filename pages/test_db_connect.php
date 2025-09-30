<?php
include '../includes/db_connect.php';
echo '<div style="margin:2em;font-size:1.2em;">';
echo $conn->connect_error ? 'Database connection failed: ' . $conn->connect_error : 'Database connection successful!';
echo '</div>';
?>