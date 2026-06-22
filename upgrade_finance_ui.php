<?php
$dir = new RecursiveDirectoryIterator('c:\xampp\htdocs\ACCOUNTING\pages\finance');
$iterator = new RecursiveIteratorIterator($dir);

$files = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$replacements = [
    // 1. Body styling
    '/<body class="bg-\[#F8FAFC\] text-slate-900">/i' => '<body class="bg-slate-50 text-slate-900 antialiased bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-100 via-slate-50 to-slate-100">',
    
    // 2. Table wrapper card
    '/<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">/i' => '<div class="bg-white rounded-[2rem] border border-slate-100/50 shadow-2xl shadow-slate-200/50 overflow-hidden ring-1 ring-slate-900/5">',
    
    // 3. Table header
    '/<tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">/i' => '<tr class="bg-slate-50/50 border-b border-slate-100 text-[0.65rem] font-black text-slate-400 uppercase tracking-[0.15em]">',
    
    // 4. Table rows
    '/<tr class="hover:bg-slate-50 transition-colors">/i' => '<tr class="hover:bg-indigo-50/30 transition-all duration-300 group">',
    '/<tr class="hover:bg-slate-50">/i' => '<tr class="hover:bg-indigo-50/30 transition-all duration-300 group">',
    
    // 5. Indigo buttons
    '/bg-indigo-600 text-white (.*?) rounded-xl hover:bg-indigo-700 shadow-sm/i' => 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white $1 rounded-xl hover:from-indigo-700 hover:to-indigo-800 shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:-translate-y-0.5',
    
    // 6. Modal backdrop
    '/<div id="(.*?)Modal" class="fixed inset-0 bg-slate-900\/50 hidden items-center/i' => '<div id="$1Modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center',
    
    // 7. Modal container
    '/<form method="POST" class="bg-white rounded-2xl shadow-2xl/i' => '<form method="POST" class="bg-white rounded-[2rem] shadow-2xl shadow-slate-900/20 ring-1 ring-slate-100',
    '/<div class="bg-white rounded-2xl shadow-2xl/i' => '<div class="bg-white rounded-[2rem] shadow-2xl shadow-slate-900/20 ring-1 ring-slate-100',
    
    // 8. Page Headers (the top bar)
    '/<div class="bg-white border-b border-gray-100 px-8 py-6">/i' => '<div class="bg-white/80 backdrop-blur-xl border-b border-slate-100 px-8 py-8 sticky top-0 z-30">',
];

$changedFiles = 0;

foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $newContent = $content;
    
    foreach ($replacements as $pattern => $replacement) {
        $newContent = preg_replace($pattern, $replacement, $newContent);
    }
    
    if ($content !== $newContent) {
        file_put_contents($path, $newContent);
        echo "Updated: $path\n";
        $changedFiles++;
    }
}

echo "\nTotal files updated: $changedFiles\n";
?>
