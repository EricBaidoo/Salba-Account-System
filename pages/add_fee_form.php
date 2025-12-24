<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';
// Fetch all classes and their Levels for dynamic class-based amount fields
$classes_result = $conn->query("SELECT name, Level FROM classes ORDER BY id ASC");
$class_groups = [];
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $level = $row['Level'] ?: 'Other';
        $class_groups[$level][] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Fee - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="view_fees.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Fees
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-money-bill-wave me-2"></i>Create New Fee</h1>
                <p class="clean-page-subtitle">Set up fees for your school with our easy-to-use wizard</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <span>Fee Type</span>
            </div>
            <div class="step-connector"></div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <span>Details</span>
            </div>
            <div class="step-connector"></div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <span>Amounts</span>
            </div>
        </div>

        <div class="fee-wizard mx-auto">
            <div class="p-4">
                <form action="add_fee.php" method="POST" id="feeForm">
                    
                    <!-- Step 1: Fee Type Selection -->
                    <div class="form-section active" id="section1">
                        <h4 class="mb-4 text-center">Choose Fee Structure</h4>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card fee-type-card" data-type="fixed" onclick="selectFeeType('fixed')">
                                    <div class="card-body text-center p-4">
                                        <i class="fas fa-money-check-alt fa-4x text-success mb-3"></i>
                                        <h5 class="card-title">Fixed Amount</h5>
                                        <p class="card-text text-muted">Same fee for all students</p>
                                        <span class="badge bg-success">Simple</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card fee-type-card" data-type="class_based" onclick="selectFeeType('class_based')">
                                    <div class="card-body text-center p-4">
                                        <i class="fas fa-layer-group fa-4x text-primary mb-3"></i>
                                        <h5 class="card-title">Class-Based</h5>
                                        <p class="card-text text-muted">Individual amounts per class</p>
                                        <span class="badge bg-primary">Flexible</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card fee-type-card" data-type="category" onclick="selectFeeType('category')">
                                    <div class="card-body text-center p-4">
                                        <i class="fas fa-tags fa-4x text-warning mb-3"></i>
                                        <h5 class="card-title">Category-Based</h5>
                                        <p class="card-text text-muted">Group classes together</p>
                                        <span class="badge bg-warning text-dark">Recommended</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-primary btn-lg" onclick="nextStep()" id="nextBtn1" disabled>
                                Continue <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Fee Details -->
                    <div class="form-section" id="section2">
                        <h4 class="mb-4 text-center">Fee Information</h4>
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <div class="mb-4">
                                    <label for="fee_name" class="form-label fw-bold">
                                        <i class="fas fa-tag me-2"></i>Fee Name *
                                    </label>
                                    <select class="form-select" id="fee_name" name="fee_name" required onchange="handleCustomFee()">
                                        <option value="">Select a fee type...</option>
                                        <option value="Tuition Fee">Tuition Fee</option>
                                        <option value="Development Levy">Development Levy</option>
                                        <option value="Books Fee">Books Fee</option>
                                        <option value="Uniform Fee">Uniform Fee</option>
                                        <option value="Sports Fee">Sports Fee</option>
                                        <option value="Examination Fee">Examination Fee</option>
                                        <option value="Library Fee">Library Fee</option>
                                        <option value="Computer Fee">Computer Fee</option>
                                        <option value="Transport Fee">Transport Fee</option>
                                        <option value="Feeding Fee">Feeding Fee</option>
                                        <option value="custom">🎯 Custom Fee Name</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4" id="customFeeGroup">
                                    <label for="custom_fee_name" class="form-label fw-bold">Custom Fee Name *</label>
                                    <input type="text" class="form-control" id="custom_fee_name" name="custom_fee_name" placeholder="Enter your custom fee name">
                                </div>

                                <div class="mb-4">
                                    <label for="description" class="form-label fw-bold">
                                        <i class="fas fa-info-circle me-2"></i>Description
                                    </label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional: Describe this fee"></textarea>
                                </div>

                                <!-- Fixed Amount Input (if fixed type selected) -->
                                <div class="mb-4" id="fixedAmountGroup">
                                    <label for="fixed_amount" class="form-label fw-bold">
                                        <i class="fas fa-dollar-sign me-2"></i>Amount (GH₵) *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">GH₵</span>
                                        <input type="number" step="0.01" class="form-control" id="fixed_amount" name="fixed_amount" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-outline-secondary me-3" onclick="previousStep()">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" onclick="nextStep()" id="nextBtn2">
                                Continue <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Amount Configuration -->
                    <div class="form-section" id="section3">
                        <h4 class="mb-4 text-center">Set Fee Amounts</h4>
                        
                        <!-- Class-Based Amounts -->
                        <div id="classBasedAmounts" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5><i class="fas fa-layer-group me-2"></i>Individual Class Amounts</h5>
                                <button type="button" class="btn preset-btn" onclick="applyTuitionPreset()">
                                    <i class="fas fa-magic me-1"></i>Apply Tuition Preset
                                </button>
                            </div>
                            <?php foreach ($class_groups as $level => $classes): ?>
                                <div class="mb-3">
                                    <h6 class="mb-2"><i class="fas fa-layer-group me-2"></i><?php echo htmlspecialchars($level); ?></h6>
                                    <div class="class-grid">
                                        <?php foreach ($classes as $class): ?>
                                            <div class="class-input-card">
                                                <label class="form-label small fw-bold"><?php echo htmlspecialchars($class); ?></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">GH₵</span>
                                                    <input type="number" step="0.01" class="form-control" name="class_amounts[<?php echo htmlspecialchars($class); ?>]" placeholder="0.00">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Category-Based Amounts (Dynamic) -->
                        <div id="categoryBasedAmounts" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5><i class="fas fa-tags me-2"></i>Category Amounts</h5>
                                <button type="button" class="btn preset-btn" onclick="applyCategoryPreset()">
                                    <i class="fas fa-magic me-1"></i>Apply Tuition Preset
                                </button>
                            </div>
                            <div class="row g-4">
                                <?php $levels = array_keys($class_groups); $colorClasses = ['success','primary','warning','info','secondary','danger']; $iconClasses = ['fa-seedling','fa-graduation-cap','fa-tags','fa-cubes','fa-layer-group','fa-star']; $i=0; ?>
                                <?php foreach ($levels as $level): ?>
                                    <div class="col-md-6">
                                        <div class="card border-<?php echo $colorClasses[$i % count($colorClasses)]; ?>">
                                            <div class="card-body text-center">
                                                <i class="fas <?php echo $iconClasses[$i % count($iconClasses)]; ?> fa-3x text-<?php echo $colorClasses[$i % count($colorClasses)]; ?> mb-3"></i>
                                                <h6 class="fw-bold"><?php echo htmlspecialchars($level); ?></h6>
                                                <p class="small text-muted mb-3">
                                                    <?php echo htmlspecialchars(implode(', ', $class_groups[$level])); ?>
                                                </p>
                                                <div class="input-group">
                                                    <span class="input-group-text">GH₵</span>
                                                    <input type="number" step="0.01" class="form-control text-center" name="category_amounts[<?php echo htmlspecialchars($level); ?>]" placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $i++; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-secondary me-3" onclick="previousStep()">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </button>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-plus me-2"></i>Create Fee
                            </button>
                        </div>
                    </div>

                    <!-- Hidden fee_type input -->
                    <input type="hidden" name="fee_type" id="fee_type" value="">
                </form>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="text-center mt-4">
            <a href="view_fees.php" class="btn btn-outline-primary me-3">
                <i class="fas fa-eye me-2"></i>View All Fees
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var currentStep = 1;
    var selectedFeeType = null;

        function selectFeeType(type) {
            // Remove previous selections
            document.querySelectorAll('.fee-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select current card
            document.querySelector(`[data-type="${type}"]`).classList.add('selected');
            selectedFeeType = type;
            document.getElementById('fee_type').value = type;
            document.getElementById('nextBtn1').disabled = false;
        }

        function nextStep() {
            if (currentStep < 3) {
                // Hide current section
                document.getElementById(`section${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}`).classList.remove('active');
                
                currentStep++;
                
                // Show next section
                document.getElementById(`section${currentStep}`).classList.add('active');
                document.getElementById(`step${currentStep}`).classList.add('active');
                
                // Configure step 2 based on fee type
                if (currentStep === 2) {
                    configureStep2();
                }
                
                // Configure step 3 based on fee type
                if (currentStep === 3) {
                    configureStep3();
                }
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                // Hide current section
                document.getElementById(`section${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}`).classList.remove('active');
                
                currentStep--;
                
                // Show previous section
                document.getElementById(`section${currentStep}`).classList.add('active');
                document.getElementById(`step${currentStep}`).classList.add('active');
            }
        }

        function configureStep2() {
            if (selectedFeeType === 'fixed') {
                document.getElementById('fixedAmountGroup').style.display = 'block';
            } else {
                document.getElementById('fixedAmountGroup').style.display = 'none';
            }
        }

        function configureStep3() {
            // Hide all amount sections
            document.getElementById('classBasedAmounts').style.display = 'none';
            document.getElementById('categoryBasedAmounts').style.display = 'none';
            
            // Show relevant section
            if (selectedFeeType === 'class_based') {
                document.getElementById('classBasedAmounts').style.display = 'block';
            } else if (selectedFeeType === 'category') {
                document.getElementById('categoryBasedAmounts').style.display = 'block';
            }
        }

        function handleCustomFee() {
            const feeSelect = document.getElementById('fee_name');
            const customGroup = document.getElementById('customFeeGroup');
            
            if (feeSelect.value === 'custom') {
                customGroup.style.display = 'block';
                document.getElementById('custom_fee_name').required = true;
            } else {
                customGroup.style.display = 'none';
                document.getElementById('custom_fee_name').required = false;
            }
        }

        function applyTuitionPreset() {
            // Early Years classes (GH₵700)
            const earlyYearsClasses = ['Creche', 'Nursery 1', 'Nursery 2', 'KG 1', 'KG 2'];
            earlyYearsClasses.forEach(className => {
                const input = document.querySelector(`input[name="class_amounts[${className}]"]`);
                if (input) input.value = '700';
            });
            // Primary classes (GH₵800)
            const primaryClasses = ['Basic 1', 'Basic 2', 'Basic 3', 'Basic 4', 'Basic 5', 'Basic 6', 'Basic 7'];
            primaryClasses.forEach(className => {
                const input = document.querySelector(`input[name="class_amounts[${className}]"]`);
                if (input) input.value = '800';
            });
        }

        function applyCategoryPreset() {
            // Apply tuition preset for category-based fees
            // Set amounts based on actual level names from database
            
            // Early years categories (GH₵700)
            const earlyYearsLevels = ['Nursery', 'KG', 'Creche', 'Early Years'];
            earlyYearsLevels.forEach(level => {
                const input = document.querySelector(`input[name="category_amounts[${level}]"]`);
                if (input) input.value = '700';
            });
            
            // Primary categories (GH₵800) 
            const primaryLevels = ['Primary', 'Basic', 'Elementary'];
            primaryLevels.forEach(level => {
                const input = document.querySelector(`input[name="category_amounts[${level}]"]`);
                if (input) input.value = '800';
            });
            
            // Also try generic approach - set all visible category inputs
            document.querySelectorAll('#categoryBasedAmounts input[name^="category_amounts"]').forEach(input => {
                if (!input.value) { // Only set if empty
                    const levelName = input.name.match(/category_amounts\[(.*?)\]/)[1].toLowerCase();
                    if (levelName.includes('nursery') || levelName.includes('kg') || levelName.includes('creche')) {
                        input.value = '700';
                    } else if (levelName.includes('primary') || levelName.includes('basic') || levelName.includes('elementary')) {
                        input.value = '800';
                    }
                }
            });
        }

        // Form validation before submission
        document.getElementById('feeForm').addEventListener('submit', function(e) {
            const feeName = document.getElementById('fee_name').value;
            const customFeeName = document.getElementById('custom_fee_name').value;
            
            if (feeName === 'custom' && !customFeeName.trim()) {
                e.preventDefault();
                alert('Please enter a custom fee name.');
                return false;
            }
            
            if (selectedFeeType === 'fixed') {
                const fixedAmount = document.getElementById('fixed_amount').value;
                if (!fixedAmount || parseFloat(fixedAmount) <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid fixed amount.');
                    return false;
                }
            }
        });

        // PHP renders class-based amount fields server-side. No JS HTML overwrite needed.
    </script>
</body>
</html>