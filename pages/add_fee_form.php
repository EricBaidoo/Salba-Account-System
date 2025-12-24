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
// Fetch dynamic fee categories
include '../includes/fee_categories.php';
?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-primary text-white text-center rounded-top-4">
                        <h3 class="mb-0">Add New Fee</h3>
                    </div>
                    <div class="card-body p-4">
                        <form action="add_fee.php" method="POST" id="feeForm">
                            <div class="mb-4">
                                <label for="fee_type" class="form-label fw-bold">Fee Type *</label>
                                <select class="form-select" id="fee_type" name="fee_type" required>
                                    <option value="">Select fee type...</option>
                                    <option value="fixed">Fixed Amount (same for all students)</option>
                                    <option value="class_based">Class-Based (different per class)</option>
                                    <option value="category">Category-Based (different per group)</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="fee_name" class="form-label fw-bold">Fee Name *</label>
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
                            <div class="mb-4" id="customFeeGroup" style="display:none;">
                                <label for="custom_fee_name" class="form-label fw-bold">Custom Fee Name *</label>
                                <input type="text" class="form-control" id="custom_fee_name" name="custom_fee_name" placeholder="Enter your custom fee name">
                            </div>
                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2" placeholder="Optional: Describe this fee"></textarea>
                            </div>
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <h5 class="mb-3 text-primary"><i class="fas fa-money-bill-wave me-2"></i>Fixed Amount (GH₵)</h5>
                                <div class="input-group">
                                    <span class="input-group-text">GH₵</span>
                                    <input type="number" step="0.01" class="form-control" id="fixed_amount" name="fixed_amount" placeholder="0.00">
                                </div>
                            </div>
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <h5 class="mb-3 text-primary"><i class="fas fa-layer-group me-2"></i>Class-Based Amounts</h5>
                                <?php foreach ($class_groups as $level => $classes): ?>
                                    <div class="mb-2"><strong><?php echo htmlspecialchars($level); ?></strong></div>
                                    <div class="row g-2 mb-2">
                                        <?php foreach ($classes as $class): ?>
                                            <div class="col-md-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">GH₵</span>
                                                    <input type="number" step="0.01" class="form-control" name="class_amounts[<?php echo htmlspecialchars($class); ?>]" placeholder="<?php echo htmlspecialchars($class); ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mb-4 p-3 bg-light rounded-3 border">
                                <h5 class="mb-3 text-primary"><i class="fas fa-tags me-2"></i>Category-Based Amounts</h5>
                                <div class="row g-2">
                                    <?php if (!empty($fee_categories)): ?>
                                        <?php foreach ($fee_categories as $cat_id => $cat_name): ?>
                                            <div class="col-md-4">
                                                <div class="input-group">
                                                    <span class="input-group-text">GH₵</span>
                                                    <input type="number" step="0.01" class="form-control" name="category_amounts[<?php echo htmlspecialchars($cat_id); ?>]" placeholder="<?php echo htmlspecialchars($cat_name); ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12"><div class="alert alert-warning">No fee categories found. <a href='manage_fee_categories.php'>Add categories</a>.</div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5 py-2">
                                    <i class="fas fa-plus me-2"></i>Create Fee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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