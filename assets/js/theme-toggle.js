(function () {
    'use strict';

    function getPreferredTheme() {
        const stored = localStorage.getItem('theme');
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        updateToggleButtons(theme);

        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme: theme } }));
    }

    function updateToggleButtons(theme) {
        const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
        toggleBtns.forEach(function (btn) {
            const icon = btn.querySelector('[data-feather]') || btn.querySelector('i');
            const textSpan = btn.querySelector('.theme-toggle-text');
            const isDark = theme === 'dark';
            
            const nextLabel = isDark ? 'Beralih ke Tema Terang' : 'Beralih ke Tema Gelap';
            btn.setAttribute('title', nextLabel);
            btn.setAttribute('aria-label', nextLabel);

            if (icon) {
                icon.setAttribute('data-feather', isDark ? 'sun' : 'moon');
            }
            if (textSpan) {
                textSpan.textContent = isDark ? ' Mode Terang' : ' Mode Gelap';
            }
        });

        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    // Handle button clicks
    document.addEventListener('DOMContentLoaded', function () {
        const currentTheme = getPreferredTheme();
        setTheme(currentTheme);

        document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
                const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
                setTheme(nextTheme);
            });
        });
    });

    // Listen to system preference changes if user hasn't explicitly set a preference
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        if (!localStorage.getItem('theme')) {
            setTheme(e.matches ? 'dark' : 'light');
        }
    });
})();
