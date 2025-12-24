<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated_by = $_SESSION['username'] ?? 'Admin';
    
    // Update current term
    if (isset($_POST['current_term'])) {
        if (setSystemSetting($conn, 'current_term', $_POST['current_term'], $updated_by)) {
            $success_message .= 'Current term updated successfully. ';
        } else {
            $error_message .= 'Failed to update current term. ';
        }
    }
    
    // Update academic year (always stored in canonical full format YYYY/YYYY)
    if (isset($_POST['academic_year'])) {
        $year_value = trim($_POST['academic_year']);
        if (preg_match('/^(\d{4})\/(\d{2,4})$/', $year_value, $m)) {
            // Normalize to YYYY/YYYY
            $startY = (int)$m[1];
            $endPart = $m[2];
            if (strlen($endPart) === 2) {
                $century = substr((string)$startY, 0, 2);
                $endY = (int)($century . $endPart);
            } else {
                $endY = (int)$endPart;
            }
            $year_value = $startY . '/' . $endY;
        }
        if (setSystemSetting($conn, 'academic_year', $year_value, $updated_by)) {
            $success_message .= 'Academic year updated successfully. ';
        } else {
            $error_message .= 'Failed to update academic year. ';
        }
    }

    // Update academic year format preference (full or short)
    if (isset($_POST['academic_year_format'])) {
        $fmt = in_array($_POST['academic_year_format'], ['full','short'], true) ? $_POST['academic_year_format'] : 'full';
        setSystemSetting($conn, 'academic_year_format', $fmt, $updated_by);
    }

    // Update academic year start month/day used for reporting windows
    if (isset($_POST['academic_year_start_month'])) {
        $m = max(1, min(12, intval($_POST['academic_year_start_month'])));
        setSystemSetting($conn, 'academic_year_start_month', sprintf('%02d', $m), $updated_by);
    }
    if (isset($_POST['academic_year_start_day'])) {
        $d = max(1, min(31, intval($_POST['academic_year_start_day'])));
        setSystemSetting($conn, 'academic_year_start_day', sprintf('%02d', $d), $updated_by);
    }

    // Payment allocation scope: global (default) or term_year
    if (isset($_POST['payment_allocation_scope'])) {
        $scope = $_POST['payment_allocation_scope'] === 'term_year' ? 'term_year' : 'global';
        setSystemSetting($conn, 'payment_allocation_scope', $scope, $updated_by);
        $success_message .= ' Payment allocation scope updated.';
    }
    
    // Update school information
    $school_fields = ['school_name', 'school_address', 'school_phone', 'school_email'];
    foreach ($school_fields as $field) {
        if (isset($_POST[$field])) {
            setSystemSetting($conn, $field, $_POST[$field], $updated_by);
        }
    }
    
    if ($success_message) {
        $success_message = rtrim($success_message);
    }
}

// Get current settings
$all_settings = getAllSettings($conn);
$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableTerms();
$year_format = getSystemSetting($conn, 'academic_year_format', 'full');
$start_month = getSystemSetting($conn, 'academic_year_start_month', '09');
$start_day = getSystemSetting($conn, 'academic_year_start_day', '01');
$alloc_scope = getSystemSetting($conn, 'payment_allocation_scope', 'global');

