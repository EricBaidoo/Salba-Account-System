<?php
if (!file_exists('composer.phar')) {
    copy('https://getcomposer.org/download/latest-stable/composer.phar', 'composer.phar');
}
$output = shell_exec('C:\xampp\php\php.exe composer.phar require smalot/pdfparser 2>&1');
echo "<pre>$output</pre>";
echo "Done.";
?>
