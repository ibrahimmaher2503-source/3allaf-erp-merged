class StocktakeScan {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-scan-input]');
        this.store = root.querySelector('[data-scan-store]');
        this.status = root.querySelector('[data-scan-save-state]');
        this.feedback = root.querySelector('[data-scan-feedback]');
        this.last = root.querySelector('[data-scan-last]');
        this.video = root.querySelector('[data-scan-video]');
        this.overrideInput = root.querySelector('[data-scan-override]');
        this.noteInput = root.querySelector('[data-scan-note]');
        this.lastLookup = '';
        this.cameraPanel = root.querySelector('[data-scan-camera-panel]');
        this.key = `stocktake:${root.dataset.countId}:${root.dataset.userId}:pending`;
        this.queue = this.readQueue();
        try {
            const failed = JSON.parse(localStorage.getItem(`${this.key}:failed`) || '[]').at(-1);
            if (failed) {
                this.overrideInput.value = failed.quantity;
                this.noteInput.value = failed.note || '';
                this.lastLookup = failed.lookup;
                this.feedback.textContent = 'لم تحفظ الكمية السابقة. راجعها وأعد المحاولة.';
            }
        } catch {}
        this.busy = false;
        this.stream = null;
        this.frame = null;
        this.lastCameraCode = '';
        this.lastCameraAt = 0;
        this.input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.scan(this.input.value);
            }
        });
        root.querySelector('[data-scan-submit]').addEventListener('click', () => this.scan(this.input.value));
        root.querySelector('[data-scan-camera]').addEventListener('click', () => this.startCamera());
        root.querySelector('[data-scan-stop]').addEventListener('click', () => this.stopCamera());
        root.querySelector('[data-scan-override-save]').addEventListener('click', () => this.saveOverride());
        this.online = () => this.flush();
        window.addEventListener('online', this.online);
        this.flush();
    }

    readQueue() {
        try {
            const pending = JSON.parse(localStorage.getItem(this.key) || '[]');
            return Array.isArray(pending) ? pending : [];
        } catch {
            return [];
        }
    }

    persist() {
        localStorage.setItem(this.key, JSON.stringify(this.queue));
    }

    scan(rawCode) {
        const lookup = String(rawCode || '').trim();
        if (!lookup) return;
        this.queue.push({ store_id: this.store.value, lookup, quantity: '1', input_method: 'scan', request_id: crypto.randomUUID(), device_id: `browser-${this.root.dataset.userId}` });
        this.persist();
        this.input.value = '';
        this.status.textContent = this.queue.length > 1 ? `${this.queue.length} محفوظة للإرسال` : 'جارٍ الحفظ…';
        this.flush();
    }

    saveOverride() {
        if (!this.lastLookup || this.overrideInput.value === '') {
            this.feedback.textContent = 'امسح منتجاً وأدخل الكمية الفعلية أولاً.';
            return;
        }
        this.queue.push({ _endpoint: this.root.dataset.quantityEndpoint, store_id: this.store.value,
            lookup: this.lastLookup, quantity: this.overrideInput.value, note: this.noteInput.value,
            request_id: crypto.randomUUID(), device_id: `browser-${this.root.dataset.userId}` });
        this.persist();
        this.status.textContent = 'جارٍ الحفظ…';
        this.flush();
    }

    async flush() {
        if (this.busy || !this.queue.length) return;
        this.busy = true;
        while (this.queue.length && this.root.isConnected) {
            const item = this.queue[0];
            this.status.textContent = 'جارٍ الحفظ…';
            try {
                const response = await fetch(item._endpoint || this.root.dataset.endpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify(item),
                });
                const result = await response.json();
                if (!response.ok || result.ok === false) {
                    if (response.status === 422 || result.ok === false) {
                        if (item._endpoint) {
                            const failed = JSON.parse(localStorage.getItem(`${this.key}:failed`) || '[]');
                            failed.push(item);
                            localStorage.setItem(`${this.key}:failed`, JSON.stringify(failed));
                        }
                        this.queue.shift();
                        this.persist();
                        this.feedback.textContent = result.message || 'باركود غير معروف أو بيانات غير صالحة.';
                        this.feedback.className = 'text-sm text-rose-700';
                        navigator.vibrate?.([40, 40, 40]);
                        continue;
                    }
                    throw new Error(result.message || 'تعذر حفظ المسح. سيعاد إرساله عند عودة الاتصال.');
                }
                this.queue.shift();
                this.persist();
                this.lastLookup = result.sku || item.lookup;
                this.last.hidden = false;
                this.last.querySelector('[data-scan-last-name]').textContent = result.name || '';
                this.last.querySelector('[data-scan-last-sku]').textContent = result.sku || '';
                this.last.querySelector('[data-scan-last-quantity]').textContent = result.quantity || '';
                this.feedback.textContent = 'تم الحفظ.';
                this.feedback.className = 'text-sm text-emerald-700';
                navigator.vibrate?.(25);
            } catch (error) {
                this.feedback.textContent = error.message || 'تعذر الحفظ. سيعاد المحاولة عند عودة الاتصال.';
                this.feedback.className = 'text-sm text-rose-700';
                this.status.textContent = `${this.queue.length} بانتظار الحفظ`;
                this.busy = false;
                return;
            }
        }
        this.status.textContent = this.queue.length ? `${this.queue.length} بانتظار الحفظ` : 'تم الحفظ';
        this.busy = false;
    }

    async startCamera() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            this.feedback.textContent = 'الكاميرا أو قارئ الباركود غير مدعوم في هذا المتصفح. استخدم الماسح أو الإدخال اليدوي.';
            return;
        }
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            this.video.srcObject = this.stream;
            this.cameraPanel.hidden = false;
            await this.video.play();
            this.detector = new BarcodeDetector();
            this.detect();
        } catch {
            this.stopCamera();
            this.feedback.textContent = 'تعذر فتح الكاميرا. تحقق من الإذن وأعد المحاولة.';
        }
    }

    async detect() {
        if (!this.stream || !this.root.isConnected) return this.stopCamera();
        try {
            const found = await this.detector.detect(this.video);
            const code = found[0]?.rawValue?.trim() || '';
            if (code && (code !== this.lastCameraCode || Date.now() - this.lastCameraAt > 1500)) {
                this.lastCameraCode = code;
                this.lastCameraAt = Date.now();
                this.scan(code);
            }
        } catch {
            this.stopCamera();
            this.feedback.textContent = 'توقف المسح بالكاميرا. يمكنك إعادة تشغيله.';
            return;
        }
        this.frame = requestAnimationFrame(() => this.detect());
    }

    stopCamera() {
        if (this.frame) cancelAnimationFrame(this.frame);
        this.frame = null;
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
        this.video.srcObject = null;
        this.cameraPanel.hidden = true;
    }
}

