<?php
include 'includes/db_connect.php';
$r=$conn->query('SELECT name, Level FROM classes'); 
while($row=$r->fetch_assoc()) { print_r($row); }
