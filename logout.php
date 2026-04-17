<?php
session_start();
include_once 'includes/db_connect.php';
session_unset();
session_destroy();
header('Location: ' . BASE_URL . 'login');
exit();
?>
