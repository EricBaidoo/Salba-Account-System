<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'Admin_Eric';
$_SESSION['role'] = 'admin';
echo "Logged in locally as Admin_Eric! <a href='pages/finance/dashboard.php'>Go to Finance Dashboard</a>";
