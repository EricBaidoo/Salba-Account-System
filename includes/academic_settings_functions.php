<?php
/**
 * Academic Settings Helper Functions
 * Database and utility functions for academic configuration
 */

if (!function_exists('getAssessmentConfigs')) {
    function getAssessmentConfigs($conn, $academic_year, $term) {
        try {
            $stmt = $conn->prepare("
                SELECT * FROM assessment_configurations 
                WHERE academic_year = ? AND term = ? 
                ORDER BY is_exam ASC, id ASC
            ");
            $stmt->bind_param("ss", $academic_year, $term);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $configs = [];
            while ($row = $result->fetch_assoc()) {
                $configs[] = $row;
            }
            return $configs;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('addAssessmentConfig')) {
    function addAssessmentConfig($conn, $academic_year, $term, $name, $marks, $is_exam, $created_by) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO assessment_configurations 
                (academic_year, term, assessment_name, max_marks_allocation, is_exam, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssdis", $academic_year, $term, $name, $marks, $is_exam, $created_by);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('deleteAssessmentConfig')) {
    function deleteAssessmentConfig($conn, $config_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM assessment_configurations WHERE id = ?");
            $stmt->bind_param("i", $config_id);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getClassSubjectMappings')) {
    function getClassSubjectMappings($conn, $class_name = null) {
        try {
            if ($class_name) {
                $stmt = $conn->prepare("
                    SELECT cs.*, s.name as subject_name 
                    FROM class_subjects cs
                    JOIN subjects s ON cs.subject_id = s.id
                    WHERE cs.class_name = ?
                    ORDER BY s.name
                ");
                $stmt->bind_param("s", $class_name);
            } else {
                $stmt = $conn->prepare("
                    SELECT cs.*, s.name as subject_name 
                    FROM class_subjects cs
                    JOIN subjects s ON cs.subject_id = s.id
                    ORDER BY cs.class_name, s.name
                ");
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $mappings = [];
            while ($row = $result->fetch_assoc()) {
                $mappings[] = $row;
            }
            return $mappings;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getGradingScales')) {
    function getGradingScales($conn, $scale_name = null) {
        try {
            if ($scale_name) {
                $stmt = $conn->prepare("
                    SELECT * FROM grading_scales 
                    WHERE scale_name = ? 
                    ORDER BY sort_order ASC
                ");
                $stmt->bind_param("s", $scale_name);
            } else {
                $stmt = $conn->prepare("
                    SELECT DISTINCT scale_name FROM grading_scales 
                    ORDER BY scale_name
                ");
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $scales = [];
            while ($row = $result->fetch_assoc()) {
                $scales[] = $row;
            }
            return $scales;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getPassMarks')) {
    function getPassMarks($conn, $subject_id = null, $class_name = null, $academic_year = null) {
        try {
            $query = "SELECT * FROM pass_marks WHERE 1=1";
            $types = "";
            $params = [];
            
            if ($subject_id) {
                $query .= " AND subject_id = ?";
                $types .= "i";
                $params[] = $subject_id;
            }
            if ($class_name) {
                $query .= " AND class_name = ?";
                $types .= "s";
                $params[] = $class_name;
            }
            if ($academic_year) {
                $query .= " AND (academic_year = ? OR academic_year IS NULL)";
                $types .= "s";
                $params[] = $academic_year;
            }
            
            $stmt = $conn->prepare($query);
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $pass_marks = [];
            while ($row = $result->fetch_assoc()) {
                $pass_marks[] = $row;
            }
            return $pass_marks;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('calculateAssessmentTotals')) {
    function calculateAssessmentTotals($conn, $academic_year, $term) {
        try {
            $result = $conn->query("
                SELECT 
                    SUM(CASE WHEN is_exam = 0 THEN max_marks_allocation ELSE 0 END) as oa_total,
                    SUM(CASE WHEN is_exam = 1 THEN max_marks_allocation ELSE 0 END) as exam_total
                FROM assessment_configurations 
                WHERE academic_year = '$academic_year' AND term = '$term'
            ");
            
            $row = $result->fetch_assoc();
            return [
                'oa_total' => floatval($row['oa_total'] ?? 0),
                'exam_total' => floatval($row['exam_total'] ?? 0),
                'remaining_oa' => 100 - (floatval($row['oa_total'] ?? 0)),
                'remaining_exam' => 100 - (floatval($row['exam_total'] ?? 0))
            ];
        } catch (Exception $e) {
            return [
                'oa_total' => 0,
                'exam_total' => 0,
                'remaining_oa' => 100,
                'remaining_exam' => 100
            ];
        }
    }
}

if (!function_exists('validateWeights')) {
    function validateWeights($oa_weight, $exam_weight) {
        $total = floatval($oa_weight) + floatval($exam_weight);
        return $total == 100;
    }
}

if (!function_exists('getClassList')) {
    function getClassList($conn) {
        try {
            $result = $conn->query("
                SELECT DISTINCT name as class_name FROM classes 
                WHERE name IS NOT NULL AND name != ''
                ORDER BY name
            ");
            
            $classes = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $classes[] = $row['class_name'];
                }
            }
            return $classes;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getSubjectList')) {
    function getSubjectList($conn) {
        try {
            $result = $conn->query("
                SELECT id, name, code FROM subjects 
                ORDER BY name
            ");
            
            $subjects = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
            }
            return $subjects;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>
