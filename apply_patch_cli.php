<?php
include 'includes/db_connect.php';

echo "Running DB migration...\n";

$queries = [
    "DROP TABLE IF EXISTS weekly_reports",
    "CREATE TABLE weekly_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_name VARCHAR(100) NOT NULL,
        week_ending DATE NOT NULL,
        week_number INT NOT NULL,
        academic_term VARCHAR(50) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        status VARCHAR(20) DEFAULT 'draft',
        supervisor_comments MEDIUMTEXT NULL,
        supervisor_id INT NULL,
        
        -- Academic Coverage & Performance
        topics_covered MEDIUMTEXT NULL,
        assessments_conducted MEDIUMTEXT NULL,
        overall_performance VARCHAR(50) NULL,
        struggling_students MEDIUMTEXT NULL,
        
        -- Classroom Management & Behavior
        general_behavior MEDIUMTEXT NULL,
        discipline_issues MEDIUMTEXT NULL,
        attendance_concerns MEDIUMTEXT NULL,
        
        -- Parent Engagement
        parents_contacted MEDIUMTEXT NULL,
        
        -- Teacher's Challenges & Needs
        challenges_faced MEDIUMTEXT NULL,
        support_required MEDIUMTEXT NULL,
        next_week_focus MEDIUMTEXT NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE INDEX idx_weekly_reports_teacher ON weekly_reports(teacher_id)",
    "CREATE INDEX idx_weekly_reports_query ON weekly_reports(academic_term, academic_year, status)"
];

foreach ($queries as $sql) {
    if (!$conn->query($sql)) {
        echo "Error: " . $conn->error . "\n";
    } else {
        echo "Query executed successfully.\n";
    }
}
echo "Migration complete.\n";
?>
