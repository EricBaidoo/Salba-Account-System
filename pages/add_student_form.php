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
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="view_students.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-user-plus me-2"></i>Student Enrollment</h1>
                <p class="clean-page-subtitle">Add new students to your school system</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

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
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="clean-card">
                        <div class="clean-card-header">
                            <h5 class="clean-card-title"><i class="fas fa-user-edit me-2"></i>Add New Student</h5>
                        </div>
                        <div class="p-4">
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
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Optional Fields Section -->
                                <div class="mb-4">
                                    <h6 class="text-secondary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Additional Information
                                        <small class="text-muted">(Optional)</small>
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="clean-form-group">
                                                <label for="date_of_birth" class="clean-form-label">Date of Birth</label>
                                                <input type="date" class="clean-form-control" id="date_of_birth" name="date_of_birth">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="clean-form-group">
                                                <label for="parent_contact" class="clean-form-label">Parent Contact</label>
                                                <input type="text" class="clean-form-control" id="parent_contact" name="parent_contact">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <small class="text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Fields marked with <span class="text-danger fw-bold">*</span> are required
                                    </small>
                                    <div class="gap-2 d-flex">
                                        <button type="reset" class="btn-clean-outline">
                                            <i class="fas fa-undo me-2"></i>Clear
                                        </button>
                                        <button type="submit" class="btn-clean-primary">
                                            <i class="fas fa-user-plus me-2"></i>Add Student
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
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