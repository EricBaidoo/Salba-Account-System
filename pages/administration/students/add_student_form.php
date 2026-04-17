<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../../index.php');
    exit;
}

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
    <title>Student Enrollment - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-3 mb-4">
                <a href="view_students.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Directory
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-plus text-blue-600"></i> Student Enrollment
                </h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Add new students to the school system individually or via bulk upload.
                </p>
            </div>
        </div>

        <div class="p-8">
            
            <!-- Method Selection -->
            <div class="flex gap-4 mb-8">
                <button type="button" onclick="selectMethod('single')" id="btnSingle" class="flex-1 bg-white border-2 border-blue-600 rounded-xl p-6 flex flex-col items-center justify-center gap-3 transition-all cursor-pointer shadow-sm relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-full h-1 bg-blue-600" id="indicatorSingle"></div>
                    <i class="fas fa-user text-3xl text-blue-600 group-hover:scale-110 transition-transform"></i>
                    <div class="text-center">
                        <h3 class="font-bold text-gray-900">Single Student</h3>
                        <p class="text-sm text-gray-500 mt-1">Enroll one student manually</p>
                    </div>
                </button>
                
                <button type="button" onclick="selectMethod('bulk')" id="btnBulk" class="flex-1 bg-white border border-gray-200 rounded-xl p-6 flex flex-col items-center justify-center gap-3 transition-all cursor-pointer hover:border-blue-300 hover:shadow-md group">
                    <div class="absolute top-0 left-0 w-full h-1 bg-transparent" id="indicatorBulk"></div>
                    <i class="fas fa-users text-3xl text-gray-400 group-hover:text-green-500 group-hover:scale-110 transition-all"></i>
                    <div class="text-center">
                        <h3 class="font-bold text-gray-900">Bulk Upload</h3>
                        <p class="text-sm text-gray-500 mt-1">Import via CSV file</p>
                    </div>
                </button>
            </div>

            <!-- Single Student Form -->
            <div id="singleSection" class="block">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden max-w-3xl">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <h5 class="font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-user-edit text-blue-500"></i> New Student Details
                        </h5>
                    </div>
                    
                    <form action="add_student.php" method="POST" id="studentForm" class="p-6">
                        <!-- Required Information -->
                        <div class="mb-8">
                            <h6 class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i class="fas fa-star"></i> Required Information
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="first_name" name="first_name" required
                                           class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors"
                                           placeholder="e.g. John">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" id="last_name" name="last_name" required
                                           class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors"
                                           placeholder="e.g. Doe">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="class" class="block text-sm font-semibold text-gray-700 mb-1">Class / Grade <span class="text-red-500">*</span></label>
                                    <select id="class" name="class" required
                                            class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors appearance-none">
                                        <option value="">Select Class/Grade...</option>
                                        <?php 
                                        $classesList = ['Creche','Nursery 1','Nursery 2','KG 1','KG 2','Basic 1','Basic 2','Basic 3','Basic 4','Basic 5','Basic 6','Basic 7'];
                                        foreach($classesList as $c): 
                                        ?>
                                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Information -->
                        <div class="mb-8">
                            <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i> Additional Information <span class="font-normal normal-case">(Optional)</span>
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label for="date_of_birth" class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth"
                                           class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                                </div>
                                <div>
                                    <label for="parent_contact" class="block text-sm font-semibold text-gray-700 mb-1">Parent Contact</label>
                                    <input type="text" id="parent_contact" name="parent_contact"
                                           class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors"
                                           placeholder="Phone number or email">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-gray-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-asterisk text-red-500 mr-1"></i> Required fields
                            </p>
                            <div class="flex gap-3">
                                <button type="reset" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                                    Clear Form
                                </button>
                                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-sm shadow-blue-200 transition-all flex items-center gap-2">
                                    <i class="fas fa-check"></i> Enroll Student
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bulk Upload Form -->
            <div id="bulkSection" class="hidden">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-8">
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                                <h5 class="font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-upload text-green-500"></i> Import CSV Data
                                </h5>
                            </div>
                            <form action="bulk_upload_students.php" method="POST" enctype="multipart/form-data" id="bulkUploadForm" class="p-6">
                                <div class="border-2 border-dashed border-gray-200 rounded-xl p-10 text-center bg-gray-50 hover:bg-gray-100 hover:border-green-400 transition-colors cursor-pointer group" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt text-5xl text-gray-300 group-hover:text-green-500 transition-colors mb-4 block"></i>
                                    <h5 class="text-lg font-bold text-gray-800 mb-1">Click or drag CSV file here</h5>
                                    <p class="text-sm text-gray-500 mb-4">Only .csv files are supported</p>
                                    
                                    <button type="button" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg shadow-sm group-hover:border-green-300">
                                        Browse Files
                                    </button>
                                    <input type="file" id="csvFile" name="csvFile" accept=".csv" class="hidden" required>
                                </div>
                                
                                <div id="fileInfo" class="mt-4 hidden animate-fade-in">
                                    <div class="p-4 bg-green-50 border border-green-200 rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
                                                <i class="fas fa-file-csv text-xl"></i>
                                            </div>
                                            <div>
                                                <div id="fileName" class="font-bold text-gray-800 text-sm"></div>
                                                <div id="fileSize" class="text-xs text-gray-500 mt-0.5"></div>
                                            </div>
                                        </div>
                                        <button type="button" class="w-8 h-8 rounded-full hover:bg-green-100 flex items-center justify-center text-gray-500 transition-colors" onclick="clearFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <button type="submit" class="w-full px-5 py-3 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm shadow-green-200 transition-all flex items-center justify-center gap-2">
                                        <i class="fas fa-cloud-upload-alt"></i> Process Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- CSV Information Side Panel -->
                    <div class="lg:col-span-4">
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-5 mb-4">
                            <h6 class="font-bold text-blue-900 mb-2 flex items-center gap-2 text-sm">
                                <i class="fas fa-info-circle text-blue-600"></i> Setup Instructions
                            </h6>
                            <ul class="space-y-2 text-sm text-blue-800 list-disc pl-4 marker:text-blue-400">
                                <li>The first row must contain exactly these headers: <code class="font-bold">first_name, last_name, class, date_of_birth, parent_contact</code></li>
                                <li><strong>Required fields:</strong> first_name, last_name, class</li>
                                <li><strong>Date format:</strong> YYYY-MM-DD (e.g. 2015-05-12)</li>
                            </ul>
                            <div class="mt-4 pt-4 border-t border-blue-200">
                                <button type="button" id="downloadTemplate" class="w-full px-4 py-2 bg-white text-blue-700 border border-blue-200 font-medium text-sm rounded-lg hover:bg-blue-600 hover:text-white transition-colors flex items-center justify-center gap-2 shadow-sm">
                                    <i class="fas fa-download"></i> Download Template
                                </button>
                            </div>
                        </div>
                        
                        <div class="bg-white border text-sm border-gray-100 rounded-xl p-0 overflow-hidden shadow-sm">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-100 font-bold text-gray-700">Sample Format</div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 font-mono">first_name</th>
                                            <th class="px-4 py-2 font-mono">class</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-600">
                                        <tr>
                                            <td class="px-4 py-2">John</td>
                                            <td class="px-4 py-2">Basic 1</td>
                                        </tr>
                                        <tr class="bg-gray-50/50">
                                            <td class="px-4 py-2">Jane</td>
                                            <td class="px-4 py-2">KG 1</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <script>
        function selectMethod(method) {
            const btnSingle = document.getElementById('btnSingle');
            const btnBulk = document.getElementById('btnBulk');
            const indSingle = document.getElementById('indicatorSingle');
            const indBulk = document.getElementById('indicatorBulk');
            const secSingle = document.getElementById('singleSection');
            const secBulk = document.getElementById('bulkSection');

            // Reset all
            btnSingle.className = 'flex-1 bg-white border border-gray-200 rounded-xl p-6 flex flex-col items-center justify-center gap-3 transition-all cursor-pointer hover:border-blue-300 hover:shadow-md group relative overflow-hidden';
            btnBulk.className   = 'flex-1 bg-white border border-gray-200 rounded-xl p-6 flex flex-col items-center justify-center gap-3 transition-all cursor-pointer hover:border-green-300 hover:shadow-md group relative overflow-hidden';
            indSingle.className = 'absolute top-0 left-0 w-full h-1 bg-transparent';
            indBulk.className   = 'absolute top-0 left-0 w-full h-1 bg-transparent';
            
            // Icon colors
            btnSingle.querySelector('i').className = 'fas fa-user text-3xl text-gray-400 group-hover:text-blue-500 group-hover:scale-110 transition-all';
            btnBulk.querySelector('i').className   = 'fas fa-users text-3xl text-gray-400 group-hover:text-green-500 group-hover:scale-110 transition-all';

            secSingle.style.display = 'none';
            secBulk.style.display = 'none';

            // Set active
            if (method === 'single') {
                btnSingle.classList.remove('border-gray-200', 'hover:border-blue-300');
                btnSingle.classList.add('border-2', 'border-blue-600', 'shadow-sm');
                indSingle.className = 'absolute top-0 left-0 w-full h-1 bg-blue-600';
                btnSingle.querySelector('i').className = 'fas fa-user text-3xl text-blue-600 group-hover:scale-110 transition-transform';
                secSingle.style.display = 'block';
            } else {
                btnBulk.classList.remove('border-gray-200', 'hover:border-green-300');
                btnBulk.classList.add('border-2', 'border-green-600', 'shadow-sm');
                indBulk.className = 'absolute top-0 left-0 w-full h-1 bg-green-600';
                btnBulk.querySelector('i').className = 'fas fa-users text-3xl text-green-600 group-hover:scale-110 transition-transform';
                secBulk.style.display = 'block';
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
                uploadArea.classList.add('bg-green-50', 'border-green-400');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('bg-green-50', 'border-green-400');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('bg-green-50', 'border-green-400');
                const files = e.dataTransfer.files;
                if (files.length > 0 && files[0].type === 'text/csv') {
                    fileInput.files = files;
                    displayFileInfo(files[0]);
                } else {
                    alert('Please drop a valid .csv file.');
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    displayFileInfo(e.target.files[0]);
                }
            });
        }

        function displayFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = `${(file.size / 1024).toFixed(1)} KB`;
            fileInfo.classList.remove('hidden');
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.classList.add('hidden');
        }

        document.getElementById('downloadTemplate').addEventListener('click', function(e) {
            e.preventDefault();
            const csvContent = "first_name,last_name,class,date_of_birth,parent_contact\nJohn,Doe,Basic 1,2015-05-12,0801234567\nJane,Smith,KG 1,,jane@email.com\nMichael,Johnson,Basic 7,2012-03-20,\nSarah,Williams,Nursery 1,2018-09-15,0809876543\nDavid,Brown,Basic 5,2014-01-10,david.parent@gmail.com";
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.hidden = true;
            a.href = url;
            a.download = 'student_template.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        });
    </script>
</body>
</html>
