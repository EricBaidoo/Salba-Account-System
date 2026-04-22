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

// 1. LESSON PLAN MODERNIZATION (GES STANDARDIZATION)
applyPatch($conn, 'lesson_plan_ges_modernization', [
    "ALTER TABLE lesson_plans MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending'",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `references` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `tlm` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `week_ending` DATE NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `day_of_week` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `duration` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `strand` VARCHAR(255) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `sub_strand` VARCHAR(255) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `class_size` INT DEFAULT 0",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `content_standard` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `indicator` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `lesson_number` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `performance_indicator` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `core_competencies` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `new_words` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `starter_activities` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `starter_resources` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `learning_activities` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `learning_resources` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `learning_assessment` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `reflection_activities` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `reflection_resources` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `homework` TEXT NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `phase1_duration` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `phase2_duration` VARCHAR(20) NULL",
    "ALTER TABLE lesson_plans ADD COLUMN IF NOT EXISTS `phase3_duration` VARCHAR(20) NULL"
]);

// 2. HIGH-CAPACITY TEXT FIELDS (EXPANSION TO MEDIUMTEXT)
applyPatch($conn, 'high_capacity_text_expansion', [
    // Lesson Plans
    "ALTER TABLE lesson_plans MODIFY objectives MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY content_standard MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY indicator MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY performance_indicator MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY core_competencies MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY references_materials MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY new_words MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY starter_activities MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY starter_resources MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY learning_activities MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY learning_resources MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY learning_assessment MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY reflection_activities MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY reflection_resources MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY homework MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY `references` MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY tlm MEDIUMTEXT NULL",
    "ALTER TABLE lesson_plans MODIFY supervisor_comments MEDIUMTEXT NULL",
    
    // Student Term Remarks
    "ALTER TABLE student_term_remarks MODIFY attitude MEDIUMTEXT NULL",
    "ALTER TABLE student_term_remarks MODIFY conduct MEDIUMTEXT NULL",
    "ALTER TABLE student_term_remarks MODIFY talent_and_interest MEDIUMTEXT NULL",
    "ALTER TABLE student_term_remarks MODIFY teacher_remarks MEDIUMTEXT NULL",
    "ALTER TABLE student_term_remarks MODIFY supervisor_remarks MEDIUMTEXT NULL",
    
    // Student Semester Remarks
    "ALTER TABLE student_semester_remarks MODIFY attitude MEDIUMTEXT NULL",
    "ALTER TABLE student_semester_remarks MODIFY conduct MEDIUMTEXT NULL",
    "ALTER TABLE student_semester_remarks MODIFY talent_and_interest MEDIUMTEXT NULL",
    "ALTER TABLE student_semester_remarks MODIFY teacher_remarks MEDIUMTEXT NULL",
    "ALTER TABLE student_semester_remarks MODIFY supervisor_remarks MEDIUMTEXT NULL",
    
    // Communication & Logs
    "ALTER TABLE announcements MODIFY message MEDIUMTEXT NULL",
    "ALTER TABLE messages MODIFY body MEDIUMTEXT NULL",
    "ALTER TABLE attendance MODIFY remarks MEDIUMTEXT NULL"
]);

// 3. SEMESTER INTEGRITY SYNC (DATA MIGRATION & REPAIR)
applyPatch($conn, 'semester_integrity_sync', [
    // Normalize "Third Semester" variants to "Trimester" across all logic tables
    "UPDATE assessment_configurations SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE attendance SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE budgets SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE expenses SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE grades SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE lesson_plans SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE payments SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE semester_budgets SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE semester_invoices SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE student_fees SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE student_semester_remarks SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE student_term_remarks SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    "UPDATE teacher_allocations SET semester = 'Trimester' WHERE semester IN ('Third Semester', 'Third Semesters', 'Third Term')",
    
    // Ensure "Trimester" exists in the dictionary if it's the current context
    "INSERT IGNORE INTO academic_semester_dictionary (semester_name) VALUES ('Trimester')"
]);

echo "<h3>Schema is up to date.</h3>";
?>
