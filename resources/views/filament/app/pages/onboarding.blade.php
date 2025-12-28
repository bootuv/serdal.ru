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
            margin-bottom: 3rem;
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

        /* Progress checklist */
        .onboarding-progress {
            background: linear-gradient(135deg, rgb(255 251 235) 0%, rgb(254 243 199) 100%);
            border: 2px solid rgb(245 158 11);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.1);
        }

        .onboarding-progress h3 {
            font-size: 1.125rem;
            font-weight: 700;
            color: rgb(17 24 39);
            margin-bottom: 1rem;
        }

        .onboarding-progress ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .onboarding-progress li {
            display: flex;
            align-items: center;
            padding: 0.625rem 0;
            color: rgb(55 65 81);
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .onboarding-progress li svg {
            width: 1.5rem;
            height: 1.5rem;
            margin-right: 0.875rem;
            flex-shrink: 0;
            color: rgb(245 158 11);
        }

        .onboarding-progress li.completed {
            color: rgb(22 163 74);
        }

        .onboarding-progress li.completed svg {
            color: rgb(22 163 74);
        }

        .onboarding-progress li.pending {
            color: rgb(55 65 81);
        }

        /* Form sections styling - add spacing to Filament form */
        #onboarding-form {
            margin-bottom: 1.5rem;
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

        .dark .onboarding-progress {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.2) 100%);
            border-color: rgb(217 119 6);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }

        .dark .onboarding-progress h3 {
            color: rgb(255 255 255);
        }

        .dark .onboarding-progress li {
            color: rgb(229 231 235);
        }

        .dark .onboarding-progress li.pending {
            color: rgb(209 213 219);
        }
    </style>

    <div class="onboarding-container">
        <!-- Welcome Header -->
        <div class="onboarding-welcome">
            <h1>👋 Добро пожаловать!</h1>
            <p>Давайте настроим ваш профиль, чтобы вы могли начать работу с учениками</p>
        </div>

        <!-- Progress Checklist -->
        <div class="onboarding-progress">
            <h3>Что нужно сделать:</h3>
            <ul>
                <li class="pending">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                    </svg>
                    <span>Загрузите фото профиля и укажите контакты для связи</span>
                </li>
                <li class="pending">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                    </svg>
                    <span>Создайте хотя бы один тип урока (индивидуальный или групповой)</span>
                </li>
                <li class="pending">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01" />
                    </svg>
                    <span>Нажмите "Завершить настройку" для доступа к полному функционалу</span>
                </li>
            </ul>
        </div>

        <!-- Profile Form -->
        <form wire:submit="submit" id="onboarding-form">
            {{ $this->form }}
        </form>

        <!-- Lesson Types Table -->
        {{ $this->table }}

        <!-- Submit Button -->
        <div
            class="mt-6 overflow-hidden rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col items-center justify-center gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">После завершения вы получите доступ ко всем функциям
                платформы</p>
            <x-filament::button wire:click="submit" size="xl" color="primary">
                ✓ Завершить настройку
            </x-filament::button>
        </div>
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