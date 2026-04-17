<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- REFACTORING SYSTEM SETTINGS KEYS (SAFE MERGE) ---\n";

$renames = [
    'current_term' => 'current_semester',
    'weeks_per_term' => 'weeks_per_semester',
    'term_oa_weight' => 'semester_oa_weight',
    'term_exam_weight' => 'semester_exam_weight',
];

foreach ($renames as $old => $new) {
    echo "Processing $old -> $new...\n";
    
    // Check if new exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $new);
    $stmt->execute();
    $newExists = ($stmt->get_result()->fetch_row()[0] > 0);
    $stmt->close();

    // Check if old exists
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $old);
    $stmt->execute();
    $oldRes = $stmt->get_result();
    $oldData = $oldRes->fetch_assoc();
    $stmt->close();

    if (!$oldData) {
        echo "  - $old does not exist. Skipping.\n";
        continue;
    }

    if ($newExists) {
        echo "  - $new already exists. Merging value from $old.\n";
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $oldData['setting_value'], $new);
        $stmt->execute();
        $stmt->close();
        
        echo "  - Deleting legacy key $old.\n";
        $stmt = $conn->prepare("DELETE FROM system_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $old);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "  - Renaming $old to $new.\n";
        $stmt = $conn->prepare("UPDATE system_settings SET setting_key = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $new, $old);
        $stmt->execute();
        $stmt->close();
    }
}

echo "\n--- SETTINGS REFACTORING COMPLETED ---\n";
?>
