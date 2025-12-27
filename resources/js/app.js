import './bootstrap';

// Hide filter badges showing 0
// Debounce function to prevent excessive calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Hide zero badges function
const hideZeroBadges = () => {
    document.querySelectorAll('.fi-badge').forEach(badge => {
        if (badge.textContent.trim() === '0') {
            badge.style.display = 'none';
        } else {
            badge.style.display = '';
        }
    });
};

// Debounced version to prevent excessive calls
const debouncedHideZeroBadges = debounce(hideZeroBadges, 100);

// Wait for Livewire to be fully loaded
document.addEventListener('livewire:init', function () {
    // Run on initial load
    hideZeroBadges();

    // Listen to Livewire lifecycle events
    Livewire.hook('morph.updated', ({ el, component }) => {
        debouncedHideZeroBadges();
    });

    Livewire.hook('commit', ({ component, commit, respond }) => {
        debouncedHideZeroBadges();
    });
});

// Fallback for non-Livewire pages
document.addEventListener('DOMContentLoaded', function () {
    // Only run if Livewire is not available
    if (typeof Livewire === 'undefined') {
        hideZeroBadges();
    }
});
