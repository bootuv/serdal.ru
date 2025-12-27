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

// Suppress non-critical Livewire component lookup errors
// This error occurs when Filament modals close and Livewire tries to find a component that's already been removed
// It doesn't affect functionality, so we suppress it to keep the console clean
window.addEventListener('unhandledrejection', function (event) {
    if (event.reason &&
        typeof event.reason === 'string' &&
        event.reason.toLowerCase().includes('could not find livewire component')) {
        event.preventDefault();
        // Optionally log it for debugging
        // console.debug('Suppressed Livewire component lookup error:', event.reason);
    }
});
