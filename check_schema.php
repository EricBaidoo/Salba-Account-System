<?php
include 'includes/db_connect.php';
$res = $conn->query("DESCRIBE staff_attendance");
if($res){
    while($row = $res->fetch_assoc()){
        print_r($row);
    }
} else {
    echo "Table not found";
}
?>
