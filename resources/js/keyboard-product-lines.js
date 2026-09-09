const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);

class ProductLookup {
    constructor(root) {
        if (root.dataset.productLookupReady) return;
        root.dataset.productLookupReady = 'true';
        this.root = root;
        this.input = root.querySelector('[data-product-search]');
        this.hidden = root.querySelector('[data-product-id]');
        this.results = root.querySelector('[data-product-results]');
        this.error = root.querySelector('[data-product-error]');
        this.items = [];
        this.active = -1;
        this.timer = null;
        this.controller = null;
        this.listId = `product-results-${Math.random().toString(36).slice(2)}`;
        this.results.id = this.listId;
        this.input.setAttribute('aria-controls', this.listId);
        this.input.addEventListener('input', () => this.queue());
        this.input.addEventListener('keydown', (event) => this.keydown(event));
        this.input.addEventListener('blur', () => setTimeout(() => this.close(), 120));
    }

    queue() {
        this.hidden.value = '';
        this.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        clearTimeout(this.timer);
        const term = this.input.value.trim();
        if (term.length < 2) return this.close();
        this.timer = setTimeout(() => this.search(term), 140);
    }

    async search(term, commitExact = false) {
        this.controller?.abort();
        this.controller = new AbortController();
        try {
            const response = await fetch(`${this.root.dataset.searchUrl}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error('lookup');
            this.items = (await response.json()).data ?? [];
            const exact = this.items.filter((item) => item.exact);
            if (exact.length === 1 && (commitExact || exact[0].barcodes?.some((barcode) => barcode.toLocaleLowerCase() === term.toLocaleLowerCase()) || exact[0].item_code?.toLocaleLowerCase() === term.toLocaleLowerCase())) {
                this.select(exact[0]);
                return;
            }
            if (commitExact && this.items.length === 0) {
                this.root.dispatchEvent(new CustomEvent('product-lookup-empty', { bubbles: true, detail: { term } }));
            }
            this.render();
        } catch (error) {
            if (error.name !== 'AbortError') this.close();
        }
    }

    render() {
        this.active = this.items.length ? 0 : -1;
        if (!this.items.length) {
            this.results.innerHTML = `<p class="p-3 text-sm text-zinc-500">${escapeHtml(document.documentElement.lang === 'ar' ? 'لا توجد منتجات مطابقة.' : 'No matching products.')}</p>`;
        } else {
            this.results.innerHTML = this.items.map((item, index) => `<button type="button" role="option" id="${this.listId}-${index}" aria-selected="${index === 0}" data-result-index="${index}" class="block w-full border-b border-zinc-100 p-3 text-start text-sm last:border-0 hover:bg-cyan-50 focus:bg-cyan-50 dark:border-zinc-800 dark:hover:bg-cyan-950/30"><strong>${escapeHtml(document.documentElement.lang === 'ar' ? (item.name_ar || item.name_en) : (item.name_en || item.name_ar))}</strong><small class="mt-1 block font-mono text-zinc-500" dir="ltr">${escapeHtml(item.item_code)} · ${escapeHtml(item.model_number || '—')} · ${escapeHtml(item.barcodes?.[0] || '—')}</small></button>`).join('');
            this.results.querySelectorAll('[data-result-index]').forEach((button) => {
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => this.select(this.items[Number(button.dataset.resultIndex)]));
            });
        }
        this.results.classList.remove('hidden');
        this.input.setAttribute('aria-expanded', 'true');
        this.syncActive();
    }

    keydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (this.results.classList.contains('hidden')) return this.search(this.input.value.trim());
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            this.active = Math.max(0, Math.min(this.items.length - 1, this.active + direction));
            this.syncActive();
            return;
        }
        if (event.key !== 'Enter') return;
        event.preventDefault();
        event.stopPropagation();
        if (!this.results.classList.contains('hidden') && this.items[this.active]) {
            this.select(this.items[this.active]);
        } else if (this.input.value.trim()) {
            this.search(this.input.value.trim(), true);
        }
    }

    syncActive() {
        this.results.querySelectorAll('[role="option"]').forEach((option, index) => option.setAttribute('aria-selected', String(index === this.active)));
        const option = this.results.querySelector(`#${this.listId}-${this.active}`);
        if (option) {
            this.input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        } else this.input.removeAttribute('aria-activedescendant');
    }

    select(item) {
        this.hidden.value = String(item[this.root.dataset.valueField || 'id'] ?? item.id);
        this.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        this.hidden.dispatchEvent(new Event('change', { bubbles: true }));
        this.input.value = `${document.documentElement.lang === 'ar' ? (item.name_ar || item.name_en) : (item.name_en || item.name_ar)} · ${item.item_code}`;
        this.input.dataset.selected = 'true';
        this.error?.classList.add('hidden');
        this.close();
        this.root.dispatchEvent(new CustomEvent('product-selected', { bubbles: true, detail: item }));
        requestAnimationFrame(() => this.root.closest('[data-product-line]')?.querySelector('[data-line-quantity]')?.focus());
    }

    close() {
        this.results.classList.add('hidden');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
    }
}

const lineIsBlank = (line) => !(line.querySelector('[data-product-id]')?.value || line.querySelector('[data-product-search]')?.value.trim());

const initializeEditor = (editor) => {
    if (editor.dataset.productLineEditorReady) return;
    editor.dataset.productLineEditorReady = 'true';
    editor.addEventListener('product-selected', (event) => {
        const line = event.target.closest('[data-product-line]');
        if (!line) return;
        line.querySelectorAll('[name$="[description_ar]"]').forEach((field) => { if (!field.value) field.value = event.detail.name_ar || event.detail.name_en || ''; });
        line.querySelectorAll('[name$="[description_en]"], [name$="[description]"]').forEach((field) => { if (!field.value) field.value = event.detail.name_en || event.detail.name_ar || ''; });
        const price = line.querySelector('[data-line-price]');
        const preferred = price?.dataset.priceKind === 'price' ? event.detail.unit_price : event.detail.unit_cost;
        if (price && !price.disabled && (!price.value || Number(price.value) === 0) && preferred !== null) {
            price.value = preferred ?? '';
            price.dispatchEvent(new Event('input', { bubbles: true }));
            price.dispatchEvent(new Event('change', { bubbles: true }));
        }
        updateTotal(line);
    });
    editor.addEventListener('input', (event) => {
        const line = event.target.closest?.('[data-product-line]');
        if (line) updateTotal(line);
    });
    editor.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.matches('[data-product-search], textarea, button')) return;
        const line = event.target.closest('[data-product-line]');
        if (!line) return;
        if (event.target.matches('[data-line-quantity]')) {
            event.preventDefault();
            event.stopPropagation();
            const next = line.querySelector('[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])');
            next ? next.focus() : commitLine(editor, line);
        } else if (event.target.matches('[data-line-price], [data-line-step], [data-line-final]')) {
            event.preventDefault();
            event.stopPropagation();
            const steps = [...line.querySelectorAll('[data-line-price]:not(:disabled):not([readonly]), [data-line-step]:not(:disabled):not([readonly]), [data-line-final]:not(:disabled):not([readonly])')];
            const next = steps[steps.indexOf(event.target) + 1];
            next ? next.focus() : commitLine(editor, line);
        }
    });
    editor.addEventListener('click', (event) => {
        const remove = event.target.closest?.('[data-remove-line]');
        if (remove) {
            remove.closest('[data-product-line]')?.remove();
            renumberStaticLines(editor);
            return;
        }
        const add = event.target.closest?.('[data-add-line]');
        if (!add) return;
        editor.dataset.focusNextBlank = 'true';
        const template = editor.querySelector('template[data-line-template]');
        if (!template) return;
        const index = Number(editor.dataset.nextIndex || editor.querySelectorAll('[data-product-line]').length);
        const holder = document.createElement('template');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        template.before(holder.content);
        editor.dataset.nextIndex = String(index + 1);
        boot();
        focusBlank(editor);
    });
    editor.querySelectorAll('[data-product-line]').forEach(updateTotal);
    focusBlank(editor);
};

