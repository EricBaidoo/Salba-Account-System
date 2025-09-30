<?php include '../includes/auth_check.php';
include '../includes/db_connect.php';

// Fetch students with their classes
$students = $conn->query("SELECT id, first_name, last_name, class FROM students ORDER BY class, first_name, last_name");

// Fetch fees with their types and amounts
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type, f.description,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GH₵', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary School'
                       END, ':GH₵', FORMAT(fa.amount, 2)
                   )
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type, f.description
    ORDER BY f.name";
$fees = $conn->query($fees_query);

// Fetch all classes from the classes table for dropdowns
$classes_result = $conn->query("SELECT name FROM classes ORDER BY id ASC");
$class_options = [];
while ($row = $classes_result->fetch_assoc()) {
    $class_options[] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignment - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Enhanced Modern Styling */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        /* Card Styling */
        .selection-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .selection-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .selection-card:hover {
            border-color: #667eea;
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }
        .selection-card:hover::before {
            opacity: 1;
        }
        .selection-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }
        .selection-card.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 15px;
            background: #667eea;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            z-index: 10;
        }
        
        /* Student Cards */
        .student-card {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .student-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        .student-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        .fee-card {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .fee-card:hover {
            border-color: #48bb78;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(72, 187, 120, 0.2);
        }
        .fee-card.selected {
            border-color: #48bb78;
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.1) 0%, rgba(56, 161, 105, 0.1) 100%);
        }
        .fee-amount-display {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            border-radius: 8px;
            padding: 0.5rem;
            font-weight: 600;
            text-align: center;
        }
        .class-badge {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .search-box {
            position: relative;
        }
        .search-box .fas {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #667eea;
        }
        .search-box input {
            padding-left: 45px;
        }
        
        /* Assignment Type Cards */
        .assignment-type-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .assignment-type-card:hover .card {
            border-color: #667eea !important;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        .assignment-type-card.active .card {
            border-color: #667eea !important;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }
        .assignment-type-card .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        /* Enhanced Card Styling */
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        .animate-slide-up {
            animation: slideUp 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Background Gradient */
        .bg-gradient {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        /* Multi-Selection Fee Cards */
        .fee-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        .fee-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.5);
        }
        .fee-card.selected {
            border: 2px solid #667eea !important;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.1));
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .fee-card .selection-checkbox {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 22px;
            height: 22px;
            border: 2px solid #dee2e6;
            border-radius: 4px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .fee-card.selected .selection-checkbox {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        .fee-card .selection-checkbox i {
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .fee-card.selected .selection-checkbox i {
            opacity: 1;
        }
        
        /* Container Enhancements */
        .glass-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        }
        
        .fee-summary {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(25, 135, 84, 0.05));
            border: 2px solid rgba(40, 167, 69, 0.2);
            border-radius: 12px;
            padding: 1rem;
        }
        
        /* Student Cards Enhancement */
        .student-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .student-card:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .student-card.selected {
            border: 2px solid #667eea !important;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
        }
        
        /* Class Badge */
        .class-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Form Controls */
        .form-select:focus,
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Button Enhancements */
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a4190);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Assignment Header */
        .assignment-header h1 {
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        /* Selection Counter */
        .selection-counter {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        /* Student Card Selection Styling */
        .student-card {
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .student-card.selected {
            border-color: #fd7e14;
            background: linear-gradient(135deg, rgba(253, 126, 20, 0.1), rgba(232, 62, 140, 0.05));
            transform: translateY(-1px);
        }

        .student-card .selection-checkbox {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            background: #fd7e14;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            z-index: 2;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .student-card.selected .selection-checkbox {
            opacity: 1;
            transform: scale(1);
        }

        /* Student Counter Styling */
        .student-counter {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }

        /* Selected Students List */
        .selected-students-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .selected-student-item {
            background: rgba(253, 126, 20, 0.1);
            border: 1px solid rgba(253, 126, 20, 0.2);
            border-radius: 8px;
            padding: 0.5rem;
            margin: 0.25rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Enhanced Submit Button Styles */
        #submitBtn {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            border: none !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            min-height: 50px !important;
        }

        #submitBtn:hover:not(:disabled) {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4) !important;
            background: linear-gradient(135deg, #218838, #1e7e34) !important;
        }

        #submitBtn:disabled {
            background: linear-gradient(135deg, #6c757d, #5a6268) !important;
            box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2) !important;
            cursor: not-allowed !important;
            transform: none !important;
            opacity: 0.7 !important;
        }

        #submitBtn:active:not(:disabled) {
            transform: translateY(0px) !important;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3) !important;
        }

        /* Reset Button Styles */
        .btn-outline-secondary {
            border: 2px solid #6c757d !important;
            border-radius: 12px !important;
            transition: all 0.3s ease !important;
            min-height: 50px !important;
        }

        .btn-outline-secondary:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.2) !important;
        }

        /* Form Status Styling */
        #formStatus {
            font-size: 0.9rem !important;
            padding: 8px 16px !important;
            border-radius: 20px !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            display: inline-block !important;
        }

        /* Assignment Details Always Visible */
        #assignmentDetails {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }

        /* Badge Styling */
        #assignmentSummary {
            font-size: 0.9rem !important;
            padding: 8px 12px !important;
            border-radius: 20px !important;
        }

        /* Floating Submit Button */
        .floating-submit-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .floating-submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 50px;
            padding: 15px 25px;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            min-width: 200px;
            position: relative;
            overflow: hidden;
        }

        .floating-submit-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(40, 167, 69, 0.4);
        }

        .floating-submit-btn:disabled {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .floating-submit-btn .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            gap: 10px;
        }

        .floating-submit-btn .btn-counter {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .floating-submit-btn .fas {
            font-size: 18px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .floating-submit-container {
                bottom: 20px;
                right: 20px;
            }
            
            .floating-submit-btn {
                min-width: 160px;
                padding: 12px 20px;
            }
            
            .floating-submit-btn .btn-text {
                font-size: 14px;
            }
        }
        
        /* Form Controls */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a4190);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        /* Animations */
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-up { animation: slideInUp 0.6s ease-out; }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-fade-in { animation: fadeIn 0.8s ease-out; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Header Section -->
        <div class="assignment-header text-center py-5 animate-fade-in">
            <div class="container">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="fas fa-user-tag me-3"></i>
                    Fee Assignment Center
                </h1>
                <p class="lead mb-4">Assign multiple fees to individual students or entire classes with ease</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="view_assigned_fees.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-list me-2"></i>View Assignments
                    </a>
                    <a href="student_balances.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-balance-scale me-2"></i>Student Balances
                    </a>
                    <a href="dashboard.php" class="btn btn-light btn-lg">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Assignment Type Selection -->
        <div class="container mb-5">
            <div class="glass-container p-4 animate-slide-up">
                <div class="section-header">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clipboard-list fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-1">Assignment Type</h4>
                            <p class="mb-0 opacity-75">Choose how you want to assign the fees</p>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="assignment-type-card selection-card" data-type="individual">
                            <div class="card-body text-center p-4">
                                <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg, #667eea, #764ba2); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-user fa-lg"></i>
                                </div>
                                <h6 class="mb-2 fw-bold">Single Student</h6>
                                <p class="text-muted mb-0 small">Assign fees to one specific student</p>
                                <div class="mt-2">
                                    <span class="badge bg-primary">Individual</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="assignment-type-card selection-card" data-type="multi-student">
                            <div class="card-body text-center p-4">
                                <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg, #fd7e14, #e83e8c); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-user-friends fa-lg"></i>
                                </div>
                                <h6 class="mb-2 fw-bold">Multiple Students</h6>
                                <p class="text-muted mb-0 small">Select and assign fees to multiple students</p>
                                <div class="mt-2">
                                    <span class="badge bg-warning text-dark">Multi-Select</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="assignment-type-card selection-card" data-type="class">
                            <div class="card-body text-center p-4">
                                <div class="icon-circle mx-auto mb-3" style="background: linear-gradient(135deg, #28a745, #20c997); width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-users fa-lg"></i>
                                </div>
                                <h6 class="mb-2 fw-bold">Entire Class</h6>
                                <p class="text-muted mb-0 small">Assign fees to all students in a class</p>
                                <div class="mt-2">
                                    <span class="badge bg-success">Bulk Assignment</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="assign_fee.php" method="POST" id="assignFeeForm" onsubmit="return handleSubmit(event)">
            <input type="hidden" name="assignment_type" id="assignmentType" value="individual">
            <input type="hidden" name="selectedFeesInput" id="selectedFeesInput">
            
            <div class="container">
                <div class="row g-4" id="assignmentContent">
                <!-- Selection Panel -->
                <div class="col-lg-6 mb-4">
                    <div class="glass-container animate-slide-up">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-users me-3"></i>
                                    <h5 class="mb-0" id="selectionTitle">Select Student</h5>
                                </div>
                                <span class="badge bg-light text-dark" id="selectionBadge">Individual Mode</span>
                            </div>
                        </div>
                            <!-- Individual Student Selection -->
                            <div id="individualSelection">
                                <div class="search-box mb-3">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="form-control" id="studentSearch" placeholder="Search students by name or class...">
                                </div>

                            <!-- Students List -->
                            <div class="student-list" style="max-height: 400px; overflow-y: auto;">
                                <?php 
                                $students->data_seek(0); // Reset result pointer
                                $current_class = '';
                                while($student = $students->fetch_assoc()): 
                                    if ($current_class !== $student['class']):
                                        if ($current_class !== '') echo '</div>';
                                        $current_class = $student['class'];
                                        echo '<h6 class="text-muted mt-3 mb-2"><i class="fas fa-layer-group me-2"></i>' . htmlspecialchars($current_class) . '</h6>';
                                        echo '<div class="class-group">';
                                    endif;
                                ?>
                                    <div class="card student-card mb-2" data-student-id="<?php echo $student['id']; ?>" data-student-class="<?php echo htmlspecialchars($student['class']); ?>" data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                        <div class="selection-checkbox" style="display: none;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                </div>
                                                <span class="class-badge"><?php echo htmlspecialchars($student['class']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                <?php if ($current_class !== '') echo '</div>'; ?>
                            </div>

                                <input type="hidden" name="selectedStudentId" id="selectedStudentId">
                                <input type="hidden" name="selectedStudentIds" id="selectedStudentIds">
                                <div id="selectedStudentDisplay" class="fee-summary d-none mt-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-check fa-2x me-3 text-success"></i>
                                        <div>
                                            <h6 class="mb-1 text-success">Selected Student</h6>
                                            <p class="mb-0 fw-bold" id="selectedStudentName"></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Student Selection Display -->
                                <div id="selectedStudentsDisplay" class="fee-summary d-none mt-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-friends fa-2x me-3 text-warning"></i>
                                            <div>
                                                <h6 class="mb-1 text-warning">Selected Students</h6>
                                                <span class="student-counter badge bg-warning text-dark" id="studentCounter">0 Selected</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="clearStudents">
                                            <i class="fas fa-times me-1"></i>Clear All
                                        </button>
                                    </div>
                                    <div class="selected-students-list mt-3" id="selectedStudentsList">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Class Selection -->
                            <div id="classSelection" class="d-none">
                                <div class="mb-3">
                                    <label for="classSelect" class="form-label fw-semibold">
                                        <i class="fas fa-layer-group me-2"></i>Select Class
                                    </label>
                                    <select class="form-select form-select-lg" id="classSelect" name="classSelect">
                                        <option value="">Choose a class...</option>
                                        <?php foreach ($class_options as $class_name): ?>
                                            <?php if ($class_name === 'KG 1') { ?>
                                                <option value="KG 1">KG 1</option>
                                            <?php } elseif ($class_name === 'KG 2') { ?>
                                                <option value="KG 2">KG 2</option>
                                            <?php } else { ?>
                                                <option value="<?php echo htmlspecialchars($class_name); ?>"><?php echo htmlspecialchars($class_name); ?></option>
                                            <?php } ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="selectedClassDisplay" class="fee-summary d-none mt-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-users fa-2x me-3 text-success"></i>
                                        <div>
                                            <h6 class="mb-1 text-success">Selected Class</h6>
                                            <p class="mb-0 fw-bold" id="selectedClassName"></p>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Fees will be assigned to <span class="fw-bold" id="studentCount">0</span> students
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee Selection -->
                <div class="col-lg-6 mb-4">
                    <div class="glass-container animate-slide-up">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-money-bill-wave me-3"></i>
                                    <h5 class="mb-0">Select Fees</h5>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="selection-counter" id="feeCounter">0 Selected</span>
                                    <button type="button" class="btn btn-outline-light btn-sm" id="clearFees">
                                        <i class="fas fa-times me-1"></i>Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Multi-Select Instructions -->
                        <div class="alert alert-info border-0 mb-3" style="background: rgba(13, 110, 253, 0.1); border-radius: 10px;">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Multi-Selection:</strong> Click on multiple fees to assign them together. Selected fees will be highlighted.
                        </div>
                        
                        <!-- Fee List -->
                        <div class="fee-list" style="max-height: 450px; overflow-y: auto; padding-right: 10px;">
                                <?php 
                                $fees->data_seek(0); // Reset result pointer
                                while($fee = $fees->fetch_assoc()): 
                                ?>
                                    <div class="fee-card mb-3" data-fee-id="<?php echo $fee['id']; ?>" data-fee-type="<?php echo $fee['fee_type']; ?>" data-fee-name="<?php echo htmlspecialchars($fee['name']); ?>">
                                        <div class="selection-checkbox">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($fee['name']); ?></h6>
                                                <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $fee['fee_type'])); ?></span>
                                            </div>
                                            
                                            <?php if ($fee['fee_type'] === 'fixed'): ?>
                                                <div class="fee-amount-display">
                                                    GH₵<?php echo number_format($fee['amount'], 2); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="fee-amount-display">
                                                    <small>Amount varies by <?php echo ($fee['fee_type'] === 'class_based') ? 'class' : 'category'; ?></small>
                                                </div>
                                                <?php if ($fee['amount_details']): ?>
                                                    <small class="text-muted d-block mt-1">
                                                        <?php echo htmlspecialchars(str_replace(' | ', ', ', $fee['amount_details'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($fee['description'])): ?>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    <?php echo htmlspecialchars($fee['description']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <!-- Selected Fees Summary -->
                            <div id="selectedFeesDisplay" class="d-none mt-4">
                                <div class="fee-summary">
                                    <h6 class="mb-3">
                                        <i class="fas fa-clipboard-check me-2"></i>
                                        Selected Fees Summary
                                    </h6>
                                    <div id="selectedFeesList" class="row g-2">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="fw-bold text-primary fs-5" id="totalFeesCount">0</div>
                                                <small class="text-muted">Total Fees</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="fw-bold text-success fs-5" id="estimatedTotal">GH₵0.00</div>
                                                <small class="text-muted">Estimated Total</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Details -->
            <div class="row justify-content-center mt-4">
                <div class="col-lg-10">
                    <div class="glass-container animate-slide-up" id="assignmentDetails">
                        <div class="section-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt me-3"></i>
                                    <h5 class="mb-0">Assignment Details</h5>
                                </div>
                                <span class="badge bg-primary text-white" id="assignmentSummary">Complete form to assign fees</span>
                            </div>
                        </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="due_date" class="form-label">
                                            <i class="fas fa-calendar me-2"></i>Due Date *
                                        </label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="term" class="form-label">
                                            <i class="fas fa-calendar-week me-2"></i>Academic Term
                                        </label>
                                        <select class="form-select" id="term" name="term">
                                            <option value="">Select Term (Optional)</option>
                                            <option value="First Term">First Term</option>
                                            <option value="Second Term">Second Term</option>
                                            <option value="Third Term">Third Term</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note me-2"></i>Notes
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Optional notes about this fee assignment"></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center align-items-center">
                                        <button type="reset" class="btn btn-outline-secondary btn-lg" onclick="resetForm()" style="min-width: 200px;">
                                            <i class="fas fa-undo me-2"></i>Reset Form
                                        </button>
                                        <button type="submit" class="btn btn-success btn-lg px-4" id="submitBtn" disabled style="min-width: 250px; font-weight: 600;">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <span id="submitBtnText">Assign Selected Fees</span>
                                        </button>
                                    </div>
                                    
                                    <!-- Form Status Indicator -->
                                    <div class="text-center mt-3">
                                        <small class="text-muted" id="formStatus">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Complete all required fields to enable assignment
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Floating Action Button -->
            <div class="floating-submit-container">
                <button type="submit" class="floating-submit-btn" id="floatingSubmitBtn" disabled>
                    <div class="btn-content">
                        <i class="fas fa-check-circle"></i>
                        <span class="btn-text">Assign Fees</span>
                        <div class="btn-counter" id="floatingCounter">0</div>
                    </div>
                </button>
            </div>

            <!-- Quick Links -->
            <div class="text-center mt-4 mb-5">
                <a href="view_assigned_fees.php" class="btn btn-outline-primary me-3">
                    <i class="fas fa-eye me-2"></i>View Assigned Fees
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedStudent = null;
        let selectedStudentId = null; // Consistent variable naming
        let selectedStudents = new Set(); // Multi-student selection
        let selectedFees = new Set(); // Multi-fee selection
        let assignmentType = 'individual';
        let selectedClass = null;
        let feeData = {}; // Store fee information
        
        // Assignment type selection
        document.querySelectorAll('.assignment-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.assignment-type-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                
                assignmentType = this.dataset.type;
                document.getElementById('assignmentType').value = assignmentType;
                
                if (assignmentType === 'individual') {
                    document.getElementById('selectionTitle').textContent = 'Select Student';
                    document.getElementById('selectionBadge').textContent = 'Individual Mode';
                    document.getElementById('selectionBadge').className = 'badge bg-primary text-white';
                    document.getElementById('individualSelection').classList.remove('d-none');
                    document.getElementById('classSelection').classList.add('d-none');
                    document.getElementById('submitBtnText').textContent = 'Assign to Student';
                    // Hide checkboxes, show single selection
                    document.querySelectorAll('.student-card .selection-checkbox').forEach(cb => cb.style.display = 'none');
                } else if (assignmentType === 'multi-student') {
                    document.getElementById('selectionTitle').textContent = 'Select Students';
                    document.getElementById('selectionBadge').textContent = 'Multi-Student Mode';
                    document.getElementById('selectionBadge').className = 'badge bg-warning text-dark';
                    document.getElementById('individualSelection').classList.remove('d-none');
                    document.getElementById('classSelection').classList.add('d-none');
                    document.getElementById('submitBtnText').textContent = 'Assign to Selected Students';
                    // Show checkboxes for multi-selection
                    document.querySelectorAll('.student-card .selection-checkbox').forEach(cb => cb.style.display = 'flex');
                } else {
                    document.getElementById('selectionTitle').textContent = 'Select Class';
                    document.getElementById('selectionBadge').textContent = 'Class Mode';
                    document.getElementById('selectionBadge').className = 'badge bg-success text-white';
                    document.getElementById('individualSelection').classList.add('d-none');
                    document.getElementById('classSelection').classList.remove('d-none');
                    document.getElementById('submitBtnText').textContent = 'Assign to Class';
                    // Hide checkboxes
                    document.querySelectorAll('.student-card .selection-checkbox').forEach(cb => cb.style.display = 'none');
                }
                
                resetSelections();
                updateAssignmentSummary();
                checkFormComplete();
            });
        });
        
        // Set default assignment type
        document.querySelector('[data-type="individual"]').classList.add('selected');
        
        // Multi-fee selection
        document.querySelectorAll('.fee-card').forEach(card => {
            const feeId = card.dataset.feeId;
            const feeName = card.dataset.feeName;
            const feeType = card.dataset.feeType;
            
            // Store fee data
            feeData[feeId] = {
                id: feeId,
                name: feeName,
                type: feeType
            };
            
            card.addEventListener('click', function() {
                toggleFeeSelection(feeId);
            });
        });
        
        // Clear all fees button
        document.getElementById('clearFees').addEventListener('click', function() {
            clearAllFees();
        });

        // Clear all students button
        document.getElementById('clearStudents').addEventListener('click', function() {
            clearAllStudents();
        });
        
        function toggleFeeSelection(feeId) {
            const card = document.querySelector(`[data-fee-id="${feeId}"]`);
            
            if (selectedFees.has(feeId)) {
                // Remove fee
                selectedFees.delete(feeId);
                card.classList.remove('selected');
            } else {
                // Add fee
                selectedFees.add(feeId);
                card.classList.add('selected');
            }
            
            updateFeeCounter();
            updateSelectedFeesDisplay();
            updateSelectedFeesInput();
            checkFormComplete();
        }
        
        function clearAllFees() {
            selectedFees.clear();
            document.querySelectorAll('.fee-card').forEach(card => {
                card.classList.remove('selected');
            });
            updateFeeCounter();
            updateSelectedFeesDisplay();
            updateSelectedFeesInput();
            checkFormComplete();
        }
        
        function updateFeeCounter() {
            const count = selectedFees.size;
            const counter = document.getElementById('feeCounter');
            counter.textContent = `${count} Selected`;
            
            if (count === 0) {
                counter.className = 'selection-counter';
            } else if (count === 1) {
                counter.className = 'selection-counter';
                counter.style.background = 'linear-gradient(135deg, #17a2b8, #138496)';
            } else {
                counter.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
            }
        }
        
        function updateSelectedFeesDisplay() {
            const display = document.getElementById('selectedFeesDisplay');
            const list = document.getElementById('selectedFeesList');
            const totalCount = document.getElementById('totalFeesCount');
            
            if (selectedFees.size === 0) {
                display.classList.add('d-none');
                return;
            }
            
            display.classList.remove('d-none');
            list.innerHTML = '';
            
            selectedFees.forEach(feeId => {
                const fee = feeData[feeId];
                const feeItem = document.createElement('div');
                feeItem.className = 'col-md-6 col-lg-4';
                feeItem.innerHTML = `
                    <div class="card border-success bg-light">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="fw-bold">${fee.name}</small>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleFeeSelection('${feeId}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <small class="text-muted">${fee.type.replace('_', ' ').toUpperCase()}</small>
                        </div>
                    </div>
                `;
                list.appendChild(feeItem);
            });
            
            totalCount.textContent = selectedFees.size;
        }
        
        function updateSelectedFeesInput() {
            const input = document.getElementById('selectedFeesInput');
            input.value = Array.from(selectedFees).join(',');
        }

        // Multi-student selection functions
        function toggleStudentSelection(studentId) {
            const studentCard = document.querySelector(`[data-student-id="${studentId}"]`);
            
            if (selectedStudents.has(studentId)) {
                // Deselect student
                selectedStudents.delete(studentId);
                studentCard.classList.remove('selected');
            } else {
                // Select student
                selectedStudents.add(studentId);
                studentCard.classList.add('selected');
            }
            
            updateStudentCounter();
            updateSelectedStudentsDisplay();
            updateSelectedStudentsInput();
        }

        function clearAllStudents() {
            selectedStudents.clear();
            document.querySelectorAll('.student-card').forEach(card => {
                card.classList.remove('selected');
            });
            updateStudentCounter();
            updateSelectedStudentsDisplay();
            updateSelectedStudentsInput();
        }

        function updateStudentCounter() {
            const counter = document.getElementById('studentCounter');
            const count = selectedStudents.size;
            counter.textContent = `${count} Student${count !== 1 ? 's' : ''} Selected`;
            
            // Show/hide the display area
            if (count > 0) {
                document.getElementById('selectedStudentsDisplay').classList.remove('d-none');
                document.getElementById('selectedStudentDisplay').classList.add('d-none');
            } else {
                document.getElementById('selectedStudentsDisplay').classList.add('d-none');
            }
        }

        function updateSelectedStudentsDisplay() {
            const container = document.getElementById('selectedStudentsList');
            container.innerHTML = '';
            
            selectedStudents.forEach(studentId => {
                const studentCard = document.querySelector(`[data-student-id="${studentId}"]`);
                const studentName = studentCard.dataset.studentName;
                const studentClass = studentCard.dataset.studentClass;
                
                const item = document.createElement('div');
                item.className = 'selected-student-item';
                item.innerHTML = `
                    <div>
                        <strong>${studentName}</strong>
                        <small class="text-muted d-block">${studentClass}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="toggleStudentSelection('${studentId}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(item);
            });
        }

        function updateSelectedStudentsInput() {
            const input = document.getElementById('selectedStudentIds');
            input.value = Array.from(selectedStudents).join(',');
        }
        
        // Update fee amounts based on selected student's class
        function updateFeeAmounts() {
            // This function can be used to update fee displays based on student selection
            // For now, fees are displayed with their base amounts or class-specific info
            updateAssignmentSummary();
        }

        function updateAssignmentSummary() {
            const summary = document.getElementById('assignmentSummary');
            let studentCount = 0;
            
            if (assignmentType === 'individual') {
                studentCount = selectedStudent ? 1 : 0;
            } else if (assignmentType === 'multi-student') {
                studentCount = selectedStudents.size;
            } else if (assignmentType === 'class') {
                studentCount = selectedClass ? parseInt(document.getElementById('studentCount')?.textContent) || 0 : 0;
            }
            
            const feeCount = selectedFees.size;
            
            if (feeCount > 0 && studentCount > 0) {
                const totalAssignments = studentCount * feeCount;
                summary.textContent = `${totalAssignments} Assignment${totalAssignments !== 1 ? 's' : ''} Ready`;
                summary.className = 'badge bg-success text-white';
            } else if (feeCount > 0) {
                summary.textContent = `${feeCount} Fee${feeCount !== 1 ? 's' : ''} Selected - Choose Target`;
                summary.className = 'badge bg-info text-white';
            } else if (studentCount > 0) {
                summary.textContent = `${studentCount} Student${studentCount !== 1 ? 's' : ''} Selected - Choose Fees`;
                summary.className = 'badge bg-warning text-dark';
            } else {
                summary.textContent = 'Select Target & Fees';
                summary.className = 'badge bg-secondary text-white';
            }
            
            // Ensure assignment details are visible
            const assignmentDetails = document.getElementById('assignmentDetails');
            if (assignmentDetails) {
                assignmentDetails.style.display = 'block';
            }
        }
        
        // Class selection
        document.getElementById('classSelect').addEventListener('change', function() {
            const className = this.value;
            if (className) {
                selectedClass = className;
                
                // Get student count for this class
                fetch(`../includes/get_class_student_count.php?class=${encodeURIComponent(className)}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('selectedClassName').textContent = className;
                        document.getElementById('studentCount').textContent = data.count;
                        document.getElementById('selectedClassDisplay').classList.remove('d-none');
                        checkFormComplete();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('selectedClassName').textContent = className;
                        document.getElementById('studentCount').textContent = '0';
                        document.getElementById('selectedClassDisplay').classList.remove('d-none');
                        checkFormComplete();
                    });
            } else {
                selectedClass = null;
                document.getElementById('selectedClassDisplay').classList.add('d-none');
                checkFormComplete();
            }
        });

        // Student selection
        document.querySelectorAll('.student-card').forEach(card => {
            card.addEventListener('click', function() {
                if (assignmentType === 'individual') {
                    // Single student selection
                    document.querySelectorAll('.student-card').forEach(c => c.classList.remove('selected'));
                    
                    this.classList.add('selected');
                    selectedStudent = {
                        id: this.dataset.studentId,
                        name: this.dataset.studentName,
                        class: this.dataset.studentClass
                    };
                    selectedStudentId = selectedStudent.id;
                    
                    // Update form
                    document.getElementById('selectedStudentId').value = selectedStudentId;
                    document.getElementById('selectedStudentName').textContent = selectedStudent.name + ' (' + selectedStudent.class + ')';
                    document.getElementById('selectedStudentDisplay').classList.remove('d-none');
                    document.getElementById('selectedStudentsDisplay').classList.add('d-none');
                    
                } else if (assignmentType === 'multi-student') {
                    // Multi-student selection
                    toggleStudentSelection(this.dataset.studentId);
                }
                
                checkFormComplete();
                updateAssignmentSummary();
            });
        });

        // Fee selection
        document.querySelectorAll('.fee-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove previous selection
                document.querySelectorAll('.fee-card').forEach(c => c.classList.remove('selected'));
                
                // Select current card
                this.classList.add('selected');
                selectedFee = {
                    id: this.dataset.feeId,
                    name: this.querySelector('h6').textContent,
                    type: this.dataset.feeType
                };
                
                // Update form
                document.getElementById('selectedFeeId').value = selectedFee.id;
                document.getElementById('selectedFeeName').textContent = selectedFee.name;
                document.getElementById('selectedFeeDisplay').classList.remove('d-none');
                
                checkFormComplete();
                updateFeeAmounts();
            });
        });

        // Student search
        document.getElementById('studentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.student-card').forEach(card => {
                const studentName = card.querySelector('strong').textContent.toLowerCase();
                const studentClass = card.dataset.studentClass.toLowerCase();
                
                if (studentName.includes(searchTerm) || studentClass.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        function resetSelections() {
            selectedStudent = null;
            selectedStudentId = null;
            selectedStudents.clear();
            selectedFees.clear();
            selectedClass = null;
            
            document.querySelectorAll('.student-card').forEach(c => c.classList.remove('selected'));
            document.querySelectorAll('.fee-card').forEach(c => c.classList.remove('selected'));
            document.getElementById('selectedStudentDisplay').classList.add('d-none');
            document.getElementById('selectedStudentsDisplay').classList.add('d-none');
            document.getElementById('selectedFeesDisplay').classList.add('d-none');
            document.getElementById('selectedClassDisplay').classList.add('d-none');
            document.getElementById('classSelect').value = '';
            document.getElementById('selectedStudentId').value = '';
            document.getElementById('selectedFeesInput').value = '';
            
            updateFeeCounter();
            updateSelectedFeesDisplay();
        }

        // Reset form
        function resetForm() {
            resetSelections();
            document.getElementById('due_date').value = '';
            document.getElementById('term').value = '';
            document.getElementById('notes').value = '';
            updateAssignmentSummary();
            checkFormComplete();
        }

        // Check if form is complete and can be submitted
        function checkFormComplete() {
            const currentAssignmentType = document.getElementById('assignmentType')?.value || assignmentType;
            const hasStudent = currentAssignmentType === 'individual' && selectedStudentId;
            const hasMultiStudents = currentAssignmentType === 'multi-student' && selectedStudents.size > 0;
            const hasClass = currentAssignmentType === 'class' && document.getElementById('classSelect')?.value;
            const hasFees = selectedFees.size > 0;
            const hasDueDate = document.getElementById('due_date')?.value;
            
            const isComplete = (hasStudent || hasMultiStudents || hasClass) && hasFees && hasDueDate;
            
            const submitBtn = document.getElementById('submitBtn');
            const floatingBtn = document.getElementById('floatingSubmitBtn');
            const floatingCounter = document.getElementById('floatingCounter');
            const formStatus = document.getElementById('formStatus');
            const assignmentSummary = document.getElementById('assignmentSummary');
            
            // Update main submit button
            if (submitBtn) {
                submitBtn.disabled = !isComplete;
                submitBtn.classList.toggle('btn-success', isComplete);
                submitBtn.classList.toggle('btn-secondary', !isComplete);
                
                if (isComplete) {
                    submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i><span>Assign ' + selectedFees.size + ' Fee(s) Now</span>';
                    formStatus.innerHTML = '<i class="fas fa-check-circle me-1 text-success"></i><span class="text-success">Ready to assign fees!</span>';
                    assignmentSummary.textContent = 'Ready to Assign ' + selectedFees.size + ' Fee(s)';
                    assignmentSummary.className = 'badge bg-success text-white';
                } else {
                    submitBtn.innerHTML = '<i class="fas fa-times-circle me-2"></i><span>Complete Form First</span>';
                    formStatus.innerHTML = '<i class="fas fa-info-circle me-1"></i>Complete all required fields to enable assignment';
                    assignmentSummary.textContent = 'Incomplete Form';
                    assignmentSummary.className = 'badge bg-secondary text-white';
                }
            }
            
            // Update floating submit button
            if (floatingBtn) {
                floatingBtn.disabled = !isComplete;
                if (floatingCounter) {
                    floatingCounter.textContent = selectedFees.size;
                }
                
                const btnText = floatingBtn.querySelector('.btn-text');
                if (btnText) {
                    if (isComplete) {
                        btnText.textContent = `Assign ${selectedFees.size} Fee${selectedFees.size !== 1 ? 's' : ''}`;
                    } else {
                        btnText.textContent = 'Select Fees';
                    }
                }
            }
            
            return isComplete;
        }

        // Handle form submission
        function handleSubmit(event) {
            if (!checkFormComplete()) {
                event.preventDefault();
                alert('Please complete all required fields and select at least one fee.');
                return false;
            }
            
            // Show loading state
            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assigning Fees...';
                submitBtn.disabled = true;
            }
            
            return true;
        }

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners
            document.getElementById('due_date').addEventListener('change', checkFormComplete);
            document.getElementById('term').addEventListener('change', checkFormComplete);
            document.getElementById('classSelect').addEventListener('change', function() {
                updateAssignmentSummary();
                checkFormComplete();
            });
            
            // Set default due date (30 days from now)
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 30);
            document.getElementById('due_date').value = defaultDate.toISOString().split('T')[0];
            
            // Initialize form display
            const assignmentDetails = document.getElementById('assignmentDetails');
            if (assignmentDetails) {
                assignmentDetails.style.display = 'block';
            }
            
            // Initial form checks
            updateAssignmentSummary();
            checkFormComplete();
        });
    </script>
</body>
</html>