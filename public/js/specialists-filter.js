class SpecialistsFilter {
    constructor() {
        this.filters = {
            user_type: new Set(),
            direct: new Set(),
            subject: new Set(),
            grade: new Set()
        };
        this.init();
    }

    init() {
        this.initializeFromURL();
        this.setupEventListeners();
        this.setupDropdowns();
        this.setupInfiniteScroll();
    }

    initializeFromURL() {
        const url = new URL(window.location.href);
        const urlParams = new URLSearchParams(url.search);

        Object.keys(this.filters).forEach(filterType => {
            // Пробуем получить массив (key[])
            let values = urlParams.getAll(filterType + '[]');

            // Обратная совместимость: если нет [], пробуем без них (хотя PHP увидит только последнее)
            if (values.length === 0) {
                values = urlParams.getAll(filterType);
            }

            values.forEach(value => this.filters[filterType].add(value));
            this.updateFilterUI(filterType);
        });
    }

    setupEventListeners() {
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => this.handleFilterChange(e));
        });
    }

    setupDropdowns() {
        document.querySelectorAll('.filter').forEach(filter => {
            const toggle = filter.querySelector('.filter-select');
            const dropdown = filter.querySelector('.dropdown-list');

            toggle.addEventListener('click', () => {
                const isOpen = dropdown.classList.contains('w--open');
                this.closeAllDropdowns();
                if (!isOpen) {
                    dropdown.classList.add('w--open');
                    toggle.classList.add('w--open');
                }
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.filter')) {
                this.closeAllDropdowns();
            }
        });
    }

    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-list').forEach(dropdown => {
            dropdown.classList.remove('w--open');
        });
        document.querySelectorAll('.filter-select').forEach(toggle => {
            toggle.classList.remove('w--open');
        });
    }

    handleFilterChange(event) {
        const checkbox = event.target;
        const filterType = checkbox.dataset.filterType;
        const value = checkbox.dataset.value;

        if (checkbox.checked) {
            this.filters[filterType].add(value);
        } else {
            this.filters[filterType].delete(value);
        }

        this.updateFilterUI(filterType);
        this.updateURL();
        this.fetchFilteredResults();
    }

    updateFilterUI(filterType) {
        const filter = document.getElementById(filterType === 'user_type' ? 'format' : filterType + 's');
        const filterCounter = filter.querySelector('.filter-counter');
        const filterSize = this.filters[filterType].size;

        filterCounter.textContent = filterSize;
        filterCounter.style.display = filterSize > 0 ? 'block' : 'none';

        if (filterSize > 0) {
            filter.classList.add('active-filter', 'selected');
        } else {
            filter.classList.remove('active-filter', 'selected');
        }
    }

    updateURL() {
        const url = new URL(window.location.href);
        const urlParams = new URLSearchParams();

        Object.entries(this.filters).forEach(([filterType, values]) => {
            values.forEach(value => urlParams.append(filterType + '[]', value));
        });

        url.search = urlParams.toString();
        window.history.pushState({}, '', url);
    }

    async fetchFilteredResults(append = false) {
        try {
            const url = new URL(window.location.href);

            if (!append) {
                url.searchParams.set('offset', 0);
            }

            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            const listContainer = document.getElementById('specialists-list');
            const loadTrigger = document.getElementById('load-trigger');

            if (!append) {
                listContainer.innerHTML = data.html;
                if (loadTrigger) {
                    loadTrigger.setAttribute('data-offset', 20);
                    if (!document.body.contains(loadTrigger)) {
                        listContainer.after(loadTrigger);
                    }
                    this.setupInfiniteScroll();
                }
            } else {
                listContainer.insertAdjacentHTML('beforeend', data.html);
            }

            if (loadTrigger) {
                if (data.hasMore) {
                    const usedOffset = parseInt(url.searchParams.get('offset')) || 0;
                    loadTrigger.setAttribute('data-offset', usedOffset + 20);
                } else {
                    loadTrigger.remove();
                }
            }

        } catch (error) {
            console.error('Error fetching filtered results:', error);
        }
    }

    setupInfiniteScroll() {
        // Reuse logic or create new
        const loadTrigger = document.getElementById('load-trigger');
        if (!loadTrigger) return;

        // Disconnect old observer if exists? 
        if (this.observer) this.observer.disconnect();

        this.observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                this.loadMore();
            }
        }, { rootMargin: '200px' });

        this.observer.observe(loadTrigger);
    }

    loadMore() {
        const loadTrigger = document.getElementById('load-trigger');
        if (!loadTrigger) return;

        const offset = parseInt(loadTrigger.getAttribute('data-offset'));

        // Update URL to reflect current offset for the fetch
        const url = new URL(window.location.href);
        url.searchParams.set('offset', offset);

        // We do NOT want to pushState for infinite scroll offsets usually, to avoid polluting history
        // But we need to pass it to fetchFilteredResults

        // Let's modify how we call fetch.
        // Actually, we can just update the window URL strictly for the fetch call inside the method
        // or pass url as arg.

        // Let's update internal state/URL object passed to fetch
        // Refactoring fetchFilteredResults to take URL or constructed params is better.
        // For now, let's just temporarily modify the history state? No.

        // Let's pass the offset via the URL object in fetchFilteredResults
        // But fetchFilteredResults reads window.location.href.
        // We should change that dependency.

        // Let's update fetchFilteredResults to read form URL but override offset.

        // Hacky but works: 
        window.history.replaceState({}, '', url); // Update URL without pushing
        this.fetchFilteredResults(true);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new SpecialistsFilter();
});