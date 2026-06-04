<?php
include 'includes/db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS weekly_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    subject_id INT NOT NULL,
    week_ending DATE NOT NULL,
    week_number INT NOT NULL,
    academic_term VARCHAR(50) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    supervisor_comments TEXT NULL,
    supervisor_id INT NULL,
    
    -- Content Fields (JSON where appropriate)
    lesson_delivery_summary JSON NULL,
    punctuality_preparedness JSON NULL,
    class_performance_rating VARCHAR(20) NULL,
    academic_evaluation TEXT NULL,
    homework_report JSON NULL,
    discipline_report JSON NULL,
    achievements TEXT NULL,
    challenges TEXT NULL,
    support_required JSON NULL,
    support_comments TEXT NULL,
    next_week_targets JSON NULL,
    teacher_reflection TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Table 'weekly_reports' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
