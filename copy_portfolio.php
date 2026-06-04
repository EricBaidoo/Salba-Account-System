<?php
if(copy('pages/teacher/lesson_portfolio.php', 'pages/teacher/report_portfolio.php')) {
    echo "Copied successfully!";
} else {
    echo "Copy failed.";
}
