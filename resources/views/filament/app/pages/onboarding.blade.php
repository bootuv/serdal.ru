<x-filament-panels::page>
    <style>
        /* Hide sidebar completely */
        aside.fi-sidebar,
        .fi-sidebar {
            display: none !important;
        }

        /* Make main content full width and centered */
        main.fi-main {
            margin-left: auto !important;
            margin-right: auto !important;
            max-width: 100% !important;
        }

        /* Hide page title/header that shows "Добро пожаловать!" */
        .fi-header {
            display: none !important;
        }

        /* Remove sticky from topbar */
        .fi-topbar.sticky {
            position: relative !important;
            top: auto !important;
        }

        /* Hide sidebar toggle buttons */
        .fi-topbar-open-sidebar-btn,
        .fi-topbar-close-sidebar-btn {
            display: none !important;
        }

        /* Center the onboarding content */
        .onboarding-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Welcome header styling */
        .onboarding-welcome {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .onboarding-welcome h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: rgb(17 24 39);
            margin-bottom: 1rem;
        }

        .onboarding-welcome p {
            font-size: 1.125rem;
            color: rgb(107 114 128);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Onboarding logo in topbar */
        .onboarding-logo {
            display: flex;
            align-items: center;
            height: 2rem;
        }

        .onboarding-logo img {
            height: 2rem;
        }

        /* ===== DARK THEME ===== */
        .dark .onboarding-welcome h1 {
            color: rgb(255 255 255);
        }

        .dark .onboarding-welcome p {
            color: rgb(156 163 175);
        }
    </style>

    <div class="onboarding-container">
        <!-- Welcome Header -->
        <div class="onboarding-welcome">
            <h1>👋 Добро пожаловать!</h1>
            <p>Три коротких шага — и можно начинать работу с учениками</p>
        </div>

        <!-- Onboarding Wizard -->
        <form wire:submit="submit" id="onboarding-form">
            {{ $this->form }}
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nav = document.querySelector('.fi-topbar nav');
            if (nav) {
                // Add logo at the beginning
                const logoContainer = document.createElement('a');
                logoContainer.href = '/tutor';
                logoContainer.className = 'onboarding-logo';
                logoContainer.innerHTML = '<img src="{{ asset("images/Logo.svg") }}" alt="Serdal">';
                nav.insertBefore(logoContainer, nav.firstChild);

                // Find and hide specific elements in topbar
                const topbarEnd = document.querySelector('[x-persist="topbar.end.panel-app"]');
                if (topbarEnd) {
                    // Hide all children except the last one (user menu)
                    const children = topbarEnd.children;
                    for (let i = 0; i < children.length - 1; i++) {
                        children[i].style.display = 'none';
                    }
                }
            }
        });
    </script>
</x-filament-panels::page>
