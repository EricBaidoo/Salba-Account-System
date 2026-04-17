<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/pages'));
$changedFiles = 0;

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $orig = $content;
        
        // Replace 'invoices/' with 'bills/' in dashboard and other files that reference it
        $content = preg_replace('#invoices/generate_semester_bills\.php#i', 'bills/generate_semester_bills.php', $content);
        $content = preg_replace('#invoices/bulk_semester_billing\.php#i', 'bills/bulk_semester_billing.php', $content);
        $content = preg_replace('#invoices/view_semester_bills\.php#i', 'bills/view_semester_bills.php', $content);
        
        // Replace invoice file references
        $content = preg_replace('#semester_invoice\.php#i', 'semester_bill.php', $content);
        $content = preg_replace('#download_semester_invoice\.php#i', 'download_semester_bill.php', $content);
        
        // Term invoice functions
        $content = preg_replace('#term_invoice_functions\.php#i', 'semester_bill_functions.php', $content);
        
        // Visual text replacements
        $content = preg_replace('#Generate Semester Invoices#i', 'Generate Semester Bills', $content);
        $content = preg_replace('#Semester Invoice#i', 'Semester Bill', $content);
        
        if ($orig !== $content) {
            file_put_contents($file->getPathname(), $content);
            $changedFiles++;
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}

// Do the same for includes directory
$includes = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/includes'));
foreach ($includes as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $orig = $content;
        
        $content = preg_replace('#term_invoice_functions\.php#i', 'semester_bill_functions.php', $content);
        $content = preg_replace('#semester_invoice\.php#i', 'semester_bill.php', $content);
        $content = preg_replace('#download_semester_invoice\.php#i', 'download_semester_bill.php', $content);
        
        if ($orig !== $content) {
            file_put_contents($file->getPathname(), $content);
            echo "Updated: " . $file->getPathname() . "\n";
        }
    }
}

echo "Total UI/Path replacements completed: $changedFiles files modified.\n";
