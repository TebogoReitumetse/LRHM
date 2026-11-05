import Alpine from 'alpinejs';

/**
 * Leave application form: live working-day calculation.
 */
Alpine.data('leaveForm', (config) => ({
    start: config.start || '',
    end: config.end || '',
    returnDate: config.returnDate || '',
    days: config.days || 0,
    holidays: [],
    error: '',
    loading: false,
    categories: config.categories || {},
    category: config.category || '',
    returnTouched: !!config.returnDate,

    get selected() {
        return this.categories[this.category] || null;
    },

    async calculate() {
        this.error = '';
        if (!this.start || !this.end) return;
        if (this.end < this.start) {
            this.days = 0;
            this.error = 'The last day of leave is before the first day.';
            return;
        }
        this.loading = true;
        try {
            const res = await fetch(`${config.url}?start=${this.start}&end=${this.end}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            this.days = data.days ?? 0;
            this.holidays = data.holidays ?? [];
            this.error = data.error ?? (this.days === 0 ? 'No working days in this period (weekends and public holidays are excluded).' : '');
            if (data.return_date && (!this.returnTouched || this.returnDate <= this.end)) {
                this.returnDate = data.return_date;
            }
        } catch (e) {
            this.error = 'Could not calculate working days. They will be calculated when you save.';
        } finally {
            this.loading = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