// Build year options centered around current academic year start
$ay_parts = explode('/', $academic_year);
$anchor_start_year = intval($ay_parts[0] ?? date('Y'));
if ($anchor_start_year <= 0) { $anchor_start_year = (int)date('Y'); }
$year_options = [];
for ($i = -2; $i <= 5; $i++) {
    $y1 = $anchor_start_year + $i;
    $y2 = $y1 + 1;
    $val = $y1 . '/' . $y2; // canonical stored value
    $label = ($year_format === 'short')
        ? ($y1 . '/' . substr((string)$y2, -2))
        : $val;
    $year_options[] = ['value' => $val, 'label' => $label];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div>
                <h1 class="clean-page-title"><i class="fas fa-cog me-2"></i>System Settings</h1>
                <p class="clean-page-subtitle">Configure academic terms, school information, and system preferences</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-2"><i class="fas fa-cogs me-2"></i>System Settings</h2>
            <p class="lead mb-0">Centralized control for term, academic year, and school information</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Academic Term Settings -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Academic Term Settings</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important:</strong> Changing the current term will affect the entire system. 
                        All pages that use term data (invoices, billing, reports) will default to this term.
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="current_term" class="form-label fw-bold">
                                <i class="fas fa-calendar-check me-2"></i>Current Active Term *
                            </label>
                            <select class="form-select form-select-lg" id="current_term" name="current_term" required>
                                <?php foreach ($available_terms as $term): ?>
                                    <option value="<?php echo htmlspecialchars($term); ?>" 
                                            <?php echo $term === $current_term ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($term); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                This is the current term for the entire school system
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="academic_year" class="form-label fw-bold">
                                <i class="fas fa-calendar-alt me-2"></i>Academic Year *
                            </label>
                            <select class="form-select form-select-lg" id="academic_year" name="academic_year" required>
                                <?php foreach ($year_options as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt['value']); ?>" <?php echo ($opt['value'] === $academic_year) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Stored as YYYY/YYYY. Display format configurable below.
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-sliders-h me-2"></i>Academic Year Preferences</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold"><i class="fas fa-eye me-1"></i>Display Format</label>
                                            <select class="form-select" name="academic_year_format">
                                                <option value="full" <?php echo $year_format==='full'?'selected':''; ?>>Full (e.g., 2025/2026)</option>
                                                <option value="short" <?php echo $year_format==='short'?'selected':''; ?>>Short (e.g., 2025/26)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold"><i class="fas fa-calendar-day me-1"></i>Year Start Month</label>
                                            <input type="number" min="1" max="12" class="form-control" name="academic_year_start_month" value="<?php echo htmlspecialchars($start_month); ?>">
                                            <div class="form-text">Used to compute reporting windows (default 9)</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold"><i class="fas fa-calendar-day me-1"></i>Year Start Day</label>
                                            <input type="number" min="1" max="31" class="form-control" name="academic_year_start_day" value="<?php echo htmlspecialchars($start_day); ?>">
                                            <div class="form-text">Used to compute reporting windows (default 1)</div>
                                        </div>
                                    </div>
                                    <div class="alert alert-secondary py-2 mb-3">
                                        <strong>Preview:</strong>
                                        <?php
                                            $preview = ($year_format === 'short')
                                                ? (intval($ay_parts[0] ?? date('Y')) . '/' . substr((string)intval(($ay_parts[1] ?? (intval(date('Y'))+1))), -2))
                                                : $academic_year;
                                            echo htmlspecialchars($preview);
                                        ?>
                                    </div>
                                    <div class="row g-3 mb-1">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold"><i class="fas fa-project-diagram me-1"></i>Payment Allocation Scope</label>
                                            <select class="form-select" name="payment_allocation_scope">
                                                <option value="global" <?php echo $alloc_scope==='global'?'selected':''; ?>>Global (oldest fees first)</option>
                                                <option value="term_year" <?php echo $alloc_scope==='term_year'?'selected':''; ?>>Limit to selected Term + Year</option>
                                            </select>
                                            <div class="form-text">Choose how student payments are allocated to fees.</div>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-2"><i class="fas fa-history me-2"></i>Last Updated</h6>
                                    <?php if (isset($all_settings['current_term'])): ?>
                                        <p class="mb-1">
                                            <strong>Current Term:</strong> 
                                            <?php echo htmlspecialchars($all_settings['current_term']['setting_value']); ?>
                                            <span class="text-muted ms-2">
                                                (Updated: <?php echo date('M j, Y g:i A', strtotime($all_settings['current_term']['updated_at'])); ?> 
                                                by <?php echo htmlspecialchars($all_settings['current_term']['updated_by']); ?>)
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (isset($all_settings['academic_year'])): ?>
                                        <p class="mb-0">
                                            <strong>Academic Year:</strong> 
                                            <?php echo htmlspecialchars($all_settings['academic_year']['setting_value']); ?>
                                            <span class="text-muted ms-2">
                                                (Updated: <?php echo date('M j, Y g:i A', strtotime($all_settings['academic_year']['updated_at'])); ?> 
                                                by <?php echo htmlspecialchars($all_settings['academic_year']['updated_by']); ?>)
                                            </span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- School Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-school me-2"></i>School Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="school_name" class="form-label fw-bold">School Name</label>
                            <input type="text" class="form-control" id="school_name" name="school_name" 
                                   value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_name', '')); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="school_email" class="form-label fw-bold">School Email</label>
                            <input type="email" class="form-control" id="school_email" name="school_email" 
                                   value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_email', '')); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="school_phone" class="form-label fw-bold">School Phone</label>
                            <input type="text" class="form-control" id="school_phone" name="school_phone" 
                                   value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_phone', '')); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="school_address" class="form-label fw-bold">School Address</label>
                            <input type="text" class="form-control" id="school_address" name="school_address" 
                                   value="<?php echo htmlspecialchars(getSystemSetting($conn, 'school_address', '')); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-center mb-5">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-5 ms-3">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>

        <!-- All Settings Table -->
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All System Settings</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Setting Key</th>
                                <th>Current Value</th>
                                <th>Description</th>
                                <th>Last Updated</th>
                                <th>Updated By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_settings as $key => $setting): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($setting['setting_value']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($setting['description'] ?? ''); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($setting['updated_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($setting['updated_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
