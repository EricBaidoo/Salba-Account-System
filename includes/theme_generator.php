<?php
/**
 * SALBA Theme Generator
 * Maps database settings to CSS variables for dynamic branding.
 */
function generateDynamicTheme($conn) {
    // 1. Fetch settings from DB
    $primaryColor   = getSystemSetting($conn, 'theme_primary_color', '#1e293b'); // Default: Slate 800
    $secondaryColor = getSystemSetting($conn, 'theme_secondary_color', '#4f46e5'); // Default: Indigo 600

    // 2. Conver Hex to HSL for dynamic variants
    function hexToHsl($hex) {
        $hex = str_replace('#', '', $hex);
        if(strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $diff = $max - $min;
        
        $l = ($max + $min) / 2;
        $s = 0;
        $h = 0;

        if ($diff != 0) {
            $s = ($l > 0.5) ? $diff / (2 - $max - $min) : $diff / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $diff + ($g < $b ? 6 : 0); break;
                case $g: $h = ($b - $r) / $diff + 2; break;
                case $b: $h = ($r - $g) / $diff + 4; break;
            }
            $h /= 6;
        }

        return [
            round($h * 360),
            round($s * 100),
            round($l * 100)
        ];
    }

    $primaryHsl = hexToHsl($primaryColor);
    $secondaryHsl = hexToHsl($secondaryColor);

    // 3. Output Style Block
    echo "
    <style>
    :root {
        --primary-h: {$primaryHsl[0]};
        --primary-s: {$primaryHsl[1]}%;
        --primary-l: {$primaryHsl[2]}%;
        
        --accent-h: {$secondaryHsl[0]};
        --accent-s: {$secondaryHsl[1]}%;
        --accent-l: {$secondaryHsl[2]}%;
    }
    </style>
    ";
}
?>
