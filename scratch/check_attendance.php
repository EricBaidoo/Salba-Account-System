<?php
$c=new mysqli('localhost','root','root','Salba_acc'); 
$r=$c->query('SELECT attendance_date, COUNT(*) FROM attendance GROUP BY attendance_date'); 
while($row=$r->fetch_assoc()) print_r($row);
?>
