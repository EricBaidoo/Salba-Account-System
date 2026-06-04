<?php
include 'includes/db_connect.php';
include 'includes/auth_functions.php';

session_start();

// Security: Only admins can run schema updates
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die("Access Denied: You must be an administrator to run schema updates.");
}

// Ensure the migration tracker exists
$conn->query("CREATE TABLE IF NOT EXISTS _migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patch_name VARCHAR(255) UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];

/**
 * Helper function to apply a patch safely
 */
function applyPatch($conn, $patch_name, $queries) {
    global $db_name;
    
    // Check if patch already applied
    $check = $conn->prepare("SELECT id FROM _migration_log WHERE patch_name = ?");
    $check->bind_param("s", $patch_name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // echo "Patch '$patch_name' already applied. Skipping.<br>";
        return;
    }

    echo "Applying patch: <b>$patch_name</b>...<br>";
    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            echo "<span style='color:red'>Error in $patch_name: " . $conn->error . "</span><br>";
            return; // Stop on error
        }
    }

    $stmt = $conn->prepare("INSERT INTO _migration_log (patch_name) VALUES (?)");
    $stmt->bind_param("s", $patch_name);
    $stmt->execute();
    echo "<span style='color:green'>Patch '$patch_name' applied successfully.</span><hr>";
}

// ============================================================================
// PATCH LOG STARTS HERE
// ============================================================================

// 1. WEEKLY REPORTS DASHBOARD MIGRATION
applyPatch($conn, 'weekly_reports_dashboard_v2', [
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
]);

echo "<h3>Schema is up to date.</h3>";
?>
