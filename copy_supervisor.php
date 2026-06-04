<?php
if(copy('pages/supervisor/lesson_plans.php', 'pages/supervisor/weekly_reports.php')) {
    echo "Copied successfully!";
} else {
    echo "Copy failed.";
}
