<?php include '../includes/auth_check.php'; ?>
<?php
include '../includes/db_connect.php';

// Fetch all classes for the class dropdown
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
    <title>Add Student - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .student-wizard {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.1);
        }
        
        .method-card {
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            height: 100%;
            border-radius: 15px;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9ff 100%);
        }
        
        .method-card:hover {
            border-color: #667eea;
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.25);
        }
        
        .method-card.active {
            border-color: #667eea;
            background: linear-gradient(145deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.12) 100%);
            transform: translateY(-5px);
        }
        
        .required-field::after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }
        
        .optional-field {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .form-floating > label {
            font-weight: 500;
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #5a6fd8;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-3px);
        }
        
        .upload-area.dragover {
            border-color: #5a6fd8;
            background: rgba(102, 126, 234, 0.15);
            transform: scale(1.02);
        }
        
        .info-card {
            background: linear-gradient(145deg, #f8f9ff 0%, #e8f0fe 100%);
            border: 1px solid #e5e7eb;
            border-radius: 15px;
        }
        
        .sample-table {
            font-size: 0.875rem;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.4);
        }
        
        .form-section {
            display: none;
            animation: fadeInUp 0.5s ease;
        }
        
        .form-section.active {
            display: block;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
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

    <div class="container mt-4">
        <div class="text-center mb-4">
            <h2><i class="fas fa-user-plus me-3 text-primary"></i>Student Enrollment</h2>
            <p class="text-muted">Add new students to your school system</p>
        </div>

        <!-- Method Selection -->
        <div class="row mb-4">
            <div class="col-md-6 mx-auto">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="card method-card active" id="singleCard" onclick="selectMethod('single')">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-user fa-3x text-primary mb-3"></i>
                                <h6 class="fw-bold">Single Student</h6>
                                <p class="small text-muted mb-0">Add one student</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card method-card" id="bulkCard" onclick="selectMethod('bulk')">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-users fa-3x text-success mb-3"></i>
                                <h6 class="fw-bold">Bulk Upload</h6>
                                <p class="small text-muted mb-0">CSV file upload</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Single Student Form -->
        <div class="form-section active" id="singleSection">
            <div class="student-wizard mx-auto" style="max-width: 700px;">
                <div class="p-4">
                    <h4 class="text-center mb-4"><i class="fas fa-user-edit me-2"></i>Add New Student</h4>
                    
                    <form action="add_student.php" method="POST" id="studentForm">
                        <!-- Required Fields Section -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-star me-2"></i>Required Information
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               placeholder="First Name" required>
                                        <label for="first_name" class="required-field">First Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               placeholder="Last Name" required>
                                        <label for="last_name" class="required-field">Last Name</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <select class="form-select" id="class" name="class" required>
                                            <option value="">Select Class/Grade</option>
                                            <?php foreach ($class_options as $class): ?>
                                                <?php if ($class === 'KG 1') { ?>
                                                    <option value="KG 1">KG 1</option>
                                                <?php } elseif ($class === 'KG 2') { ?>
                                                    <option value="KG 2">KG 2</option>
                                                <?php } else { ?>
                                                    <option value="<?php echo $class; ?>"><?php echo $class; ?></option>
                                                <?php } ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="class" class="required-field">Class/Grade</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Fields Section -->
                        <div class="mb-4">
                            <h5 class="text-secondary mb-3">
                                <i class="fas fa-info-circle me-2"></i>Additional Information
                                <small class="optional-field">(Optional - can be added later)</small>
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                        <label for="date_of_birth">Date of Birth</label>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>Helps calculate age for reports
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="parent_contact" name="parent_contact" 
                                               placeholder="Phone or Email">
                                        <label for="parent_contact">Parent Contact</label>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i>Phone number or email address
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Fields marked with <span class="text-danger fw-bold">*</span> are required
                            </small>
                            <div class="gap-2 d-flex">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Clear
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Add Student
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bulk Upload Form -->
        <div class="form-section" id="bulkSection">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="student-wizard">
                        <div class="p-4">
                            <h4 class="text-center mb-4">
                                <i class="fas fa-upload me-2"></i>Bulk Upload Students
                            </h4>
                            
                            <form action="bulk_upload_students.php" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-4x text-primary mb-3"></i>
                                    <h5>Drop CSV File Here</h5>
                                    <p class="text-muted">or <strong>click to browse files</strong></p>
                                    <input type="file" id="csvFile" name="csvFile" accept=".csv" class="d-none" required>
                                    <small class="text-muted">Only CSV files are supported</small>
                                </div>
                                
                                <div id="fileInfo" class="mt-3 d-none">
                                    <div class="alert alert-success d-flex align-items-center">
                                        <i class="fas fa-file-csv fa-2x me-3"></i>
                                        <div>
                                            <strong id="fileName"></strong><br>
                                            <small id="fileSize" class="text-muted"></small>
                                        </div>
                                        <button type="button" class="btn-close ms-auto" onclick="clearFile()"></button>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-upload me-2"></i>Upload Students
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="info-card">
                        <div class="card-body">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>CSV Format Guide
                            </h6>
                            
                            <div class="table-responsive mb-3">
                                <table class="table table-sm sample-table">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>first_name*</th>
                                            <th>last_name*</th>
                                            <th>class*</th>
                                            <th>date_of_birth</th>
                                            <th>parent_contact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>John</td>
                                            <td>Doe</td>
                                            <td>Basic 1</td>
                                            <td>2015-05-12</td>
                                            <td>0801234567</td>
                                        </tr>
                                        <tr>
                                            <td>Jane</td>
                                            <td>Smith</td>
                                            <td>KG 1</td>
                                            <td></td>
                                            <td>jane@email.com</td>
                                        </tr>
                                        <tr>
                                            <td>Michael</td>
                                            <td>Johnson</td>
                                            <td>Basic 7</td>
                                            <td>2012-03-20</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-success">Requirements:</h6>
                                <ul class="small mb-0">
                                    <li><strong>Required:</strong> first_name, last_name, class</li>
                                    <li><strong>Optional:</strong> date_of_birth, parent_contact</li>
                                    <li>Date format: YYYY-MM-DD (e.g., 2015-05-12)</li>
                                    <li>First row must contain column headers</li>
                                    <li>Empty cells allowed for optional fields</li>
                                </ul>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-outline-primary" id="downloadTemplate">
                                    <i class="fas fa-download me-2"></i>Download Template
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        }

        <!-- Quick Links -->
        <div class="text-center mt-5">
            <a href="view_students.php" class="btn btn-outline-primary me-3">
                <i class="fas fa-users me-2"></i>View All Students
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Method selection
        function selectMethod(method) {
            // Remove active class from all cards
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Hide all sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Activate selected method
            if (method === 'single') {
                document.getElementById('singleCard').classList.add('active');
                document.getElementById('singleSection').classList.add('active');
            } else if (method === 'bulk') {
                document.getElementById('bulkCard').classList.add('active');
                document.getElementById('bulkSection').classList.add('active');
            }
        }

        // Drag and Drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', () => fileInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'text/csv') {
                    fileInput.files = files;
                    displayFileInfo(files[0]);
                } else {
                    alert('Please select a valid CSV file.');
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    displayFileInfo(e.target.files[0]);
                }
            });
        }

        function displayFileInfo(file) {
            if (fileName && fileSize && fileInfo) {
                fileName.textContent = file.name;
                fileSize.textContent = `Size: ${(file.size / 1024).toFixed(2)} KB`;
                fileInfo.classList.remove('d-none');
            }
        }

        function clearFile() {
            if (fileInput && fileInfo) {
                fileInput.value = '';
                fileInfo.classList.add('d-none');
            }
        }

        // Download CSV template
        document.getElementById('downloadTemplate').addEventListener('click', function(e) {
            e.preventDefault();
            const csvContent = "first_name,last_name,class,date_of_birth,parent_contact\nJohn,Doe,Basic 1,2015-05-12,0801234567\nJane,Smith,KG 1,,jane@email.com\nMichael,Johnson,Basic 7,2012-03-20,\nSarah,Williams,Nursery 1,2018-09-15,0809876543\nDavid,Brown,Basic 5,2014-01-10,david.parent@gmail.com";
            const csvContentFixed = csvContent.replace(/KG2|KG 2/g, 'KG 2');
            const blob = new Blob([csvContentFixed], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'student_template.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });

        // Form validation
        document.getElementById('studentForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const studentClass = document.getElementById('class').value;

            if (!firstName || !lastName || !studentClass) {
                e.preventDefault();
                alert('Please fill in all required fields: First Name, Last Name, and Class.');
                return false;
            }
        });
    </script>
</body>
</html>