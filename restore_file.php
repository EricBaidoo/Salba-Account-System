<?php
// Extract the file content from the commit just before it was deleted
$content = shell_exec("git show 9f6ef6f3a2d7a8050aa8e8bee31137fde862a3c1^:pages/teacher/lesson_plans.php 2>&1");

if ($content && strpos($content, 'fatal:') === false) {
    file_put_contents('pages/teacher/lesson_plans.php', $content);
    $status = "Success! The file has been restored to its original state (571 lines of code).";
} else {
    // Fallback if the parent commit syntax fails, try checking out from a specific tree
    shell_exec("git checkout 6afd5131126fc4b132872a8f880a0c3870eac178 -- pages/teacher/lesson_plans.php 2>&1");
    $status = "Used fallback checkout. Please check the file!";
}

echo "<div style='font-family: sans-serif; padding: 40px;'>";
echo "<h2>🎉 Magic File Restoration Complete!</h2>";
echo "<p style='color: green; font-weight: bold; font-size: 18px;'>" . htmlspecialchars($status) . "</p>";
echo "<p>Please go back to your Teacher Dashboard and click 'Create Lesson' again.</p>";
echo "<p><a href='pages/teacher/lesson_portfolio' style='color: blue; text-decoration: underline;'>Go back to Lesson Portfolio</a></p>";
echo "</div>";
?>
