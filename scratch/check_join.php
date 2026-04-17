<?php
$c=new mysqli('localhost','root','root','Salba_acc'); 
$att_res = $c->prepare("SELECT s.class, COUNT(DISTINCT a.student_id) as marked_count FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.attendance_date = ? AND s.status='active' GROUP BY s.class");
$td = '2026-04-17';
$att_res->bind_param('s', $td);
$att_res->execute();
$res = $att_res->get_result();
while ($row = $res->fetch_assoc()) print_r($row);
?>
