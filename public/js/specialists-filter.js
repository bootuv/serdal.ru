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
    }

    initializeFromURL() {
        const url = new URL(window.location.href);
        const urlParams = new URLSearchParams(url.search);

        Object.keys(this.filters).forEach(filterType => {
            const values = urlParams.getAll(filterType);
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
            values.forEach(value => urlParams.append(filterType, value));
        });

        url.search = urlParams.toString();
        window.history.pushState({}, '', url);
    }

    async fetchFilteredResults() {
        try {
            const url = new URL(window.location.href);
            const response = await fetch(url);
            const text = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const newSpecialistsList = doc.getElementById('specialists-list');
            document.getElementById('specialists-list').innerHTML = newSpecialistsList.innerHTML;
        } catch (error) {
            console.error('Error fetching filtered results:', error);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new SpecialistsFilter();
});