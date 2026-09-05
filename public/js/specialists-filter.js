class SpecialistsFilter {
    static DEFAULT_SORT = 'popular';

    constructor() {
        // Группы фильтров берём из разметки: каждый чекбокс несёт data-filter-type.
        // Один и тот же фильтр может быть и в быстром дропдауне, и в окне «Фильтры» — состояние общее.
        this.filters = {};
        document.querySelectorAll('.filter-checkbox[data-filter-type]').forEach(checkbox => {
            const type = checkbox.dataset.filterType;
            if (!this.filters[type]) {
                this.filters[type] = new Set();
            }
        });

        this.sort = SpecialistsFilter.DEFAULT_SORT;
        this.lastRequestId = 0;

        // Триггер подгрузки удаляется из DOM, когда страниц больше нет; храним ссылку, чтобы вернуть его
        this.loadTrigger = document.getElementById('load-trigger');

        this.modal = document.getElementById('filters-modal');

        // Ползунок цены: границы берём из разметки, выбранное — из URL
        this.priceSlider = document.getElementById('price-slider');
        this.price = { min: null, max: null };
        if (this.priceSlider) {
            this.priceBounds = {
                min: parseInt(this.priceSlider.dataset.min, 10),
                max: parseInt(this.priceSlider.dataset.max, 10)
            };
        }

        this.init();
    }

    init() {
        this.initializeFromURL();
        this.setupEventListeners();
        this.setupPriceSlider();
        this.setupDropdowns();
        this.setupModal();
        this.setupInfiniteScroll();
    }

    // ---- Состояние из URL ----

    initializeFromURL() {
        const urlParams = new URLSearchParams(window.location.search);

        Object.keys(this.filters).forEach(filterType => {
            // Массив (key[]) или одиночное значение (key) для single-select групп
            let values = urlParams.getAll(filterType + '[]');
            if (values.length === 0) {
                values = urlParams.getAll(filterType);
            }

            values.forEach(value => {
                this.filters[filterType].add(value);
                this.setChecked(filterType, value, true);
            });
            this.updateFilterUI(filterType);
        });

        if (this.priceSlider) {
            const min = parseInt(urlParams.get('price_min'), 10);
            const max = parseInt(urlParams.get('price_max'), 10);
            this.price.min = Number.isFinite(min) && min > this.priceBounds.min ? min : null;
            this.price.max = Number.isFinite(max) && max < this.priceBounds.max ? max : null;
            this.updatePriceUI();
        }

        const sort = urlParams.get('sort');
        if (sort && this.findSortOption(sort)) {
            this.sort = sort;
        }
        this.updateSortUI();
        this.updateFiltersButtonUI();
        this.updateResetUI();
    }

    // ---- Вспомогательное ----

    inputsFor(filterType, value = null) {
        const inputs = Array.from(document.querySelectorAll(`.filter-checkbox[data-filter-type="${filterType}"]`));
        return value === null ? inputs : inputs.filter(input => input.dataset.value === String(value));
    }

    setChecked(filterType, value, checked) {
        this.inputsFor(filterType, value).forEach(input => { input.checked = checked; });
    }

    isSingle(filterType) {
        return !!document.querySelector(`[data-filter-group="${filterType}"][data-single="true"]`);
    }

    findSortOption(value) {
        return Array.from(document.querySelectorAll('.sort-option'))
            .find(option => option.value === value) || null;
    }

    hasActivePrice() {
        return this.price.min !== null || this.price.max !== null;
    }

    hasActiveFilters() {
        return this.hasActivePrice() || Object.values(this.filters).some(values => values.size > 0);
    }

    activeGroupsCount() {
        let count = this.hasActivePrice() ? 1 : 0;
        Object.values(this.filters).forEach(values => { if (values.size > 0) count++; });
        return count;
    }

    // ---- Обработчики ----

    setupEventListeners() {
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => this.handleFilterChange(e));
        });

        document.querySelectorAll('.sort-option').forEach(option => {
            option.addEventListener('change', (e) => this.handleSortChange(e));
        });

        document.querySelectorAll('.filters-reset, [data-reset-filters]').forEach(button => {
            button.addEventListener('click', () => this.resetFilters());
        });

        // Кнопка «сбросить фильтры» внутри пустого списка появляется после AJAX-обновления
        document.addEventListener('click', (e) => {
            if (e.target.closest('.specialists-empty-reset')) {
                e.preventDefault();
                this.resetFilters();
            }
        });
    }

    handleFilterChange(event) {
        const checkbox = event.target;
        const filterType = checkbox.dataset.filterType;
        const value = checkbox.dataset.value;

        if (this.isSingle(filterType) && checkbox.checked) {
            this.filters[filterType].clear();
            this.inputsFor(filterType).forEach(input => { input.checked = input.dataset.value === value; });
        } else {
            // Дубликат того же фильтра в другом месте страницы
            this.setChecked(filterType, value, checkbox.checked);
        }

        if (checkbox.checked) {
            this.filters[filterType].add(value);
        } else {
            this.filters[filterType].delete(value);
        }

        this.updateFilterUI(filterType);
        this.applyChanges();
    }

    handleSortChange(event) {
        this.sort = event.target.value;
        this.updateSortUI();
        this.closeAllDropdowns();
        this.updateURL();
        this.fetchFilteredResults();
    }

    resetFilters() {
        Object.keys(this.filters).forEach(filterType => {
            this.filters[filterType].clear();
            this.inputsFor(filterType).forEach(input => { input.checked = false; });
            this.updateFilterUI(filterType);
        });

        this.price = { min: null, max: null };
        this.updatePriceUI();

        this.closeAllDropdowns();
        this.applyChanges();
    }

    // Общий хвост любого изменения фильтров
    applyChanges() {
        this.updateFiltersButtonUI();
        this.updateResetUI();
        this.updateURL();
        this.fetchFilteredResults();
    }

    // ---- Дропдауны в панели ----

    setupDropdowns() {
        document.querySelectorAll('.filter').forEach(filter => {
            const toggle = filter.querySelector('.filter-select');
            const dropdown = filter.querySelector('.dropdown-list');

            if (!toggle || !dropdown) return;

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

    // ---- Окно «Фильтры» ----

    setupModal() {
        if (!this.modal) return;

        document.querySelectorAll('[data-open-filters]').forEach(button => {
            button.addEventListener('click', () => this.openModal());
        });

        this.modal.querySelectorAll('[data-close-filters]').forEach(element => {
            element.addEventListener('click', () => this.closeModal());
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('is-open')) {
                this.closeModal();
            }
        });
    }

    openModal() {
        this.closeAllDropdowns();
        this.modal.classList.add('is-open');
        this.modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('filters-modal-open');

        const close = this.modal.querySelector('.filters-modal-close');
        if (close) close.focus();
    }

    closeModal() {
        this.modal.classList.remove('is-open');
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('filters-modal-open');

        const opener = document.querySelector('[data-open-filters]');
        if (opener) opener.focus();
    }

    // ---- Ползунок цены ----

    setupPriceSlider() {
        if (!this.priceSlider) return;

        const inputs = this.priceSlider.querySelectorAll('.range-input');
        const step = parseInt(this.priceSlider.dataset.step, 10) || 1;

        inputs.forEach(input => {
            // Во время перетаскивания только обновляем подписи, запрос — по отпусканию
            input.addEventListener('input', () => {
                const [minInput, maxInput] = inputs;
                let min = parseInt(minInput.value, 10);
                let max = parseInt(maxInput.value, 10);

                // Ползунки не должны пересекаться
                if (min > max - step) {
                    if (input === minInput) {
                        min = max - step;
                        minInput.value = min;
                    } else {
                        max = min + step;
                        maxInput.value = max;
                    }
                }

                this.renderPrice(min, max);
            });

            input.addEventListener('change', () => {
                const [minInput, maxInput] = inputs;
                const min = parseInt(minInput.value, 10);
                const max = parseInt(maxInput.value, 10);

                this.price.min = min > this.priceBounds.min ? min : null;
                this.price.max = max < this.priceBounds.max ? max : null;

                this.applyChanges();
            });
        });
    }

    updatePriceUI() {
        if (!this.priceSlider) return;

        const [minInput, maxInput] = this.priceSlider.querySelectorAll('.range-input');
        const min = this.price.min ?? this.priceBounds.min;
        const max = this.price.max ?? this.priceBounds.max;
        minInput.value = min;
        maxInput.value = max;
        this.renderPrice(min, max);
    }

    renderPrice(min, max) {
        const group = this.priceSlider.closest('[data-filter-group]');
        const format = n => n.toLocaleString('ru-RU');

        group.querySelector('.price-value-min').textContent = format(min);
        group.querySelector('.price-value-max').textContent = format(max);

        const range = this.priceBounds.max - this.priceBounds.min || 1;
        const fill = this.priceSlider.querySelector('.range-slider-fill');
        fill.style.left = ((min - this.priceBounds.min) / range * 100) + '%';
        fill.style.right = (100 - (max - this.priceBounds.min) / range * 100) + '%';

        group.classList.toggle('is-active', min > this.priceBounds.min || max < this.priceBounds.max);
    }

    // ---- Отрисовка состояния ----

    updateFilterUI(filterType) {
        const size = this.filters[filterType].size;

        document.querySelectorAll(`[data-filter-group="${filterType}"]`).forEach(group => {
            group.classList.toggle('is-active', size > 0);

            const counter = group.querySelector(':scope > .filter-select > .filter-counter');
            if (counter) {
                counter.textContent = size;
                counter.style.display = size > 0 ? 'block' : 'none';
            }

            if (group.classList.contains('filter')) {
                group.classList.toggle('selected', size > 0);
            }
        });
    }

    // Счётчик на кнопке «Фильтры»: сколько групп задействовано
    updateFiltersButtonUI() {
        const active = this.activeGroupsCount();

        document.querySelectorAll('.filters-open').forEach(button => {
            const counter = button.querySelector('.filter-counter');
            if (counter) {
                counter.textContent = active;
                counter.style.display = active > 0 ? 'block' : 'none';
            }
            button.classList.toggle('selected', active > 0);
        });
    }

    updateSortUI() {
        const sortFilter = document.getElementById('sort');
        if (!sortFilter) return;

        const option = this.findSortOption(this.sort);
        if (option) {
            option.checked = true;
            const label = sortFilter.querySelector('.sort-label');
            if (label) {
                label.textContent = option.parentElement.textContent.trim();
            }
        }

        sortFilter.classList.toggle('selected', this.sort !== SpecialistsFilter.DEFAULT_SORT);
    }

    updateResetUI() {
        document.querySelectorAll('.filters-reset').forEach(button => {
            button.hidden = !this.hasActiveFilters();
        });
    }

    updateCount(totalCount) {
        if (typeof totalCount !== 'number') return;

        const counter = document.getElementById('specialists-count');
        if (counter) {
            counter.dataset.count = totalCount;
            counter.textContent = SpecialistsFilter.plural(totalCount, 'специалист', 'специалиста', 'специалистов');
        }

        // «Показать 5 специалистов» / «Показать 1 специалиста»
        document.querySelectorAll('.modal-apply-count').forEach(el => {
            el.textContent = SpecialistsFilter.plural(totalCount, 'специалиста', 'специалиста', 'специалистов');
        });
    }

    static plural(n, one, few, many) {
        const mod10 = n % 10;
        const mod100 = n % 100;
        let word = many;
        if (mod10 === 1 && mod100 !== 11) {
            word = one;
        } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
            word = few;
        }
        return `${n} ${word}`;
    }

    // ---- URL и загрузка ----

    updateURL() {
        if (!document.getElementById('specialists-list')) return;

        const url = new URL(window.location.href);
        const urlParams = new URLSearchParams();

        Object.entries(this.filters).forEach(([filterType, values]) => {
            const single = this.isSingle(filterType);
            values.forEach(value => {
                if (single) {
                    urlParams.set(filterType, value);
                } else {
                    urlParams.append(filterType + '[]', value);
                }
            });
        });

        if (this.price.min !== null) urlParams.set('price_min', this.price.min);
        if (this.price.max !== null) urlParams.set('price_max', this.price.max);

        if (this.sort !== SpecialistsFilter.DEFAULT_SORT) {
            urlParams.set('sort', this.sort);
        }

        url.search = urlParams.toString();
        window.history.pushState({}, '', url);
    }

    async fetchFilteredResults(append = false, offset = null) {
        try {
            const url = new URL(window.location.href);

            if (!append) {
                url.searchParams.set('offset', 0);
            } else if (offset !== null) {
                url.searchParams.set('offset', offset);
            }

            // Ответ устаревшего запроса (быстрые клики по фильтрам) не должен перетереть свежий
            const requestId = ++this.lastRequestId;

            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (requestId !== this.lastRequestId) return;

            const listContainer = document.getElementById('specialists-list');

            if (!append) {
                listContainer.innerHTML = data.html;
            } else {
                listContainer.insertAdjacentHTML('beforeend', data.html);
            }

            this.updateCount(data.totalCount);

            if (this.loadTrigger) {
                if (data.hasMore) {
                    const usedOffset = parseInt(url.searchParams.get('offset')) || 0;
                    this.loadTrigger.setAttribute('data-offset', usedOffset + 20);
                    if (!document.body.contains(this.loadTrigger)) {
                        listContainer.after(this.loadTrigger);
                    }
                    this.setupInfiniteScroll();
                } else {
                    this.loadTrigger.remove();
                }
            }

        } catch (error) {
            console.error('Error fetching filtered results:', error);
        }
    }

    setupInfiniteScroll() {
        if (!this.loadTrigger || !document.body.contains(this.loadTrigger)) return;

        if (this.observer) this.observer.disconnect();

        this.observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                this.loadMore();
            }
        }, { rootMargin: '200px' });

        this.observer.observe(this.loadTrigger);
    }

    loadMore() {
        if (!this.loadTrigger || !document.body.contains(this.loadTrigger)) return;

        const offset = parseInt(this.loadTrigger.getAttribute('data-offset'));

        this.fetchFilteredResults(true, offset);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.specialistsFilter = new SpecialistsFilter();
});
