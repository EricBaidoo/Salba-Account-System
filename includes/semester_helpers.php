<?php
// Idempotent helpers: guard function declarations to avoid redeclare on multiple includes

/**
 * Helper function to determine semester order in academic year
 * Returns array of terms that should be considered "previous" (for arrears calculation)
 */
if (!function_exists('getPreviousSemesters')) {
    function getPreviousSemesters($current_term) {
        $term_order = [
            'First Semester' => [],  // First semester has no previous terms in same academic year
            'Second Semester' => ['First Semester'],  // Second semester: First is previous
            'Third Semester' => ['First Semester', 'Second Semester']  // Third semester: First and Second are previous
        ];
        
        return $term_order[$current_term] ?? [];
    }
}

/**
 * Get the immediate previous semester (cyclic across academic years)
 */
if (!function_exists('getImmediatePreviousSemester')) {
    function getImmediatePreviousSemester($current_term) {
        $prev = [
            'First Semester' => 'Third Semester',
            'Second Semester' => 'First Semester',
            'Third Semester' => 'Second Semester',
        ];
        return $prev[$current_term] ?? 'First Semester';
    }
}

/**
 * Get next semester in sequence
 */
if (!function_exists('getNextSemester')) {
    function getNextSemester($current_term) {
        $next = [
            'First Semester' => 'Second Semester',
            'Second Semester' => 'Third Semester',
            'Third Semester' => 'First Semester'  // Cycles back
        ];
        
        return $next[$current_term] ?? 'First Semester';
    }
}

/**
 * Check if semester A comes before semester B in academic calendar
 */
if (!function_exists('isSemesterBefore')) {
    function isSemesterBefore($term_a, $term_b) {
        $order = ['First Semester' => 1, 'Second Semester' => 2, 'Third Semester' => 3];
        return ($order[$term_a] ?? 0) < ($order[$term_b] ?? 0);
    }
}

/**
 * Given a current semester and academic year (YYYY/YYYY or YYYY/YY),
 * compute the immediate previous semester and its academic year.
 */
if (!function_exists('getPreviousSemesterYear')) {
    function getPreviousSemesterYear($current_term, $current_academic_year) {
        $prev_term = getImmediatePreviousSemester($current_term);
        $prev_year = $current_academic_year;
        if ($current_term === 'First Semester') {
            $parts = explode('/', $current_academic_year);
            if (count($parts) === 2) {
                $start = intval($parts[0]);
                $endRaw = $parts[1];
                $prevStart = $start - 1;
                if (strlen($endRaw) === 2) {
                    $prevEnd = str_pad(($prevStart % 100), 2, '0', STR_PAD_LEFT);
                    $prev_year = $prevStart . '/' . $prevEnd;
                } else {
                    $prev_year = $prevStart . '/' . ($prevStart + 1);
                }
            }
        }
        return [$prev_term, $prev_year];
    }
}
?>
<?php
// Additional date range helpers for semester-scoped summaries

if (!function_exists('getAcademicYearStart')) {
    function getAcademicYearStart($conn, $academic_year) {
        // Defaults if settings missing: Sept 1st
        $start_month = getSystemSetting($conn, 'academic_year_start_month', '09');
        $start_day = getSystemSetting($conn, 'academic_year_start_day', '01');
        $parts = explode('/', $academic_year);
        $start_year = intval($parts[0] ?? date('Y'));
        $start_date = sprintf('%04d-%02d-%02d', $start_year, (int)$start_month, (int)$start_day);
        return $start_date;
    }
}

if (!function_exists('getSemesterDateRange')) {
    function getSemesterDateRange($conn, $semester, $academic_year) {
        // Divide academic year into 3 blocks of ~4 months each from the configured start.
        $start_date = getAcademicYearStart($conn, $academic_year);
        $start_ts = strtotime($start_date);
        // Semester offsets in months - support both "Semester 1" and "First Semester" formats
        $offsets = [
            'First Semester' => 0,
            'Second Semester' => 4,
            'Third Semester' => 8,
            'Semester 1' => 0,
            'Semester 2' => 4,
            'Semester 3' => 8,
        ];
        $offset = $offsets[$semester] ?? 0;
        $term_start_ts = strtotime("+{$offset} months", $start_ts);
        $term_end_ts = strtotime("+" . ($offset + 4) . " months -1 day", $start_ts);
        return [
            'start' => date('Y-m-d', $term_start_ts),
            'end' => date('Y-m-d', $term_end_ts),
        ];
    }
}
?>
