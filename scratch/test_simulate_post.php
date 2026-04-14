<?php
$conn=new mysqli('localhost','root','root','Salba_acc');
$date_to_mark = '2026-04-14';
$sid = 19; // A valid active student ID
$stat = 'absent';
$rem = '';
$current_term = 'Term 1';
$current_year = '2025/2026';

$check = $conn->query("SELECT id FROM attendance WHERE student_id = $sid AND attendance_date = '$date_to_mark'");
if ($check->num_rows > 0) {
    if(!$conn->query("UPDATE attendance SET status = '$stat', remarks = '$rem' WHERE student_id = $sid AND attendance_date = '$date_to_mark'")) {
        echo "Update Err: " . $conn->error;
    } else { echo "Updated!\n"; }
} else {
    if(!$conn->query("INSERT INTO attendance (student_id, attendance_date, status, remarks, term, academic_year) VALUES ($sid, '$date_to_mark', '$stat', '$rem', '$current_term', '$current_year')")) {
        echo "Insert Err: " . $conn->error;
    } else { echo "Inserted!\n"; }
}
?>
