/**
 * SALBA Management System - Theme Manager
 * Handles Light/Dark mode transitions and custom color injections.
 */
class ThemeManager {
    constructor() {
        this.themeKey = 'salba_theme_mode';
        this.init();
    }

    init() {
        // 1. Initial Theme Set
        const savedTheme = localStorage.getItem(this.themeKey) || 'light';
        this.setTheme(savedTheme);

        // 2. Watch for system preference changes if no preference saved
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem(this.themeKey)) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    setTheme(mode) {
        document.documentElement.setAttribute('data-theme', mode);
        localStorage.setItem(this.themeKey, mode);
        
        // Update any toggle buttons in the UI
        const icons = document.querySelectorAll('.theme-toggle-icon');
        icons.forEach(icon => {
            if (mode === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });
    }

    toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        this.setTheme(next);
    }
}

// Initialize on load
const themeManager = new ThemeManager();

// Global handler for the button
function toggleSalbaTheme() {
    themeManager.toggleTheme();
}
