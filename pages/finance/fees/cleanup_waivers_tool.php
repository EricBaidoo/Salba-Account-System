<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

// Only allow logged in users (admins)
if (!is_logged_in()) {
    die("Unauthorized. Please log in first.");
}

if (isset($_POST['run_cleanup'])) {
    // 1. Get the Waiver Fee ID
    $res = $conn->query("SELECT id FROM fees WHERE name = 'Waivers & Scholarships' LIMIT 1");
    if ($res->num_rows === 0) {
        die("Waiver fee not found in database.");
    }
    $waiver_fee_id = $res->fetch_assoc()['id'];
    
    // Find students with runaway positive waivers
    $students_res = $conn->query("SELECT DISTINCT student_id FROM student_fees WHERE fee_id = $waiver_fee_id AND amount > 0");
    $count = 0;
    while ($row = $students_res->fetch_assoc()) {
        $sid = $row['student_id'];
        
        // Delete all waivers for this student
        $conn->query("DELETE FROM student_fees WHERE student_id = $sid AND fee_id = $waiver_fee_id");
        
        // Recalculate their waivers from scratch using the FIXED math
        include_once '../../../includes/waiver_functions.php';
        apply_student_waivers($conn, $sid);
        $count++;
    }
    
    echo "<h3>Cleanup Complete!</h3>";
    echo "<p>Successfully cleaned and reset waivers for $count students.</p>";
    echo "<a href='../reports/student_balances.php'>Go back to Student Balances</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Waiver Cleanup Tool</title>
    <style>
        body { font-family: sans-serif; padding: 50px; text-align: center; background: #f8fafc; }
        .box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 500px; margin: 0 auto; }
        button { background: #4f46e5; color: white; border: none; padding: 15px 30px; font-size: 16px; border-radius: 10px; cursor: pointer; font-weight: bold; }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="color: #334155; margin-top: 0;">Waiver Loop Cleanup Tool</h2>
        <p style="color: #64748b; line-height: 1.6; margin-bottom: 30px;">This tool will automatically find any students stuck in the "exponential waiver loop" and reset their waivers cleanly so that the huge amounts disappear.</p>
        <form method="POST">
            <button type="submit" name="run_cleanup">Clean Up Database Now</button>
        </form>
    </div>
</body>
</html>