const renumberStaticLines = (editor) => {
    editor.querySelectorAll('[data-product-line]').forEach((line, index) => {
        line.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace(/\[(\d+)\]/, `[${index}]`);
        });
    });
    editor.dataset.nextIndex = String(editor.querySelectorAll('[data-product-line]').length);
};

const updateTotal = (line) => {
    const quantity = Number(line.querySelector('[data-line-quantity]')?.value || 0);
    const price = Number(line.querySelector('[data-line-price]')?.value || 0);
    const discountType = line.querySelector('[data-line-discount-type]')?.value || '';
    const discountValue = Number(line.querySelector('[data-line-discount]')?.value || 0);
    const taxRate = Number(line.querySelector('[data-line-tax]')?.value || 0);
    const total = line.querySelector('[data-line-total]');
    const gross = quantity * price;
    const discount = discountType === 'percentage' ? gross * discountValue / 100 : (discountType === 'amount' ? discountValue : 0);
    const taxable = Math.max(0, gross - discount);
    const result = taxable + (taxable * taxRate / 100);
    if (total) total.textContent = Number.isFinite(result) ? result.toFixed(2) : '0.00';
};

const commitLine = (editor, line) => {
    if (line.dataset.committing === 'true') return;
    const product = line.querySelector('[data-product-id]');
    const quantity = line.querySelector('[data-line-quantity]');
    const price = line.querySelector('[data-line-price]:not(:disabled)');
    const productValue = product?.value || line.dataset.productValue;
    if (!productValue || !quantity?.checkValidity() || (price && !price.checkValidity())) {
        line.querySelector('[data-product-error]')?.classList.toggle('hidden', Boolean(productValue));
        (!productValue ? line.querySelector('[data-product-search]') : (!quantity?.checkValidity() ? quantity : price))?.focus();
        return;
    }
    const lines = [...editor.querySelectorAll('[data-product-line]')];
    const laterBlank = lines.slice(lines.indexOf(line) + 1).find(lineIsBlank);
    if (laterBlank) return laterBlank.querySelector('[data-product-search]')?.focus();
    const add = editor.querySelector('[data-add-line]');
    if (add && !add.disabled) {
        line.dataset.committing = 'true';
        editor.dataset.focusNextBlank = 'true';
        add.click();
    } else {
        editor.querySelector('[data-product-search]')?.focus();
    }
};

