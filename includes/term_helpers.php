<?php
// Idempotent helpers: guard function declarations to avoid redeclare on multiple includes

/**
 * Helper function to determine term order in academic year
 * Returns array of terms that should be considered "previous" (for arrears calculation)
 */
if (!function_exists('getPreviousTerms')) {
    function getPreviousTerms($current_term) {
        $term_order = [
            'First Term' => [],  // First term has no previous terms in same academic year
            'Second Term' => ['First Term'],  // Second term: First is previous
            'Third Term' => ['First Term', 'Second Term']  // Third term: First and Second are previous
        ];
        
        return $term_order[$current_term] ?? [];
    }
}

/**
 * Get the immediate previous term (cyclic across academic years)
 */
if (!function_exists('getImmediatePreviousTerm')) {
    function getImmediatePreviousTerm($current_term) {
        $prev = [
            'First Term' => 'Third Term',
            'Second Term' => 'First Term',
            'Third Term' => 'Second Term',
        ];
        return $prev[$current_term] ?? 'First Term';
    }
}

/**
 * Get next term in sequence
 */
if (!function_exists('getNextTerm')) {
    function getNextTerm($current_term) {
        $next = [
            'First Term' => 'Second Term',
            'Second Term' => 'Third Term',
            'Third Term' => 'First Term'  // Cycles back
        ];
        
        return $next[$current_term] ?? 'First Term';
    }
}

/**
 * Check if term A comes before term B in academic calendar
 */
if (!function_exists('isTermBefore')) {
    function isTermBefore($term_a, $term_b) {
        $order = ['First Term' => 1, 'Second Term' => 2, 'Third Term' => 3];
        return ($order[$term_a] ?? 0) < ($order[$term_b] ?? 0);
    }
}

/**
 * Given a current term and academic year (YYYY/YYYY or YYYY/YY),
 * compute the immediate previous term and its academic year.
 */
if (!function_exists('getPreviousTermYear')) {
    function getPreviousTermYear($current_term, $current_academic_year) {
        $prev_term = getImmediatePreviousTerm($current_term);
        $prev_year = $current_academic_year;
        if ($current_term === 'First Term') {
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
// Additional date range helpers for term-scoped summaries

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

if (!function_exists('getTermDateRange')) {
    function getTermDateRange($conn, $term, $academic_year) {
        // Divide academic year into 3 blocks of ~4 months each from the configured start.
        $start_date = getAcademicYearStart($conn, $academic_year);
        $start_ts = strtotime($start_date);
        // Term offsets in months
        $offsets = [
            'First Term' => 0,
            'Second Term' => 4,
            'Third Term' => 8,
        ];
        $offset = $offsets[$term] ?? 0;
        $term_start_ts = strtotime("+{$offset} months", $start_ts);
        $term_end_ts = strtotime("+" . ($offset + 4) . " months -1 day", $start_ts);
        return [
            'start' => date('Y-m-d', $term_start_ts),
            'end' => date('Y-m-d', $term_end_ts),
        ];
    }
}
?>
