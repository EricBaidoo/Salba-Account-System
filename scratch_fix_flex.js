const fs = require('fs');
const path = require('path');

function walkDir(dir, callback) {
    fs.readdirSync(dir).forEach(f => {
        let dirPath = path.join(dir, f);
        let isDirectory = fs.statSync(dirPath).isDirectory();
        isDirectory ? walkDir(dirPath, callback) : callback(path.join(dir, f));
    });
}

let modified = 0;
walkDir('./pages', function(filePath) {
    if (filePath.endsWith('.php')) {
        let content = fs.readFileSync(filePath, 'utf8');
        let newContent = content;

        // 1. Fix Page Headers: flex items-center justify-between -> flex-col md:flex-row items-center justify-between gap-4
        newContent = newContent.replace(
            /<div class="([^"]*)flex items-center justify-between/g, 
            '<div class="$1flex flex-col md:flex-row items-start md:items-center justify-between gap-4'
        );

        // 2. Fix Grid columns that don't transition on mobile (grid-cols-2 -> grid-cols-1 md:grid-cols-2)
        // Only if it doesn't already have grid-cols-1
        newContent = newContent.replace(
            /class="([^"]*)grid-cols-2/g,
            (match, p1) => {
                if (p1.includes('grid-cols-1')) return match; // Already responsive
                if (p1.includes('md:grid-cols-2') || p1.includes('lg:grid-cols-2')) return match; // Handled differently
                return `class="${p1}grid-cols-1 md:grid-cols-2`;
            }
        );
        newContent = newContent.replace(
            /class="([^"]*)grid-cols-3/g,
            (match, p1) => {
                if (p1.includes('grid-cols-1')) return match; // Already responsive
                if (p1.includes('md:grid-cols-3') || p1.includes('lg:grid-cols-3')) return match; // Handled differently
                return `class="${p1}grid-cols-1 md:grid-cols-3`;
            }
        );
        newContent = newContent.replace(
            /class="([^"]*)grid-cols-4/g,
            (match, p1) => {
                if (p1.includes('grid-cols-1') || p1.includes('grid-cols-2')) return match; // Already responsive
                if (p1.includes('md:grid-cols-4') || p1.includes('lg:grid-cols-4')) return match; // Handled differently
                return `class="${p1}grid-cols-1 md:grid-cols-2 lg:grid-cols-4`;
            }
        );

        if (content !== newContent) {
            fs.writeFileSync(filePath, newContent, 'utf8');
            modified++;
        }
    }
});

console.log(`Successfully normalized layout classes in ${modified} files.`);
