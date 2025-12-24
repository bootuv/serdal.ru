import './bootstrap';

// Hide filter badges showing 0
document.addEventListener('DOMContentLoaded', function () {
    const hideZeroBadges = () => {
        document.querySelectorAll('.fi-badge').forEach(badge => {
            if (badge.textContent.trim() === '0') {
                badge.style.display = 'none';
            } else {
                badge.style.display = '';
            }
        });
    };

    // Run on page load
    hideZeroBadges();

    // Run on Livewire updates
    document.addEventListener('livewire:navigated', hideZeroBadges);
    document.addEventListener('livewire:morph', hideZeroBadges);

    // MutationObserver for dynamic content
    const observer = new MutationObserver(hideZeroBadges);
    observer.observe(document.body, { childList: true, subtree: true });
});
