<?php
$conn=new mysqli('localhost','root','root','Salba_acc');
$conn->query("INSERT INTO attendance (student_id, attendance_date, status) VALUES (12, '2020-01-01', 'absent')");
echo "Err: " . $conn->error;
?>
