# Path fixing script for reorganized module structure

$ErrorActionPreference = "Stop"

# Define path corrections for files at different depths
$pathCorrections = @{
    # Depth 2 files (need extra ../)
    'administration/students' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
        'includes/' = '../../includes/'
        'css/' = '../../css/'
        'pages/core/' = '../../core/'
        'pages/administration/' = '../../administration/'
        'pages/finance/' = '../../finance/'
        'pages/academics/' = '../../academics/'
    }
    'administration/staff' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/fees' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/payments' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/expenses' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/budgets' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/invoices' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
    'finance/reports' = @{
        '../includes' = '../../includes'
        '../css' = '../../css'
    }
}

Write-Host "PHASE 2: FIXING ALL RELATIVE PATHS"
Write-Host "===================================="

$fixed = 0
$skipped = 0
$errors = 0

# Fix core directory files (depth 1 - already correct)
Write-Host "`nCore files (no changes needed)..." -ForegroundColor Yellow
Get-ChildItem -Recurse -Path "core" -Filter "*.php" -File | ForEach-Object {
    Write-Host "  [OK] $($_.FullName)" -ForegroundColor Green
    $skipped++
}

# Fix depth 2 directories
foreach ($dir in $pathCorrections.Keys) {
    if (Test-Path $dir) {
        Write-Host "`nProcessing: $dir" -ForegroundColor Yellow
        
        Get-ChildItem -Recurse -Path $dir -Filter "*.php" -File | ForEach-Object {
            $filePath = $_.FullName
            $maxRetries = 3
            $retryCount = 0
            $success = $false
            
            while ($retryCount -lt $maxRetries -and -not $success) {
                try {
                    $content = Get-Content $filePath -Raw -ErrorAction Stop
                    $originalContent = $content
                    
                    foreach ($oldPath in $pathCorrections[$dir].Keys) {
                        $newPath = $pathCorrections[$dir][$oldPath]
                        if ($content -match [regex]::Escape($oldPath)) {
                            $content = $content -replace [regex]::Escape($oldPath), $newPath
                        }
                    }
                    
                    if ($content -ne $originalContent) {
                        Set-Content -Path $filePath -Value $content -NoNewline -ErrorAction Stop
                        Write-Host "  [FIXED] $filePath" -ForegroundColor Green
                        $fixed++
                    }
                    else {
                        Write-Host "  [OK] $filePath" -ForegroundColor Cyan
                    }
                    $success = $true
                }
                catch {
                    $retryCount++
                    if ($retryCount -lt $maxRetries) {
                        Start-Sleep -Milliseconds 500
                    }
                    else {
                        Write-Host "  [ERROR] $filePath - $_" -ForegroundColor Red
                        $errors++
                    }
                }
            }
        }
    }
}

Write-Host ""
Write-Host "===================================="
Write-Host "Path fixing complete!"
Write-Host "Files fixed: $fixed"
Write-Host "Files verified: $skipped"
Write-Host "Errors: $errors"
Write-Host "===================================="
