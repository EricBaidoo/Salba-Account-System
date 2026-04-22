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

echo "<h3>Schema is up to date.</h3>";
?>
