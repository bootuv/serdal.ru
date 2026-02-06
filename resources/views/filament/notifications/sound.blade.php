{{-- Notification Sound Script --}}
<script>
    (function () {
        let audioUnlocked = false;
        let soundEnabled = false;
        const pageLoadTime = Date.now();

        // Audio unlock handler - also enables sound after delay
        function unlockAudio() {
            if (audioUnlocked) return;

            const audio = new Audio('/sounds/notification.mp3');
            audio.volume = 0;
            audio.play().then(() => {
                audio.pause();
                audioUnlocked = true;
                // Enable sound only after 3 seconds from page load
                // This prevents sound from playing on notifications that arrive
                // right after page refresh (e.g., from WebSocket reconnection)
                enableSoundAfterDelay();
            }).catch(() => {
                // Even if audio fails, enable sound after delay
                enableSoundAfterDelay();
            });
        }

        function enableSoundAfterDelay() {
            const timeSinceLoad = Date.now() - pageLoadTime;
            const delay = Math.max(0, 3000 - timeSinceLoad);
            setTimeout(() => {
                soundEnabled = true;
            }, delay);
        }

        // Unlock audio on first user interaction
        ['click', 'touchstart', 'keydown'].forEach(event => {
            document.addEventListener(event, unlockAudio, { once: true, passive: true });
        });

        // Observe DOM for new toast notifications
        const observer = new MutationObserver((mutations) => {
            // Only play sound if enabled (after page has been loaded for 3+ seconds)
            if (!soundEnabled) return;

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