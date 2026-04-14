<?php
$conn=new mysqli('localhost','root','root','Salba_acc');
$rs=$conn->query("SELECT * FROM attendance");
while($r = $rs->fetch_assoc()) {
    print_r($r);
}
?>
