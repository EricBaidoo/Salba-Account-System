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
        let newContent = content.replace(/class="lg:ml-72 p-8/g, 'class="lg:ml-72 p-4 md:p-8');
        if (content !== newContent) {
            fs.writeFileSync(filePath, newContent, 'utf8');
            modified++;
        }
    }
});

console.log(`Successfully fixed padding classes in ${modified} files.`);