const initStocktakeScans = () => document.querySelectorAll('[data-stocktake-scan]').forEach((root) => {
    if (!root._stocktakeScan) root._stocktakeScan = new StocktakeScan(root);
});

document.addEventListener('DOMContentLoaded', initStocktakeScans);
document.addEventListener('livewire:navigated', initStocktakeScans);

class StocktakeQuantityEditor {
    constructor(root) {
        this.root = root;
        this.quantity = root.querySelector('[data-count-quantity]');
        this.note = root.querySelector('[data-count-note]');
        this.status = root.querySelector('[data-count-save-state]');
        this.key = `stocktake:${root.dataset.countId}:${root.dataset.userId}:${root.dataset.storeId}:${root.dataset.lookup}:draft`;
        this.pending = this.readPending();
        if (this.pending) {
            this.quantity.value = this.pending.quantity;
            this.note.value = this.pending.note;
            this.status.textContent = 'بانتظار الحفظ';
        }
        this.timer = null;
        this.saving = false;
        const changed = () => {
            this.pending = { store_id: root.dataset.storeId, lookup: root.dataset.lookup,
                quantity: this.quantity.value, note: this.note.value, request_id: crypto.randomUUID(),
                device_id: `browser-${root.dataset.userId}` };
            localStorage.setItem(this.key, JSON.stringify(this.pending));
            this.status.textContent = 'جارٍ الحفظ…';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 700);
        };
        this.quantity.addEventListener('input', changed);
        this.note.addEventListener('input', changed);
        this.online = () => this.save();
        window.addEventListener('online', this.online);
        if (this.pending) this.save();
    }

    readPending() {
        try { return JSON.parse(localStorage.getItem(this.key) || 'null'); } catch { return null; }
    }

    async save() {
        if (this.saving || !this.pending || this.pending.quantity === '') return;
        this.saving = true;
        const payload = this.pending;
        try {
            const response = await fetch(this.root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'تعذر حفظ العد. راجع المدخلات وأعد المحاولة.');
            if (this.pending?.request_id === payload.request_id) {
                this.pending = null;
                localStorage.removeItem(this.key);
                this.status.textContent = 'تم الحفظ';
            }
        } catch (error) {
            this.status.textContent = error.message || 'خطأ في الحفظ';
        } finally {
            this.saving = false;
            if (this.pending && this.pending.request_id !== payload.request_id) this.save();
        }
    }
}

const initStocktakeEditors = () => document.querySelectorAll('[data-stocktake-quantity]').forEach((root) => {
    if (!root._stocktakeEditor) root._stocktakeEditor = new StocktakeQuantityEditor(root);
});

document.addEventListener('DOMContentLoaded', initStocktakeEditors);
document.addEventListener('livewire:navigated', initStocktakeEditors);
