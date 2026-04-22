/**
 * SALBA Montessori Management System
 * Global System Configuration & Theme Manager
 */

// 1. Unified Tailwind Configuration
// Map Inter and Outfit to Tailwind's family tokens
if (window.tailwind) {
    window.tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', -apple-system, 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                    display: ['Outfit', 'sans-serif'],
                },
                letterSpacing: {
                    tighter: '-0.05em',
                    tight: '-0.025em',
                }
            }
        }
    };
}

// 2. Global Accessibility & Rendering
document.addEventListener('DOMContentLoaded', () => {
    // Force smooth font rendering
    document.body.style.webkitFontSmoothing = 'antialiased';
    document.body.style.mozOsxFontSmoothing = 'grayscale';
});
