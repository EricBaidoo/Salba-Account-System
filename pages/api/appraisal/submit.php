<?php
require_once '../../../includes/auth_functions.php';
require_once '../../../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'index', 'error', 'Invalid request method.');
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . 'index', 'error', 'Session expired. Please try again.');
}

$action = $_POST['action'] ?? '';
$appraisal_id = (int)($_POST['appraisal_id'] ?? 0);
$uid = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    if ($action === 'teacher_submit') {
        require_role(['staff', 'facilitator']);
        
        $month = $_POST['appraisal_month'] ?? date('F Y');
        $academic_year = $_POST['academic_year'] ?? 'Current'; // Should be dynamically set
        
        if (!$appraisal_id) {
            $stmt = $conn->prepare("INSERT INTO appraisals (teacher_id, appraisal_month, academic_year, date_of_appraisal, status) VALUES (?, ?, ?, NOW(), 'pending_supervisor')");
            $stmt->bind_param("iss", $uid, $month, $academic_year);
            $stmt->execute();
            $appraisal_id = $conn->insert_id;
        } else {
            // Ensure they own it
            $stmt = $conn->prepare("UPDATE appraisals SET status = 'pending_supervisor' WHERE id = ? AND teacher_id = ? AND status IN ('draft_teacher', 'pending_supervisor')");
            $stmt->bind_param("ii", $appraisal_id, $uid);
            $stmt->execute();
        }
        
        // Clear existing scores for this appraisal to allow clean insert (lazy but effective for drafts)
        $conn->query("DELETE FROM appraisal_scores WHERE appraisal_id = $appraisal_id AND teacher_score IS NOT NULL");

        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            $stmt = $conn->prepare("INSERT INTO appraisal_scores (appraisal_id, section_name, criteria, max_score, teacher_score) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['scores'] as $section => $criteria_list) {
                foreach ($criteria_list as $criteria => $data) {
                    $max = (int)($data['max'] ?? 5);
                    $score = (int)($data['score'] ?? 0);
                    $stmt->bind_param("issii", $appraisal_id, $section, $criteria, $max, $score);
                    $stmt->execute();
                }
            }
        }
        
        log_activity($conn, 'Appraisal', "Teacher submitted appraisal #$appraisal_id for supervisor review.");
        $conn->commit();
        redirect(BASE_URL . 'pages/teacher/appraisal_portfolio', 'success', 'Self-appraisal submitted successfully.');

    } elseif ($action === 'supervisor_submit') {
        require_role(['supervisor', 'admin']);
        
        $strengths = $_POST['strengths'] ?? '';
        $areas = $_POST['areas_for_improvement'] ?? '';
        $targets = $_POST['targets'] ?? '';
        $cpd = $_POST['cpd_support'] ?? '';
        $comments = $_POST['appraiser_comments'] ?? '';
        $checklist = json_encode($_POST['checklist'] ?? []);
        
        $stmt = $conn->prepare("UPDATE appraisals SET supervisor_id = ?, status = 'pending_admin', strengths = ?, areas_for_improvement = ?, targets = ?, cpd_support = ?, appraiser_comments = ?, observation_checklist = ?, supervisor_signature_date = NOW() WHERE id = ?");
        $stmt->bind_param("issssssi", $uid, $strengths, $areas, $targets, $cpd, $comments, $checklist, $appraisal_id);
        $stmt->execute();
        
        // Update Appraiser Scores
        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            $stmt = $conn->prepare("UPDATE appraisal_scores SET appraiser_score = ? WHERE appraisal_id = ? AND section_name = ? AND criteria = ?");
            foreach ($_POST['scores'] as $section => $criteria_list) {
                foreach ($criteria_list as $criteria => $data) {
                    $score = (int)($data['appraiser_score'] ?? 0);
                    $stmt->bind_param("iiss", $score, $appraisal_id, $section, $criteria);
                    $stmt->execute();
                }
            }
        }

        log_activity($conn, 'Appraisal', "Supervisor submitted evaluation for appraisal #$appraisal_id");
        $conn->commit();
        redirect(BASE_URL . 'pages/supervisor/staff_appraisals', 'success', 'Evaluation submitted to Administration.');

    } elseif ($action === 'admin_submit') {
        require_role('admin');
        
        $overall_score = (float)($_POST['overall_score'] ?? 0);
        $rating = $_POST['performance_rating'] ?? '';
        $admin_comments = $_POST['admin_comments'] ?? '';
        
        $stmt = $conn->prepare("UPDATE appraisals SET admin_id = ?, status = 'completed', overall_score = ?, performance_rating = ?, admin_comments = ?, admin_signature_date = NOW() WHERE id = ?");
        $stmt->bind_param("idssi", $uid, $overall_score, $rating, $admin_comments, $appraisal_id);
        $stmt->execute();
        
        // Final Agreed Scores
        if (isset($_POST['scores']) && is_array($_POST['scores'])) {
            $stmt = $conn->prepare("UPDATE appraisal_scores SET agreed_score = ? WHERE appraisal_id = ? AND section_name = ? AND criteria = ?");
            foreach ($_POST['scores'] as $section => $criteria_list) {
                foreach ($criteria_list as $criteria => $data) {
                    $score = (int)($data['agreed_score'] ?? 0);
                    $stmt->bind_param("iiss", $score, $appraisal_id, $section, $criteria);
                    $stmt->execute();
                }
            }
        }

        log_activity($conn, 'Appraisal', "Administration finalized appraisal #$appraisal_id");
        $conn->commit();
        redirect(BASE_URL . 'pages/administration/staff_appraisals', 'success', 'Appraisal finalized and archived.');
    } else {
        throw new Exception("Unknown action: " . $action);
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Appraisal Submit Error: " . $e->getMessage());
    $redirect_url = BASE_URL . 'index';
    if (has_role(['staff', 'facilitator'])) $redirect_url = BASE_URL . 'pages/teacher/appraisal_portfolio';
    if (has_role('supervisor')) $redirect_url = BASE_URL . 'pages/supervisor/staff_appraisals';
    if (has_role('admin')) $redirect_url = BASE_URL . 'pages/administration/staff_appraisals';
    redirect($redirect_url, 'error', 'An error occurred while saving the appraisal.');
}
?>
