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

    /** e.g. Sick Leave needs a medical certificate only for more than 2 days. */
    get docRequired() {
        const c = this.selected;
        return !!c && c.doc && (c.after === null || this.days > c.after);
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

/**
 * Draw-to-sign box. `value` holds the signature as a PNG data URL ('' when empty).
 */
Alpine.data('signaturePad', (initial) => ({
    value: initial || '',
    empty: !initial,
    drawing: false,
    error: '',

    init() {
        this.ctx = this.$refs.canvas.getContext('2d');
        this.$nextTick(() => this.resize());
        window.addEventListener('resize', () => this.resize());
    },

    resize() {
        const canvas = this.$refs.canvas;
        const w = canvas.offsetWidth;
        const h = canvas.offsetHeight;
        if (!w || !h) return;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = w * ratio;
        canvas.height = h * ratio;
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.ctx.lineWidth = 2;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#111827';
        if (this.value) {
            const img = new Image();
            img.onload = () => this.ctx.drawImage(img, 0, 0, w, h);
            img.src = this.value;
        }
    },

    point(e) {
        const r = this.$refs.canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    },

    start(e) {
        if (!this.$refs.canvas.width || this.$refs.canvas.width === 300) this.resize();
        e.preventDefault();
        this.$refs.canvas.setPointerCapture?.(e.pointerId);
        this.drawing = true;
        const p = this.point(e);
        this.ctx.beginPath();
        this.ctx.moveTo(p.x, p.y);
    },

    move(e) {
        if (!this.drawing) return;
        const p = this.point(e);
        this.ctx.lineTo(p.x, p.y);
        this.ctx.stroke();
        this.empty = false;
    },

    end() {
        if (!this.drawing) return;
        this.drawing = false;
        if (!this.empty) this.value = this.$refs.canvas.toDataURL('image/png');
    },

    clear() {
        const c = this.$refs.canvas;
        this.ctx.clearRect(0, 0, c.width, c.height);
        this.value = '';
        this.empty = true;
        this.error = '';
    },

    // Draws an uploaded PNG onto the canvas (fitted, centred), so it is posted like a drawn signature.
    upload(e) {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        if (file.type !== 'image/png') {
            this.error = 'Please choose a PNG image.';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            this.error = 'The image is too large. Please choose a PNG under 2 MB.';
            return;
        }
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            const c = this.$refs.canvas;
            if (!c.width || c.width === 300) this.resize();
            const w = c.offsetWidth;
            const h = c.offsetHeight;
            const scale = Math.min((w - 16) / img.width, (h - 16) / img.height, 1);
            const dw = img.width * scale;
            const dh = img.height * scale;
            this.ctx.clearRect(0, 0, c.width, c.height);
            this.ctx.drawImage(img, (w - dw) / 2, (h - dh) / 2, dw, dh);
            this.value = c.toDataURL('image/png');
            this.empty = false;
            this.error = '';
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            this.error = 'That image could not be read. Please try another PNG.';
        };
        img.src = url;
    },
}));

window.Alpine = Alpine;
Alpine.start();
