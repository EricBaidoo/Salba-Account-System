<?php
$conn=new mysqli('localhost','root','root','Salba_acc');
$stmt = $conn->prepare("
    SELECT s.id, s.first_name, s.last_name, a.status, a.remarks 
    FROM students s 
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = '2026-04-14' 
    WHERE s.class = 'Basic 1' AND s.status = 'active'
    ORDER BY s.first_name ASC LIMIT 2
");
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()){
    print_r($row);
}
?>
