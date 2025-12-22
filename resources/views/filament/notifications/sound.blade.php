{{-- Notification Sound Script --}}
<script>
    let audioUnlocked = false;
    let pageLoadTime = Date.now();

    // Audio unlock handler
    function unlockAudio() {
        if (audioUnlocked) return;

        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0;
        audio.play().then(() => {
            audio.pause();
            audioUnlocked = true;
        }).catch(() => { });
    }

    // Unlock audio on first user interaction
    ['click', 'touchstart', 'keydown'].forEach(event => {
        document.addEventListener(event, unlockAudio, { once: true, passive: true });
    });

    // Observe DOM for new toast notifications
    const observer = new MutationObserver((mutations) => {
        // Ignore notifications during initial page load
        if (Date.now() - pageLoadTime < 2000) return;

        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                // Check if this is a notification element
                if (node.nodeType === 1 && node.classList?.contains('fi-no-notification')) {
                    // Toast notifications don't have fi-inline class
                    // Bell icon notifications have fi-inline class
                    const isToast = !node.classList.contains('fi-inline');

                    if (isToast) {
                        playNotificationSound();
                    }
                }
            });
        });
    });

    // Start observing
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Play notification sound
    function playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = 0.5;

        audio.play().catch(() => {
            // Audio play blocked (user hasn't interacted yet)
        });
    }
</script>