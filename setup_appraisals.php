<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Appraisal Database Setup Log</h2>";

$sql = "
CREATE TABLE IF NOT EXISTS `appraisals` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `teacher_id` int(11) NOT NULL,
    `supervisor_id` int(11) DEFAULT NULL,
    `admin_id` int(11) DEFAULT NULL,
    `appraisal_month` varchar(50) NOT NULL,
    `academic_year` varchar(20) NOT NULL,
    `date_of_appraisal` date DEFAULT NULL,
    `status` enum('draft_teacher', 'pending_supervisor', 'pending_admin', 'completed') DEFAULT 'draft_teacher',
    `total_max_score` int(11) DEFAULT 100,
    `overall_score` decimal(5,2) DEFAULT NULL,
    `performance_rating` varchar(50) DEFAULT NULL,
    `observation_checklist` text DEFAULT NULL,
    `strengths` text DEFAULT NULL,
    `areas_for_improvement` text DEFAULT NULL,
    `targets` text DEFAULT NULL,
    `cpd_support` text DEFAULT NULL,
    `appraiser_comments` text DEFAULT NULL,
    `teacher_comments` text DEFAULT NULL,
    `admin_comments` text DEFAULT NULL,
    `teacher_signature_date` datetime DEFAULT NULL,
    `supervisor_signature_date` datetime DEFAULT NULL,
    `admin_signature_date` datetime DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_teacher` (`teacher_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_scores` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `appraisal_id` int(11) NOT NULL,
    `section_name` varchar(100) NOT NULL,
    `criteria` varchar(255) NOT NULL,
    `max_score` int(11) NOT NULL,
    `teacher_score` int(11) DEFAULT NULL,
    `appraiser_score` int(11) DEFAULT NULL,
    `agreed_score` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_appraisal` (`appraisal_id`),
    CONSTRAINT `fk_appraisal_scores` FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        echo "<div style='color: red; margin-bottom: 5px;'>❌ Error during table execution: " . $conn->error . "</div>";
    } else {
        echo "<div style='color: green; margin-bottom: 5px;'>✅ Appraisal tables created/verified successfully.</div>";
    }
} else {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error starting table execution: " . $conn->error . "</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Setup script completed execution.</h3>";
echo "</div>";
?>
