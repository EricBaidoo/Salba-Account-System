<?php
include 'includes/db_connect.php';

$queries = [
    "ALTER TABLE staff_attendance ADD COLUMN check_out_time DATETIME NULL AFTER check_in_time;",

    "CREATE TABLE IF NOT EXISTS `academic_semester_dictionary` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `semester_name` varchar(100) NOT NULL,
      `created_at` timestamp DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_semester` (`semester_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "TRUNCATE TABLE `academic_semester_dictionary`;",
    
    "INSERT IGNORE INTO `academic_semester_dictionary` (`semester_name`) VALUES 
    ('First Semester'),
    ('Second Semester'),
    ('Trimester');",
    
    "ALTER TABLE `staff_profiles` MODIFY `staff_type` VARCHAR(100) DEFAULT 'teaching';"
];

$success = true;
foreach ($queries as $i => $sql) {
    if($conn->query($sql)) {
        echo "Query " . ($i + 1) . " executed successfully.<br>";
    } else {
        echo "Error on Query " . ($i + 1) . ": " . $conn->error . "<br>";
        $success = false;
    }
}

if ($success) {
    echo "<br><b>All schema updates executed successfully!</b>";
}
?>
