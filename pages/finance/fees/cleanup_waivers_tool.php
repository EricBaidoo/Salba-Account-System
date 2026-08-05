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
        
        // Recalculate their waivers from scratch using the FIXED math, for ALL their active semesters
        include_once '../../../includes/waiver_functions.php';
        
        $terms_res = $conn->query("SELECT DISTINCT semester, academic_year FROM student_fees WHERE student_id = $sid AND semester IS NOT NULL AND academic_year IS NOT NULL");
        while ($term = $terms_res->fetch_assoc()) {
            apply_student_waivers($conn, $sid, $term['semester'], $term['academic_year']);
        }
        $count++;
    }
    
    echo "<h3>Cleanup Complete!</h3>";
    echo "<p>Successfully cleaned and reset waivers for $count students.</p>";
    echo "<a href='../reports/student_balances.php'>Go back to Student Balances</a>";
    exit;
}

if (isset($_POST['fix_student'])) {
    $sid = intval($_POST['student_id']);
    
    if (isset($_POST['delete_feeding'])) {
        $feed_res = $conn->query("SELECT id FROM fees WHERE name = 'Feeding Fee' LIMIT 1");
        if ($feed_res->num_rows > 0) {
            $feed_id = $feed_res->fetch_assoc()['id'];
            $conn->query("DELETE FROM student_fees WHERE student_id = $sid AND fee_id = $feed_id");
        }
    }
    
    if (isset($_POST['override_arrears']) && $_POST['override_arrears'] !== '') {
        $target = floatval($_POST['override_arrears']);
        // Find the Outstanding Balance fee ID
        $ob_res = $conn->query("SELECT id FROM fees WHERE name = 'Outstanding Balance' LIMIT 1");
        if ($ob_res->num_rows > 0) {
            $ob_id = $ob_res->fetch_assoc()['id'];
            $conn->query("UPDATE student_fees SET amount = $target WHERE student_id = $sid AND fee_id = $ob_id");
        }
    }
    
    echo "<h3>Student $sid Fixed!</h3>";
    echo "<a href='../reports/student_balance_details.php?id=$sid'>Go back to Student's Account</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Waiver Cleanup & Fix Tool</title>
    <style>
        body { font-family: sans-serif; padding: 50px; text-align: center; background: #f8fafc; }
        .box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); max-width: 500px; margin: 0 auto; margin-bottom: 20px; }
        button { background: #4f46e5; color: white; border: none; padding: 15px 30px; font-size: 16px; border-radius: 10px; cursor: pointer; font-weight: bold; }
        button:hover { background: #4338ca; }
        input[type="number"] { padding: 10px; border: 1px solid #ccc; border-radius: 5px; width: 100%; margin-bottom: 10px; box-sizing: border-box; }
        .label { display: block; text-align: left; margin-bottom: 5px; font-weight: bold; color: #334155; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="color: #334155; margin-top: 0;">Global Waiver Loop Cleanup</h2>
        <p style="color: #64748b; line-height: 1.6; margin-bottom: 30px;">This tool will automatically find any students stuck in the "exponential waiver loop" and reset their waivers cleanly.</p>
        <form method="POST">
            <button type="submit" name="run_cleanup">Clean Up Database Now</button>
        </form>
    </div>
    
    <div class="box">
        <h2 style="color: #334155; margin-top: 0;">Manual Student Fix Override</h2>
        <p style="color: #64748b; line-height: 1.6; margin-bottom: 20px;">Use this if a specific student's outstanding balance got messed up, or if their feeding fee refuses to delete.</p>
        <form method="POST">
            <label class="label">Student ID (Look at the URL when viewing their account: ?id=...)</label>
            <input type="number" name="student_id" required placeholder="e.g. 89">
            
            <label class="label">Force Outstanding Balance to exactly (GH₵):</label>
            <input type="number" step="0.01" name="override_arrears" placeholder="e.g. 317.00">
            
            <label class="label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="delete_feeding" style="width: auto;">
                Force Delete Feeding Fee
            </label>
            
            <button type="submit" name="fix_student" style="background: #dc2626; margin-top: 20px; width: 100%;">Force Fix Student</button>
        </form>
    </div>
</body>
</html>
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