const posKeyboardNavigation = (event) => {
    const search = document.getElementById('live-pos-product-search');
    const productButtons = [...document.querySelectorAll('[data-pos-product-grid] article button:not(:disabled)')];
    if (!search || !productButtons.length) return;

    if (event.target === search && event.key === 'ArrowDown') {
        event.preventDefault();
        productButtons[0].focus();
        return;
    }

    const current = productButtons.indexOf(event.target.closest?.('button'));
    if (current < 0) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        search.focus();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        productButtons[Math.min(productButtons.length - 1, current + 1)].focus();
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        (current === 0 ? search : productButtons[current - 1]).focus();
    }
};

const focusBlank = (editor) => {
    if (editor.dataset.focusNextBlank !== 'true' && editor.dataset.autofocus !== 'true') return;
    const blank = [...editor.querySelectorAll('[data-product-line]')].find(lineIsBlank);
    if (blank) {
        editor.dataset.focusNextBlank = 'false';
        editor.dataset.autofocus = 'false';
        requestAnimationFrame(() => blank.querySelector('[data-product-search]')?.focus());
    }
};

const boot = () => {
    document.querySelectorAll('[data-product-lookup]').forEach((root) => new ProductLookup(root));
    document.querySelectorAll('[data-product-line-editor]').forEach(initializeEditor);
    document.querySelectorAll('[data-product-line-editor]').forEach(focusBlank);
};

new MutationObserver(boot).observe(document.documentElement, { childList: true, subtree: true });
document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:navigated', boot);
document.addEventListener('keydown', posKeyboardNavigation);
window.addEventListener('pos-product-search-focus', () => requestAnimationFrame(() => document.getElementById('live-pos-product-search')?.focus()));
boot();
