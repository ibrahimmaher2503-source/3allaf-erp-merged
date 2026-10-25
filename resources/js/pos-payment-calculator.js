const DECIMAL_AMOUNT = /^\d+(?:\.\d{1,2})?$/;

export const decimalToCents = (value) => {
    const normalized = String(value ?? '').trim();
    if (!DECIMAL_AMOUNT.test(normalized)) return null;
    const [whole, fraction = ''] = normalized.split('.');
    return (BigInt(whole) * 100n) + BigInt(fraction.padEnd(2, '0'));
};

export const formatCents = (value) => {
    const cents = value < 0n ? -value : value;
    const formatted = `${cents / 100n}.${String(cents % 100n).padStart(2, '0')}`;
    return value < 0n ? `-${formatted}` : formatted;
};

export const summarizePaymentParts = (payable, parts) => {
    const payableCents = decimalToCents(payable);
    if (payableCents === null || payableCents <= 0n || !Array.isArray(parts) || parts.length === 0) {
        return { valid: false, remaining: payableCents ?? 0n, change: 0n };
    }

    const methodIds = new Set();
    let nonCash = 0n;
    let cashReceived = null;
    let cashCount = 0;

    for (const part of parts) {
        const methodId = String(part.methodId ?? '').trim();
        if (methodId === '' || methodIds.has(methodId)) {
            return { valid: false, remaining: payableCents, change: 0n };
        }
        methodIds.add(methodId);

        if (part.isCash) {
            cashCount += 1;
            cashReceived = decimalToCents(part.tendered);
            if (cashCount > 1 || cashReceived === null || cashReceived <= 0n) {
                return { valid: false, remaining: payableCents - nonCash, change: 0n };
            }
            continue;
        }

        const amount = decimalToCents(part.amount);
        if (amount === null || amount <= 0n) {
            return { valid: false, remaining: payableCents - nonCash, change: 0n };
        }
        nonCash += amount;
        if (nonCash > payableCents) {
            return { valid: false, remaining: 0n, change: 0n };
        }
    }

    const cashDue = payableCents - nonCash;
    if (cashCount === 0) {
        return { valid: nonCash === payableCents, remaining: cashDue, change: 0n };
    }
    if (cashDue <= 0n) {
        return { valid: false, remaining: 0n, change: 0n };
    }

    return {
        valid: cashReceived >= cashDue,
        remaining: cashReceived < cashDue ? cashDue - cashReceived : 0n,
        change: cashReceived > cashDue ? cashReceived - cashDue : 0n,
    };
};

export const paymentProgress = (payable, parts) => {
    const due = decimalToCents(payable) ?? 0n;
    let nonCash = 0n;
    let cashReceived = 0n;
    for (const part of Array.isArray(parts) ? parts : []) {
        if (String(part.methodId ?? '').trim() === '') continue;
        const amount = decimalToCents(part.isCash ? part.tendered : part.amount);
        if (amount === null || amount <= 0n) continue;
        if (part.isCash) cashReceived += amount;
        else nonCash += amount;
    }
    const paid = nonCash + cashReceived;
    const cashDue = due > nonCash ? due - nonCash : 0n;
    return {
        paid: paid > due ? due : paid,
        remaining: paid < due ? due - paid : 0n,
        change: cashReceived > cashDue ? cashReceived - cashDue : 0n,
    };
};

window.posPaymentCalculator = ({ payable }) => ({
    payable: String(payable),
    revision: 0,
    submitting: false,
    activeAmountInput: null,
    parts() {
        this.revision;
        return [...this.$root.querySelectorAll('[data-payment-part]')].map((part) => ({
            methodId: part.querySelector('[data-payment-method]')?.value ?? '',
            isCash: part.hasAttribute('data-cash-payment'),
            amount: part.querySelector('[data-noncash-amount]')?.value ?? '',
            tendered: part.hasAttribute('data-cash-payment') ? (part.querySelector('[data-cash-received]')?.value ?? '') : null,
        }));
    },
    currentPayable() { return this.$root.querySelector('[data-pos-payable]')?.value ?? this.payable; },
    summary() {
        return summarizePaymentParts(this.currentPayable(), this.parts());
    },
    progress() {
        return paymentProgress(this.currentPayable(), this.parts());
    },
    cashDueCents() {
        const payableCents = decimalToCents(this.currentPayable()) ?? 0n;
        const nonCash = this.parts().filter((part) => !part.isCash)
            .reduce((total, part) => total + (decimalToCents(part.amount) ?? 0n), 0n);
        return payableCents > nonCash ? payableCents - nonCash : 0n;
    },
    paidAmount() { return formatCents(this.progress().paid); },
    amountRemaining() { return formatCents(this.progress().remaining); },
    changeDue() { return formatCents(this.progress().change); },
    hasRemaining() { return this.progress().remaining > 0n; },
    hasChange() { return this.progress().change > 0n; },
    isExact() { const summary = this.summary(); return summary.valid && summary.remaining === 0n && summary.change === 0n; },
    canComplete() { return this.summary().valid; },
    setActiveAmount(input) { this.activeAmountInput = input; },
    pressKey(key) {
        const input = this.activeAmountInput?.isConnected
            ? this.activeAmountInput
            : this.$root.querySelector('[data-pos-amount-input]');
        if (!input) return;
        let value = String(input.value ?? '');
        if (key === 'clear') value = '';
        else if (key === 'backspace') value = value.slice(0, -1);
        else if (key === '.') {
            if (!value.includes('.')) value = `${value || '0'}.`;
        } else if (/^\d$/.test(key)) {
            const fraction = value.split('.')[1];
            if (fraction === undefined || fraction.length < 2) value += key;
        }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        this.revision += 1;
        input.focus();
    },
    useExactCash(id) {
        this.setCashValue(id, formatCents(this.cashDueCents()));
    },
    useQuickCash(value, id) {
        this.setCashValue(id, String(value));
    },
    setCashValue(id, value) {
        const input = document.getElementById(id);
        if (!input) return;
        input.value = value;
        this.revision += 1;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    },
});
