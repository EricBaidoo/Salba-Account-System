<?php
$conn=new mysqli('localhost','root','root','Salba_acc');
$rs=$conn->query("SHOW CREATE TABLE attendance");
$r = $rs->fetch_array();
echo $r[1];
?>
