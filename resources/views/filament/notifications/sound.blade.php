{{-- Notification Sound Script --}}
<script>
    (function () {
        let audioUnlocked = false;
        let userHasInteracted = false;
        let soundEnabled = false;

        // Audio unlock handler - also marks that user has interacted
        function unlockAudio() {
            userHasInteracted = true;

            if (audioUnlocked) {
                // Already unlocked, just enable sound after interaction
                soundEnabled = true;
                return;
            }

            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0;
            audio.play().then(() => {
                audio.pause();
                audioUnlocked = true;
                soundEnabled = true;
            }).catch(() => {
                // Even if audio fails, mark sound as enabled after user interaction
                soundEnabled = true;
            });
        }

        // Unlock audio on first user interaction
        ['click', 'touchstart', 'keydown'].forEach(event => {
            document.addEventListener(event, unlockAudio, { once: true, passive: true });
        });

        // Observe DOM for new toast notifications
        const observer = new MutationObserver((mutations) => {
            // Only play sound if user has interacted with the page
            // This prevents sound on page load/refresh
            if (!userHasInteracted || !soundEnabled) return;

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
            if (!soundEnabled) return;

            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0.5;

            audio.play().catch(() => {
                // Audio play blocked
            });
        }
    })();
</script>